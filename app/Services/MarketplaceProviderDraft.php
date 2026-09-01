<?php
/**
 * File / 文件：app/Services/MarketplaceProviderDraft.php
 * EN: Application service for reusable business or integration logic.
 * 中文：该文件负责可复用的业务逻辑或外部集成服务。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
namespace App\Services;

class MarketplaceProviderDraft
{
    public const TYPES = [
        'brightdata',
        'apify',
        'scrapecreators',
        'generic_json',
    ];

    /**
     * EN: Builds, formats, or transforms data for `fromPost` (from Post).
     * 中文：为 `fromPost`（from Post）构建、格式化或转换数据。
     */
    public static function fromPost(array $input): array
    {
        $type = strtolower(trim((string)($input['provider_type'] ?? '')));
        $name = trim((string)($input['provider_name'] ?? ''));
        $token = trim((string)($input['api_token'] ?? ''));
        $website = HttpEndpointGuard::optionalWebsite(
            (string)($input['website_url'] ?? '')
        );

        if (!in_array($type, self::TYPES, true)) {
            throw new ProviderValidationException('provider_type', 'Choose a supported provider type.');
        }

        if ($name === '' || strlen($name) > 300) {
            throw new ProviderValidationException('provider_name', 'Provider name is required and must be 100 characters or less.');
        }

        $profile = [
            'id' => 0,
            'provider_type' => $type,
            'name' => $name,
            'website_url' => $website,
            'api_endpoint' => '',
            'api_token' => $token,
            'config' => [],
            'enabled' => 1,
            'verified_at' => null,
        ];

        if ($type === 'brightdata') {
            if ($token === '') {
                throw new ProviderValidationException('api_token', 'Bright Data API token is required.');
            }

            $dataset = trim((string)(
                $input['brightdata_dataset_id']
                ?? BrightDataMarketplaceProvider::DEFAULT_DATASET_ID
            ));

            if (!preg_match('/^gd_[A-Za-z0-9]+$/', $dataset)) {
                throw new ProviderValidationException('brightdata_dataset_id', 'Bright Data Dataset ID must start with gd_.');
            }

            $profile['website_url'] = $website ?: 'https://brightdata.com/';
            $profile['api_endpoint'] = 'https://api.brightdata.com/datasets/v3/';
            $profile['config'] = [
                'dataset_id' => $dataset,
                'timeout_seconds' => max(15, min(90, (int)($input['timeout_seconds'] ?? 45))),
                'poll_seconds' => max(2, min(10, (int)($input['poll_seconds'] ?? 3))),
            ];
        } elseif ($type === 'apify') {
            if ($token === '') {
                throw new ProviderValidationException('api_token', 'Apify API token is required.');
            }

            $profile['website_url'] = $website ?: 'https://apify.com/';
            $profile['api_endpoint'] =
                'https://api.apify.com/v2/actors/apify~facebook-marketplace-scraper/run-sync-get-dataset-items';
            $profile['config'] = [
                'timeout_seconds' => max(20, min(180, (int)($input['timeout_seconds'] ?? 90))),
            ];
        } elseif ($type === 'scrapecreators') {
            if ($token === '') {
                throw new ProviderValidationException('api_token', 'ScrapeCreators API key is required.');
            }

            $profile['website_url'] = $website ?: 'https://scrapecreators.com/';
            $profile['api_endpoint'] =
                'https://api.scrapecreators.com/v1/facebook/marketplace/item';
            $profile['config'] = [
                'timeout_seconds' => max(8, min(45, (int)($input['timeout_seconds'] ?? 20))),
            ];
        } else {
            $endpoint = HttpEndpointGuard::assertPublicHttps(
                (string)($input['api_endpoint'] ?? '')
            );
            $method = strtoupper(trim((string)($input['request_method'] ?? 'GET')));
            $authMode = strtolower(trim((string)($input['auth_mode'] ?? 'bearer')));
            $inputMode = strtolower(trim((string)($input['input_mode'] ?? 'query')));
            $inputKey = trim((string)($input['input_key'] ?? 'url'));
            $authName = trim((string)($input['auth_name'] ?? ''));

            if (!in_array($method, ['GET', 'POST'], true)) {
                throw new ProviderValidationException('request_method', 'Custom API method must be GET or POST.');
            }

            if (!in_array($authMode, ['none', 'bearer', 'header', 'query'], true)) {
                throw new ProviderValidationException('auth_mode', 'Choose a valid authentication mode.');
            }

            if (!in_array($inputMode, ['query', 'json'], true)) {
                throw new ProviderValidationException('input_mode', 'Custom API input mode must be Query or JSON.');
            }

            if ($method === 'GET' && $inputMode === 'json') {
                throw new ProviderValidationException('input_mode', 'GET requests cannot use JSON input mode.');
            }

            if ($inputKey === '' || !preg_match('/^[A-Za-z0-9_.-]{1,80}$/', $inputKey)) {
                throw new ProviderValidationException('input_key', 'Input field name is invalid.');
            }

            if ($authMode !== 'none' && $token === '') {
                throw new ProviderValidationException('api_token', 'API token/key is required for the selected auth mode.');
            }

            if (in_array($authMode, ['header', 'query'], true)) {
                if ($authName === '' || !preg_match('/^[A-Za-z0-9_.-]{1,80}$/', $authName)) {
                    throw new ProviderValidationException('auth_name', 'Auth header/query parameter name is invalid.');
                }
            }

            $paths = [
                'id_path' => trim((string)($input['id_path'] ?? 'id')),
                'title_path' => trim((string)($input['title_path'] ?? 'title')),
                'description_path' => trim((string)($input['description_path'] ?? 'description')),
                'date_path' => trim((string)($input['date_path'] ?? 'creation_time')),
                'url_path' => trim((string)($input['url_path'] ?? 'url')),
            ];

            foreach (['id_path','title_path','description_path','date_path'] as $requiredPath) {
                if ($paths[$requiredPath] === '') {
                    throw new ProviderValidationException($requiredPath, 'This response JSON path is required.');
                }
            }

            $profile['api_endpoint'] = $endpoint;
            $profile['config'] = array_merge($paths, [
                'request_method' => $method,
                'auth_mode' => $authMode,
                'auth_name' => $authName,
                'input_mode' => $inputMode,
                'input_key' => $inputKey,
                'timeout_seconds' => max(8, min(60, (int)($input['timeout_seconds'] ?? 20))),
            ]);
        }

        return $profile;
    }

    /**
     * EN: Implements the application operation `fingerprint` (fingerprint).
     * 中文：实现应用操作 `fingerprint`（fingerprint）。
     */
    public static function fingerprint(array $profile): string
    {
        $safe = $profile;
        $safe['api_token'] = hash('sha256', (string)($profile['api_token'] ?? ''));
        ksort($safe);

        return hash(
            'sha256',
            json_encode(
                $safe,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            )
        );
    }

    /**
     * EN: Implements the application operation `typeLabel` (type Label).
     * 中文：实现应用操作 `typeLabel`（type Label）。
     */
    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'brightdata' => 'Bright Data',
            'apify' => 'Apify',
            'scrapecreators' => 'ScrapeCreators',
            'generic_json' => 'Custom JSON API',
            default => $type,
        };
    }
}
