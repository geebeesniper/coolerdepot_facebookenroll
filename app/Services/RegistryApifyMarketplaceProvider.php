<?php
/**
 * File / 文件：app/Services/RegistryApifyMarketplaceProvider.php
 * EN: Defines the RegistryApifyMarketplaceProvider service used by application business, security, or provider integration flows.
 * 中文：定义 RegistryApifyMarketplaceProvider 服务，用于应用业务、安全或 Provider 集成流程。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Services;

use App\Models\FetchJob;

/**
 * EN: Application service that encapsulates registry apify marketplace provider business, security, or integration behavior.
 * 中文：封装 registry apify marketplace provider 业务、安全或外部集成行为的应用服务。
 */
class RegistryApifyMarketplaceProvider
{
    private array $profile;

    /**
     * EN: Initialize RegistryApifyMarketplaceProvider with the dependencies and configuration required by later operations.
     * 中文：初始化 RegistryApifyMarketplaceProvider，保存后续操作所需的依赖与配置。
     *
     * @param array $profile Profile value used by this operation. / 本操作使用的“profile”参数值。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function __construct(array $profile)
    {
        $this->profile = $profile;
    }

    /**
     * EN: Retrieve the fetch operation for registry apify marketplace provider through the configured external provider.
     * 中文：读取 registry apify marketplace provider 的“fetch”操作，并通过已配置的外部 Provider 完成。
     *
     * @param string $url URL to validate, resolve, fetch, or process. / 需要验证、解析、抓取或处理的 URL。
     * @param int $userId Application user identifier. / 应用用户 ID。
     * @param bool $bypassCache Bypass cache value used by this operation. / 本操作使用的“bypass cache”参数值。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     *
     * @throws \RuntimeException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     * @throws \Throwable When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
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
     * EN: Perform the provider key operation for registry apify marketplace provider.
     * 中文：执行 registry apify marketplace provider 的“provider key”操作。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
     */
    private function providerKey(): string
    {
        $id = (int)($this->profile['id'] ?? 0);
        return $id > 0 ? 'profile_' . $id : 'test_apify';
    }

    /**
     * EN: Check or validate the complete operation for registry apify marketplace provider.
     * 中文：检查或验证 registry apify marketplace provider 的“complete”操作。
     *
     * @param array $item Current item being processed. / 当前正在处理的数据项。
     *
     * @return bool True when the requested condition is satisfied; otherwise false. / 请求条件满足时返回 true，否则返回 false。
     */
    private function complete(array $item): bool
    {
        return trim((string)($item['external_post_id'] ?? '')) !== ''
            && trim((string)($item['title'] ?? '')) !== ''
            && trim((string)($item['description'] ?? '')) !== ''
            && trim((string)($item['published_raw'] ?? '')) !== '';
    }

    /**
     * EN: Retrieve the find record operation for registry apify marketplace provider.
     * 中文：读取 registry apify marketplace provider 的“find record”操作。
     *
     * @param mixed $data Structured input data processed by this operation. / 本操作处理的结构化输入数据。
     * @param ?string $expectedId Identifier of the expected record or entity. / expected 记录或实体的标识 ID。
     *
     * @return ?array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
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
     * EN: Normalize or format the normalize operation for registry apify marketplace provider.
     * 中文：规范化或格式化 registry apify marketplace provider 的“normalize”操作。
     *
     * @param array $record Record value used by this operation. / 本操作使用的“record”参数值。
     * @param string $submittedUrl Submitted url value used by this operation. / 本操作使用的“submitted url”参数值。
     * @param ?string $expectedId Identifier of the expected record or entity. / expected 记录或实体的标识 ID。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
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
     * EN: Perform the text value operation for registry apify marketplace provider.
     * 中文：执行 registry apify marketplace provider 的“text value”操作。
     *
     * @param mixed $value Value processed or stored by this operation. / 本操作处理或保存的值。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
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
     * EN: Perform the message operation for registry apify marketplace provider.
     * 中文：执行 registry apify marketplace provider 的“message”操作。
     *
     * @param mixed $json Json value used by this operation. / 本操作使用的“json”参数值。
     * @param string $raw Raw value used by this operation. / 本操作使用的“raw”参数值。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
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
