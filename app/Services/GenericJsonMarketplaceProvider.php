<?php
/**
 * File / 文件：app/Services/GenericJsonMarketplaceProvider.php
 * EN: Application service for reusable business or integration logic.
 * 中文：该文件负责可复用的业务逻辑或外部集成服务。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
namespace App\Services;

use App\Models\FetchJob;

class GenericJsonMarketplaceProvider
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
     * EN: Implements the application operation `request` (request).
     * 中文：实现应用操作 `request`（request）。
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
     * EN: Builds, formats, or transforms data for `normalize` (normalize).
     * 中文：为 `normalize`（normalize）构建、格式化或转换数据。
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
     * EN: Implements the application operation `path` (path).
     * 中文：实现应用操作 `path`（path）。
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
     * EN: Implements the application operation `providerKey` (provider Key).
     * 中文：实现应用操作 `providerKey`（provider Key）。
     */
    private function providerKey(): string
    {
        $id = (int)($this->profile['id'] ?? 0);
        return $id > 0 ? 'profile_' . $id : 'test_generic';
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
}
