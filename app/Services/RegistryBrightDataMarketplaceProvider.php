<?php
/**
 * File / 文件：app/Services/RegistryBrightDataMarketplaceProvider.php
 * EN: Defines the RegistryBrightDataMarketplaceProvider service used by application business, security, or provider integration flows.
 * 中文：定义 RegistryBrightDataMarketplaceProvider 服务，用于应用业务、安全或 Provider 集成流程。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Services;

use App\Models\FetchJob;

/**
 * EN: Application service that encapsulates registry bright data marketplace provider business, security, or integration behavior.
 * 中文：封装 registry bright data marketplace provider 业务、安全或外部集成行为的应用服务。
 */
class RegistryBrightDataMarketplaceProvider
{
    private array $profile;

    /**
     * EN: Initialize RegistryBrightDataMarketplaceProvider with the dependencies and configuration required by later operations.
     * 中文：初始化 RegistryBrightDataMarketplaceProvider，保存后续操作所需的依赖与配置。
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
     * EN: Retrieve the fetch operation for registry bright data marketplace provider through the configured external provider.
     * 中文：读取 registry bright data marketplace provider 的“fetch”操作，并通过已配置的外部 Provider 完成。
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

    /**
     * EN: Perform the provider key operation for registry bright data marketplace provider.
     * 中文：执行 registry bright data marketplace provider 的“provider key”操作。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
     */
    private function providerKey(): string
    {
        $id = (int)($this->profile['id'] ?? 0);
        return $id > 0 ? 'profile_' . $id : 'test_brightdata';
    }

    /**
     * EN: Check or validate the complete operation for registry bright data marketplace provider.
     * 中文：检查或验证 registry bright data marketplace provider 的“complete”操作。
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
     * EN: Normalize or format the normalize operation for registry bright data marketplace provider.
     * 中文：规范化或格式化 registry bright data marketplace provider 的“normalize”操作。
     *
     * @param array $record Record value used by this operation. / 本操作使用的“record”参数值。
     * @param string $submittedUrl Submitted url value used by this operation. / 本操作使用的“submitted url”参数值。
     * @param string $snapshotId Identifier of the snapshot record or entity. / snapshot 记录或实体的标识 ID。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
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

    /**
     * EN: Perform the first record operation for registry bright data marketplace provider.
     * 中文：执行 registry bright data marketplace provider 的“first record”操作。
     *
     * @param mixed $data Structured input data processed by this operation. / 本操作处理的结构化输入数据。
     *
     * @return ?array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
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

    /**
     * EN: Send or process the request operation for registry bright data marketplace provider through the configured external provider.
     * 中文：发送或处理 registry bright data marketplace provider 的“request”操作，并通过已配置的外部 Provider 完成。
     *
     * @param string $method HTTP or operation method being processed. / 正在处理的 HTTP 或操作方法。
     * @param string $url URL to validate, resolve, fetch, or process. / 需要验证、解析、抓取或处理的 URL。
     * @param string $token Authentication, inspection, or operation token being processed. / 正在处理的认证、检查或操作 Token。
     * @param ?array $body Body value used by this operation. / 本操作使用的“body”参数值。
     * @param int $timeout Timeout value used by this operation. / 本操作使用的“timeout”参数值。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     *
     * @throws \RuntimeException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
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

    /**
     * EN: Perform the message operation for registry bright data marketplace provider.
     * 中文：执行 registry bright data marketplace provider 的“message”操作。
     *
     * @param mixed $json Json value used by this operation. / 本操作使用的“json”参数值。
     * @param string $raw Raw value used by this operation. / 本操作使用的“raw”参数值。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
     */
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
