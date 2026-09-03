<?php
/**
 * File / 文件：app/Controllers/ApiController.php
 * EN: Defines the ApiController HTTP controller and request/response actions.
 * 中文：定义 ApiController HTTP Controller 及其请求/响应操作。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Logger;
use App\Models\Inspection;
use App\Models\Post;
use App\Models\VerificationQueue;
use App\Services\PostInspector;
use App\Services\PlatformUrl;
use App\Services\InspectionProcessLock;

/**
 * EN: HTTP controller for api requests, responses, and server-side authorization.
 * 中文：负责 api 请求、响应及服务器端权限控制的 HTTP Controller。
 */
class ApiController extends Controller
{
    /**
     * EN: Handle the inspect preflight HTTP action for api controller and return the appropriate response.
     * 中文：处理 api controller 的“inspect preflight”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function inspectPreflight(): void
    {
        $u = Auth::requireRole('sales');
        Csrf::verify($_POST['_csrf'] ?? null);

        $url = trim((string)($_POST['url'] ?? ''));
        if ($url === '') {
            $this->json(['ok'=>false,'message'=>'Enter a post URL.'],422);
        }

        $platform = PlatformUrl::platformFor($url);
        if (!$platform) {
            $this->json(['ok'=>false,'message'=>'Unsupported URL. Use Facebook Marketplace, OfferUp, or Craigslist.'],422);
        }

        $normalizedUrl = PlatformUrl::normalize($url,$platform);
        if (!$normalizedUrl) {
            $this->json(['ok'=>false,'message'=>'The post URL is malformed. Paste one complete listing URL.'],422);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $externalId = PlatformUrl::externalId($platform,$normalizedUrl);
        $dup = Post::duplicate((int)$u['id'],$platform,$normalizedUrl,$externalId,null,null);
        if(!$dup){
            $dup = VerificationQueue::reservationDuplicate((int)$u['id'],$platform,$normalizedUrl,$externalId);
        }

        $this->json([
            'ok'=>$dup===null,
            'platform'=>$platform,
            'canonical_url'=>$normalizedUrl,
            'external_post_id'=>$externalId,
            'duplicate_url'=>$dup['canonical_url']??null,
            'duplicate_title'=>$dup['title']??null,
            'duplicate_kind'=>$dup['kind']??null,
            'message'=>$dup['reason']??'Ready for title and image duplicate check.',
        ]);
    }

    /**
     * EN: Report whether this Sales user already has a Marketplace verification request in progress.
     * 中文：返回当前 Sales 用户是否已有 Marketplace 验证请求正在运行。
     *
     * This endpoint is read-only and lets a reopened Submit modal keep Check Post
     * disabled while an earlier request is still executing on the server.
     * 该接口只读；用户关闭后重新打开 Submit 弹窗时，可据此继续禁用 Check Post，
     * 防止前一个服务器端请求尚未结束时再次启动重复验证。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function inspectStatus(): void
    {
        $u = Auth::requireRole('sales');

        try {
            $inProgress = InspectionProcessLock::isLocked((int)$u['id']);
        } catch (\Throwable $e) {
            Logger::exception(
                $e,
                'post-inspector',
                ['event' => 'Inspection process lock status check failed'],
                'error'
            );
            $this->json([
                'ok' => false,
                'in_progress' => true,
                'failure_code' => 'INSPECTION_LOCK_UNAVAILABLE',
                'message' => 'Verification status could not be checked. Please wait before trying again.',
            ], 503);
        }

        $this->json([
            'ok' => true,
            'in_progress' => $inProgress,
        ]);
    }

    /**
     * EN: Handle the inspect HTTP action for api controller and return the appropriate response.
     * 中文：处理 api controller 的“inspect”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function inspect(): void
    {
        $u = Auth::requireRole('sales');
        Csrf::verify($_POST['_csrf'] ?? null);

        if ((string)($_POST['manual_marketplace'] ?? $_POST['manual_craigslist'] ?? '') === '1') {
            $this->inspectManualMarketplace($u);
            return;
        }

        $url = trim((string)($_POST['url'] ?? ''));

        if ($url === '') {
            $this->json(['ok' => false, 'message' => 'Enter a post URL.'], 422);
        }

        $platform = PlatformUrl::platformFor($url);

        if (!$platform) {
            $this->json([
                'ok' => false,
                'message' => 'Unsupported URL. Use Facebook Marketplace, OfferUp, or Craigslist.'
            ], 422);
        }

        $normalizedUrl = PlatformUrl::normalize($url, $platform);

        if (!$normalizedUrl) {
            $this->json([
                'ok' => false,
                'message' => 'The post URL is malformed. Paste one complete listing URL.'
            ], 422);
        }

        $salesUserId = (int)$u['id'];

        try {
            $lockAcquired = InspectionProcessLock::acquire($salesUserId, $platform, $normalizedUrl);
        } catch (\Throwable $e) {
            Logger::exception(
                $e,
                'post-inspector',
                ['event' => 'Inspection process lock acquisition failed'],
                'error'
            );
            $this->json([
                'ok' => false,
                'failure_code' => 'INSPECTION_LOCK_UNAVAILABLE',
                'message' => 'Verification could not start because the process lock is unavailable. Please try again.',
            ], 503);
        }

        if (!$lockAcquired) {
            Logger::log(
                'warning',
                'Duplicate Marketplace verification request blocked by process lock',
                [
                    'event' => 'inspection_process_lock_busy',
                    'sales_user_id' => $salesUserId,
                    'platform' => $platform,
                ],
                'post-inspector'
            );
            $this->json([
                'ok' => false,
                'in_progress' => true,
                'failure_code' => 'INSPECTION_IN_PROGRESS',
                'message' => 'A verification is already running. Wait for it to finish before checking another post.',
            ], 409);
        }

        try {
            // EN: Remote provider work can take several seconds. Release only the PHP
            // session lock; the MySQL advisory lock above remains held until this
            // inspection finishes, preventing duplicate checks from the same Sales user.
            // 中文：远程 Provider 可能耗时数秒。这里只释放 PHP Session 锁；上面的
            // MySQL advisory lock 会一直持有到本次验证结束，从而阻止同一 Sales 重复启动 Verify。
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            $r = (new PostInspector())->inspect($salesUserId, $platform, $normalizedUrl);
            $token = Inspection::create($r);
            $duplicateMatch = $this->duplicateMatchFromResult($r);

            $manualRequired = $r['verification_status'] === 'manual_pending'
                && in_array(
                    $r['failure_code'],
                    ['CRAIGSLIST_REMOTE_BLOCKED', 'OFFERUP_REMOTE_BLOCKED'],
                    true
                );

            $platformAccount=is_array($r['raw_meta']['platform_account']??null)
                ? $r['raw_meta']['platform_account']
                : null;

            $response = [
                'ok' => $r['verification_status'] === 'verified',
                'manual_required' => $manualRequired,
                'manual_pending' => false,
                'verification_status' => $r['verification_status'],
                'inspection_token' => $token,
                'platform' => $r['platform'],
                'resolved_url' => $r['resolved_url'],
                'canonical_url' => $r['canonical_url'],
                'external_post_id' => $r['external_post_id'],
                'platform_account' => $platformAccount,
                'title' => $r['title'],
                'description' => $r['description'],
                'published_at' => $r['published_at'],
                'published_date' => $r['published_date'],
                'failure_code' => $r['failure_code'],
                'images' => array_slice(\App\Services\ImageFingerprint::urls($r['raw_meta'] ?? []), 0, 1),
                'duplicate_warnings' => $r['raw_meta']['duplicate_report']['warnings'] ?? [],
                'duplicate_matches' => $r['raw_meta']['duplicate_report']['matches'] ?? [],
                'duplicate_url' => is_array($duplicateMatch) ? ($duplicateMatch['canonical_url'] ?? null) : null,
                'duplicate_title' => is_array($duplicateMatch) ? ($duplicateMatch['title'] ?? null) : null,
                'duplicate_kind' => is_array($duplicateMatch) ? ($duplicateMatch['kind'] ?? null) : null,
                'message' => $r['failure_message'] ?: 'Post verified. It will be saved to '.$r['published_date'].'. Review any comparison warnings before saving.'
            ];
        } finally {
            try {
                InspectionProcessLock::release($salesUserId);
            } catch (\Throwable $releaseError) {
                Logger::exception(
                    $releaseError,
                    'post-inspector',
                    [
                        'event' => 'Inspection process lock release failed',
                        'sales_user_id' => $salesUserId,
                    ],
                    'warning'
                );
            }
        }

        $this->json($response);
    }
    /**
     * EN: Validate Sales-entered Craigslist or OfferUp details after direct verification and provider fallback both failed.
     * 中文：Craigslist 或 OfferUp 直接验证及 Provider 回退均失败后，验证 Sales 手动填写的帖子详情。
     *
     * @param array $user Authenticated Sales user. / 已登录的 Sales 用户。
     *
     * @return void No value is returned. / 无返回值。
     */
    private function inspectManualMarketplace(array $user): void
    {
        $token = trim((string)($_POST['inspection_token'] ?? ''));
        $title = trim((string)($_POST['manual_title'] ?? ''));
        $description = trim((string)($_POST['manual_description'] ?? ''));
        $publishedDate = trim((string)($_POST['manual_published_date'] ?? ''));

        if ($token === '') {
            $this->json([
                'ok' => false,
                'message' => 'Manual verification expired or is invalid. Check the marketplace post again.',
            ], 422);
        }

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();
            $source = Inspection::manualCandidate(
                $token,
                (int)$user['id'],
                true
            );

            if (!$source) {
                throw new \DomainException(
                    'Manual verification expired or was already completed. Check the marketplace post again.'
                );
            }

            $platform = strtolower(trim((string)($source['platform'] ?? '')));
            $label = $platform === 'offerup' ? 'OfferUp' : 'Craigslist';

            $result = (new PostInspector())->finalizeMarketplaceManual(
                (int)$user['id'],
                $source,
                $title,
                $description,
                $publishedDate
            );

            if (($result['verification_status'] ?? '') !== 'manual_pending'
                || !empty($result['failure_code'])) {
                $pdo->rollBack();
                $duplicateMatch = $this->duplicateMatchFromResult($result);
                $this->json([
                    'ok' => false,
                    'manual_required' => true,
                    'manual_pending' => false,
                    'verification_status' => $result['verification_status'] ?? 'failed',
                    'inspection_token' => $token,
                    'platform' => $platform,
                    'resolved_url' => $result['resolved_url'] ?? ($source['resolved_url'] ?? null),
                    'canonical_url' => $result['canonical_url'] ?? ($source['canonical_url'] ?? null),
                    'external_post_id' => $result['external_post_id'] ?? ($source['external_post_id'] ?? null),
                    'platform_account' => is_array($result['raw_meta']['platform_account']??null)
                        ? $result['raw_meta']['platform_account']
                        : null,
                    'title' => $result['title'] ?? $title,
                    'description' => $result['description'] ?? $description,
                    'published_at' => $result['published_at'] ?? null,
                    'published_date' => $result['published_date'] ?? null,
                    'failure_code' => $result['failure_code'] ?? 'MANUAL_VALIDATION_FAILED',
                    'images' => array_slice(\App\Services\ImageFingerprint::urls($result['raw_meta'] ?? []), 0, 1),
                    'duplicate_warnings' => $result['raw_meta']['duplicate_report']['warnings'] ?? [],
                    'duplicate_matches' => $result['raw_meta']['duplicate_report']['matches'] ?? [],
                    'duplicate_url' => is_array($duplicateMatch) ? ($duplicateMatch['canonical_url'] ?? null) : null,
                    'duplicate_title' => is_array($duplicateMatch) ? ($duplicateMatch['title'] ?? null) : null,
                    'duplicate_kind' => is_array($duplicateMatch) ? ($duplicateMatch['kind'] ?? null) : null,
                    'message' => $result['failure_message'] ?? 'Manual marketplace verification could not be completed.',
                ], 422);
            }

            Inspection::updateManual((int)$source['id'], $result);
            $pdo->commit();

            $this->json([
                'ok' => true,
                'manual_required' => false,
                'manual_pending' => true,
                'verification_status' => 'manual_pending',
                'inspection_token' => $token,
                'platform' => $platform,
                'resolved_url' => $result['resolved_url'],
                'canonical_url' => $result['canonical_url'],
                'external_post_id' => $result['external_post_id'],
                'platform_account' => is_array($result['raw_meta']['platform_account']??null)
                    ? $result['raw_meta']['platform_account']
                    : null,
                'title' => $result['title'],
                'description' => $result['description'],
                'published_at' => $result['published_at'],
                'published_date' => $result['published_date'],
                'failure_code' => null,
                'images' => array_slice(\App\Services\ImageFingerprint::urls($result['raw_meta'] ?? []), 0, 1),
                'duplicate_warnings' => $result['raw_meta']['duplicate_report']['warnings'] ?? [],
                'duplicate_matches' => $result['raw_meta']['duplicate_report']['matches'] ?? [],
                'duplicate_url' => null,
                'duplicate_title' => null,
                'duplicate_kind' => null,
                'message' => 'Manual ' . $label . ' details accepted. Save this post for Admin verification.',
            ]);
        } catch (\DomainException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Logger::exception(
                $e,
                'post-inspector',
                ['event' => 'Marketplace manual verification failed'],
                'error'
            );
            $this->json([
                'ok' => false,
                'message' => 'Manual marketplace verification could not be completed. Please try again.',
            ], 500);
        }
    }

    /**
     * Receive same-origin browser diagnostics from diagnostics.js.
     *
     * The endpoint is same-origin/CSRF-protected and session-rate-limited
     * so a browser error cannot be turned into an unbounded log flood.
     */
    /**
     * EN: Handle the client log HTTP action for api controller and return the appropriate response.
     * 中文：处理 api controller 的“client log”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function clientLog(): void
    {
        $user = Auth::user();
        $raw = (string)file_get_contents('php://input');
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            $payload = $_POST;
        }

        $csrf = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($payload['_csrf'] ?? ''));
        Csrf::verify($csrf);

        $now = time();
        $window = (int)($_SESSION['client_log_window'] ?? 0);
        $count = (int)($_SESSION['client_log_count'] ?? 0);
        if ($window <= 0 || ($now - $window) >= 60) {
            $window = $now;
            $count = 0;
        }
        $count++;
        $_SESSION['client_log_window'] = $window;
        $_SESSION['client_log_count'] = $count;

        if ($count > 30) {
            Logger::warning(
                'Browser diagnostics rate limit reached.',
                ['event' => 'client_log_rate_limited'],
                'client'
            );
            $this->json(['ok' => true, 'rate_limited' => true], 202);
        }

        $type = substr(trim((string)($payload['type'] ?? 'client_error')), 0, 80);
        $message = substr(trim((string)($payload['message'] ?? 'Browser error')), 0, 2000);

        $httpStatus = (int)($payload['http_status'] ?? 0);
        $severeClientFailure = in_array(
            $type,
            ['javascript_error', 'unhandled_promise_rejection'],
            true
        ) || $httpStatus >= 500;

        Logger::log(
            $severeClientFailure ? 'error' : 'warning',
            'Browser diagnostic: ' . $message,
            [
                'event' => $type,
                'source' => $this->diagnosticUrl((string)($payload['source'] ?? '')),
                'line' => (int)($payload['line'] ?? 0),
                'column' => (int)($payload['column'] ?? 0),
                'stack' => substr((string)($payload['stack'] ?? ''), 0, 8000),
                'page_url' => $this->diagnosticUrl((string)($payload['page_url'] ?? '')),
                'page_request_id' => substr((string)($payload['page_request_id'] ?? ''), 0, 64),
                'http_status' => $httpStatus,
                'request_url' => $this->diagnosticUrl((string)($payload['request_url'] ?? '')),
                'server_request_id' => substr((string)($payload['server_request_id'] ?? ''), 0, 64),
                'user_id' => (int)($user['id'] ?? 0),
            ],
            'client'
        );

        $this->json(['ok' => true], 202);
    }

    /**
     * Strip query strings/fragments from browser-supplied diagnostic URLs.
     * Client-side cleaning is helpful but cannot be trusted as the security
     * boundary because /api/client-log is still a normal HTTP endpoint.
     */
    /**
     * EN: Perform the diagnostic url operation.
     * 中文：执行“diagnostic url”操作。
     *
     * @param string $value Value processed or stored by this operation. / 本操作处理或保存的值。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
     */
    private function diagnosticUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $parts = parse_url($value);
        if (!is_array($parts)) {
            return substr(preg_split('/[?#]/', $value, 2)[0] ?? '', 0, 1000);
        }

        $path = (string)($parts['path'] ?? '');
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = (string)($parts['host'] ?? '');

        if (in_array($scheme, ['http', 'https'], true) && $host !== '') {
            $port = isset($parts['port']) ? ':' . (int)$parts['port'] : '';
            return substr($scheme . '://' . $host . $port . ($path !== '' ? $path : '/'), 0, 1000);
        }

        return substr($path !== '' ? $path : $value, 0, 1000);
    }

    /**
     * EN: Resolve the concrete duplicate record that should be shown to Sales.
     * Same-platform Post::duplicate matches take priority; otherwise use the first
     * exact Website/Image match produced by DuplicateIndex.
     * 中文：解析需要展示给 Sales 的具体重复记录。优先使用同平台 Post::duplicate
     * 命中；否则使用 DuplicateIndex 返回的第一条官网/图片完全重复记录。
     *
     * @param array $result Inspection result. / 验证结果。
     * @return ?array Normalized duplicate metadata. / 标准化后的重复记录。
     */
    private function duplicateMatchFromResult(array $result): ?array
    {
        $direct = $result['raw_meta']['duplicate_match'] ?? null;
        if (is_array($direct)) {
            return $direct;
        }

        $matches = $result['raw_meta']['duplicate_report']['matches'] ?? [];
        if (!is_array($matches)) {
            return null;
        }

        foreach ($matches as $match) {
            if (!is_array($match)) {
                continue;
            }
            $url = trim((string)($match['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            return [
                'canonical_url' => $url,
                'title' => $match['title'] ?? null,
                'kind' => $match['kind'] ?? null,
                'platform' => $match['platform'] ?? null,
            ];
        }

        return null;
    }

}
