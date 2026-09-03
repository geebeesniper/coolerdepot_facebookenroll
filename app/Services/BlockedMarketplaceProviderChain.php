<?php
/**
 * File / 文件：app/Services/BlockedMarketplaceProviderChain.php
 * EN: Provider fallback chain used when Craigslist or OfferUp blocks direct server requests.
 * 中文：当 Craigslist 或 OfferUp 阻止服务器直接抓取时使用的 Provider 回退链。
 * Maintenance / 维护：Keep provider secrets encrypted at rest and never write tokens to logs.
 * 维护要求：Provider Token 必须保持加密存储，日志中不得写入 Token。
 */
namespace App\Services;

use App\Models\FetchJob;
use App\Models\ProviderProfile;
use App\Models\Setting;

/**
 * EN: Tries configured Bright Data Web Unlocker and Apify fallbacks for blocked public marketplace pages.
 * 中文：针对被阻止的公开 Marketplace 页面，依次尝试已配置的 Bright Data Web Unlocker 与 Apify 回退。
 */
class BlockedMarketplaceProviderChain
{
    /**
     * EN: Current Apify actors verified to accept direct public listing URLs.
     * 中文：当前已确认可接收公开帖子直链的 Apify Actor。
     */
    private const APIFY_ACTORS = [
        'craigslist' => 'automation-lab~craigslist-scraper',
        'offerup' => 'abotapi~offerup-scraper',
    ];

    /**
     * EN: Fetch listing data through configured fallback providers in Admin priority order.
     * 中文：按 Admin 中的优先级顺序通过已配置 Provider 回退抓取帖子数据。
     *
     * @param string $platform Supported marketplace platform. / 支持的 Marketplace 平台。
     * @param string $url Listing URL. / 帖子 URL。
     * @param int $userId Sales/Admin user ID that initiated the request. / 发起请求的 Sales/Admin 用户 ID。
     * @param bool $bypassCache Whether cached provider results should be ignored. / 是否忽略 Provider 缓存结果。
     *
     * @return array Normalized provider result. / 标准化 Provider 结果。
     *
     * @throws \RuntimeException When all configured fallbacks fail. / 所有已配置回退均失败时抛出。
     */
    public function fetch(
        string $platform,
        string $url,
        int $userId,
        bool $bypassCache = false
    ): array {
        $platform = strtolower(trim($platform));
        if (!in_array($platform, ['craigslist', 'offerup'], true)) {
            throw new \RuntimeException(
                'Blocked marketplace fallback supports Craigslist and OfferUp only.'
            );
        }

        $url = PlatformUrl::normalize($url, $platform) ?: trim($url);
        if (!PlatformUrl::allowed($url, $platform)) {
            throw new \RuntimeException('Marketplace fallback URL is invalid.');
        }

        $attempts = [];
        foreach ($this->profiles() as $profile) {
            $type = strtolower((string)($profile['provider_type'] ?? ''));
            $name = trim((string)($profile['name'] ?? $type));

            try {
                if ($type === 'brightdata') {
                    // EN: Reuse the stored Bright Data API key. When an Unlocker
                    // zone is not stored, discover an active one automatically.
                    // 中文：复用已保存的 Bright Data API Key；未保存 Unlocker
                    // Zone 时自动发现当前账号中启用的 Zone。
                    $result = $this->fetchBrightData(
                        $profile,
                        $platform,
                        $url,
                        $userId,
                        $bypassCache
                    );
                } elseif ($type === 'apify') {
                    $result = $this->fetchApify(
                        $profile,
                        $platform,
                        $url,
                        $userId,
                        $bypassCache
                    );
                } else {
                    continue;
                }

                $result['_fallback_used'] = true;
                $result['_provider_profile_id'] = (int)($profile['id'] ?? 0);
                $result['_provider_profile_name'] = $name;
                $result['_provider_type'] = $type;
                if ($attempts) {
                    $result['_previous_attempts'] = $attempts;
                }

                return $result;
            } catch (\Throwable $e) {
                \App\Core\Logger::exception(
                    $e,
                    'provider',
                    [
                        'event' => 'Blocked marketplace provider attempt failed',
                        'platform' => $platform,
                        'provider' => $name,
                        'provider_type' => $type,
                    ],
                    'warning'
                );
                $attempts[] = $name . ': ' . $e->getMessage();
            }
        }

        throw new \RuntimeException(
            $attempts
                ? 'All blocked-marketplace providers failed. ' . implode(' | ', $attempts)
                : 'No compatible Bright Data or Apify provider is configured.'
        );
    }

    /**
     * EN: Build provider list while preserving Admin priority order.
     * 中文：按 Admin 设置的优先级顺序构建 Provider 列表。
     *
     * @return array Provider profiles with runtime-only decrypted tokens. / 含运行时解密 Token 的 Provider Profile。
     */
    private function profiles(): array
    {
        $profiles = ProviderProfile::activeVerifiedWithSecrets();
        if ($profiles) {
            return $profiles;
        }

        // EN: Legacy fallback keeps older installations usable until Provider Registry is enabled.
        // 中文：Provider Registry 尚未启用时继续兼容旧版 Provider Settings。
        $legacy = [];
        try {
            $bright = trim((string)Setting::get('brightdata_api_token', ''));
            if ($bright !== '') {
                $legacy[] = [
                    'id' => 0,
                    'provider_type' => 'brightdata',
                    'name' => 'Bright Data',
                    'api_token' => $bright,
                    'config' => [],
                ];
            }

            $apify = trim((string)Setting::get('apify_api_token', ''));
            if (Setting::get('apify_enabled', '0') === '1' && $apify !== '') {
                $legacy[] = [
                    'id' => 0,
                    'provider_type' => 'apify',
                    'name' => 'Apify',
                    'api_token' => $apify,
                    'config' => [
                        'timeout_seconds' => (int)Setting::get('apify_timeout_seconds', '90'),
                    ],
                ];
            }
        } catch (\Throwable $e) {
            \App\Core\Logger::exception(
                $e,
                'provider',
                ['event' => 'Legacy blocked marketplace provider lookup failed'],
                'warning'
            );
        }

        return $legacy;
    }

    /**
     * EN: Fetch raw marketplace HTML through Bright Data Web Unlocker.
     * 中文：通过 Bright Data Web Unlocker 获取 Marketplace 原始 HTML。
     *
     * @param array $profile Provider profile containing runtime-only credentials. / 含运行时凭据的 Provider Profile。
     * @param string $platform Marketplace platform. / Marketplace 平台。
     * @param string $url Listing URL. / 帖子 URL。
     * @param int $userId Requesting user ID. / 请求用户 ID。
     * @param bool $bypassCache Whether cached provider results should be ignored. / 是否忽略 Provider 缓存。
     *
     * @return array Provider result containing unlocked HTML. / 包含已解锁 HTML 的 Provider 结果。
     */
    private function fetchBrightData(
        array $profile,
        string $platform,
        string $url,
        int $userId,
        bool $bypassCache
    ): array {
        $token = trim((string)($profile['api_token'] ?? ''));
        if ($token === '') {
            throw new \RuntimeException('Bright Data token is missing.');
        }

        $zone = $this->brightDataZone($profile, $token);
        if ($zone === '') {
            throw new \RuntimeException('Bright Data Web Unlocker zone is not configured.');
        }

        $externalId = PlatformUrl::externalId($platform, $url);
        $providerKey = $this->providerKey($profile, 'brightdata_unlocker');
        if (!$bypassCache) {
            $cached = FetchJob::recentReady($platform, $externalId, 10, $providerKey);
            if ($cached && trim((string)($cached['html'] ?? '')) !== '') {
                $cached['_provider_cache'] = true;
                return $cached;
            }
        }

        $jobId = FetchJob::create($userId, $platform, $url, $externalId, $providerKey);
        $config = is_array($profile['config'] ?? null) ? $profile['config'] : [];
        $timeout = max(15, min(120, (int)($config['timeout_seconds'] ?? 45)));

        try {
            $ch = curl_init('https://api.brightdata.com/request');
            if ($ch === false) {
                throw new \RuntimeException('Could not initialize Bright Data Web Unlocker client.');
            }

            $payload = [
                'zone' => $zone,
                'url' => $url,
                'format' => 'raw',
                'method' => 'GET',
                'country' => 'us',
            ];

            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json, text/html;q=0.9, */*;q=0.8',
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
                    'Bright Data Web Unlocker network error: ' . ($error ?: 'unknown error')
                );
            }
            if ($status < 200 || $status >= 300) {
                throw new \RuntimeException('Bright Data Web Unlocker returned HTTP ' . $status . '.');
            }

            $html = (string)$raw;
            $decoded = json_decode($html, true);
            if (is_array($decoded) && array_key_exists('body', $decoded)) {
                $targetStatus = (int)($decoded['status_code'] ?? 200);
                if ($targetStatus >= 400) {
                    throw new \RuntimeException(
                        'Bright Data target page returned HTTP ' . $targetStatus . '.'
                    );
                }
                $html = (string)$decoded['body'];
            }

            if (trim($html) === '') {
                throw new \RuntimeException('Bright Data Web Unlocker returned an empty page.');
            }

            $result = [
                'provider' => 'brightdata_unlocker',
                'provider_name' => (string)($profile['name'] ?? 'Bright Data'),
                'provider_job_id' => null,
                'submitted_url' => $url,
                'resolved_url' => $url,
                'canonical_url' => $url,
                'external_post_id' => $externalId,
                'html' => $html,
                'raw' => [
                    'unlocker_zone' => $zone,
                    'target_platform' => $platform,
                ],
            ];

            FetchJob::setReady($jobId, $result, $status);
            return $result;
        } catch (\Throwable $e) {
            try {
                FetchJob::setStatus($jobId, 'failed', null, $e->getMessage());
            } catch (\Throwable $ignored) {
                \App\Core\Logger::exception(
                    $ignored,
                    'provider',
                    ['event' => 'Bright Data Unlocker fetch-job failure could not be persisted'],
                    'error'
                );
            }
            throw $e;
        }
    }

    /**
     * EN: Fetch structured listing data through the Apify actor dedicated to the platform.
     * 中文：通过对应平台的 Apify Actor 获取结构化帖子数据。
     *
     * @param array $profile Provider profile containing runtime-only credentials. / 含运行时凭据的 Provider Profile。
     * @param string $platform Marketplace platform. / Marketplace 平台。
     * @param string $url Listing URL. / 帖子 URL。
     * @param int $userId Requesting user ID. / 请求用户 ID。
     * @param bool $bypassCache Whether cached provider results should be ignored. / 是否忽略 Provider 缓存。
     *
     * @return array Normalized listing metadata. / 标准化帖子元数据。
     */
    private function fetchApify(
        array $profile,
        string $platform,
        string $url,
        int $userId,
        bool $bypassCache
    ): array {
        $token = trim((string)($profile['api_token'] ?? ''));
        if ($token === '') {
            throw new \RuntimeException('Apify token is missing.');
        }

        $actor = self::APIFY_ACTORS[$platform] ?? '';
        $overrideName = $platform === 'craigslist'
            ? 'CDSP_APIFY_CRAIGSLIST_ACTOR'
            : 'CDSP_APIFY_OFFERUP_ACTOR';
        $override = trim((string)getenv($overrideName));
        if ($override !== '') {
            $actor = str_replace('/', '~', $override);
        }
        if ($actor === '') {
            throw new \RuntimeException('No Apify actor is configured for this platform.');
        }

        $externalId = PlatformUrl::externalId($platform, $url);
        $providerKey = $this->providerKey($profile, 'apify_' . $platform);
        if (!$bypassCache) {
            $cached = FetchJob::recentReady($platform, $externalId, 10, $providerKey);
            if ($cached && $this->complete($cached)) {
                $cached['_provider_cache'] = true;
                return $cached;
            }
        }

        $config = is_array($profile['config'] ?? null) ? $profile['config'] : [];
        $timeout = max(30, min(180, (int)($config['timeout_seconds'] ?? 90)));
        $jobId = FetchJob::create($userId, $platform, $url, $externalId, $providerKey);

        try {
            if ($platform === 'craigslist') {
                // EN: automation-lab/craigslist-scraper accepts direct listing URLs in listingUrls.
                // 中文：automation-lab/craigslist-scraper 通过 listingUrls 接收帖子直链。
                $payload = [
                    'listingUrls' => [$url],
                    'includeDetails' => true,
                    'maxResults' => 1,
                ];
            } else {
                // EN: abotapi/offerup-scraper supports direct item URLs in URL mode.
                // OfferUp blocks datacenter traffic, so request a US residential proxy.
                // 中文：abotapi/offerup-scraper 支持 URL 模式直链；OfferUp 会阻止
                // 机房流量，因此显式使用美国住宅代理。
                $payload = [
                    'mode' => 'url',
                    'urls' => [$url],
                    'fetchDetails' => true,
                    'maxItems' => 1,
                    'proxy' => [
                        'useApifyProxy' => true,
                        'apifyProxyGroups' => ['RESIDENTIAL'],
                        'apifyProxyCountry' => 'US',
                    ],
                ];
            }

            $endpoint = 'https://api.apify.com/v2/acts/' . $actor
                . '/run-sync-get-dataset-items?format=json&clean=true&maxItems=1&maxTotalChargeUsd=0.25';

            $ch = curl_init($endpoint);
            if ($ch === false) {
                throw new \RuntimeException('Could not initialize Apify fallback client.');
            }

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
                CURLOPT_USERAGENT => 'CoolerDepot-SalesPosts/'
                    . (string)($GLOBALS['config']['app']['version'] ?? 'dev'),
            ]);

            $raw = curl_exec($ch);
            $error = curl_error($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);

            if ($raw === false) {
                throw new \RuntimeException('Apify network error: ' . ($error ?: 'unknown error'));
            }

            $data = json_decode((string)$raw, true);
            if ($status < 200 || $status >= 300) {
                throw new \RuntimeException(
                    'Apify request returned HTTP ' . $status . ': '
                    . $this->providerMessage($data, (string)$raw)
                );
            }

            $record = $this->firstListingRecord($data, $platform);
            if (!$record) {
                throw new \RuntimeException(
                    'Apify returned no usable ' . ucfirst($platform) . ' listing.'
                );
            }

            $result = $this->normalizeApify($platform, $record, $url, $externalId);
            $result['provider_name'] = trim((string)($profile['name'] ?? '')) ?: 'Apify';
            if (!$this->complete($result)) {
                throw new \RuntimeException('Apify returned incomplete listing metadata.');
            }

            FetchJob::setSnapshot(
                $jobId,
                (string)($result['external_post_id'] ?? $externalId ?? $jobId),
                $status
            );
            FetchJob::setReady($jobId, $result, $status);
            return $result;
        } catch (\Throwable $e) {
            try {
                FetchJob::setStatus($jobId, 'failed', null, $e->getMessage());
            } catch (\Throwable $ignored) {
                \App\Core\Logger::exception(
                    $ignored,
                    'provider',
                    ['event' => 'Apify blocked-marketplace fetch-job failure could not be persisted'],
                    'error'
                );
            }
            throw $e;
        }
    }

    /**
     * EN: Normalize supported Apify actor output into the application's common listing shape.
     * 中文：将支持的 Apify Actor 输出标准化为应用统一的帖子结构。
     *
     * @param string $platform Marketplace platform. / Marketplace 平台。
     * @param array $record Raw Apify listing record. / Apify 原始帖子记录。
     * @param string $submittedUrl Original submitted listing URL. / 原始提交帖子 URL。
     * @param ?string $expectedId External ID parsed before provider execution. / Provider 执行前解析的外部 ID。
     *
     * @return array Normalized listing metadata. / 标准化帖子元数据。
     */
    private function normalizeApify(
        string $platform,
        array $record,
        string $submittedUrl,
        ?string $expectedId
    ): array {
        if ($platform === 'craigslist') {
            $id = $this->firstText(
                $record,
                ['id', 'listingId', 'postId', 'post_id', 'postingId', 'posting_id']
            ) ?: (string)($expectedId ?? '');
            $title = $this->firstText($record, ['title', 'listingTitle']);
            $description = $this->firstText(
                $record,
                ['description', 'body', 'postBody', 'postingBody']
            );
            $published = $this->firstText(
                $record,
                ['postedAt', 'posted_at', 'datePosted', 'publishedAt', 'posted_at_local']
            );
            $canonicalSource = $this->firstText(
                $record,
                ['url', 'detail_url', 'listingUrl', 'listing_url']
            ) ?: $submittedUrl;
        } else {
            $id = $this->firstText($record, ['listingId', 'listing_id', 'id', 'itemId'])
                ?: (string)($expectedId ?? '');
            $title = $this->firstText($record, ['title', 'listingTitle']);
            $description = $this->firstText(
                $record,
                ['description', 'listingDescription']
            );
            $published = $this->firstText(
                $record,
                ['postDate', 'postedAt', 'posted_at', 'publishedAt', 'createdAt']
            );
            $canonicalSource = $this->firstText(
                $record,
                ['url', 'listingUrl', 'listing_url', 'itemUrl']
            ) ?: $submittedUrl;
        }

        $canonical = PlatformUrl::normalize($canonicalSource, $platform) ?: $submittedUrl;
        $images = array_slice(ImageFingerprint::urls($record), 0, 1);

        return [
            'provider' => 'apify_' . $platform,
            'provider_name' => 'Apify',
            'provider_job_id' => $id !== '' ? $id : null,
            'submitted_url' => $submittedUrl,
            'resolved_url' => $canonical,
            'canonical_url' => $canonical,
            'external_post_id' => $id !== ''
                ? $id
                : PlatformUrl::externalId($platform, $canonical),
            'title' => $title,
            'description' => $description,
            'published_raw' => $published !== '' ? $published : null,
            'photos' => array_map(
                static fn(string $image): array => ['url' => $image],
                $images
            ),
            'raw' => $record,
        ];
    }

    /**
     * EN: Return the first usable listing row and ignore summary/block sentinel rows.
     * 中文：返回第一个可用帖子记录，并忽略 Summary/Blocked Sentinel 记录。
     *
     * @param mixed $data Decoded Apify response. / 已解码的 Apify 返回数据。
     * @param string $platform Marketplace platform. / Marketplace 平台。
     *
     * @return ?array First usable listing row or null. / 第一个可用帖子记录或 null。
     */
    private function firstListingRecord($data, string $platform): ?array
    {
        if (!is_array($data)) {
            return null;
        }

        $rows = array_is_list($data) ? $data : [$data];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $type = strtolower((string)($row['type'] ?? $row['rowType'] ?? $row['recordType'] ?? ''));
            if (str_contains($type, 'blocked') || str_contains($type, 'summary')) {
                continue;
            }

            $title = $this->firstText($row, ['title', 'listingTitle']);
            $id = $platform === 'offerup'
                ? $this->firstText($row, ['listingId', 'listing_id', 'id', 'itemId'])
                : $this->firstText(
                    $row,
                    ['id', 'listingId', 'postId', 'post_id', 'postingId', 'posting_id']
                );

            if ($title !== '' && $id !== '') {
                return $row;
            }
        }

        return null;
    }

    /**
     * EN: Read the first non-empty scalar value from candidate keys.
     * 中文：从候选字段中读取第一个非空标量值。
     *
     * @param array $record Listing record. / 帖子记录。
     * @param array $keys Candidate field names. / 候选字段名称。
     *
     * @return string First non-empty text value. / 第一个非空文本值。
     */
    private function firstText(array $record, array $keys): string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $record)) {
                continue;
            }

            $value = $record[$key];
            if (is_string($value) || is_numeric($value)) {
                $text = trim((string)$value);
                if ($text !== '') {
                    return $text;
                }
            }

            if (is_array($value)) {
                foreach (['text', 'value', 'label', 'url'] as $nested) {
                    if (isset($value[$nested]) && is_scalar($value[$nested])) {
                        $text = trim((string)$value[$nested]);
                        if ($text !== '') {
                            return $text;
                        }
                    }
                }
            }
        }

        return '';
    }

    /**
     * EN: Check whether normalized listing metadata is sufficient for verification.
     * 中文：检查标准化帖子元数据是否足以完成验证。
     *
     * @param array $item Normalized listing metadata. / 标准化帖子元数据。
     *
     * @return bool True when required verification fields are present. / 必要验证字段齐全时返回 true。
     */
    private function complete(array $item): bool
    {
        return trim((string)($item['external_post_id'] ?? '')) !== ''
            && trim((string)($item['title'] ?? '')) !== ''
            && trim((string)($item['published_raw'] ?? '')) !== '';
    }

    /**
     * EN: Build the provider job key without exposing secrets.
     * 中文：构建 Provider Job Key，不暴露敏感信息。
     *
     * @param array $profile Provider profile. / Provider Profile。
     * @param string $suffix Non-secret provider job suffix. / 非敏感 Provider Job 后缀。
     *
     * @return string Provider job key. / Provider Job Key。
     */
    private function providerKey(array $profile, string $suffix): string
    {
        $id = (int)($profile['id'] ?? 0);
        return ($id > 0 ? 'profile_' . $id : 'legacy') . '_' . $suffix;
    }

    /**
     * EN: Extract a short safe provider error message without exposing credentials.
     * 中文：提取简短安全的 Provider 错误信息，同时避免泄露凭据。
     *
     * @param mixed $json Decoded provider response. / 已解码的 Provider 返回数据。
     * @param string $raw Raw provider response body. / Provider 原始返回正文。
     *
     * @return string Sanitized provider error message. / 清理后的 Provider 错误信息。
     */
    private function providerMessage($json, string $raw): string
    {
        if (is_array($json)) {
            if (isset($json['error']['message']) && is_scalar($json['error']['message'])) {
                return substr(trim((string)$json['error']['message']), 0, 500);
            }
            foreach (['error', 'message', 'detail'] as $key) {
                if (isset($json[$key]) && is_scalar($json[$key])) {
                    return substr(trim((string)$json[$key]), 0, 500);
                }
            }
        }

        $clean = trim(preg_replace('/\s+/u', ' ', strip_tags($raw)));
        return $clean !== '' ? substr($clean, 0, 500) : 'Unknown provider error.';
    }

    /**
     * EN: Resolve the Bright Data Web Unlocker zone from profile config or environment.
     * 中文：从 Provider 配置或环境变量读取 Bright Data Web Unlocker Zone。
     *
     * @param array $profile Provider profile. / Provider Profile。
     * @param string $token Bright Data API key used only at runtime. / 仅运行时使用的 Bright Data API Key。
     *
     * @return string Configured Web Unlocker zone or an empty string. / 已配置的 Web Unlocker Zone，未配置时为空字符串。
     */
    private function brightDataZone(array $profile, string $token): string
    {
        $config = is_array($profile['config'] ?? null) ? $profile['config'] : [];
        $configured = trim((string)($config['unlocker_zone'] ?? ''));
        if ($configured !== '') {
            return $configured;
        }

        $environment = trim((string)(getenv('BRIGHTDATA_UNLOCKER_ZONE') ?: ''));
        if ($environment !== '') {
            return $environment;
        }

        // EN: Existing Provider Registry records were created for the Facebook
        // Dataset API and do not contain an Unlocker zone. Reuse the same API key
        // to discover the first active Bright Data `unblocker` zone automatically.
        // 中文：旧 Provider Registry 的 Bright Data 记录原本用于 Facebook
        // Dataset API，没有 Unlocker Zone。这里复用同一个 API Key 自动发现
        // 第一个 active 的 `unblocker` Zone，无需修改 config 文件。
        $cacheKey = hash('sha256', $token);
        static $zoneCache = [];
        if (array_key_exists($cacheKey, $zoneCache)) {
            return (string)$zoneCache[$cacheKey];
        }
        $zoneCache[$cacheKey] = '';

        if ($token === '') {
            return '';
        }

        $ch = curl_init('https://api.brightdata.com/zone/get_active_zones');
        if ($ch === false) {
            return '';
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer ' . $token,
            ],
        ]);

        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($raw === false || $status < 200 || $status >= 300) {
            return '';
        }

        $zones = json_decode((string)$raw, true);
        if (!is_array($zones)) {
            return '';
        }

        foreach ($zones as $zone) {
            if (!is_array($zone)) {
                continue;
            }
            if (strtolower(trim((string)($zone['type'] ?? ''))) !== 'unblocker') {
                continue;
            }
            $name = trim((string)($zone['name'] ?? ''));
            if ($name !== '') {
                $zoneCache[$cacheKey] = $name;
                return $name;
            }
        }

        return '';
    }
}
