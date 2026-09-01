<?php
/**
 * File / 文件：app/Controllers/ApiController.php
 * EN: HTTP controller for request validation, orchestration, and responses.
 * 中文：该文件负责 HTTP 请求校验、业务编排与响应。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Logger;
use App\Models\Inspection;
use App\Models\Post;
use App\Services\PostInspector;
use App\Services\PlatformUrl;

class ApiController extends Controller
{
    /**
     * EN: Implements the application operation `inspectPreflight` (inspect Preflight).
     * 中文：实现应用操作 `inspectPreflight`（inspect Preflight）。
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

        $this->json([
            'ok'=>$dup===null,
            'platform'=>$platform,
            'canonical_url'=>$normalizedUrl,
            'external_post_id'=>$externalId,
            'duplicate_url'=>$dup['canonical_url']??null,
            'duplicate_title'=>$dup['title']??null,
            'duplicate_kind'=>$dup['kind']??null,
            'message'=>$dup['reason']??'No URL or platform post ID duplicate found.',
        ]);
    }

    /**
     * EN: Implements the application operation `inspect` (inspect).
     * 中文：实现应用操作 `inspect`（inspect）。
     */
    public function inspect(): void
    {
        $u = Auth::requireRole('sales');
        Csrf::verify($_POST['_csrf'] ?? null);

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

        // Inspection may spend time waiting on a remote provider. Release the
        // PHP session lock so multiple checks from the same user can finish
        // independently instead of being serialized by the session.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $r = (new PostInspector())->inspect((int)$u['id'], $platform, $normalizedUrl);
        $token = Inspection::create($r);
        $duplicateMatch = $r['raw_meta']['duplicate_match'] ?? null;

        $this->json([
            'ok' => $r['verification_status'] === 'verified',
            'inspection_token' => $token,
            'platform' => $r['platform'],
            'resolved_url' => $r['resolved_url'],
            'canonical_url' => $r['canonical_url'],
            'external_post_id' => $r['external_post_id'],
            'title' => $r['title'],
            'description' => $r['description'],
            'published_at' => $r['published_at'],
            'published_date' => $r['published_date'],
            'failure_code' => $r['failure_code'],
            'duplicate_warnings' => $r['raw_meta']['duplicate_report']['warnings'] ?? [],
            'duplicate_matches' => $r['raw_meta']['duplicate_report']['matches'] ?? [],
            'duplicate_url' => is_array($duplicateMatch) ? ($duplicateMatch['canonical_url'] ?? null) : null,
            'duplicate_title' => is_array($duplicateMatch) ? ($duplicateMatch['title'] ?? null) : null,
            'duplicate_kind' => is_array($duplicateMatch) ? ($duplicateMatch['kind'] ?? null) : null,
            'message' => $r['failure_message'] ?: 'Post verified. It will be saved to '.$r['published_date'].'. Review any comparison warnings before saving.'
        ]);
    }
    /**
     * Receive same-origin browser diagnostics from diagnostics.js.
     *
     * The endpoint is same-origin/CSRF-protected and session-rate-limited
     * so a browser error cannot be turned into an unbounded log flood.
     */
    /**
     * EN: Implements the application operation `clientLog` (client Log).
     * 中文：实现应用操作 `clientLog`（client Log）。
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
     * EN: Implements the application operation `diagnosticUrl` (diagnostic Url).
     * 中文：实现应用操作 `diagnosticUrl`（diagnostic Url）。
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

}
