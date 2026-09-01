<?php
/**
 * File / 文件：app/Services/FacebookMarketplaceProviderChain.php
 * EN: Application service for reusable business or integration logic.
 * 中文：该文件负责可复用的业务逻辑或外部集成服务。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
namespace App\Services;

use App\Models\ProviderProfile;

class FacebookMarketplaceProviderChain
{
    /**
     * EN: Retrieves or loads data for `fetch` (fetch).
     * 中文：读取或加载 `fetch`（fetch）所需的数据。
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
 * EN: Retrieves or loads data for `fetchRegistry` (fetch Registry).
 * 中文：读取或加载 `fetchRegistry`（fetch Registry）所需的数据。
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
 * EN: Retrieves or loads data for `fetchLegacy` (fetch Legacy).
 * 中文：读取或加载 `fetchLegacy`（fetch Legacy）所需的数据。
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
 * EN: Checks or validates the condition represented by `hasPhoto` (has Photo).
 * 中文：检查或校验 `hasPhoto`（has Photo）所表示的条件。
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
 * EN: Implements the application operation `containsHttpsImage` (contains Https Image).
 * 中文：实现应用操作 `containsHttpsImage`（contains Https Image）。
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
