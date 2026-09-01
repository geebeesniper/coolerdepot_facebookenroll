<?php
/**
 * File / 文件：app/Services/BrightDataMarketplaceProvider.php
 * EN: Application service for reusable business or integration logic.
 * 中文：该文件负责可复用的业务逻辑或外部集成服务。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
namespace App\Services;

use App\Models\FetchJob;
use App\Models\Setting;

class BrightDataMarketplaceProvider
{
    public const DEFAULT_DATASET_ID = 'gd_lvt9iwuh6fbcwmx1a';

    /**
     * EN: Checks or validates the condition represented by `configured` (configured).
     * 中文：检查或校验 `configured`（configured）所表示的条件。
     */
    public function configured(): bool
    {
        try {
            return Setting::get('brightdata_enabled', '0') === '1'
                && count($this->credentials()) > 0;
        } catch (\Throwable $e) {
            \App\Core\Logger::exception(
                $e,
                'provider',
                ['event' => 'Bright Data configuration check failed'],
                'warning'
            );
            return false;
        }
    }

    /**
     * EN: Implements the application operation `credentialStatus` (credential Status).
     * 中文：实现应用操作 `credentialStatus`（credential Status）。
     */
    public function credentialStatus(): array
    {
        $credentials = $this->credentials();

        return [
            'primary' => isset($credentials['primary']),
            'secondary' => isset($credentials['secondary']),
        ];
    }

    /**
     * EN: Retrieves or loads data for `fetch` (fetch).
     * 中文：读取或加载 `fetch`（fetch）所需的数据。
     */
    public function fetch(string $url, int $requestedByUserId): array
    {
        if (Setting::get('brightdata_enabled', '0') !== '1') {
            throw new \RuntimeException('Bright Data is disabled.');
        }

        $normalizedUrl = PlatformUrl::normalize($url, 'facebook');

        if (!$normalizedUrl) {
            throw new \RuntimeException('Facebook Marketplace URL is malformed.');
        }

        $url = $normalizedUrl;
        $externalId = PlatformUrl::externalId('facebook', $url);

        // Cache is shared across both Bright Data credentials. Only return a
        // complete cached listing. An incomplete older result must not skip
        // credential failover.
        $cached = FetchJob::recentReady(
            'facebook',
            $externalId,
            10,
            'brightdata'
        );

        if ($cached && $this->complete($cached)) {
            $cached['_provider_cache'] = true;
            return $cached;
        }

        $credentials = $this->credentials();

        if (!$credentials) {
            throw new \RuntimeException('No Bright Data API token is configured.');
        }

        $datasetId = trim((string)Setting::get(
            'brightdata_marketplace_dataset_id',
            self::DEFAULT_DATASET_ID
        ));

        $timeout = max(
            15,
            min(
                90,
                (int)Setting::get('brightdata_timeout_seconds', '45')
            )
        );

        $pollSeconds = max(
            2,
            min(
                10,
                (int)Setting::get('brightdata_poll_seconds', '3')
            )
        );

        if (!preg_match('/^gd_[A-Za-z0-9]+$/', $datasetId)) {
            throw new \RuntimeException(
                'Bright Data Marketplace dataset ID is invalid.'
            );
        }

        $failures = [];
        $attempt = 0;

        // PHP arrays retain insertion order: primary is always attempted first.
        foreach ($credentials as $slot => $token) {
            $attempt++;

            try {
                $item = $this->fetchWithCredential(
                    $url,
                    $externalId,
                    $requestedByUserId,
                    $datasetId,
                    $timeout,
                    $pollSeconds,
                    $slot,
                    $token
                );

                // A provider response that cannot satisfy our Marketplace
                // verification contract is treated as a failed credential
                // attempt so the second Bright Data key is tried BEFORE Apify.
                if (!$this->complete($item)) {
                    throw new \RuntimeException(
                        'Bright Data returned incomplete listing metadata.'
                    );
                }

                $item['_brightdata_attempt'] = $attempt;
                $item['_brightdata_credential'] = $slot;
                $item['_brightdata_failover_used'] = $attempt > 1;

                if ($failures) {
                    $item['_brightdata_failover_reason'] =
                        implode(' | ', $failures);
                }

                return $item;
            } catch (\Throwable $e) {
                \App\Core\Logger::exception(
                    $e,
                    'provider',
                    [
                        'event' => 'Bright Data credential attempt failed',
                        'credential_slot' => $slot,
                        'attempt' => $attempt,
                    ],
                    'warning'
                );
                $failures[] =
                    'Key #' . $attempt . ' (' . $slot . '): '
                    . $e->getMessage();

                // Continue automatically to the next configured Bright Data key.
            }
        }

        throw new \RuntimeException(
            'All Bright Data credentials failed. '
            . implode(' | ', $failures)
        );
    }

    /**
     * EN: Implements the application operation `credentials` (credentials).
     * 中文：实现应用操作 `credentials`（credentials）。
     */
    private function credentials(): array
    {
        $credentials = [];

        $primary = trim((string)Setting::get(
            'brightdata_api_token',
            ''
        ));

        if ($primary !== '') {
            $credentials['primary'] = $primary;
        }

        $secondary = trim((string)Setting::get(
            'brightdata_api_token_secondary',
            ''
        ));

        if ($secondary !== '') {
            $credentials['secondary'] = $secondary;
        }

        return $credentials;
    }

    /**
     * EN: Retrieves or loads data for `fetchWithCredential` (fetch With Credential).
     * 中文：读取或加载 `fetchWithCredential`（fetch With Credential）所需的数据。
     */
    private function fetchWithCredential(
        string $url,
        ?string $externalId,
        int $requestedByUserId,
        string $datasetId,
        int $timeout,
        int $pollSeconds,
        string $slot,
        string $token
    ): array {
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

            if ($trigger['status'] < 200
                || $trigger['status'] >= 300
                || $snapshotId === '') {
                $message = $this->providerMessage(
                    $triggerData,
                    $trigger['body']
                );

                $this->failJob(
                    $jobId,
                    $trigger['status'],
                    $slot,
                    'Trigger failed: ' . $message
                );

                throw new \RuntimeException(
                    'trigger failed: ' . $message
                );
            }

            FetchJob::setSnapshot(
                $jobId,
                $snapshotId,
                $trigger['status']
            );

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

                $progressData = json_decode(
                    $progress['body'],
                    true
                );

                $status = strtolower((string)(
                    is_array($progressData)
                        ? ($progressData['status'] ?? '')
                        : ''
                ));

                // Any HTTP/provider failure is a failed credential attempt.
                // This includes invalid/expired/exhausted/rate-limited keys.
                if ($progress['status'] === 401
                    || $progress['status'] === 403
                    || $progress['status'] === 402
                    || $progress['status'] === 429) {
                    $message = $this->providerMessage(
                        $progressData,
                        $progress['body']
                    );

                    $this->failJob(
                        $jobId,
                        $progress['status'],
                        $slot,
                        'Credential rejected or unavailable: ' . $message
                    );

                    throw new \RuntimeException(
                        'credential rejected/unavailable: ' . $message
                    );
                }

                if ($status === 'failed') {
                    $message = $this->providerMessage(
                        $progressData,
                        $progress['body']
                    );

                    $this->failJob(
                        $jobId,
                        $progress['status'],
                        $slot,
                        'Job failed: ' . $message
                    );

                    throw new \RuntimeException(
                        'job returned failure: ' . $message
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

                    $downloadData = json_decode(
                        $download['body'],
                        true
                    );

                    if ($download['status'] < 200
                        || $download['status'] >= 300) {
                        $message = $this->providerMessage(
                            $downloadData,
                            $download['body']
                        );

                        $this->failJob(
                            $jobId,
                            $download['status'],
                            $slot,
                            'Snapshot download failed: ' . $message
                        );

                        throw new \RuntimeException(
                            'snapshot download failed: ' . $message
                        );
                    }

                    $record = $this->firstRecord($downloadData);

                    if (!$record) {
                        $this->failJob(
                            $jobId,
                            $download['status'],
                            $slot,
                            'Snapshot contained no valid listing record.'
                        );

                        throw new \RuntimeException(
                            'no valid Marketplace listing was returned.'
                        );
                    }

                    $normalized = $this->normalize(
                        $record,
                        $url,
                        $snapshotId,
                        $slot
                    );

                    if (!$this->complete($normalized)) {
                        $this->failJob(
                            $jobId,
                            $download['status'],
                            $slot,
                            'Listing metadata was incomplete.'
                        );

                        throw new \RuntimeException(
                            'listing metadata was incomplete.'
                        );
                    }

                    FetchJob::setReady(
                        $jobId,
                        $normalized,
                        $download['status']
                    );

                    return $normalized;
                }

                if (!in_array(
                        $status,
                        ['', 'starting', 'running', 'building'],
                        true
                    )
                    && $progress['status'] >= 400) {
                    $message = $this->providerMessage(
                        $progressData,
                        $progress['body']
                    );

                    $this->failJob(
                        $jobId,
                        $progress['status'],
                        $slot,
                        'Progress check failed: ' . $message
                    );

                    throw new \RuntimeException(
                        'progress check failed: ' . $message
                    );
                }

                FetchJob::setStatus(
                    $jobId,
                    'running',
                    $progress['status']
                );

                sleep($pollSeconds);
            }

            $this->failJob(
                $jobId,
                null,
                $slot,
                'Timed out after ' . $timeout . ' seconds.'
            );

            throw new \RuntimeException(
                'timed out while fetching the Facebook listing.'
            );
        } catch (\Throwable $e) {
            // Guarantee every failed attempt is diagnosable. The token itself
            // is never recorded.
            try {
                $this->failJob(
                    $jobId,
                    null,
                    $slot,
                    $e->getMessage()
                );
            } catch (\Throwable $ignored) {
                \App\Core\Logger::exception(
                    $ignored,
                    'provider',
                    ['event' => 'Bright Data fetch-job failure could not be persisted'],
                    'error'
                );
            }

            throw $e;
        }
    }

    /**
     * EN: Implements the application operation `complete` (complete).
     * 中文：实现应用操作 `complete`（complete）。
     */
    private function complete(array $item): bool
    {
        return trim((string)($item['external_post_id'] ?? '')) !== ''
            && trim((string)($item['title'] ?? '')) !== ''
            && trim((string)($item['description'] ?? '')) !== ''
            && trim((string)($item['published_raw'] ?? '')) !== '';
    }

    /**
     * EN: Implements the application operation `failJob` (fail Job).
     * 中文：实现应用操作 `failJob`（fail Job）。
     */
    private function failJob(
        int $jobId,
        ?int $httpStatus,
        string $slot,
        string $message
    ): void {
        FetchJob::setStatus(
            $jobId,
            'failed',
            $httpStatus,
            ucfirst($slot) . ' key: ' . $message
        );
    }

    /**
     * EN: Builds, formats, or transforms data for `normalize` (normalize).
     * 中文：为 `normalize`（normalize）构建、格式化或转换数据。
     */
    private function normalize(
        array $record,
        string $submittedUrl,
        string $snapshotId,
        string $credentialSlot
    ): array {
        $title = trim((string)($record['title'] ?? ''));
        $description = trim((string)($record['description'] ?? ''));

        $productId = trim((string)(
            $record['product_id']
            ?? $record['listing_id']
            ?? PlatformUrl::externalId(
                'facebook',
                $submittedUrl
            )
            ?? ''
        ));

        $canonicalUrl = trim((string)(
            $record['url'] ?? $submittedUrl
        ));

        if (!PlatformUrl::allowed($canonicalUrl, 'facebook')) {
            $canonicalUrl = $submittedUrl;
        }

        // Generic Bright Data crawl timestamp is NOT a Facebook publish time.
        $publishedRaw = null;

        foreach ([
            'listing_date',
            'date_posted',
            'posted_at',
            'creation_time',
            'created_at',
            'date_created',
        ] as $field) {
            if (isset($record[$field])
                && trim((string)$record[$field]) !== '') {
                $publishedRaw = trim((string)$record[$field]);
                break;
            }
        }

        return [
            'provider' => 'brightdata',
            'provider_job_id' => $snapshotId,
            'credential_slot' => $credentialSlot,
            'submitted_url' => $submittedUrl,
            'resolved_url' => $canonicalUrl,
            'canonical_url' => $canonicalUrl,
            'external_post_id' =>
                $productId !== '' ? $productId : null,
            'title' => $title,
            'description' => $description,
            'published_raw' => $publishedRaw,
            'raw' => $record,
        ];
    }

    /**
     * EN: Implements the application operation `firstRecord` (first Record).
     * 中文：实现应用操作 `firstRecord`（first Record）。
     */
    private function firstRecord($data): ?array
    {
        if (!is_array($data)) {
            return null;
        }

        $candidates = [];

        if (array_is_list($data)) {
            $candidates = $data;
        } else {
            foreach ([
                'data',
                'results',
                'records',
                'items',
            ] as $key) {
                if (!empty($data[$key])
                    && is_array($data[$key])) {
                    if (array_is_list($data[$key])) {
                        foreach ($data[$key] as $row) {
                            $candidates[] = $row;
                        }
                    } else {
                        $candidates[] = $data[$key];
                    }
                }
            }

            $candidates[] = $data;
        }

        foreach ($candidates as $row) {
            if (!is_array($row)) {
                continue;
            }

            foreach ([
                'title',
                'product_id',
                'listing_id',
                'url',
                'description',
                'price',
                'listing_date',
                'date_posted',
                'posted_at',
                'creation_time',
            ] as $field) {
                if (array_key_exists($field, $row)
                    && trim((string)($row[$field] ?? '')) !== '') {
                    return $row;
                }
            }
        }

        return null;
    }

    /**
     * EN: Implements the application operation `providerMessage` (provider Message).
     * 中文：实现应用操作 `providerMessage`（provider Message）。
     */
    private function providerMessage($json, string $raw): string
    {
        if (is_array($json)) {
            foreach ([
                'error_message',
                'message',
                'error',
                'detail',
            ] as $key) {
                if (isset($json[$key])
                    && is_scalar($json[$key])) {
                    return substr(
                        trim((string)$json[$key]),
                        0,
                        500
                    );
                }
            }
        }

        $clean = trim(
            preg_replace('/\s+/u', ' ', strip_tags($raw))
        );

        return $clean !== ''
            ? substr($clean, 0, 500)
            : 'Unknown provider error.';
    }

    /**
     * EN: Implements the application operation `request` (request).
     * 中文：实现应用操作 `request`（request）。
     */
    private function request(
        string $method,
        string $url,
        string $token,
        ?array $jsonBody = null,
        int $timeout = 20
    ): array {
        $ch = curl_init($url);

        if ($ch === false) {
            throw new \RuntimeException(
                'Could not initialize HTTP client.'
            );
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
            CURLOPT_USERAGENT => 'CoolerDepot-SalesPosts/'
                . (string)(
                    $GLOBALS['config']['app']['version'] ?? 'dev'
                ),
        ];

        if ($jsonBody !== null) {
            $payload = json_encode(
                $jsonBody,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
            );

            if ($payload === false) {
                throw new \RuntimeException(
                    'Could not encode Bright Data request.'
                );
            }

            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_HTTPHEADER] = $headers;
            $options[CURLOPT_POSTFIELDS] = $payload;
        }

        curl_setopt_array($ch, $options);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo(
            $ch,
            CURLINFO_RESPONSE_CODE
        );

        curl_close($ch);

        if ($body === false) {
            throw new \RuntimeException(
                'Bright Data network error: '
                . ($error ?: 'unknown error')
            );
        }

        return [
            'status' => $status,
            'body' => (string)$body,
        ];
    }
}
