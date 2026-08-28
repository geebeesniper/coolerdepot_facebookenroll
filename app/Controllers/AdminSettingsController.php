<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Models\FetchJob;
use App\Models\ProviderProfile;
use App\Services\MarketplaceProviderDraft;
use App\Services\MarketplaceProviderFactory;
use App\Services\PlatformUrl;

class AdminSettingsController extends Controller
{
    private const TEST_TTL = 600;

    public function index(): void
    {
        $admin = Auth::requireRole('admin');

        $providers = ProviderProfile::allAdmin();
        $jobs = FetchJob::recent(20);
        $registryReady = ProviderProfile::registryEnabled();
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
            'providerNames'
        ));
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
                throw new \RuntimeException(
                    'Enter a valid Facebook Marketplace item URL for testing.'
                );
            }

            $profile = MarketplaceProviderDraft::fromPost($_POST);
            $result = MarketplaceProviderFactory::make($profile)->fetch(
                $testUrl,
                (int)$admin['id'],
                true
            );

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
        } catch (\Throwable $e) {
            $this->json([
                'ok' => false,
                'message' => $e->getMessage(),
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
