<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Models\FetchJob;
use App\Models\ProviderProfile;
use App\Models\Setting;
use App\Services\MarketplaceProviderDraft;
use App\Services\MarketplaceProviderFactory;
use App\Services\PlatformUrl;
use App\Services\ProviderValidationException;
use App\Services\WebsiteCatalog;

class AdminSettingsController extends Controller
{
    private const TEST_TTL = 600;

    public function index(): void
    {
        $admin = Auth::requireRole('admin');

        $providers = ProviderProfile::allAdmin();
        $jobs = FetchJob::recent(20);
        $registryReady = ProviderProfile::registryEnabled();
        $websiteStats = \App\Services\DuplicateIndex::websiteStats();
        $companyName = trim((string)Setting::get('company_name', 'CoolerDepot')) ?: 'CoolerDepot';
        $websiteUrl = trim((string)Setting::get('company_website_url', ''));
        $websiteQuery = trim((string)($_GET['website_q'] ?? ''));
        $websiteReferences = [];
        $websiteStats['library_ready'] = false;
        if (!empty($websiteStats['ready'])) {
            try {
                $websiteReferences = WebsiteCatalog::search($websiteQuery, 100);
                $websiteStats['library_ready'] = true;
            } catch (\Throwable $e) {
                $websiteReferences = [];
            }
        }
        $providerNames = [];

        foreach ($providers as $provider) {
            $providerNames['profile_' . (int)$provider['id']] =
                (string)$provider['name'];
        }

        $this->render('admin/settings', compact(
            'admin',
            'providers',
            'jobs',
            'registryReady',
            'providerNames',
            'websiteStats',
            'companyName',
            'websiteUrl',
            'websiteQuery',
            'websiteReferences'
        ));
    }

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

        $jobs = FetchJob::recent(20);
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
            'server_time' => date('Y-m-d H:i:s'),
        ]);
    }

    public function importWebsiteCatalog(): void
    {
        Auth::requireRole('admin');
        Csrf::verify($_POST['_csrf'] ?? null);
        try {
            $websiteUrl=trim((string)Setting::get('company_website_url',''));
            if($websiteUrl===''){
                throw new \DomainException('Save the company website URL in Settings first.');
            }
            $file=$_FILES['catalog']??[];
            if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']??'')){
                throw new \DomainException('Choose a CSV file smaller than 5 MB.');
            }
            $result=WebsiteCatalog::importCsv($file['tmp_name'],$websiteUrl);
            $_SESSION['flash_success']=(int)$result['saved'].' website references imported from '.(int)$result['processed'].' CSV rows.';
            if(!empty($result['failed'])){
                $_SESSION['flash_success'].=' Some rows failed; search the website library to review the imported records.';
            }
        } catch (\DomainException $e) {
            $_SESSION['flash_error']=$e->getMessage();
        } catch (\Throwable $e) {
            error_log('[CDSP website catalog] '.$e->getMessage());
            $_SESSION['flash_error']='Website catalog could not be imported. Check the migration and CSV, then retry.';
        }
        $this->redirect('/admin/settings#website-comparison');
    }

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
            $this->json([
                'ok' => false,
                'message' => $e->getMessage() !== ''
                    ? $e->getMessage()
                    : 'Provider test failed during response validation.',
            ], 422);
        }
    }

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
            $this->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

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
            $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

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
            $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

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
            $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // Old v0.1.11 endpoints stay harmless for bookmarks/forms during rollout.
    public function save(): void
    {
        Auth::requireRole('admin');
        $_SESSION['flash_error'] =
            'Provider settings are now managed with the Provider Manager.';
        $this->redirect('/admin/settings');
    }

    public function test(): void
    {
        Auth::requireRole('admin');
        $_SESSION['flash_error'] =
            'Use + Add Provider, test it, then add it to the provider chain.';
        $this->redirect('/admin/settings');
    }

    public function saveBrand(): void
    {
        $admin=Auth::requireRole('admin');
        Csrf::verify($_POST['_csrf']??null);
        $name=trim(strip_tags((string)($_POST['company_name']??'')));
        if($name===''||mb_strlen($name)>80||str_contains($name,'<')||str_contains($name,'>')){
            $_SESSION['flash_error']='Company name must be plain text, 1–80 characters.';
            $this->redirect('/admin/settings#application-settings');
        }
        Setting::set('company_name',$name,(int)$admin['id']);
        $_SESSION['flash_success']='Company name updated.';
        $this->redirect('/admin/settings#application-settings');
    }

    public function saveWebsiteSource(): void
    {
        $admin=Auth::requireRole('admin');
        Csrf::verify($_POST['_csrf']??null);
        try{
            $url=WebsiteCatalog::normalizeUrl((string)($_POST['website_url']??''));
            Setting::set('company_website_url',$url,(int)$admin['id']);
            $_SESSION['flash_success']='Company website URL saved.';
        }catch(\Throwable $e){
            $_SESSION['flash_error']=$e->getMessage();
        }
        $this->redirect('/admin/settings#website-comparison');
    }

    public function scanWebsite(): void
    {
        Auth::requireRole('admin');
        Csrf::verify($_POST['_csrf']??null);
        try{
            $website=trim((string)Setting::get('company_website_url',''));
            if($website===''){throw new \DomainException('Save the company website URL first.');}
            $source=trim((string)($_POST['source_url']??''));

            // Website scans can take several seconds. Release the session lock so
            // other admin checks remain independent and can finish first.
            if(session_status()===PHP_SESSION_ACTIVE){session_write_close();}

            $result=WebsiteCatalog::scan($website,$source);
            $message=(int)$result['saved'].' URLs checked and saved';
            if((int)$result['failed']>0){$message.=', '.(int)$result['failed'].' had problems';}
            if(!empty($result['limited'])){$message.=' (first 75 URLs this run)';}
            // Reopen session only to store a flash message for the redirect.
            if(session_status()!==PHP_SESSION_ACTIVE){@session_start();}
            $_SESSION['flash_success']=$message.'.';
        }catch(\Throwable $e){
            if(session_status()!==PHP_SESSION_ACTIVE){@session_start();}
            $_SESSION['flash_error']=$e->getMessage();
        }
        $this->redirect('/admin/settings#website-comparison');
    }

    public function addWebsiteReference(): void
    {
        Auth::requireRole('admin');
        Csrf::verify($_POST['_csrf']??null);
        try{
            $website=trim((string)Setting::get('company_website_url',''));
            if($website===''){throw new \DomainException('Save the company website URL first.');}
            WebsiteCatalog::addManual(
                $website,
                (string)($_POST['page_url']??''),
                (string)($_POST['title']??''),
                (string)($_POST['description']??''),
                (string)($_POST['image_url']??'')
            );
            $_SESSION['flash_success']='Website reference saved.';
        }catch(\Throwable $e){
            $_SESSION['flash_error']=$e->getMessage();
        }
        $this->redirect('/admin/settings#website-comparison');
    }

    public function websiteReferences(): void
    {
        Auth::requireRole('admin');
        if(session_status()===PHP_SESSION_ACTIVE){session_write_close();}
        try{
            $rows=WebsiteCatalog::search(trim((string)($_GET['q']??'')),100);
            $this->json(['ok'=>true,'rows'=>$rows]);
        }catch(\Throwable $e){
            $this->json(['ok'=>false,'message'=>$e->getMessage()],422);
        }
    }

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
            $this->json(['ok'=>false,'message'=>$e->getMessage()],422);
        }
    }

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
