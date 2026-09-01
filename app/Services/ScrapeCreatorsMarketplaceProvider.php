<?php
/**
 * File / 文件：app/Services/ScrapeCreatorsMarketplaceProvider.php
 * EN: Defines the ScrapeCreatorsMarketplaceProvider service used by application business, security, or provider integration flows.
 * 中文：定义 ScrapeCreatorsMarketplaceProvider 服务，用于应用业务、安全或 Provider 集成流程。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Services;

use App\Models\FetchJob;
use App\Models\Setting;

/**
 * EN: Application service that encapsulates scrape creators marketplace provider business, security, or integration behavior.
 * 中文：封装 scrape creators marketplace provider 业务、安全或外部集成行为的应用服务。
 */
class ScrapeCreatorsMarketplaceProvider
{
    private const ENDPOINT = 'https://api.scrapecreators.com/v1/facebook/marketplace/item';

    /**
     * EN: Check or validate the configured operation for scrape creators marketplace provider.
     * 中文：检查或验证 scrape creators marketplace provider 的“configured”操作。
     *
     * @return bool True when the requested condition is satisfied; otherwise false. / 请求条件满足时返回 true，否则返回 false。
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
     * EN: Retrieve the fetch operation for scrape creators marketplace provider through the configured external provider.
     * 中文：读取 scrape creators marketplace provider 的“fetch”操作，并通过已配置的外部 Provider 完成。
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
     * EN: Normalize or format the normalize operation for scrape creators marketplace provider.
     * 中文：规范化或格式化 scrape creators marketplace provider 的“normalize”操作。
     *
     * @param array $record Record value used by this operation. / 本操作使用的“record”参数值。
     * @param string $submittedUrl Submitted url value used by this operation. / 本操作使用的“submitted url”参数值。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
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
     * EN: Send or process the request operation for scrape creators marketplace provider through the configured external provider.
     * 中文：发送或处理 scrape creators marketplace provider 的“request”操作，并通过已配置的外部 Provider 完成。
     *
     * @param array $query Query value used by this operation. / 本操作使用的“query”参数值。
     * @param string $apiKey Api key value used by this operation. / 本操作使用的“api key”参数值。
     * @param int $timeout Timeout value used by this operation. / 本操作使用的“timeout”参数值。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     *
     * @throws \RuntimeException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
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
     * EN: Perform the message operation for scrape creators marketplace provider.
     * 中文：执行 scrape creators marketplace provider 的“message”操作。
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
        }

        $clean = trim(preg_replace('/\s+/u', ' ', strip_tags($raw)));

        return $clean !== ''
            ? substr($clean, 0, 500)
            : 'Unknown provider error.';
    }
}
