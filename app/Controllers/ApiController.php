<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Csrf;
use App\Models\Inspection;
use App\Services\PostInspector;
use App\Services\PlatformUrl;

class ApiController extends Controller
{
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

        $r = (new PostInspector())->inspect((int)$u['id'], $platform, $url);
        $token = Inspection::create($r);

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
            'message' => $r['failure_message'] ?: 'Post verified. You may save it now.'
        ]);
    }
}
