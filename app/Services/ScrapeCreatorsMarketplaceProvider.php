<?php
/**
 * File / 文件：app/Services/ScrapeCreatorsMarketplaceProvider.php
 * EN: Application service for reusable business or integration logic.
 * 中文：该文件负责可复用的业务逻辑或外部集成服务。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
namespace App\Services;

use App\Models\FetchJob;
use App\Models\Setting;

class ScrapeCreatorsMarketplaceProvider
{
    private const ENDPOINT = 'https://api.scrapecreators.com/v1/facebook/marketplace/item';

    /**
     * EN: Implements the application operation `configured` (configured).
     * 中文：实现应用操作 `configured`（configured）。
     */
    public function configured(): bool
    {
        try {
            return Setting::get('scrapecreators_enabled', '0') === '1'
                && trim((string)Setting::get('scrapecreators_api_key', '')) !== '';
        } catch (\Throwable $e) {
            \App\Core\Logger::exception(
                $e,
                'provider',
                ['event' => 'ScrapeCreators configuration check failed'],
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
            throw new \RuntimeException('ScrapeCreators is not configured.');
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
            'scrapecreators'
        );

        if ($cached) {
            $cached['_provider_cache'] = true;
            return $cached;
        }

        $apiKey = trim((string)Setting::get('scrapecreators_api_key', ''));
        $timeout = max(
            8,
            min(45, (int)Setting::get('scrapecreators_timeout_seconds', '20'))
        );

        $jobId = FetchJob::create(
            $requestedByUserId,
            'facebook',
            $url,
            $externalId,
            'scrapecreators'
        );

        try {
            $query = $externalId
                ? ['id' => $externalId]
                : ['url' => $url];

            $response = $this->request($query, $apiKey, $timeout);
            $json = json_decode($response['body'], true);

            if ($response['status'] < 200 || $response['status'] >= 300) {
                $message = $this->message($json, $response['body']);
                FetchJob::setStatus($jobId, 'failed', $response['status'], $message);
                throw new \RuntimeException('ScrapeCreators request failed: ' . $message);
            }

            if (!is_array($json) || empty($json['success'])) {
                $message = $this->message($json, $response['body']);
                FetchJob::setStatus($jobId, 'failed', $response['status'], $message);
                throw new \RuntimeException('ScrapeCreators did not return a valid listing: ' . $message);
            }

            $normalized = $this->normalize($json, $url);

            if (!$normalized['external_post_id'] || $normalized['title'] === '') {
                FetchJob::setStatus(
                    $jobId,
                    'failed',
                    $response['status'],
                    'Marketplace response was missing required listing fields.'
                );

                throw new \RuntimeException(
                    'ScrapeCreators response was missing required listing fields.'
                );
            }

            if ($normalized['external_post_id']) {
                FetchJob::setSnapshot(
                    $jobId,
                    (string)$normalized['external_post_id'],
                    $response['status']
                );
            }

            FetchJob::setReady($jobId, $normalized, $response['status']);

            return $normalized;
        } catch (\Throwable $e) {
            try {
                FetchJob::setStatus($jobId, 'failed', null, $e->getMessage());
            } catch (\Throwable $ignored) {
                \App\Core\Logger::exception(
                    $ignored,
                    'provider',
                    ['event' => 'ScrapeCreators fetch-job failure could not be persisted'],
                    'error'
                );
            }

            throw $e;
        }
    }

    /**
     * EN: Builds, formats, or transforms data for `normalize` (normalize).
     * 中文：为 `normalize`（normalize）构建、格式化或转换数据。
     */
    private function normalize(array $record, string $submittedUrl): array
    {
        $url = trim((string)($record['url'] ?? $submittedUrl));

        if (!PlatformUrl::allowed($url, 'facebook')) {
            $url = $submittedUrl;
        }

        $id = trim((string)(
            $record['id']
            ?? $record['product_id']
            ?? PlatformUrl::externalId('facebook', $submittedUrl)
            ?? ''
        ));

        // ScrapeCreators officially exposes creation_time for Marketplace Item.
        // listing_date_text is useful supplementary text but is not used as the
        // canonical timestamp when creation_time exists.
        $published = trim((string)(
            $record['creation_time']
            ?? $record['listing_date']
            ?? $record['date_posted']
            ?? $record['posted_at']
            ?? ''
        ));

        return [
            'provider' => 'scrapecreators',
            'provider_job_id' => $id !== '' ? $id : null,
            'submitted_url' => $submittedUrl,
            'resolved_url' => $url,
            'canonical_url' => $url,
            'external_post_id' => $id !== '' ? $id : null,
            'title' => trim((string)($record['title'] ?? '')),
            'description' => trim((string)($record['description'] ?? '')),
            'published_raw' => $published !== '' ? $published : null,
            'listing_date_text' => $record['listing_date_text'] ?? null,
            'raw' => $record,
        ];
    }

    /**
     * EN: Implements the application operation `request` (request).
     * 中文：实现应用操作 `request`（request）。
     */
    private function request(array $query, string $apiKey, int $timeout): array
    {
        $url = self::ENDPOINT . '?' . http_build_query($query);

        $ch = curl_init($url);

        if ($ch === false) {
            throw new \RuntimeException('Could not initialize ScrapeCreators HTTP client.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'x-api-key: ' . $apiKey,
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
                'ScrapeCreators network error: ' . ($error ?: 'unknown error')
            );
        }

        return [
            'status' => $status,
            'body' => (string)$body,
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
        }

        $clean = trim(preg_replace('/\s+/u', ' ', strip_tags($raw)));

        return $clean !== ''
            ? substr($clean, 0, 500)
            : 'Unknown provider error.';
    }
}
