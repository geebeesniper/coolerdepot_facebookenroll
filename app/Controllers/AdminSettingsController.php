<?php
/**
 * File / 文件：app/Controllers/AdminSettingsController.php
 * EN: Defines the AdminSettingsController HTTP controller and request/response actions.
 * 中文：定义 AdminSettingsController HTTP Controller 及其请求/响应操作。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Models\FetchJob;
use App\Models\ProviderProfile;
use App\Models\Setting;
use App\Models\Location;
use App\Services\MarketplaceProviderDraft;
use App\Services\MarketplaceProviderFactory;
use App\Services\PlatformUrl;
use App\Services\ProviderValidationException;
use App\Services\WebsiteCatalog;
use App\Services\WebsiteScanJob;
use App\Services\WebsiteActivityHistory;
use App\Services\InspectionProcessLock;

/**
 * EN: HTTP controller for admin settings requests, responses, and server-side authorization.
 * 中文：负责 admin settings 请求、响应及服务器端权限控制的 HTTP Controller。
 */
class AdminSettingsController extends Controller
{
    private const TEST_TTL = 600;

    /**
     * EN: Handle the index HTTP action for admin settings controller and return the appropriate response.
     * 中文：处理 admin settings controller 的“index”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function index(): void
    {
        $admin = Auth::requireRole('admin');

        $providers = ProviderProfile::allAdmin();
        $locations = Location::allWithSalesCounts();
        $unassignedSalesCount = Location::unassignedSalesCount();
        $locationNotice = trim((string)($_GET['location_notice'] ?? ''));
        $jobPageData = FetchJob::recentPage(1, 8, '24h');
        $jobs = $jobPageData['jobs'];
        $registryReady = ProviderProfile::registryEnabled();
        $websiteStats = \App\Services\DuplicateIndex::websiteStats();
        $companyName = trim((string)Setting::get('company_name', 'CoolerDepot')) ?: 'CoolerDepot';
        $authFailureRedirectUrl = trim((string)Setting::get('auth_failure_redirect_url', ''));
        $websiteSources = WebsiteCatalog::sources();
        $websiteUrl = (string)($websiteSources[0]['url'] ?? trim((string)Setting::get('company_website_url', '')));
        $websiteSourceStats = [];
        try { $websiteSourceStats = WebsiteCatalog::sourceStats(); } catch (\Throwable $e) {
            \App\Core\Logger::exception($e, 'website-catalog', ['event' => 'Website source stats could not be loaded'], 'warning');
        }
        $websiteProductScanHistory=[];$websiteCsvHistory=[];$websiteAdvancedHistory=[];$websiteRunningScanHosts=[];$websiteResumableScanHistoryIds=[];
        try {
            $websiteProductScanHistory=WebsiteActivityHistory::allForActions(['product_scan']);
            $websiteCsvHistory=WebsiteActivityHistory::recent(['csv_import'],80);
            $websiteAdvancedHistory=WebsiteActivityHistory::recent(['advanced_import'],80);
            $websiteRunningScanHosts=WebsiteScanJob::runningHosts();
            $websiteResumableScanHistoryIds=WebsiteScanJob::resumableHistoryIds();
        } catch (\Throwable $e) {
            \App\Core\Logger::exception($e,'website-catalog',['event'=>'Website activity history/status could not be loaded'],'warning');
        }
        $websiteQuery = trim((string)($_GET['website_q'] ?? ''));
        $websiteReferences = [];
        $websiteStats['library_ready'] = false;
        if (!empty($websiteStats['ready'])) {
            try {
                $websiteReferences = WebsiteCatalog::search($websiteQuery, 100);
                $websiteStats['library_ready'] = true;
            } catch (\Throwable $e) {
                \App\Core\Logger::exception(
                    $e,
                    'website-catalog',
                    ['event' => 'Website reference library could not be loaded'],
                    'warning'
                );
                $websiteReferences = [];
            }
        }
        $inspectionLocks = [];
        $inspectionLockError = null;
        try {
            $inspectionLocks = InspectionProcessLock::activeLocks();
        } catch (\Throwable $e) {
            $inspectionLockError = $e->getMessage();
            \App\Core\Logger::exception(
                $e,
                'admin-settings',
                ['event' => 'Inspection lock list could not be loaded'],
                'warning'
            );
        }

        $providerNames = [];

        foreach ($providers as $provider) {
            $providerNames['profile_' . (int)$provider['id']] =
                (string)$provider['name'];
        }

        $this->render('admin/settings', compact(
            'admin',
            'providers',
            'locations',
            'unassignedSalesCount',
            'locationNotice',
            'jobs',
            'jobPageData',
            'registryReady',
            'providerNames',
            'websiteStats',
            'companyName',
            'authFailureRedirectUrl',
            'websiteUrl',
            'websiteSources',
            'websiteSourceStats',
            'websiteProductScanHistory',
            'websiteCsvHistory',
            'websiteAdvancedHistory',
            'websiteRunningScanHosts',
            'websiteResumableScanHistoryIds',
            'websiteQuery',
            'websiteReferences',
            'inspectionLocks',
            'inspectionLockError'
        ));
    }

    /**
     * EN: Add an Admin-managed Sales location.
     * 中文：新增由 Admin 管理的 Sales Location。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function addLocation(): void
    {
        Auth::requireRole('admin');
        Csrf::verify($_POST['_csrf'] ?? null);
        $ajax=(string)($_POST['ajax'] ?? '')==='1';

        try {
            $location=Location::create((string)($_POST['location_name'] ?? ''));
            if($ajax){
                $this->json([
                    'ok'=>true,
                    'location'=>$location,
                    'locations_count'=>count(Location::allWithSalesCounts()),
                    'unassigned_sales_count'=>Location::unassignedSalesCount(),
                    'message'=>'Location added.'
                ]);
                return;
            }
            $this->redirect('/admin/settings?location_notice=added#sales-locations');
        } catch (\InvalidArgumentException $e) {
            $notice = str_contains(strtolower($e->getMessage()), 'exists')
                ? 'duplicate'
                : 'invalid';
            if($ajax){
                $this->json([
                    'ok'=>false,
                    'code'=>$notice,
                    'message'=>$e->getMessage()
                ],422);
                return;
            }
            $this->redirect('/admin/settings?location_notice=' . $notice . '#sales-locations');
        } catch (\Throwable $e) {
            \App\Core\Logger::exception(
                $e,
                'admin-settings',
                ['event'=>'Sales location could not be added'],
                'warning'
            );
            if($ajax){
                $this->json(['ok'=>false,'code'=>'error','message'=>'Location could not be saved.'],500);
                return;
            }
            $this->redirect('/admin/settings?location_notice=error#sales-locations');
        }
    }

    /**
     * EN: Rename an existing Admin-managed Sales location without changing Sales assignments.
     * 中文：修改现有 Sales Location 名称，不改变任何 Sales 的 Location 分配。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function updateLocation(): void
    {
        Auth::requireRole('admin');
        Csrf::verify($_POST['_csrf'] ?? null);
        $ajax=(string)($_POST['ajax'] ?? '')==='1';
        $locationId=(int)($_POST['location_id'] ?? 0);

        try {
            $location=Location::rename(
                $locationId,
                (string)($_POST['location_name'] ?? '')
            );
            if(!$location){
                throw new \RuntimeException('Location was not found.');
            }
            if($ajax){
                $this->json([
                    'ok'=>true,
                    'location'=>$location,
                    'locations_count'=>count(Location::allWithSalesCounts()),
                    'unassigned_sales_count'=>Location::unassignedSalesCount(),
                    'message'=>'Location updated.'
                ]);
                return;
            }
            $this->redirect('/admin/settings?location_notice=updated#sales-locations');
        } catch (\InvalidArgumentException $e) {
            $notice = str_contains(strtolower($e->getMessage()), 'exists')
                ? 'duplicate'
                : 'invalid';
            if($ajax){
                $this->json(['ok'=>false,'code'=>$notice,'message'=>$e->getMessage()],422);
                return;
            }
            $this->redirect('/admin/settings?location_notice=' . $notice . '#sales-locations');
        } catch (\RuntimeException $e) {
            if($ajax){
                $this->json(['ok'=>false,'code'=>'missing','message'=>$e->getMessage()],404);
                return;
            }
            $this->redirect('/admin/settings?location_notice=missing#sales-locations');
        } catch (\Throwable $e) {
            \App\Core\Logger::exception(
                $e,
                'admin-settings',
                ['event'=>'Sales location could not be updated','location_id'=>$locationId],
                'warning'
            );
            if($ajax){
                $this->json(['ok'=>false,'code'=>'error','message'=>'Location could not be saved.'],500);
                return;
            }
            $this->redirect('/admin/settings?location_notice=error#sales-locations');
        }
    }

    /**
     * EN: Delete an Admin-managed Sales location and move assigned Sales to Unassigned.
     * 中文：删除 Admin Location，并把已分配 Sales 自动移到 Unassigned。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function deleteLocation(): void
    {
        Auth::requireRole('admin');
        Csrf::verify($_POST['_csrf'] ?? null);
        $locationId=(int)($_POST['location_id'] ?? 0);
        $ajax=(string)($_POST['ajax'] ?? '')==='1';

        try {
            if ($locationId < 1 || !Location::deleteWithUnassign($locationId)) {
                if($ajax){
                    $this->json(['ok'=>false,'code'=>'missing','message'=>'Location was not found.'],404);
                    return;
                }
                $this->redirect('/admin/settings?location_notice=missing#sales-locations');
                return;
            }
            if($ajax){
                $this->json([
                    'ok'=>true,
                    'location_id'=>$locationId,
                    'locations_count'=>count(Location::allWithSalesCounts()),
                    'unassigned_sales_count'=>Location::unassignedSalesCount(),
                    'message'=>'Location deleted.'
                ]);
                return;
            }
            $this->redirect('/admin/settings?location_notice=deleted#sales-locations');
        } catch (\RuntimeException $e) {
            if($ajax){
                $this->json(['ok'=>false,'code'=>'error','message'=>$e->getMessage()],500);
                return;
            }
            $this->redirect('/admin/settings?location_notice=error#sales-locations');
        } catch (\Throwable $e) {
            \App\Core\Logger::exception(
                $e,
                'admin-settings',
                ['event'=>'Sales location could not be deleted','location_id'=>$locationId],
                'warning'
            );
            if($ajax){
                $this->json(['ok'=>false,'code'=>'error','message'=>'Location could not be deleted.'],500);
                return;
            }
            $this->redirect('/admin/settings?location_notice=error#sales-locations');
        }
    }

    /**
     * EN: Handle the provider jobs HTTP action for admin settings controller and return the appropriate response.
     * 中文：处理 admin settings controller 的“provider jobs”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function providerJobs(): void
    {
        Auth::requireRole('admin');

        // The poll endpoint is read-only. Release the PHP session lock
        // immediately so it never blocks a provider test or another poll.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $providers = ProviderProfile::allAdmin();
        $providerNames = [];

        foreach ($providers as $provider) {
            $providerNames['profile_' . (int)$provider['id']] =
                (string)$provider['name'];
        }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(1, min(25, (int)($_GET['per_page'] ?? 8)));
        $timeFilter = (string)($_GET['time'] ?? '24h');
        $jobPageData = FetchJob::recentPage($page, $perPage, $timeFilter);
        $jobs = $jobPageData['jobs'];
        $out = [];

        foreach ($jobs as $job) {
            $providerKey = (string)$job['provider'];

            $out[] = [
                'id' => (int)$job['id'],
                'created_at' => (string)$job['created_at'],
                'updated_at' => (string)$job['updated_at'],
                'user' => (string)$job['display_name'],
                'provider' => (string)(
                    $providerNames[$providerKey]
                    ?? ucwords(str_replace('_', ' ', $providerKey))
                ),
                'item' => (string)($job['external_post_id'] ?: '—'),
                'status' => strtolower((string)$job['status']),
                'http' => $job['provider_http_status'] !== null
                    ? (int)$job['provider_http_status']
                    : null,
                'error' => (string)($job['error_message'] ?: '—'),
            ];
        }

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $this->json([
            'ok' => true,
            'jobs' => $out,
            'pagination' => [
                'total' => (int)$jobPageData['total'],
                'page' => (int)$jobPageData['page'],
                'pages' => (int)$jobPageData['pages'],
                'per_page' => (int)$jobPageData['per_page'],
                'time_filter' => (string)$jobPageData['time_filter'],
            ],
            'server_time' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * EN: Handle the import website catalog HTTP action for admin settings controller and return the appropriate response.
     * 中文：处理 admin settings controller 的“import website catalog”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     *
     * @throws \DomainException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    public function importWebsiteCatalog(): void
    {
        $admin=Auth::requireRole('admin');
        Csrf::verify($_POST['_csrf'] ?? null);
        $historyId=0;$historyHost='';$historyWebsite='';
        try {
            $file=$_FILES['catalog']??[];
            if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']??'')){
                throw new \DomainException('Choose a CSV file smaller than 5 MB.');
            }
            // Step 2 does not ask the admin to choose a website. The CSV URL
            // column identifies the website and registers it in Step 1 when needed.
            $detectedSource=WebsiteCatalog::inferCsvSource($file['tmp_name'],(int)$admin['id']);
            $websiteUrl=(string)$detectedSource['url'];
            $historyWebsite=$websiteUrl;
            $historyHost=(string)$detectedSource['host'];
            if($historyHost!==''){
                $historyId=WebsiteActivityHistory::begin(
                    $historyHost,$historyWebsite,'csv_import',(int)$admin['id'],'',
                    'CSV import: '.basename((string)($file['name']??'catalog.csv'))
                );
            }
            $result=WebsiteCatalog::importCsv($file['tmp_name'],$websiteUrl);
            if($historyId>0){
                WebsiteActivityHistory::update(
                    $historyId,'completed',(int)$result['processed'],(int)$result['saved'],count((array)$result['failed']),
                    'CSV import completed.',true
                );
            }
            $_SESSION['flash_success']=(int)$result['saved'].' website references imported from '.(int)$result['processed'].' CSV rows.';
            if(!empty($result['failed'])){
                $_SESSION['flash_success'].=' Some rows failed; open URL CSV history for details.';
            }
        } catch (\DomainException $e) {
            if($historyId>0){WebsiteActivityHistory::fail($historyId,$e->getMessage());}
            \App\Core\Logger::exception($e, 'website-catalog', ['event' => 'Website catalog import rejected'], 'warning');
            $_SESSION['flash_error']=$e->getMessage();
        } catch (\Throwable $e) {
            if($historyId>0){try{WebsiteActivityHistory::fail($historyId,$e->getMessage());}catch(\Throwable $historyError){}}
            \App\Core\Logger::exception($e, 'website-catalog', ['event' => 'Website catalog import failed'], 'error');
            $_SESSION['flash_error']='Website catalog could not be imported. Check the migration and CSV, then retry.';
        }
        $this->redirect('/admin/settings#website-comparison');
    }

    /**
     * EN: Handle the test provider HTTP action for admin settings controller and return the appropriate response.
     * 中文：处理 admin settings controller 的“test provider”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     *
     * @throws ProviderValidationException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    public function testProvider(): void
    {
        $admin = Auth::requireRole('admin');
        Csrf::verify($_POST['_csrf'] ?? null);

        try {
            $testUrl = PlatformUrl::normalize(
                trim((string)($_POST['test_url'] ?? '')),
                'facebook'
            );

            if (!$testUrl) {
                throw new ProviderValidationException(
                    'test_url',
                    'Enter a complete Facebook Marketplace item URL, for example: https://www.facebook.com/marketplace/item/1609835460847233'
                );
            }

            $profile = MarketplaceProviderDraft::fromPost($_POST);

            // Provider tests can take tens of seconds. PHP normally locks the
            // session for the whole request, which would block this admin's
            // live jobs polling. We no longer need session state while the
            // provider runs, so release the lock first.
            $sessionId = session_id();

            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            $previousErrorHandler = set_error_handler(
                static function (
                    int $severity,
                    string $message,
                    string $file,
                    int $line
                ): bool {
                    if (!(error_reporting() & $severity)) {
                        return false;
                    }

                    throw new \ErrorException(
                        $message,
                        0,
                        $severity,
                        $file,
                        $line
                    );
                }
            );

            try {
                $result = MarketplaceProviderFactory::make($profile)->fetch(
                    $testUrl,
                    (int)$admin['id'],
                    true
                );
            } finally {
                restore_error_handler();
            }

            foreach ([
                'external_post_id',
                'title',
                'description',
                'published_raw',
            ] as $required) {
                if (trim((string)($result[$required] ?? '')) === '') {
                    throw new \RuntimeException(
                        'Test response is missing required field: ' . $required
                    );
                }
            }

            $expectedId = PlatformUrl::externalId('facebook', $testUrl);
            $returnedId = trim((string)$result['external_post_id']);

            if ($expectedId && $returnedId !== $expectedId) {
                throw new \RuntimeException(
                    'Test API returned a different Marketplace item ID.'
                );
            }

            try {
                new \DateTime((string)$result['published_raw']);
            } catch (\Throwable $e) {
                throw new \RuntimeException(
                    'Test API returned a listing date that cannot be parsed.'
                );
            }

            $fingerprint = MarketplaceProviderDraft::fingerprint($profile);
            $ticket = bin2hex(random_bytes(24));

            if (session_status() !== PHP_SESSION_ACTIVE) {
                if ($sessionId !== '') {
                    session_id($sessionId);
                }

                if (!session_start()) {
                    throw new \RuntimeException(
                        'Provider test passed, but the Admin session could not be reopened.'
                    );
                }
            }

            $_SESSION['provider_test_tickets'][$ticket] = [
                'fingerprint' => $fingerprint,
                'expires_at' => time() + self::TEST_TTL,
                'summary' => [
                    'provider' => (string)$profile['name'],
                    'item_id' => (string)$result['external_post_id'],
                    'title' => (string)$result['title'],
                    'description' => (string)$result['description'],
                    'listing_date' => (string)$result['published_raw'],
                ],
            ];

            $this->pruneTickets();

            $this->json([
                'ok' => true,
                'ticket' => $ticket,
                'message' => 'Test passed. This provider can now be added.',
                'result' => $_SESSION['provider_test_tickets'][$ticket]['summary'],
            ]);
        } catch (ProviderValidationException $e) {
            $this->json([
                'ok' => false,
                'field' => $e->field(),
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            \App\Core\Logger::exception($e, 'admin-settings', ['event' => 'Provider test failed'], 'warning');
            $this->json([
                'ok' => false,
                'message' => $e->getMessage() !== ''
                    ? $e->getMessage()
                    : 'Provider test failed during response validation.',
            ], 422);
        }
    }

    /**
     * EN: Handle the add provider HTTP action for admin settings controller and return the appropriate response.
     * 中文：处理 admin settings controller 的“add provider”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     *
     * @throws \RuntimeException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    public function addProvider(): void
    {
        $admin = Auth::requireRole('admin');
        Csrf::verify($_POST['_csrf'] ?? null);

        try {
            $ticket = trim((string)($_POST['test_ticket'] ?? ''));
            $profile = MarketplaceProviderDraft::fromPost($_POST);
            $fingerprint = MarketplaceProviderDraft::fingerprint($profile);

            $test = $_SESSION['provider_test_tickets'][$ticket] ?? null;

            if (!$ticket
                || !is_array($test)
                || (int)($test['expires_at'] ?? 0) < time()
                || !hash_equals(
                    (string)($test['fingerprint'] ?? ''),
                    $fingerprint
                )) {
                throw new \RuntimeException(
                    'Provider settings changed or the successful test expired. Test it again before adding.'
                );
            }

            $id = ProviderProfile::createVerified(
                $profile,
                (int)$admin['id'],
                'Test passed before provider was added.'
            );

            unset($_SESSION['provider_test_tickets'][$ticket]);

            $this->json([
                'ok' => true,
                'id' => $id,
                'message' => 'Provider added and enabled.',
            ]);
        } catch (\Throwable $e) {
            \App\Core\Logger::exception($e, 'admin-settings', ['event' => 'Provider add failed'], 'warning');
            $this->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * EN: Handle the reorder providers HTTP action for admin settings controller and return the appropriate response.
     * 中文：处理 admin settings controller 的“reorder providers”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     *
     * @throws \RuntimeException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    public function reorderProviders(): void
    {
        $admin = Auth::requireRole('admin');
        Csrf::verify($_POST['_csrf'] ?? null);

        try {
            $ids = $_POST['ids'] ?? [];

            if (is_string($ids)) {
                $ids = json_decode($ids, true);
            }

            if (!is_array($ids)) {
                throw new \RuntimeException('Invalid provider order.');
            }

            ProviderProfile::reorder($ids, (int)$admin['id']);

            $this->json([
                'ok' => true,
                'message' => 'Provider priority updated.',
            ]);
        } catch (\Throwable $e) {
            \App\Core\Logger::exception($e, 'admin-settings', ['event' => 'Provider reorder failed'], 'warning');
            $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * EN: Handle the toggle provider HTTP action for admin settings controller and return the appropriate response.
     * 中文：处理 admin settings controller 的“toggle provider”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     *
     * @throws \RuntimeException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    public function toggleProvider(): void
    {
        $admin = Auth::requireRole('admin');
        Csrf::verify($_POST['_csrf'] ?? null);

        try {
            $id = (int)($_POST['id'] ?? 0);
            $enabled = (string)($_POST['enabled'] ?? '0') === '1';

            if ($id <= 0) {
                throw new \RuntimeException('Invalid provider.');
            }

            ProviderProfile::setEnabled($id, $enabled, (int)$admin['id']);

            $this->json([
                'ok' => true,
                'message' => $enabled ? 'Provider enabled.' : 'Provider disabled.',
            ]);
        } catch (\Throwable $e) {
            \App\Core\Logger::exception($e, 'admin-settings', ['event' => 'Provider toggle failed'], 'warning');
            $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * EN: Handle the delete provider HTTP action for admin settings controller and return the appropriate response.
     * 中文：处理 admin settings controller 的“delete provider”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     *
     * @throws \RuntimeException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    public function deleteProvider(): void
    {
        Auth::requireRole('admin');
        Csrf::verify($_POST['_csrf'] ?? null);

        try {
            $id = (int)($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new \RuntimeException('Invalid provider.');
            }

            ProviderProfile::deleteById($id);

            $this->json([
                'ok' => true,
                'message' => 'Provider removed.',
            ]);
        } catch (\Throwable $e) {
            \App\Core\Logger::exception($e, 'admin-settings', ['event' => 'Provider delete failed'], 'warning');
            $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * EN: Force-clear one Sales Marketplace verification gate from Admin Settings.
     * 中文：从 Admin Settings 手动清除指定 Sales 的 Marketplace 验证锁。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function unlockInspection(): void
    {
        $admin = Auth::requireRole('admin');
        Csrf::verify($_POST['_csrf'] ?? null);
        $salesUserId = (int)($_POST['sales_user_id'] ?? 0);

        if ($salesUserId <= 0) {
            $_SESSION['flash_error'] = 'Invalid Sales verification lock.';
            $this->redirect('/admin/settings#verification-locks');
        }

        try {
            $released = InspectionProcessLock::forceRelease($salesUserId);
            \App\Core\Logger::log(
                $released ? 'warning' : 'info',
                $released ? 'Admin manually unlocked Sales Marketplace verification' : 'Admin unlock requested but no Sales verification lock existed',
                [
                    'event' => 'admin_inspection_force_unlock',
                    'admin_user_id' => (int)$admin['id'],
                    'sales_user_id' => $salesUserId,
                    'released' => $released,
                ],
                'admin-settings'
            );
            $_SESSION['flash_success'] = $released
                ? 'Sales verification lock cleared.'
                : 'No active verification lock was found for that Sales user.';
        } catch (\Throwable $e) {
            \App\Core\Logger::exception(
                $e,
                'admin-settings',
                [
                    'event' => 'Admin inspection force unlock failed',
                    'admin_user_id' => (int)$admin['id'],
                    'sales_user_id' => $salesUserId,
                ],
                'error'
            );
            $_SESSION['flash_error'] = 'Verification lock could not be cleared: ' . $e->getMessage();
        }

        $this->redirect('/admin/settings#verification-locks');
    }

    // Old v0.1.11 endpoints stay harmless for bookmarks/forms during rollout.
    /**
     * EN: Handle the save HTTP action for admin settings controller and return the appropriate response.
     * 中文：处理 admin settings controller 的“save”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function save(): void
    {
        Auth::requireRole('admin');
        $_SESSION['flash_error'] =
            'Provider settings are now managed with the Provider Manager.';
        $this->redirect('/admin/settings');
    }

    /**
     * EN: Handle the test HTTP action for admin settings controller and return the appropriate response.
     * 中文：处理 admin settings controller 的“test”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function test(): void
    {
        Auth::requireRole('admin');
        $_SESSION['flash_error'] =
            'Use + Add Provider, test it, then add it to the provider chain.';
        $this->redirect('/admin/settings');
    }

    /**
     * EN: Handle the save brand HTTP action for admin settings controller and return the appropriate response.
     * 中文：处理 admin settings controller 的“save brand”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function saveBrand(): void
    {
        $admin=Auth::requireRole('admin');
        Csrf::verify($_POST['_csrf']??null);
        $scope=trim((string)($_POST['setting_scope']??'both'));

        if($scope==='company_name'){
            $name=trim(strip_tags((string)($_POST['company_name']??'')));
            if($name===''||mb_strlen($name)>80||str_contains($name,'<')||str_contains($name,'>')){
                $_SESSION['flash_error']='Company name must be plain text, 1–80 characters.';
                $this->redirect('/admin/settings#application-settings');
            }
            Setting::set('company_name',$name,(int)$admin['id']);
            $_SESSION['flash_success']='Company name updated.';
            $this->redirect('/admin/settings#application-settings');
        }

        if($scope==='portal_url'){
            $redirectUrl=trim((string)($_POST['auth_failure_redirect_url']??''));
            if($redirectUrl!=='' && (
                strlen($redirectUrl)>2048
                || !filter_var($redirectUrl,FILTER_VALIDATE_URL)
                || !in_array(strtolower((string)parse_url($redirectUrl,PHP_URL_SCHEME)),['http','https'],true)
            )){
                $_SESSION['flash_error']='Portal fallback URL must be a valid http:// or https:// address.';
                $this->redirect('/admin/settings#application-settings');
            }
            if($redirectUrl===''){
                Setting::delete('auth_failure_redirect_url');
            }else{
                Setting::set('auth_failure_redirect_url',$redirectUrl,(int)$admin['id']);
            }
            $_SESSION['flash_success']='Portal fallback URL updated.';
            $this->redirect('/admin/settings#application-settings');
        }

        // Backward compatibility for an older combined form still open in a browser tab.
        $name=trim(strip_tags((string)($_POST['company_name']??'')));
        $redirectUrl=trim((string)($_POST['auth_failure_redirect_url']??''));
        if($name===''||mb_strlen($name)>80||str_contains($name,'<')||str_contains($name,'>')){
            $_SESSION['flash_error']='Company name must be plain text, 1–80 characters.';
            $this->redirect('/admin/settings#application-settings');
        }
        if($redirectUrl!=='' && (
            strlen($redirectUrl)>2048
            || !filter_var($redirectUrl,FILTER_VALIDATE_URL)
            || !in_array(strtolower((string)parse_url($redirectUrl,PHP_URL_SCHEME)),['http','https'],true)
        )){
            $_SESSION['flash_error']='Portal fallback URL must be a valid http:// or https:// address.';
            $this->redirect('/admin/settings#application-settings');
        }
        Setting::set('company_name',$name,(int)$admin['id']);
        if($redirectUrl===''){
            Setting::delete('auth_failure_redirect_url');
        }else{
            Setting::set('auth_failure_redirect_url',$redirectUrl,(int)$admin['id']);
        }
        $_SESSION['flash_success']='Application settings updated.';
        $this->redirect('/admin/settings#application-settings');
    }

    /**
     * EN: Handle the save website source HTTP action for admin settings controller and return the appropriate response.
     * 中文：处理 admin settings controller 的“save website source”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function saveWebsiteSource(): void
    {
        $admin=Auth::requireRole('admin');
        Csrf::verify($_POST['_csrf']??null);
        try{
            $source=WebsiteCatalog::addSource((string)($_POST['website_url']??''),(int)$admin['id']);
            $_SESSION['flash_success']='Website source added: '.$source['host'].'.';
        }catch(\Throwable $e){
            \App\Core\Logger::exception($e, 'website-catalog', ['event' => 'Website source save failed'], 'warning');
            $_SESSION['flash_error']=$e->getMessage();
        }
        $this->redirect('/admin/settings#website-comparison');
    }

    /**
     * Show one configured website and only the URLs/products indexed for that
     * source. Admin can search, add and delete records without mixing sites.
     * 显示单个网站及其扫描得到的 URL / 产品记录；Admin 可在该网站范围内搜索、添加和删除。
     */
    public function websiteSourceDetail(): void
    {
        $admin=Auth::requireRole('admin');
        $host=strtolower(trim((string)($_GET['host']??'')));
        try{
            $source=WebsiteCatalog::source($host);
            if(!$source){throw new \DomainException('Website source was not found.');}
            $query=trim((string)($_GET['q']??''));
            $references=WebsiteCatalog::search($query,200,$host);
            $allStats=WebsiteCatalog::sourceStats();
            $sourceStats=$allStats[$host]??['total'=>0,'indexed'=>0,'last_imported'=>null];
            $this->render('admin/website-source',compact(
                'admin','source','sourceStats','query','references'
            ));
        }catch(\Throwable $e){
            \App\Core\Logger::exception($e,'website-catalog',['event'=>'Website source detail could not be loaded','host'=>$host],'warning');
            $_SESSION['flash_error']=$e->getMessage();
            $this->redirect('/admin/settings#website-comparison');
        }
    }

    /**
     * EN: Handle the scan website HTTP action for admin settings controller and return the appropriate response.
     * 中文：处理 admin settings controller 的“scan website”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     *
     * @throws \DomainException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    public function scanWebsite(): void
    {
        $admin=Auth::requireRole('admin');
        Csrf::verify($_POST['_csrf']??null);
        $historyId=0;
        try{
            $source=trim((string)($_POST['source_url']??''));
            if($source===''){throw new \DomainException('Enter a page or sitemap URL.');}
            // Step 3 derives the website directly from the URL. If this host is
            // new, it is added to the Step 1 website list automatically.
            $detectedSource=WebsiteCatalog::ensureSourceForUrl($source,(int)$admin['id']);
            $website=(string)$detectedSource['url'];
            $host=(string)$detectedSource['host'];
            if($host!==''){
                $historyId=WebsiteActivityHistory::begin(
                    $host,$website,'advanced_import',(int)$admin['id'],$source,
                    $source!==''?'Page / sitemap import started.':'Website import started.'
                );
            }

            // Website scans can take several seconds. Release the session lock so
            // other admin checks remain independent and can finish first.
            if(session_status()===PHP_SESSION_ACTIVE){session_write_close();}

            $result=WebsiteCatalog::scan($website,$source);
            if($historyId>0){
                WebsiteActivityHistory::update(
                    $historyId,'completed',(int)$result['checked'],(int)$result['saved'],(int)$result['failed'],
                    !empty($result['limited'])?'Completed; limited to the first 75 URLs for this run.':'Scan & import completed.',true
                );
            }
            $message=(int)$result['saved'].' URLs checked and saved';
            if((int)$result['failed']>0){$message.=', '.(int)$result['failed'].' had problems';}
            if(!empty($result['limited'])){$message.=' (first 75 URLs this run)';}
            // Reopen session only to store a flash message for the redirect.
            if(session_status()!==PHP_SESSION_ACTIVE){@session_start();}
            $_SESSION['flash_success']=$message.'.';
        }catch(\Throwable $e){
            if($historyId>0){try{WebsiteActivityHistory::fail($historyId,$e->getMessage());}catch(\Throwable $historyError){}}
            if(session_status()!==PHP_SESSION_ACTIVE){@session_start();}
            \App\Core\Logger::exception($e, 'website-catalog', ['event' => 'Website scan failed'], 'warning');
            $_SESSION['flash_error']=$e->getMessage();
        }
        $this->redirect('/admin/settings#website-comparison');
    }

    /**
     * Remove one configured website source and all URLs/products indexed from
     * that source host. / 删除网站来源，并删除该网站关联的全部扫描 URL / 产品记录。
     */
    public function removeWebsiteSource(): void
    {
        $admin=Auth::requireRole('admin');Csrf::verify($_POST['_csrf']??null);
        try{
            $host=(string)($_POST['host']??'');
            $runningHosts=WebsiteScanJob::runningHosts();
            if($runningHosts){
                throw new \DomainException('Stop the active website scan before deleting any website. Currently scanning: '.$runningHosts[0].'.');
            }
            $deleted=WebsiteCatalog::removeSource($host,(int)$admin['id']);
            try{WebsiteScanJob::remove($host);}catch(\Throwable $scanCleanupError){\App\Core\Logger::exception($scanCleanupError,'website-catalog',['event'=>'Website scan state cleanup failed','host'=>$host],'warning');}
            $_SESSION['flash_success']='Website source deleted with '.$deleted.' related URL'.($deleted===1?'':'s').'.';
        }catch(\Throwable $e){
            \App\Core\Logger::exception($e,'website-catalog',['event'=>'Website source remove failed'],'warning');
            $_SESSION['flash_error']=$e->getMessage();
        }
        $this->redirect('/admin/settings#website-comparison');
    }

    /**
     * Run one AJAX product-crawler batch. The browser repeatedly calls this
     * endpoint so a large website does not require one long PHP request.
     * 运行一次 AJAX 产品扫描批次；浏览器重复调用，避免长时间 PHP 请求。
     */
    public function scanWebsiteProductsBatch(): void
    {
        $admin=Auth::requireRole('admin');Csrf::verify($_POST['_csrf']??null);
        try{
            $website=(string)($_POST['website_url']??'');
            $urls=json_decode((string)($_POST['urls']??'[]'),true);
            if(!is_array($urls)){throw new \DomainException('Invalid product scan batch.');}

            // EN: Scan Products is also allowed to be the first action. Ensure
            // the typed website is saved before scanning so Admin does not have
            // to click Save Website and then click Scan Products separately.
            // 中文：允许 Scan Products 作为第一步；扫描前自动保存输入的网站，
            // Admin 不需要先 Save Website 再另外点一次 Scan Products。
            $source=WebsiteCatalog::ensureSource($website,(int)$admin['id']);
            $website=(string)$source['url'];

            if(session_status()===PHP_SESSION_ACTIVE){session_write_close();}
            $result=WebsiteCatalog::scanProductBatch($website,$urls);
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            $this->json(['ok'=>true,'website_url'=>$website,'source_host'=>$source['host']]+$result);
        }catch(\Throwable $e){
            \App\Core\Logger::exception($e,'website-catalog',['event'=>'Website product scan batch failed'],'warning');
            $this->json(['ok'=>false,'message'=>$e->getMessage()],422);
        }
    }

    /** Start or resume a persistent website product scan. / 启动或恢复持久化网站产品扫描。 */
    public function startWebsiteProductScan(): void
    {
        $admin=Auth::requireRole('admin');Csrf::verify($_POST['_csrf']??null);
        try{
            $state=WebsiteScanJob::start((string)($_POST['website_url']??''),(int)$admin['id']);
            $stats=WebsiteCatalog::sourceStats();$state['library_stats']=$stats[(string)($state['source_host']??'')]??[];
            $state['history_items']=WebsiteActivityHistory::scanItems((int)($state['history_id']??0),0,100);
            $this->json(['ok'=>true,'state'=>$state]);
        }catch(\Throwable $e){
            \App\Core\Logger::exception($e,'website-catalog',['event'=>'Website product scan start failed'],'warning');
            $this->json(['ok'=>false,'message'=>$e->getMessage()],422);
        }
    }

    /** Process one short persisted website scan step. / 处理一个短批次的持久化网站扫描。 */
    public function stepWebsiteProductScan(): void
    {
        Auth::requireRole('admin');Csrf::verify($_POST['_csrf']??null);
        if(session_status()===PHP_SESSION_ACTIVE){session_write_close();}
        try{
            $historyId=(int)($_POST['history_id']??0);
            $afterItemId=max(0,(int)($_POST['after_item_id']??0));
            $state=WebsiteScanJob::step((string)($_POST['host']??''),$historyId);
            $stats=WebsiteCatalog::sourceStats();$state['library_stats']=$stats[(string)($state['source_host']??'')]??[];
            $state['history_items']=WebsiteActivityHistory::scanItems((int)($state['history_id']??0),$afterItemId,100);
            $this->json(['ok'=>true,'state'=>$state]);
        }catch(\Throwable $e){
            \App\Core\Logger::exception($e,'website-catalog',['event'=>'Website product scan step failed'],'warning');
            $this->json(['ok'=>false,'message'=>$e->getMessage()],422);
        }
    }

    /** Return persisted website scan state after refresh. / 页面刷新后读取持久化扫描状态。 */
    public function websiteProductScanStatus(): void
    {
        Auth::requireRole('admin');
        if(session_status()===PHP_SESSION_ACTIVE){session_write_close();}
        try{
            $historyId=(int)($_GET['history_id']??0);
            $afterItemId=max(0,(int)($_GET['after_item_id']??0));
            $state=$historyId>0
                ?WebsiteScanJob::statusByHistory($historyId)
                :WebsiteScanJob::status((string)($_GET['host']??''));
            if($state){
                $stats=WebsiteCatalog::sourceStats();$state['library_stats']=$stats[(string)($state['source_host']??'')]??[];
                $state['history_items']=WebsiteActivityHistory::scanItems((int)($state['history_id']??0),$afterItemId,$historyId>0?5000:100);
            }
            $this->json(['ok'=>true,'state'=>$state]);
        }catch(\Throwable $e){
            \App\Core\Logger::exception($e,'website-catalog',['event'=>'Website product scan status failed'],'warning');
            $this->json(['ok'=>false,'message'=>$e->getMessage()],422);
        }
    }

    /** Stop one persistent website scan without deleting scanned products. / 停止扫描但保留已扫描产品。 */
    public function stopWebsiteProductScan(): void
    {
        Auth::requireRole('admin');Csrf::verify($_POST['_csrf']??null);
        try{
            $mode=strtolower(trim((string)($_POST['mode']??'pause')));
            $historyId=(int)($_POST['history_id']??0);
            $state=$mode==='stop'
                ?WebsiteScanJob::terminate((string)($_POST['host']??''),$historyId)
                :WebsiteScanJob::pause((string)($_POST['host']??''),$historyId);
            if($state){
                $stats=WebsiteCatalog::sourceStats();$state['library_stats']=$stats[(string)($state['source_host']??'')]??[];
                $state['history_items']=WebsiteActivityHistory::scanItems((int)($state['history_id']??0),0,100);
            }
            $this->json(['ok'=>true,'state'=>$state]);
        }catch(\Throwable $e){
            \App\Core\Logger::exception($e,'website-catalog',['event'=>'Website product scan stop failed'],'warning');
            $this->json(['ok'=>false,'message'=>$e->getMessage()],422);
        }
    }


    /** Continue a stopped/interrupted persistent scan from the saved queue. */
    public function resumeWebsiteProductScan(): void
    {
        Auth::requireRole('admin');Csrf::verify($_POST['_csrf']??null);
        try{
            $historyId=(int)($_POST['history_id']??0);
            $state=WebsiteScanJob::resume((string)($_POST['host']??''),$historyId);
            if(!$state){throw new \DomainException('Website scan job was not found.');}
            $stats=WebsiteCatalog::sourceStats();$state['library_stats']=$stats[(string)($state['source_host']??'')]??[];
            $state['history_items']=WebsiteActivityHistory::scanItems((int)($state['history_id']??0),0,100);
            $this->json(['ok'=>true,'state'=>$state]);
        }catch(\Throwable $e){
            \App\Core\Logger::exception($e,'website-catalog',['event'=>'Website product scan resume failed'],'warning');
            $this->json(['ok'=>false,'message'=>$e->getMessage()],422);
        }
    }

    /**
     * EN: Handle the add website reference HTTP action for admin settings controller and return the appropriate response.
     * 中文：处理 admin settings controller 的“add website reference”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     *
     * @throws \DomainException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    public function addWebsiteReference(): void
    {
        Auth::requireRole('admin');
        Csrf::verify($_POST['_csrf']??null);
        try{
            $website=trim((string)($_POST['website_url']??''));
            if($website===''){$website=(string)(WebsiteCatalog::sources()[0]['url']??'');}
            if($website===''){throw new \DomainException('Add a company website source first.');}
            $referenceId=WebsiteCatalog::addManual(
                $website,
                (string)($_POST['page_url']??''),
                (string)($_POST['title']??''),
                (string)($_POST['description']??''),
                (string)($_POST['image_url']??'')
            );
            if((string)($_POST['ajax']??'')==='1'){
                $this->json(['ok'=>true,'id'=>$referenceId,'message'=>'Website reference saved.']);
                return;
            }
            $_SESSION['flash_success']='Website reference saved.';
        }catch(\Throwable $e){
            \App\Core\Logger::exception($e, 'website-catalog', ['event' => 'Manual website reference save failed'], 'warning');
            if((string)($_POST['ajax']??'')==='1'){
                $this->json(['ok'=>false,'message'=>$e->getMessage()],422);
                return;
            }
            $_SESSION['flash_error']=$e->getMessage();
        }
        $returnHost=strtolower(trim((string)($_POST['return_host']??'')));
        if($returnHost!==''&&WebsiteCatalog::source($returnHost)){
            $this->redirect('/admin/website/source?host='.rawurlencode($returnHost));
        }
        $this->redirect('/admin/settings#website-comparison');
    }

    /**
     * EN: Handle the website references HTTP action for admin settings controller and return the appropriate response.
     * 中文：处理 admin settings controller 的“website references”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function websiteReferences(): void
    {
        Auth::requireRole('admin');
        if(session_status()===PHP_SESSION_ACTIVE){session_write_close();}
        try{
            $host=strtolower(trim((string)($_GET['host']??'')));
            if($host!==''&&!WebsiteCatalog::source($host)){
                throw new \DomainException('Website source was not found.');
            }
            $rows=WebsiteCatalog::search(trim((string)($_GET['q']??'')),200,$host);
            $this->json(['ok'=>true,'host'=>$host,'rows'=>$rows]);
        }catch(\Throwable $e){
            \App\Core\Logger::exception($e, 'website-catalog', ['event' => 'Website reference search failed'], 'warning');
            $this->json(['ok'=>false,'message'=>$e->getMessage()],422);
        }
    }

    /**
     * EN: Handle the delete website reference HTTP action for admin settings controller and return the appropriate response.
     * 中文：处理 admin settings controller 的“delete website reference”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     *
     * @throws \DomainException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    public function deleteWebsiteReference(): void
    {
        Auth::requireRole('admin');
        Csrf::verify($_POST['_csrf']??null);
        try{
            $id=(int)($_POST['id']??0);
            if(!WebsiteCatalog::deleteReference($id)){
                throw new \DomainException('Website reference was not found.');
            }
            $this->json(['ok'=>true,'id'=>$id,'message'=>'Website reference deleted.']);
        }catch(\Throwable $e){
            \App\Core\Logger::exception($e, 'website-catalog', ['event' => 'Website reference delete failed'], 'warning');
            $this->json(['ok'=>false,'message'=>$e->getMessage()],422);
        }
    }

    /**
     * EN: Handle the website catalog sample HTTP action for admin settings controller and return the appropriate response.
     * 中文：处理 admin settings controller 的“website catalog sample”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function websiteCatalogSample(): void
    {
        Auth::requireRole('admin');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="website-reference-sample.csv"');
        header('Cache-Control: no-store');
        echo "page_url,title,description,image_url\n";
        echo "https://example.com/product/example-product,Example Product,Example product description,https://example.com/images/example-product.jpg\n";
        exit;
    }

    /**
     * EN: Update the prune tickets operation.
     * 中文：更新“prune tickets”操作。
     *
     * @return void No value is returned. / 无返回值。
     */
    private function pruneTickets(): void
    {
        $tickets = $_SESSION['provider_test_tickets'] ?? [];

        foreach ($tickets as $key => $ticket) {
            if ((int)($ticket['expires_at'] ?? 0) < time()) {
                unset($tickets[$key]);
            }
        }

        $_SESSION['provider_test_tickets'] = $tickets;
    }
}
