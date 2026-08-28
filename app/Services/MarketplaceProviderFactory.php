<?php
namespace App\Services;

class MarketplaceProviderFactory
{
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
