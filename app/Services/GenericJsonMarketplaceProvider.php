<?php
/**
 * File / 文件：app/Services/GenericJsonMarketplaceProvider.php
 * EN: Defines the GenericJsonMarketplaceProvider service used by application business, security, or provider integration flows.
 * 中文：定义 GenericJsonMarketplaceProvider 服务，用于应用业务、安全或 Provider 集成流程。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Services;

use App\Models\FetchJob;

/**
 * EN: Application service that encapsulates generic json marketplace provider business, security, or integration behavior.
 * 中文：封装 generic json marketplace provider 业务、安全或外部集成行为的应用服务。
 */
class GenericJsonMarketplaceProvider
{
    private array $profile;

    /**
     * EN: Initialize GenericJsonMarketplaceProvider with the dependencies and configuration required by later operations.
     * 中文：初始化 GenericJsonMarketplaceProvider，保存后续操作所需的依赖与配置。
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
     * EN: Retrieve the fetch operation for generic json marketplace provider through the configured external provider.
     * 中文：读取 generic json marketplace provider 的“fetch”操作，并通过已配置的外部 Provider 完成。
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

        $config = $this->profile['config'] ?? [];
        $endpoint = HttpEndpointGuard::assertPublicHttps(
            (string)($this->profile['api_endpoint'] ?? '')
        );
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
            $response = $this->request($endpoint, $url, $config);
            $data = json_decode($response['body'], true);

            if ($response['status'] < 200 || $response['status'] >= 300) {
                throw new \RuntimeException(
                    'Custom API returned HTTP ' . $response['status'] . '.'
                );
            }

            if (!is_array($data)) {
                throw new \RuntimeException('Custom API did not return valid JSON.');
            }

            $result = $this->normalize($data, $url, $config);
            if (!$this->complete($result)) {
                throw new \RuntimeException(
                    'Custom API mapping did not produce ID, title, description, and listing date.'
                );
            }

            FetchJob::setSnapshot(
                $jobId,
                (string)$result['external_post_id'],
                $response['status']
            );
            FetchJob::setReady($jobId, $result, $response['status']);

            return $result;
        } catch (\Throwable $e) {
            try {
                FetchJob::setStatus($jobId, 'failed', null, $e->getMessage());
            } catch (\Throwable $ignored) {
                \App\Core\Logger::exception(
                    $ignored,
                    'provider',
                    ['event' => 'Generic JSON provider fetch-job failure could not be persisted'],
                    'error'
                );
            }
            throw $e;
        }
    }

    /**
     * EN: Send or process the request operation for generic json marketplace provider through the configured external provider.
     * 中文：发送或处理 generic json marketplace provider 的“request”操作，并通过已配置的外部 Provider 完成。
     *
     * @param string $endpoint Endpoint value used by this operation. / 本操作使用的“endpoint”参数值。
     * @param string $listingUrl Listing url value used by this operation. / 本操作使用的“listing url”参数值。
     * @param array $config Configuration values used by this operation. / 本操作使用的配置数据。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     *
     * @throws \RuntimeException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    private function request(string $endpoint, string $listingUrl, array $config): array
    {
        $method = strtoupper((string)($config['request_method'] ?? 'GET'));
        $inputMode = (string)($config['input_mode'] ?? 'query');
        $inputKey = (string)($config['input_key'] ?? 'url');
        $authMode = (string)($config['auth_mode'] ?? 'bearer');
        $authName = (string)($config['auth_name'] ?? '');
        $token = (string)($this->profile['api_token'] ?? '');
        $timeout = max(8, min(60, (int)($config['timeout_seconds'] ?? 20)));

        $headers = ['Accept: application/json'];
        $query = [];
        $body = null;

        if ($inputMode === 'query') {
            $query[$inputKey] = $listingUrl;
        } else {
            $body = [$inputKey => $listingUrl];
        }

        if ($authMode === 'bearer') {
            $headers[] = 'Authorization: Bearer ' . $token;
        } elseif ($authMode === 'header') {
            $headers[] = $authName . ': ' . $token;
        } elseif ($authMode === 'query') {
            $query[$authName] = $token;
        }

        if ($query) {
            $endpoint .= (str_contains($endpoint, '?') ? '&' : '?')
                . http_build_query($query);
        }

        // Revalidate the final host. Query values do not change the destination.
        HttpEndpointGuard::assertPublicHttps($endpoint);

        $ch = curl_init($endpoint);

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
                'Custom API network error: ' . ($error ?: 'unknown error')
            );
        }

        return ['status' => $status, 'body' => (string)$raw];
    }

    /**
     * EN: Normalize or format the normalize operation for generic json marketplace provider.
     * 中文：规范化或格式化 generic json marketplace provider 的“normalize”操作。
     *
     * @param array $data Structured input data processed by this operation. / 本操作处理的结构化输入数据。
     * @param string $submittedUrl Submitted url value used by this operation. / 本操作使用的“submitted url”参数值。
     * @param array $config Configuration values used by this operation. / 本操作使用的配置数据。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
    private function normalize(array $data, string $submittedUrl, array $config): array
    {
        $id = trim((string)$this->path($data, (string)$config['id_path']));
        $title = trim((string)$this->path($data, (string)$config['title_path']));
        $description = trim((string)$this->path(
            $data,
            (string)$config['description_path']
        ));
        $published = trim((string)$this->path($data, (string)$config['date_path']));
        $mappedUrl = trim((string)$this->path(
            $data,
            (string)($config['url_path'] ?? '')
        ));

        $canonical = PlatformUrl::normalize($mappedUrl, 'facebook')
            ?: $submittedUrl;

        return [
            'provider' => 'generic_json',
            'provider_profile_id' => (int)($this->profile['id'] ?? 0),
            'provider_name' => (string)($this->profile['name'] ?? 'Custom JSON API'),
            'provider_job_id' => $id !== '' ? $id : null,
            'submitted_url' => $submittedUrl,
            'resolved_url' => $canonical,
            'canonical_url' => $canonical,
            'external_post_id' => $id !== '' ? $id : null,
            'title' => $title,
            'description' => $description,
            'published_raw' => $published !== '' ? $published : null,
            'raw' => $data,
        ];
    }

    /**
     * EN: Perform the path operation for generic json marketplace provider.
     * 中文：执行 generic json marketplace provider 的“path”操作。
     *
     * @param array $data Structured input data processed by this operation. / 本操作处理的结构化输入数据。
     * @param string $path Filesystem, route, or data path used by the operation. / 本操作使用的文件、路由或数据路径。
     *
     * @return mixed Result produced by this operation; the concrete type depends on the execution path. / 本操作生成的结果；具体类型取决于执行路径。
     */
    private function path(array $data, string $path)
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        $value = $data;

        foreach (explode('.', $path) as $segment) {
            if ($segment === '') {
                continue;
            }

            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return is_scalar($value) ? $value : null;
    }

    /**
     * EN: Perform the provider key operation for generic json marketplace provider.
     * 中文：执行 generic json marketplace provider 的“provider key”操作。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
     */
    private function providerKey(): string
    {
        $id = (int)($this->profile['id'] ?? 0);
        return $id > 0 ? 'profile_' . $id : 'test_generic';
    }

    /**
     * EN: Check or validate the complete operation for generic json marketplace provider.
     * 中文：检查或验证 generic json marketplace provider 的“complete”操作。
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
}
