<?php
/**
 * File / 文件：app/Services/ApifyMarketplaceProvider.php
 * EN: Defines the ApifyMarketplaceProvider service used by application business, security, or provider integration flows.
 * 中文：定义 ApifyMarketplaceProvider 服务，用于应用业务、安全或 Provider 集成流程。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Services;

use App\Models\FetchJob;
use App\Models\Setting;

/**
 * EN: Application service that encapsulates apify marketplace provider business, security, or integration behavior.
 * 中文：封装 apify marketplace provider 业务、安全或外部集成行为的应用服务。
 */
class ApifyMarketplaceProvider
{
    private const ENDPOINT =
        'https://api.apify.com/v2/actors/apify~facebook-marketplace-scraper/run-sync-get-dataset-items';

    /**
     * EN: Check or validate the configured operation for apify marketplace provider.
     * 中文：检查或验证 apify marketplace provider 的“configured”操作。
     *
     * @return bool True when the requested condition is satisfied; otherwise false. / 请求条件满足时返回 true，否则返回 false。
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
     * EN: Retrieve the fetch operation for apify marketplace provider through the configured external provider.
     * 中文：读取 apify marketplace provider 的“fetch”操作，并通过已配置的外部 Provider 完成。
     *
     * @param string $url URL to validate, resolve, fetch, or process. / 需要验证、解析、抓取或处理的 URL。
     * @param int $requestedByUserId Application or external user identifier. / 应用或外部用户 ID。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     *
     * @throws \RuntimeException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     * @throws \Throwable When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
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

            if ($unavailable = FacebookListingMetadata::unavailableReason($json, $response['status'])) {
                throw new FacebookListingUnavailableException($unavailable);
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

            if ($unavailable = FacebookListingMetadata::unavailableReason($record, $response['status'])) {
                throw new FacebookListingUnavailableException($unavailable);
            }

            $normalized = $this->normalize($record, $url, $externalId);

            if (!FacebookListingMetadata::providerUsable($normalized)) {
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
     * EN: Send or process the request operation for apify marketplace provider through the configured external provider.
     * 中文：发送或处理 apify marketplace provider 的“request”操作，并通过已配置的外部 Provider 完成。
     *
     * @param array $payload Input payload supplied to this operation. / 传入本操作的输入载荷。
     * @param string $token Authentication, inspection, or operation token being processed. / 正在处理的认证、检查或操作 Token。
     * @param int $timeout Timeout value used by this operation. / 本操作使用的“timeout”参数值。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     *
     * @throws \RuntimeException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
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
     * EN: Retrieve the find listing operation for apify marketplace provider.
     * 中文：读取 apify marketplace provider 的“find listing”操作。
     *
     * @param mixed $data Structured input data processed by this operation. / 本操作处理的结构化输入数据。
     * @param ?string $expectedId Identifier of the expected record or entity. / expected 记录或实体的标识 ID。
     *
     * @return ?array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
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
     * EN: Normalize or format the normalize operation for apify marketplace provider.
     * 中文：规范化或格式化 apify marketplace provider 的“normalize”操作。
     *
     * @param array $record Record value used by this operation. / 本操作使用的“record”参数值。
     * @param string $submittedUrl Submitted url value used by this operation. / 本操作使用的“submitted url”参数值。
     * @param ?string $expectedId Identifier of the expected record or entity. / expected 记录或实体的标识 ID。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
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

        return FacebookListingMetadata::normalizeItem([
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
        ]);
    }

    /**
     * EN: Perform the message operation for apify marketplace provider.
     * 中文：执行 apify marketplace provider 的“message”操作。
     *
     * @param mixed $json Json value used by this operation. / 本操作使用的“json”参数值。
     * @param string $raw Raw value used by this operation. / 本操作使用的“raw”参数值。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
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
