<?php
namespace App\Services;

use App\Models\FetchJob;

class RegistryScrapeCreatorsMarketplaceProvider
{
    private array $profile;

    public function __construct(array $profile)
    {
        $this->profile = $profile;
    }

    public function fetch(string $url, int $userId, bool $bypassCache = false): array
    {
        $url = PlatformUrl::normalize($url, 'facebook');
        if (!$url) {
            throw new \RuntimeException('Facebook Marketplace URL is malformed.');
        }

        $token = trim((string)($this->profile['api_token'] ?? ''));
        if ($token === '') {
            throw new \RuntimeException('ScrapeCreators API key is missing.');
        }

        $externalId = PlatformUrl::externalId('facebook', $url);
        $providerKey = $this->providerKey();

        if (!$bypassCache) {
            $cached = FetchJob::recentReady('facebook', $externalId, 10, $providerKey);
            if ($cached && $this->complete($cached)) {
                $cached['_provider_cache'] = true;
                return $cached;
            }
        }

        $timeout = max(
            8,
            min(45, (int)(($this->profile['config']['timeout_seconds'] ?? 20)))
        );

        $jobId = FetchJob::create($userId, 'facebook', $url, $externalId, $providerKey);

        try {
            $query = $externalId ? ['id' => $externalId] : ['url' => $url];
            $endpoint =
                'https://api.scrapecreators.com/v1/facebook/marketplace/item?'
                . http_build_query($query);

            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'x-api-key: ' . $token,
                ],
            ]);

            $raw = curl_exec($ch);
            $error = curl_error($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);

            if ($raw === false) {
                throw new \RuntimeException(
                    'ScrapeCreators network error: ' . ($error ?: 'unknown error')
                );
            }

            $data = json_decode((string)$raw, true);

            if ($status < 200 || $status >= 300 || !is_array($data) || empty($data['success'])) {
                throw new \RuntimeException(
                    'ScrapeCreators request failed: '
                    . $this->message($data, (string)$raw)
                );
            }

            $result = $this->normalize($data, $url, $externalId);
            if (!$this->complete($result)) {
                throw new \RuntimeException(
                    'ScrapeCreators returned incomplete listing metadata.'
                );
            }

            FetchJob::setSnapshot($jobId, (string)$result['external_post_id'], $status);
            FetchJob::setReady($jobId, $result, $status);

            return $result;
        } catch (\Throwable $e) {
            try {
                FetchJob::setStatus($jobId, 'failed', null, $e->getMessage());
            } catch (\Throwable $ignored) {
            }
            throw $e;
        }
    }

    private function providerKey(): string
    {
        $id = (int)($this->profile['id'] ?? 0);
        return $id > 0 ? 'profile_' . $id : 'test_scrapecreators';
    }

    private function complete(array $item): bool
    {
        return trim((string)($item['external_post_id'] ?? '')) !== ''
            && trim((string)($item['title'] ?? '')) !== ''
            && trim((string)($item['description'] ?? '')) !== ''
            && trim((string)($item['published_raw'] ?? '')) !== '';
    }

    private function normalize(array $record, string $submittedUrl, ?string $expectedId): array
    {
        $id = trim((string)(
            $record['id'] ?? $record['product_id'] ?? $expectedId ?? ''
        ));
        $canonical = PlatformUrl::normalize(
            (string)($record['url'] ?? $submittedUrl),
            'facebook'
        ) ?: $submittedUrl;

        return [
            'provider' => 'scrapecreators',
            'provider_profile_id' => (int)($this->profile['id'] ?? 0),
            'provider_name' => (string)($this->profile['name'] ?? 'ScrapeCreators'),
            'provider_job_id' => $id !== '' ? $id : null,
            'submitted_url' => $submittedUrl,
            'resolved_url' => $canonical,
            'canonical_url' => $canonical,
            'external_post_id' => $id !== '' ? $id : null,
            'title' => trim((string)($record['title'] ?? '')),
            'description' => trim((string)($record['description'] ?? '')),
            'published_raw' => trim((string)(
                $record['creation_time']
                ?? $record['listing_date']
                ?? $record['date_posted']
                ?? $record['posted_at']
                ?? ''
            )) ?: null,
            'raw' => $record,
        ];
    }

    private function message($json, string $raw): string
    {
        if (is_array($json)) {
            foreach (['error','message','detail'] as $key) {
                if (isset($json[$key]) && is_scalar($json[$key])) {
                    return substr(trim((string)$json[$key]), 0, 500);
                }
            }
        }

        $clean = trim(preg_replace('/\s+/u', ' ', strip_tags($raw)));
        return $clean !== '' ? substr($clean, 0, 500) : 'Unknown provider error.';
    }
}
