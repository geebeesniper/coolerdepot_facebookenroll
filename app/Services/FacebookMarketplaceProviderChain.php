<?php
/**
 * File / 文件：app/Services/FacebookMarketplaceProviderChain.php
 * EN: Defines the FacebookMarketplaceProviderChain service used by application business, security, or provider integration flows.
 * 中文：定义 FacebookMarketplaceProviderChain 服务，用于应用业务、安全或 Provider 集成流程。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Services;

use App\Models\ProviderProfile;

/**
 * EN: Application service that encapsulates facebook marketplace provider chain business, security, or integration behavior.
 * 中文：封装 facebook marketplace provider chain 业务、安全或外部集成行为的应用服务。
 */
class FacebookMarketplaceProviderChain
{
    /**
     * EN: Retrieve the fetch operation for facebook marketplace provider chain.
     * 中文：读取 facebook marketplace provider chain 的“fetch”操作。
     *
     * @param string $url URL to validate, resolve, fetch, or process. / 需要验证、解析、抓取或处理的 URL。
     * @param int $requestedByUserId Application or external user identifier. / 应用或外部用户 ID。
     * @param bool $bypassCache Bypass cache value used by this operation. / 本操作使用的“bypass cache”参数值。
     * @param bool $requirePhoto Require photo value used by this operation. / 本操作使用的“require photo”参数值。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
    public function fetch(string $url, int $requestedByUserId, bool $bypassCache = false, bool $requirePhoto = false): array
    {
        if (ProviderProfile::registryEnabled()) {
            return $this->fetchRegistry($url, $requestedByUserId, $bypassCache, $requirePhoto);
        }

        // Backward compatibility until migration 005 has been run.
        return $this->fetchLegacy($url, $requestedByUserId, $requirePhoto);
    }


/**
 * EN: Retrieve the fetch registry operation for facebook marketplace provider chain.
 * 中文：读取 facebook marketplace provider chain 的“fetch registry”操作。
 *
 * @param string $url URL to validate, resolve, fetch, or process. / 需要验证、解析、抓取或处理的 URL。
 * @param int $userId Application user identifier. / 应用用户 ID。
 * @param bool $bypassCache Bypass cache value used by this operation. / 本操作使用的“bypass cache”参数值。
 * @param bool $requirePhoto Require photo value used by this operation. / 本操作使用的“require photo”参数值。
 *
 * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
 *
 * @throws \RuntimeException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
 */
private function fetchRegistry(
    string $url,
    int $userId,
    bool $bypassCache = false,
    bool $requirePhoto = false
): array {
    $profiles = ProviderProfile::activeVerifiedWithSecrets();

    if (!$profiles) {
        throw new \RuntimeException(
            'No enabled and verified Facebook providers are configured.'
        );
    }

    $attempts = [];
    $chain = [];
    $firstCompleteWithoutPhoto = null;

    foreach ($profiles as $index => $profile) {
        $label = (string)$profile['name'];
        $chain[] = [
            'id' => (int)$profile['id'],
            'name' => $label,
            'type' => (string)$profile['provider_type'],
        ];

        try {
            $item = MarketplaceProviderFactory::make($profile)->fetch(
                $url,
                $userId,
                $bypassCache
            );

            if (!$this->complete($item)) {
                throw new \RuntimeException(
                    'Provider returned incomplete listing metadata.'
                );
            }

            $item['_provider_chain'] = $chain;
            $item['_fallback_used'] = $index > 0;
            $item['_fallback_level'] = $index;
            $item['_provider_profile_id'] = (int)$profile['id'];
            $item['_provider_profile_name'] = $label;

            if ($attempts) {
                $item['_fallback_reason'] = implode(
                    ' | ',
                    $attempts
                );
            }

            if (!$requirePhoto || $this->hasPhoto($item)) {
                return $item;
            }

            if ($firstCompleteWithoutPhoto === null) {
                $firstCompleteWithoutPhoto = $item;
            }

            $attempts[] =
                $label
                . ': listing metadata was returned, but no image was returned.';
        } catch (\Throwable $e) {
            \App\Core\Logger::exception(
                $e,
                'provider',
                ['event' => 'Registry provider attempt failed', 'provider' => $label],
                'warning'
            );
            $attempts[] = $label . ': ' . $e->getMessage();
        }
    }

    if ($firstCompleteWithoutPhoto !== null) {
        $firstCompleteWithoutPhoto['_image_missing'] = true;
        $firstCompleteWithoutPhoto['_fallback_reason'] =
            implode(' | ', $attempts);

        return $firstCompleteWithoutPhoto;
    }

    throw new \RuntimeException(
        'All configured Facebook providers failed. '
        . implode(' | ', $attempts)
    );
}


/**
 * EN: Retrieve the fetch legacy operation for facebook marketplace provider chain.
 * 中文：读取 facebook marketplace provider chain 的“fetch legacy”操作。
 *
 * @param string $url URL to validate, resolve, fetch, or process. / 需要验证、解析、抓取或处理的 URL。
 * @param int $userId Application user identifier. / 应用用户 ID。
 * @param bool $requirePhoto Require photo value used by this operation. / 本操作使用的“require photo”参数值。
 *
 * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
 *
 * @throws \RuntimeException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
 */
private function fetchLegacy(
    string $url,
    int $userId,
    bool $requirePhoto = false
): array {
    $attempts = [];
    $providers = [
        ['Bright Data', new BrightDataMarketplaceProvider()],
        ['Apify', new ApifyMarketplaceProvider()],
        ['ScrapeCreators', new ScrapeCreatorsMarketplaceProvider()],
    ];
    $firstCompleteWithoutPhoto = null;

    foreach ($providers as $index => [$name, $provider]) {
        if (!$provider->configured()) {
            $attempts[] = $name . ' is not configured.';
            continue;
        }

        try {
            $item = $provider->fetch($url, $userId);

            if (!$this->complete($item)) {
                $attempts[] =
                    $name . ' returned incomplete metadata.';
                continue;
            }

            $item['_provider_chain'][] =
                strtolower(str_replace(' ', '', $name));
            $item['_fallback_used'] = $index > 0;
            $item['_fallback_level'] = $index;

            if (!$requirePhoto || $this->hasPhoto($item)) {
                return $item;
            }

            if ($firstCompleteWithoutPhoto === null) {
                $firstCompleteWithoutPhoto = $item;
            }

            $attempts[] =
                $name . ' returned metadata but no image.';
        } catch (\Throwable $e) {
            \App\Core\Logger::exception(
                $e,
                'provider',
                ['event' => 'Legacy provider attempt failed', 'provider' => $name],
                'warning'
            );
            $attempts[] = $name . ': ' . $e->getMessage();
        }
    }

    if ($firstCompleteWithoutPhoto !== null) {
        $firstCompleteWithoutPhoto['_image_missing'] = true;
        $firstCompleteWithoutPhoto['_fallback_reason'] =
            implode(' | ', $attempts);

        return $firstCompleteWithoutPhoto;
    }

    throw new \RuntimeException(implode(' | ', $attempts));
}


/**
 * EN: Check or validate the has photo operation for facebook marketplace provider chain.
 * 中文：检查或验证 facebook marketplace provider chain 的“has photo”操作。
 *
 * @param array $item Current item being processed. / 当前正在处理的数据项。
 *
 * @return bool True when the requested condition is satisfied; otherwise false. / 请求条件满足时返回 true，否则返回 false。
 */
private function hasPhoto(array $item): bool
{
    $raw = is_array($item['raw'] ?? null)
        ? $item['raw']
        : [];

    $candidates = [
        $raw['listingPhotos'] ?? null,
        $raw['photos'] ?? null,
        $raw['images'] ?? null,
        $raw['image'] ?? null,
        $raw['image_url'] ?? null,
        $raw['thumbnail'] ?? null,
        $raw['thumbnail_url'] ?? null,
        $item['photos'] ?? null,
        $item['image'] ?? null,
        $item['image_url'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        if ($this->containsHttpsImage($candidate)) {
            return true;
        }
    }

    return false;
}

/**
 * EN: Perform the contains https image operation for facebook marketplace provider chain.
 * 中文：执行 facebook marketplace provider chain 的“contains https image”操作。
 *
 * @param mixed $value Value processed or stored by this operation. / 本操作处理或保存的值。
 *
 * @return bool True when the requested condition is satisfied; otherwise false. / 请求条件满足时返回 true，否则返回 false。
 */
private function containsHttpsImage($value): bool
{
    if (is_string($value)) {
        return str_starts_with(trim($value), 'https://');
    }

    if (!is_array($value)) {
        return false;
    }

    foreach ($value as $key => $child) {
        if (is_string($child)
            && in_array(
                strtolower((string)$key),
                [
                    'url',
                    'uri',
                    'src',
                    'image_url',
                    'thumbnail_url',
                    'photo_url',
                ],
                true
            )
            && str_starts_with(trim($child), 'https://')) {
            return true;
        }

        if (is_array($child) && $this->containsHttpsImage($child)) {
            return true;
        }
    }

    return false;
}

    /**
     * EN: Check or validate the complete operation for facebook marketplace provider chain.
     * 中文：检查或验证 facebook marketplace provider chain 的“complete”操作。
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
