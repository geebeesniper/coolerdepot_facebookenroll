<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Csrf;
use App\Models\Inspection;
use App\Models\Post;
use App\Services\PostInspector;
use App\Services\PlatformUrl;

class ApiController extends Controller
{
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
}
