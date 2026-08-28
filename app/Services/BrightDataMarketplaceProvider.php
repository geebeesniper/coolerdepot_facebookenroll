<?php
namespace App\Services;

use App\Models\FetchJob;
use App\Models\Setting;

class BrightDataMarketplaceProvider
{
    public const DEFAULT_DATASET_ID = 'gd_lvt9iwuh6fbcwmx1a';

    public function configured(): bool
    {
        try {
            return Setting::get('brightdata_enabled', '0') === '1'
                && trim((string)Setting::get('brightdata_api_token', '')) !== '';
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function fetch(string $url, int $requestedByUserId): array
    {
        if (!$this->configured()) {
            throw new \RuntimeException('Bright Data is not configured.');
        }

        $externalId = PlatformUrl::externalId('facebook', $url);

        // Avoid spending another record when Sales re-checks the same listing within 10 minutes.
        $cached = FetchJob::recentReady('facebook', $externalId, 10);
        if ($cached) {
            $cached['_provider_cache'] = true;
            return $cached;
        }

        $token = trim((string)Setting::get('brightdata_api_token', ''));
        $datasetId = trim((string)Setting::get(
            'brightdata_marketplace_dataset_id',
            self::DEFAULT_DATASET_ID
        ));
        $timeout = max(15, min(90, (int)Setting::get('brightdata_timeout_seconds', '45')));
        $pollSeconds = max(2, min(10, (int)Setting::get('brightdata_poll_seconds', '3')));

        if (!preg_match('/^gd_[A-Za-z0-9]+$/', $datasetId)) {
            throw new \RuntimeException('Bright Data Marketplace dataset ID is invalid.');
        }

        $jobId = FetchJob::create(
            $requestedByUserId,
            'facebook',
            $url,
            $externalId,
            'brightdata'
        );

        try {
            $trigger = $this->request(
                'POST',
                'https://api.brightdata.com/datasets/v3/trigger'
                    . '?dataset_id=' . rawurlencode($datasetId)
                    . '&format=json&uncompressed_webhook=true',
                $token,
                [['url' => $url]],
                20
            );

            $triggerData = json_decode($trigger['body'], true);
            $snapshotId = is_array($triggerData)
                ? (string)($triggerData['snapshot_id'] ?? '')
                : '';

            if ($trigger['status'] < 200 || $trigger['status'] >= 300 || $snapshotId === '') {
                $message = $this->providerMessage($triggerData, $trigger['body']);
                FetchJob::setStatus($jobId, 'failed', $trigger['status'], $message);
                throw new \RuntimeException('Bright Data trigger failed: ' . $message);
            }

            FetchJob::setSnapshot($jobId, $snapshotId, $trigger['status']);

            $deadline = microtime(true) + $timeout;

            while (microtime(true) < $deadline) {
                $progress = $this->request(
                    'GET',
                    'https://api.brightdata.com/datasets/v3/progress/' . rawurlencode($snapshotId),
                    $token,
                    null,
                    15
                );

                $progressData = json_decode($progress['body'], true);
                $status = strtolower((string)(
                    is_array($progressData) ? ($progressData['status'] ?? '') : ''
                ));

                if ($progress['status'] === 401) {
                    FetchJob::setStatus($jobId, 'failed', 401, 'Bright Data token was rejected.');
                    throw new \RuntimeException('Bright Data token was rejected.');
                }

                if ($status === 'failed') {
                    $message = $this->providerMessage($progressData, $progress['body']);
                    FetchJob::setStatus($jobId, 'failed', $progress['status'], $message);
                    throw new \RuntimeException('Bright Data job failed: ' . $message);
                }

                if ($status === 'ready') {
                    $download = $this->request(
                        'GET',
                        'https://api.brightdata.com/datasets/v3/snapshot/'
                            . rawurlencode($snapshotId) . '?format=json',
                        $token,
                        null,
                        20
                    );

                    $downloadData = json_decode($download['body'], true);

                    if ($download['status'] < 200 || $download['status'] >= 300) {
                        $message = $this->providerMessage($downloadData, $download['body']);
                        FetchJob::setStatus($jobId, 'failed', $download['status'], $message);
                        throw new \RuntimeException('Bright Data snapshot download failed: ' . $message);
                    }

                    $record = $this->firstRecord($downloadData);

                    if (!$record) {
                        FetchJob::setStatus($jobId, 'failed', $download['status'], 'Snapshot contained no listing record.');
                        throw new \RuntimeException('Bright Data returned an empty Marketplace result.');
                    }

                    $normalized = $this->normalize($record, $url, $snapshotId);
                    FetchJob::setReady($jobId, $normalized, $download['status']);

                    return $normalized;
                }

                if (!in_array($status, ['', 'starting', 'running', 'building'], true)
                    && $progress['status'] >= 400) {
                    $message = $this->providerMessage($progressData, $progress['body']);
                    FetchJob::setStatus($jobId, 'failed', $progress['status'], $message);
                    throw new \RuntimeException('Bright Data progress check failed: ' . $message);
                }

                FetchJob::setStatus($jobId, 'running', $progress['status']);
                sleep($pollSeconds);
            }

            FetchJob::setStatus(
                $jobId,
                'failed',
                null,
                'Bright Data did not finish within ' . $timeout . ' seconds.'
            );

            throw new \RuntimeException('Bright Data timed out while fetching the Facebook listing.');
        } catch (\Throwable $e) {
            // If the failure happened before a status update, make sure the job is still diagnosable.
            try {
                FetchJob::setStatus($jobId, 'failed', null, $e->getMessage());
            } catch (\Throwable $ignored) {
            }

            throw $e;
        }
    }

    private function normalize(array $record, string $submittedUrl, string $snapshotId): array
    {
        $title = trim((string)($record['title'] ?? ''));
        $description = trim((string)($record['description'] ?? ''));
        $productId = trim((string)(
            $record['product_id']
            ?? $record['listing_id']
            ?? PlatformUrl::externalId('facebook', $submittedUrl)
            ?? ''
        ));

        $canonicalUrl = trim((string)($record['url'] ?? $submittedUrl));

        if (!PlatformUrl::allowed($canonicalUrl, 'facebook')) {
            $canonicalUrl = $submittedUrl;
        }

        // IMPORTANT: Bright Data may return a generic crawl "timestamp".
        // That is not accepted as the Facebook listing publish date.
        // Only semantically listing/post creation fields are used here.
        $publishedRaw = null;
        foreach ([
            'listing_date',
            'date_posted',
            'posted_at',
            'creation_time',
            'created_at',
            'date_created',
        ] as $field) {
            if (isset($record[$field]) && trim((string)$record[$field]) !== '') {
                $publishedRaw = trim((string)$record[$field]);
                break;
            }
        }

        return [
            'provider' => 'brightdata',
            'provider_job_id' => $snapshotId,
            'submitted_url' => $submittedUrl,
            'resolved_url' => $canonicalUrl,
            'canonical_url' => $canonicalUrl,
            'external_post_id' => $productId !== '' ? $productId : null,
            'title' => $title,
            'description' => $description,
            'published_raw' => $publishedRaw,
            'raw' => $record,
        ];
    }

    private function firstRecord($data): ?array
    {
        if (!is_array($data)) {
            return null;
        }

        if (array_is_list($data)) {
            foreach ($data as $row) {
                if (is_array($row)) {
                    return $row;
                }
            }
            return null;
        }

        // Some responses may wrap rows.
        foreach (['data', 'results', 'records', 'items'] as $key) {
            if (!empty($data[$key]) && is_array($data[$key])) {
                if (array_is_list($data[$key])) {
                    foreach ($data[$key] as $row) {
                        if (is_array($row)) {
                            return $row;
                        }
                    }
                } elseif (is_array($data[$key])) {
                    return $data[$key];
                }
            }
        }

        // If the payload itself looks like one Marketplace record, accept it.
        if (isset($data['title']) || isset($data['product_id']) || isset($data['url'])) {
            return $data;
        }

        return null;
    }

    private function providerMessage($json, string $raw): string
    {
        if (is_array($json)) {
            foreach (['error_message', 'message', 'error', 'detail'] as $key) {
                if (isset($json[$key]) && is_scalar($json[$key])) {
                    return substr(trim((string)$json[$key]), 0, 500);
                }
            }
        }

        $clean = trim(preg_replace('/\s+/u', ' ', strip_tags($raw)));

        return $clean !== '' ? substr($clean, 0, 500) : 'Unknown provider error.';
    }

    private function request(
        string $method,
        string $url,
        string $token,
        ?array $jsonBody = null,
        int $timeout = 20
    ): array {
        $ch = curl_init($url);

        if ($ch === false) {
            throw new \RuntimeException('Could not initialize HTTP client.');
        }

        $headers = [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ];

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => 'CoolerDepot-SalesPosts/' . (string)($GLOBALS['config']['app']['version'] ?? 'dev'),
        ];

        if ($jsonBody !== null) {
            $payload = json_encode($jsonBody, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if ($payload === false) {
                throw new \RuntimeException('Could not encode Bright Data request.');
            }

            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_HTTPHEADER] = $headers;
            $options[CURLOPT_POSTFIELDS] = $payload;
        }

        curl_setopt_array($ch, $options);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($body === false) {
            throw new \RuntimeException('Bright Data network error: ' . ($error ?: 'unknown error'));
        }

        return [
            'status' => $status,
            'body' => (string)$body,
        ];
    }
}
