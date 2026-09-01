<?php
/**
 * File / 文件：app/Services/RegistryApifyMarketplaceProvider.php
 * EN: Application service for reusable business or integration logic.
 * 中文：该文件负责可复用的业务逻辑或外部集成服务。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
namespace App\Services;

use App\Models\FetchJob;

class RegistryApifyMarketplaceProvider
{
    private array $profile;

    /**
     * EN: `__construct` initializes this object and its required dependencies/state.
     * 中文：`__construct` 用于初始化当前对象及其所需依赖与状态。
     */
    public function __construct(array $profile)
    {
        $this->profile = $profile;
    }

    /**
     * EN: Retrieves or loads data for `fetch` (fetch).
     * 中文：读取或加载 `fetch`（fetch）所需的数据。
     */
    public function fetch(string $url, int $userId, bool $bypassCache = false): array
    {
        $url = PlatformUrl::normalize($url, 'facebook');
        if (!$url) {
            throw new \RuntimeException('Facebook Marketplace URL is malformed.');
        }

        $token = trim((string)($this->profile['api_token'] ?? ''));
        if ($token === '') {
            throw new \RuntimeException('Apify token is missing.');
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
            20,
            min(180, (int)(($this->profile['config']['timeout_seconds'] ?? 90)))
        );

        $jobId = FetchJob::create($userId, 'facebook', $url, $externalId, $providerKey);

        try {
            $payload = [
                'startUrls' => [['url' => $url]],
                'resultsLimit' => 1,
                'includeListingDetails' => true,
            ];

            $endpoint =
                'https://api.apify.com/v2/actors/apify~facebook-marketplace-scraper/'
                . 'run-sync-get-dataset-items?format=json&clean=true&maxItems=1&maxTotalChargeUsd=0.10';

            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $token,
                ],
            ]);

            $raw = curl_exec($ch);
            $error = curl_error($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);

            if ($raw === false) {
                throw new \RuntimeException(
                    'Apify network error: ' . ($error ?: 'unknown error')
                );
            }

            $data = json_decode((string)$raw, true);

            if ($status < 200 || $status >= 300) {
                throw new \RuntimeException(
                    'Apify request failed: ' . $this->message($data, (string)$raw)
                );
            }

            $record = $this->findRecord($data, $externalId);
            if (!$record) {
                throw new \RuntimeException('Apify returned no matching listing.');
            }

            $result = $this->normalize($record, $url, $externalId);
            if (!$this->complete($result)) {
                throw new \RuntimeException('Apify returned incomplete listing metadata.');
            }

            FetchJob::setSnapshot($jobId, (string)$result['external_post_id'], $status);
            FetchJob::setReady($jobId, $result, $status);

            return $result;
        } catch (\Throwable $e) {
            try {
                FetchJob::setStatus($jobId, 'failed', null, $e->getMessage());
            } catch (\Throwable $ignored) {
                \App\Core\Logger::exception(
                    $ignored,
                    'provider',
                    ['event' => 'Registry Apify fetch-job failure could not be persisted'],
                    'error'
                );
            }
            throw $e;
        }
    }

    /**
     * EN: Implements the application operation `providerKey` (provider Key).
     * 中文：实现应用操作 `providerKey`（provider Key）。
     */
    private function providerKey(): string
    {
        $id = (int)($this->profile['id'] ?? 0);
        return $id > 0 ? 'profile_' . $id : 'test_apify';
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
     * EN: Retrieves or loads data for `findRecord` (find Record).
     * 中文：读取或加载 `findRecord`（find Record）所需的数据。
     */
    private function findRecord($data, ?string $expectedId): ?array
    {
        if (!is_array($data)) {
            return null;
        }

        $rows = array_is_list($data) ? $data : [$data];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            if ($expectedId && trim((string)($row['id'] ?? '')) === $expectedId) {
                return $row;
            }
        }

        foreach ($rows as $row) {
            if (is_array($row)
                && !empty($row['id'])
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
    private function normalize(array $record, string $submittedUrl, ?string $expectedId): array
    {
        $id = trim((string)(
            $record['id'] ?? $record['listingId'] ?? $expectedId ?? ''
        ));

        $canonicalSource =
            $record['itemUrl']
            ?? $record['facebookUrl']
            ?? $submittedUrl;

        $canonical = PlatformUrl::normalize(
            is_scalar($canonicalSource)
                ? (string)$canonicalSource
                : $submittedUrl,
            'facebook'
        ) ?: $submittedUrl;

        $title = $this->textValue(
            $record['listingTitle']
            ?? $record['title']
            ?? ''
        );

        // Apify's detailed Marketplace response currently returns:
        // "description": {"text": "..."}
        // Do not cast the array itself to string; doing so emits a PHP warning
        // and can corrupt an AJAX JSON response.
        $description = $this->textValue(
            $record['description']
            ?? $record['listingDescription']
            ?? ''
        );

        $publishedRaw = $this->textValue(
            $record['timestamp']
            ?? $record['listingDate']
            ?? $record['creation_time']
            ?? ''
        );

        return [
            'provider' => 'apify',
            'provider_profile_id' => (int)($this->profile['id'] ?? 0),
            'provider_name' => (string)($this->profile['name'] ?? 'Apify'),
            'provider_job_id' => $id !== '' ? $id : null,
            'submitted_url' => $submittedUrl,
            'resolved_url' => $canonical,
            'canonical_url' => $canonical,
            'external_post_id' => $id !== '' ? $id : null,
            'title' => $title,
            'description' => $description,
            'published_raw' => $publishedRaw !== '' ? $publishedRaw : null,
            'raw' => $record,
        ];
    }

    /**
     * EN: Implements the application operation `textValue` (text Value).
     * 中文：实现应用操作 `textValue`（text Value）。
     */
    private function textValue($value): string
    {
        if (is_string($value) || is_numeric($value)) {
            return trim((string)$value);
        }

        if (!is_array($value)) {
            return '';
        }

        // Common social-scraper shapes.
        foreach (['text', 'value', 'label', 'description', 'title'] as $key) {
            if (isset($value[$key])
                && (is_string($value[$key]) || is_numeric($value[$key]))) {
                return trim((string)$value[$key]);
            }
        }

        return '';
    }

    /**
     * EN: Implements the application operation `message` (message).
     * 中文：实现应用操作 `message`（message）。
     */
    private function message($json, string $raw): string
    {
        if (is_array($json)) {
            if (isset($json['error']['message']) && is_scalar($json['error']['message'])) {
                return substr(trim((string)$json['error']['message']), 0, 500);
            }

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
