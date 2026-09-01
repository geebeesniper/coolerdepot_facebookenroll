<?php
namespace App\Services;

use App\Models\FetchJob;

class RegistryBrightDataMarketplaceProvider
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
            throw new \RuntimeException('Bright Data token is missing.');
        }

        $config = $this->profile['config'] ?? [];
        $dataset = trim((string)(
            $config['dataset_id'] ?? BrightDataMarketplaceProvider::DEFAULT_DATASET_ID
        ));
        $timeout = max(15, min(90, (int)($config['timeout_seconds'] ?? 45)));
        $poll = max(2, min(10, (int)($config['poll_seconds'] ?? 3)));
        $externalId = PlatformUrl::externalId('facebook', $url);
        $providerKey = $this->providerKey();

        if (!$bypassCache) {
            $cached = FetchJob::recentReady('facebook', $externalId, 10, $providerKey);
            if ($cached && $this->complete($cached)) {
                $cached['_provider_cache'] = true;
                return $cached;
            }
        }

        $jobId = FetchJob::create($userId, 'facebook', $url, $externalId, $providerKey);

        try {
            $trigger = $this->request(
                'POST',
                'https://api.brightdata.com/datasets/v3/trigger'
                    . '?dataset_id=' . rawurlencode($dataset)
                    . '&format=json&uncompressed_webhook=true',
                $token,
                [['url' => $url]],
                20
            );

            $triggerData = json_decode($trigger['body'], true);
            $snapshotId = is_array($triggerData)
                ? trim((string)($triggerData['snapshot_id'] ?? ''))
                : '';

            if ($trigger['status'] < 200 || $trigger['status'] >= 300 || $snapshotId === '') {
                throw new \RuntimeException(
                    'Bright Data trigger failed: '
                    . $this->message($triggerData, $trigger['body'])
                );
            }

            FetchJob::setSnapshot($jobId, $snapshotId, $trigger['status']);
            $deadline = microtime(true) + $timeout;

            while (microtime(true) < $deadline) {
                $progress = $this->request(
                    'GET',
                    'https://api.brightdata.com/datasets/v3/progress/'
                        . rawurlencode($snapshotId),
                    $token,
                    null,
                    15
                );

                $progressData = json_decode($progress['body'], true);
                $status = strtolower((string)(
                    is_array($progressData) ? ($progressData['status'] ?? '') : ''
                ));

                if ($progress['status'] >= 400 || $status === 'failed') {
                    throw new \RuntimeException(
                        'Bright Data job failed: '
                        . $this->message($progressData, $progress['body'])
                    );
                }

                if ($status === 'ready') {
                    $download = $this->request(
                        'GET',
                        'https://api.brightdata.com/datasets/v3/snapshot/'
                            . rawurlencode($snapshotId)
                            . '?format=json',
                        $token,
                        null,
                        20
                    );

                    $data = json_decode($download['body'], true);

                    if ($download['status'] < 200 || $download['status'] >= 300) {
                        throw new \RuntimeException(
                            'Bright Data snapshot failed: '
                            . $this->message($data, $download['body'])
                        );
                    }

                    $record = $this->firstRecord($data);
                    if (!$record) {
                        throw new \RuntimeException(
                            'Bright Data returned no valid Marketplace listing.'
                        );
                    }

                    $result = $this->normalize($record, $url, $snapshotId);
                    if (!$this->complete($result)) {
                        throw new \RuntimeException(
                            'Bright Data returned incomplete listing metadata.'
                        );
                    }

                    FetchJob::setReady($jobId, $result, $download['status']);
                    return $result;
                }

                FetchJob::setStatus($jobId, 'running', $progress['status']);
                sleep($poll);
            }

            throw new \RuntimeException('Bright Data timed out.');
        } catch (\Throwable $e) {
            try {
                FetchJob::setStatus($jobId, 'failed', null, $e->getMessage());
            } catch (\Throwable $ignored) {
                \App\Core\Logger::exception(
                    $ignored,
                    'provider',
                    ['event' => 'Registry Bright Data fetch-job failure could not be persisted'],
                    'error'
                );
            }
            throw $e;
        }
    }

    private function providerKey(): string
    {
        $id = (int)($this->profile['id'] ?? 0);
        return $id > 0 ? 'profile_' . $id : 'test_brightdata';
    }

    private function complete(array $item): bool
    {
        return trim((string)($item['external_post_id'] ?? '')) !== ''
            && trim((string)($item['title'] ?? '')) !== ''
            && trim((string)($item['description'] ?? '')) !== ''
            && trim((string)($item['published_raw'] ?? '')) !== '';
    }

    private function normalize(array $record, string $submittedUrl, string $snapshotId): array
    {
        $published = null;

        foreach ([
            'listing_date',
            'date_posted',
            'posted_at',
            'creation_time',
            'created_at',
            'date_created',
        ] as $field) {
            if (isset($record[$field]) && trim((string)$record[$field]) !== '') {
                $published = trim((string)$record[$field]);
                break;
            }
        }

        $id = trim((string)(
            $record['product_id']
            ?? $record['listing_id']
            ?? PlatformUrl::externalId('facebook', $submittedUrl)
            ?? ''
        ));

        $canonical = PlatformUrl::normalize(
            (string)($record['url'] ?? $submittedUrl),
            'facebook'
        ) ?: $submittedUrl;

        return [
            'provider' => 'brightdata',
            'provider_profile_id' => (int)($this->profile['id'] ?? 0),
            'provider_name' => (string)($this->profile['name'] ?? 'Bright Data'),
            'provider_job_id' => $snapshotId,
            'submitted_url' => $submittedUrl,
            'resolved_url' => $canonical,
            'canonical_url' => $canonical,
            'external_post_id' => $id !== '' ? $id : null,
            'title' => trim((string)($record['title'] ?? '')),
            'description' => trim((string)($record['description'] ?? '')),
            'published_raw' => $published,
            'raw' => $record,
        ];
    }

    private function firstRecord($data): ?array
    {
        if (!is_array($data)) {
            return null;
        }

        $rows = array_is_list($data) ? $data : [];

        if (!$rows) {
            foreach (['data','results','records','items'] as $key) {
                if (!empty($data[$key]) && is_array($data[$key])) {
                    if (array_is_list($data[$key])) {
                        $rows = array_merge($rows, $data[$key]);
                    } else {
                        $rows[] = $data[$key];
                    }
                }
            }
            $rows[] = $data;
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            foreach ([
                'title','product_id','listing_id','url','description',
                'listing_date','date_posted','posted_at','creation_time'
            ] as $field) {
                if (isset($row[$field]) && trim((string)$row[$field]) !== '') {
                    return $row;
                }
            }
        }

        return null;
    }

    private function request(
        string $method,
        string $url,
        string $token,
        ?array $body,
        int $timeout
    ): array {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Could not initialize Bright Data HTTP client.');
        }

        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $token,
        ];

        $opts = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $headers,
        ];

        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_SLASHES);
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_HTTPHEADER] = $headers;
        }

        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($raw === false) {
            throw new \RuntimeException(
                'Bright Data network error: ' . ($error ?: 'unknown error')
            );
        }

        return ['status' => $status, 'body' => (string)$raw];
    }

    private function message($json, string $raw): string
    {
        if (is_array($json)) {
            foreach (['error_message','message','error','detail'] as $key) {
                if (isset($json[$key]) && is_scalar($json[$key])) {
                    return substr(trim((string)$json[$key]), 0, 500);
                }
            }
        }

        $clean = trim(preg_replace('/\s+/u', ' ', strip_tags($raw)));
        return $clean !== '' ? substr($clean, 0, 500) : 'Unknown provider error.';
    }
}
