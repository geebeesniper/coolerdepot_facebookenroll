<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Models\FetchJob;
use App\Models\Setting;
use App\Services\BrightDataMarketplaceProvider;
use App\Services\PlatformUrl;

class AdminSettingsController extends Controller
{
    public function index(): void
    {
        $admin = Auth::requireRole('admin');

        $this->renderSettings($admin);
    }

    public function save(): void
    {
        $admin = Auth::requireRole('admin');
        Csrf::verify($_POST['_csrf'] ?? null);

        try {
            $enabled = isset($_POST['brightdata_enabled']) ? '1' : '0';
            $datasetId = trim((string)($_POST['dataset_id'] ?? BrightDataMarketplaceProvider::DEFAULT_DATASET_ID));
            $timeout = max(15, min(90, (int)($_POST['timeout_seconds'] ?? 45)));
            $poll = max(2, min(10, (int)($_POST['poll_seconds'] ?? 3)));
            $token = trim((string)($_POST['api_token'] ?? ''));
            $removeToken = isset($_POST['remove_api_token']);

            if (!preg_match('/^gd_[A-Za-z0-9]+$/', $datasetId)) {
                throw new \RuntimeException('Dataset ID must start with gd_.');
            }

            Setting::set('brightdata_enabled', $enabled, (int)$admin['id']);
            Setting::set('brightdata_marketplace_dataset_id', $datasetId, (int)$admin['id']);
            Setting::set('brightdata_timeout_seconds', (string)$timeout, (int)$admin['id']);
            Setting::set('brightdata_poll_seconds', (string)$poll, (int)$admin['id']);

            if ($removeToken) {
                Setting::delete('brightdata_api_token');
            } elseif ($token !== '') {
                Setting::set('brightdata_api_token', $token, (int)$admin['id'], true);
            }

            $_SESSION['flash_success'] = 'Bright Data settings saved.';
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        $this->redirect('/admin/settings');
    }

    public function test(): void
    {
        $admin = Auth::requireRole('admin');
        Csrf::verify($_POST['_csrf'] ?? null);

        $submittedUrl = trim((string)($_POST['test_url'] ?? ''));
        $url = PlatformUrl::normalize($submittedUrl, 'facebook');
        $testResult = null;
        $testError = null;

        if (!$url) {
            $testError = 'Enter a valid Facebook Marketplace URL.';
            $url = $submittedUrl;
        } else {
            try {
                $testResult = (new BrightDataMarketplaceProvider())->fetch(
                    $url,
                    (int)$admin['id']
                );
            } catch (\Throwable $e) {
                $testError = $e->getMessage();
            }
        }

        $this->renderSettings($admin, $testResult, $testError, $url);
    }

    private function renderSettings(
        array $admin,
        ?array $testResult = null,
        ?string $testError = null,
        string $testUrl = ''
    ): void {
        $tokenConfigured = false;

        try {
            $tokenConfigured = trim((string)Setting::get('brightdata_api_token', '')) !== '';
        } catch (\Throwable $e) {
            $testError = $testError ?: $e->getMessage();
        }

        $settings = [
            'enabled' => Setting::get('brightdata_enabled', '0') === '1',
            'dataset_id' => Setting::get(
                'brightdata_marketplace_dataset_id',
                BrightDataMarketplaceProvider::DEFAULT_DATASET_ID
            ),
            'timeout_seconds' => (int)Setting::get('brightdata_timeout_seconds', '45'),
            'poll_seconds' => (int)Setting::get('brightdata_poll_seconds', '3'),
        ];

        $jobs = FetchJob::recent(12);

        $this->render('admin/settings', compact(
            'admin',
            'settings',
            'tokenConfigured',
            'testResult',
            'testError',
            'testUrl',
            'jobs'
        ));
    }
}
