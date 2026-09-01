<?php
/**
 * File / 文件：app/Services/BrightDataMarketplaceProvider.php
 * EN: Defines the BrightDataMarketplaceProvider service used by application business, security, or provider integration flows.
 * 中文：定义 BrightDataMarketplaceProvider 服务，用于应用业务、安全或 Provider 集成流程。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Services;

use App\Models\FetchJob;
use App\Models\Setting;

/**
 * EN: Application service that encapsulates bright data marketplace provider business, security, or integration behavior.
 * 中文：封装 bright data marketplace provider 业务、安全或外部集成行为的应用服务。
 */
class BrightDataMarketplaceProvider
{
    public const DEFAULT_DATASET_ID = 'gd_lvt9iwuh6fbcwmx1a';

    /**
     * EN: Check or validate the configured operation for bright data marketplace provider.
     * 中文：检查或验证 bright data marketplace provider 的“configured”操作。
     *
     * @return bool True when the requested condition is satisfied; otherwise false. / 请求条件满足时返回 true，否则返回 false。
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
     * EN: Perform the credential status operation for bright data marketplace provider.
     * 中文：执行 bright data marketplace provider 的“credential status”操作。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
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
     * EN: Retrieve the fetch operation for bright data marketplace provider.
     * 中文：读取 bright data marketplace provider 的“fetch”操作。
     *
     * @param string $url URL to validate, resolve, fetch, or process. / 需要验证、解析、抓取或处理的 URL。
     * @param int $requestedByUserId Application or external user identifier. / 应用或外部用户 ID。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     *
     * @throws \RuntimeException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
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
     * EN: Perform the credentials operation for bright data marketplace provider.
     * 中文：执行 bright data marketplace provider 的“credentials”操作。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
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
     * EN: Retrieve the fetch with credential operation for bright data marketplace provider through the configured external provider.
     * 中文：读取 bright data marketplace provider 的“fetch with credential”操作，并通过已配置的外部 Provider 完成。
     *
     * @param string $url URL to validate, resolve, fetch, or process. / 需要验证、解析、抓取或处理的 URL。
     * @param ?string $externalId Identifier of the external record or entity. / external 记录或实体的标识 ID。
     * @param int $requestedByUserId Application or external user identifier. / 应用或外部用户 ID。
     * @param string $datasetId Identifier of the dataset record or entity. / dataset 记录或实体的标识 ID。
     * @param int $timeout Timeout value used by this operation. / 本操作使用的“timeout”参数值。
     * @param int $pollSeconds Poll seconds value used by this operation. / 本操作使用的“poll seconds”参数值。
     * @param string $slot Slot value used by this operation. / 本操作使用的“slot”参数值。
     * @param string $token Authentication, inspection, or operation token being processed. / 正在处理的认证、检查或操作 Token。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     *
     * @throws \RuntimeException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     * @throws \Throwable When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
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
     * EN: Check or validate the complete operation for bright data marketplace provider.
     * 中文：检查或验证 bright data marketplace provider 的“complete”操作。
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
     * EN: Perform the fail job operation for bright data marketplace provider.
     * 中文：执行 bright data marketplace provider 的“fail job”操作。
     *
     * @param int $jobId Identifier of the job record or entity. / job 记录或实体的标识 ID。
     * @param ?int $httpStatus Http status value used by this operation. / 本操作使用的“http status”参数值。
     * @param string $slot Slot value used by this operation. / 本操作使用的“slot”参数值。
     * @param string $message Human-readable message associated with the result or log entry. / 与结果或日志记录关联的可读消息。
     *
     * @return void No value is returned. / 无返回值。
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
     * EN: Normalize or format the normalize operation for bright data marketplace provider.
     * 中文：规范化或格式化 bright data marketplace provider 的“normalize”操作。
     *
     * @param array $record Record value used by this operation. / 本操作使用的“record”参数值。
     * @param string $submittedUrl Submitted url value used by this operation. / 本操作使用的“submitted url”参数值。
     * @param string $snapshotId Identifier of the snapshot record or entity. / snapshot 记录或实体的标识 ID。
     * @param string $credentialSlot Credential slot value used by this operation. / 本操作使用的“credential slot”参数值。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
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
     * EN: Perform the first record operation for bright data marketplace provider.
     * 中文：执行 bright data marketplace provider 的“first record”操作。
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
     * EN: Perform the provider message operation for bright data marketplace provider.
     * 中文：执行 bright data marketplace provider 的“provider message”操作。
     *
     * @param mixed $json Json value used by this operation. / 本操作使用的“json”参数值。
     * @param string $raw Raw value used by this operation. / 本操作使用的“raw”参数值。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
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
     * EN: Send or process the request operation for bright data marketplace provider through the configured external provider.
     * 中文：发送或处理 bright data marketplace provider 的“request”操作，并通过已配置的外部 Provider 完成。
     *
     * @param string $method HTTP or operation method being processed. / 正在处理的 HTTP 或操作方法。
     * @param string $url URL to validate, resolve, fetch, or process. / 需要验证、解析、抓取或处理的 URL。
     * @param string $token Authentication, inspection, or operation token being processed. / 正在处理的认证、检查或操作 Token。
     * @param ?array $jsonBody Json body value used by this operation. / 本操作使用的“json body”参数值。
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
