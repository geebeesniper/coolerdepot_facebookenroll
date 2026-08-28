<?php
namespace App\Services;

use App\Models\ProviderProfile;

class FacebookMarketplaceProviderChain
{
    public function fetch(string $url, int $requestedByUserId): array
    {
        if (ProviderProfile::registryEnabled()) {
            return $this->fetchRegistry($url, $requestedByUserId);
        }

        // Backward compatibility until migration 005 has been run.
        return $this->fetchLegacy($url, $requestedByUserId);
    }

    private function fetchRegistry(string $url, int $userId): array
    {
        $profiles = ProviderProfile::activeVerifiedWithSecrets();

        if (!$profiles) {
            throw new \RuntimeException(
                'No enabled and verified Facebook providers are configured.'
            );
        }

        $attempts = [];
        $chain = [];

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
                    $userId
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
                    $item['_fallback_reason'] = implode(' | ', $attempts);
                }

                return $item;
            } catch (\Throwable $e) {
                $attempts[] = $label . ': ' . $e->getMessage();
            }
        }

        throw new \RuntimeException(
            'All configured Facebook providers failed. '
            . implode(' | ', $attempts)
        );
    }

    private function fetchLegacy(string $url, int $userId): array
    {
        $attempts = [];
        $providers = [
            ['Bright Data', new BrightDataMarketplaceProvider()],
            ['Apify', new ApifyMarketplaceProvider()],
            ['ScrapeCreators', new ScrapeCreatorsMarketplaceProvider()],
        ];

        foreach ($providers as $index => [$name, $provider]) {
            if (!$provider->configured()) {
                $attempts[] = $name . ' is not configured.';
                continue;
            }

            try {
                $item = $provider->fetch($url, $userId);

                if ($this->complete($item)) {
                    $item['_provider_chain'][] = strtolower(str_replace(' ', '', $name));
                    $item['_fallback_used'] = $index > 0;
                    $item['_fallback_level'] = $index;
                    return $item;
                }

                $attempts[] = $name . ' returned incomplete metadata.';
            } catch (\Throwable $e) {
                $attempts[] = $name . ': ' . $e->getMessage();
            }
        }

        throw new \RuntimeException(implode(' | ', $attempts));
    }

    private function complete(array $item): bool
    {
        return trim((string)($item['external_post_id'] ?? '')) !== ''
            && trim((string)($item['title'] ?? '')) !== ''
            && trim((string)($item['description'] ?? '')) !== ''
            && trim((string)($item['published_raw'] ?? '')) !== '';
    }
}
