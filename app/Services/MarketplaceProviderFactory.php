<?php
/**
 * File / 文件：app/Services/MarketplaceProviderFactory.php
 * EN: Application service for reusable business or integration logic.
 * 中文：该文件负责可复用的业务逻辑或外部集成服务。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
namespace App\Services;

class MarketplaceProviderFactory
{
    /**
     * EN: Builds, formats, or transforms data for `make` (make).
     * 中文：为 `make`（make）构建、格式化或转换数据。
     */
    public static function make(array $profile): object
    {
        return match ((string)($profile['provider_type'] ?? '')) {
            'brightdata' => new RegistryBrightDataMarketplaceProvider($profile),
            'apify' => new RegistryApifyMarketplaceProvider($profile),
            'scrapecreators' => new RegistryScrapeCreatorsMarketplaceProvider($profile),
            'generic_json' => new GenericJsonMarketplaceProvider($profile),
            default => throw new \RuntimeException('Unsupported provider type.'),
        };
    }
}
