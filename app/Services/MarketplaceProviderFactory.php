<?php
/**
 * File / 文件：app/Services/MarketplaceProviderFactory.php
 * EN: Defines the MarketplaceProviderFactory service used by application business, security, or provider integration flows.
 * 中文：定义 MarketplaceProviderFactory 服务，用于应用业务、安全或 Provider 集成流程。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Services;

/**
 * EN: Application service that encapsulates marketplace provider factory business, security, or integration behavior.
 * 中文：封装 marketplace provider factory 业务、安全或外部集成行为的应用服务。
 */
class MarketplaceProviderFactory
{
    /**
     * EN: Build the make operation for marketplace provider factory.
     * 中文：构建 marketplace provider factory 的“make”操作。
     *
     * @param array $profile Profile value used by this operation. / 本操作使用的“profile”参数值。
     *
     * @return object object result produced by this operation. / 本操作生成的 object 类型结果。
     *
     * @throws \RuntimeException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
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
