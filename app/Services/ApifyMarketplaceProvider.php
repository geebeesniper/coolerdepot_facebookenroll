<?php
/**
 * File / 文件：app/Services/ApifyMarketplaceProvider.php
 * EN: Application service for reusable business or integration logic.
 * 中文：该文件负责可复用的业务逻辑或外部集成服务。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
namespace App\Services;

use App\Models\FetchJob;
use App\Models\Setting;

class ApifyMarketplaceProvider
{
    private const ENDPOINT =
        'https://api.apify.com/v2/actors/apify~facebook-marketplace-scraper/run-sync-get-dataset-items';

    /**
     * EN: Implements the application operation `configured` (configured).
     * 中文：实现应用操作 `configured`（configured）。
     */
    public function configured(): bool
    {
        try {
            return Setting::get('apify_enabled', '0') === '1'
                && trim((string)Setting::get('apify_api_token', '')) !== '';
        } catch (\Throwable $e) {
            \App\Core\Logger::exception(
                $e,
                'provider',
                ['event' => 'Apify configuration check failed'],
                'warning'
            );
            return false;
        }
    }

    /**
     * EN: Retrieves or loads data for `fetch` (fetch).
     * 中文：读取或加载 `fetch`（fetch）所需的数据。
     */
    public function fetch(string $url, int $requestedByUserId): array
    {
        if (!$this->configured()) {
            throw new \RuntimeException('Apify is not configured.');
        }

        $url = PlatformUrl::normalize($url, 'facebook');

        if (!$url) {
            throw new \RuntimeException('Facebook Marketplace URL is malformed.');
        }

        $externalId = PlatformUrl::externalId('facebook', $url);

        $cached = FetchJob::recentReady(
            'facebook',
            $externalId,
            10,
            'apify'
        );

        if ($cached) {
            $cached['_provider_cache'] = true;
            return $cached;
        }

        $token = trim((string)Setting::get('apify_api_token', ''));
        $timeout = max(
            20,
            min(180, (int)Setting::get('apify_timeout_seconds', '90'))
        );

        $jobId = FetchJob::create(
            $requestedByUserId,
            'facebook',
            $url,
            $externalId,
            'apify'
        );

        try {
            $payload = [
                'startUrls' => [
                    ['url' => $url],
                ],
                'resultsLimit' => 1,
                'includeListingDetails' => true,
            ];

            $response = $this->request($payload, $token, $timeout);
            $json = json_decode($response['body'], true);

            if ($response['status'] < 200 || $response['status'] >= 300) {
                $message = $this->message($json, $response['body']);
                FetchJob::setStatus(
                    $jobId,
                    'failed',
                    $response['status'],
                    $message
                );

                throw new \RuntimeException(
                    'Apify request failed: ' . $message
                );
            }

            $record = $this->findListing($json, $externalId);

            if (!$record) {
                FetchJob::setStatus(
                    $jobId,
                    'failed',
                    $response['status'],
                    'Apify returned no matching Marketplace listing.'
                );

                throw new \RuntimeException(
                    'Apify returned no matching Marketplace listing.'
                );
            }

            $normalized = $this->normalize($record, $url, $externalId);

            if (!$normalized['external_post_id']
                || $normalized['title'] === ''
                || !$normalized['published_raw']) {
                FetchJob::setStatus(
                    $jobId,
                    'failed',
                    $response['status'],
                    'Apify response was missing required listing metadata.'
                );

                throw new \RuntimeException(
                    'Apify response was missing required listing metadata.'
                );
            }

            FetchJob::setSnapshot(
                $jobId,
                (string)$normalized['external_post_id'],
                $response['status']
            );

            FetchJob::setReady(
                $jobId,
                $normalized,
                $response['status']
            );

            return $normalized;
        } catch (\Throwable $e) {
            try {
                FetchJob::setStatus(
                    $jobId,
                    'failed',
                    null,
                    $e->getMessage()
                );
            } catch (\Throwable $ignored) {
                \App\Core\Logger::exception(
                    $ignored,
                    'provider',
                    ['event' => 'Apify fetch-job failure could not be persisted'],
                    'error'
                );
            }

            throw $e;
        }
    }

    /**
     * EN: Implements the application operation `request` (request).
     * 中文：实现应用操作 `request`（request）。
     */
    private function request(array $payload, string $token, int $timeout): array
    {
        // Token is deliberately sent in Authorization header rather than URL.
        // maxItems protects against accidental multi-result billing.
        $url = self::ENDPOINT
            . '?format=json&clean=true&maxItems=1&maxTotalChargeUsd=0.10';

        $ch = curl_init($url);

        if ($ch === false) {
            throw new \RuntimeException('Could not initialize Apify HTTP client.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_POSTFIELDS => json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES
            ),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ],
            CURLOPT_USERAGENT => 'CoolerDepot-SalesPosts/'
                . (string)($GLOBALS['config']['app']['version'] ?? 'dev'),
        ]);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($body === false) {
            throw new \RuntimeException(
                'Apify network error: ' . ($error ?: 'unknown error')
            );
        }

        return [
            'status' => $status,
            'body' => (string)$body,
        ];
    }

    /**
     * EN: Retrieves or loads data for `findListing` (find Listing).
     * 中文：读取或加载 `findListing`（find Listing）所需的数据。
     */
    private function findListing($data, ?string $expectedId): ?array
    {
        if (!is_array($data)) {
            return null;
        }

        $rows = array_is_list($data) ? $data : [$data];

        if ($expectedId) {
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $id = trim((string)($row['id'] ?? ''));

                if ($id === $expectedId) {
                    return $row;
                }
            }
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            if (!empty($row['id'])
                && (!empty($row['listingTitle']) || !empty($row['title']))) {
                return $row;
            }
        }

        return null;
    }

    /**
     * EN: Builds, formats, or transforms data for `normalize` (normalize).
     * 中文：为 `normalize`（normalize）构建、格式化或转换数据。
     */
    private function normalize(
        array $record,
        string $submittedUrl,
        ?string $expectedId
    ): array {
        $id = trim((string)(
            $record['id']
            ?? $record['listingId']
            ?? $expectedId
            ?? ''
        ));

        $url = trim((string)(
            $record['itemUrl']
            ?? $record['facebookUrl']
            ?? $submittedUrl
        ));

        $url = PlatformUrl::normalize($url, 'facebook') ?: $submittedUrl;

        $title = trim((string)(
            $record['listingTitle']
            ?? $record['title']
            ?? ''
        ));

        $description = trim((string)(
            $record['description']
            ?? $record['listingDescription']
            ?? ''
        ));

        // IMPORTANT:
        // For this official Apify Actor, "timestamp" is returned only when
        // listing details are enabled and represents listing-detail time.
        // It was cross-checked against Bright Data on the same FB item.
        // Do not generalize this rule to Bright Data's generic timestamp.
        $published = trim((string)(
            $record['timestamp']
            ?? $record['listingDate']
            ?? $record['creation_time']
            ?? ''
        ));

        return [
            'provider' => 'apify',
            'provider_job_id' => $id !== '' ? $id : null,
            'submitted_url' => $submittedUrl,
            'resolved_url' => $url,
            'canonical_url' => $url,
            'external_post_id' => $id !== '' ? $id : null,
            'title' => $title,
            'description' => $description,
            'published_raw' => $published !== '' ? $published : null,
            'raw' => $record,
        ];
    }

    /**
     * EN: Implements the application operation `message` (message).
     * 中文：实现应用操作 `message`（message）。
     */
    private function message($json, string $raw): string
    {
        if (is_array($json)) {
            foreach (['error', 'message', 'detail'] as $key) {
                if (isset($json[$key]) && is_scalar($json[$key])) {
                    return substr(trim((string)$json[$key]), 0, 500);
                }
            }

            if (isset($json['error']['message'])
                && is_scalar($json['error']['message'])) {
                return substr(
                    trim((string)$json['error']['message']),
                    0,
                    500
                );
            }
        }

        $clean = trim(
            preg_replace('/\s+/u', ' ', strip_tags($raw))
        );

        return $clean !== ''
            ? substr($clean, 0, 500)
            : 'Unknown provider error.';
    }
}
