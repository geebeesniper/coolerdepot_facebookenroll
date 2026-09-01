<?php
/**
 * File / 文件：scripts/migrate_provider_registry.php
 * EN: CLI maintenance/deployment script for migrate provider registry.
 * 中文：用于 migrate provider registry 的命令行维护/部署脚本。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
$config = require dirname(__DIR__) . '/config/bootstrap.php';

use App\Core\Database;
use App\Models\ProviderProfile;
use App\Models\Setting;
use App\Services\BrightDataMarketplaceProvider;

$pdo = Database::connection();

$sql = file_get_contents(
    dirname(__DIR__) . '/database/migrations/005_provider_registry.sql'
);

if ($sql === false) {
    fwrite(STDERR, "Could not read provider registry migration SQL.\n");
    exit(1);
}

$pdo->exec($sql);

$adminId = (int)$pdo->query(
    "SELECT id
     FROM cdsp_users
     WHERE role='admin' AND active=1
     ORDER BY id
     LIMIT 1"
)->fetchColumn();

if ($adminId <= 0) {
    fwrite(STDERR, "No active Admin user exists.\n");
    exit(1);
}

$imported = [];

$brightEnabled = Setting::get('brightdata_enabled', '0') === '1';
$dataset = Setting::get(
    'brightdata_marketplace_dataset_id',
    BrightDataMarketplaceProvider::DEFAULT_DATASET_ID
);
$brightConfig = [
    'dataset_id' => $dataset,
    'timeout_seconds' => (int)Setting::get('brightdata_timeout_seconds', '45'),
    'poll_seconds' => (int)Setting::get('brightdata_poll_seconds', '3'),
];

$primary = trim((string)Setting::get('brightdata_api_token', ''));
if ($primary !== '') {
    $imported[] = ProviderProfile::importLegacy(
        'legacy_brightdata_primary',
        [
            'provider_type' => 'brightdata',
            'name' => 'Bright Data Primary',
            'website_url' => 'https://brightdata.com/',
            'api_endpoint' => 'https://api.brightdata.com/datasets/v3/',
            'api_token' => $primary,
            'config' => $brightConfig,
        ],
        $adminId,
        $brightEnabled
    );
}

$secondary = trim((string)Setting::get('brightdata_api_token_secondary', ''));
if ($secondary !== '') {
    $imported[] = ProviderProfile::importLegacy(
        'legacy_brightdata_secondary',
        [
            'provider_type' => 'brightdata',
            'name' => 'Bright Data Secondary',
            'website_url' => 'https://brightdata.com/',
            'api_endpoint' => 'https://api.brightdata.com/datasets/v3/',
            'api_token' => $secondary,
            'config' => $brightConfig,
        ],
        $adminId,
        $brightEnabled
    );
}

$apify = trim((string)Setting::get('apify_api_token', ''));
if ($apify !== '') {
    $imported[] = ProviderProfile::importLegacy(
        'legacy_apify',
        [
            'provider_type' => 'apify',
            'name' => 'Apify',
            'website_url' => 'https://apify.com/',
            'api_endpoint' =>
                'https://api.apify.com/v2/actors/apify~facebook-marketplace-scraper/'
                . 'run-sync-get-dataset-items',
            'api_token' => $apify,
            'config' => [
                'timeout_seconds' => (int)Setting::get(
                    'apify_timeout_seconds',
                    '90'
                ),
            ],
        ],
        $adminId,
        Setting::get('apify_enabled', '0') === '1'
    );
}

$scrapeCreators = trim((string)Setting::get('scrapecreators_api_key', ''));
if ($scrapeCreators !== '') {
    $imported[] = ProviderProfile::importLegacy(
        'legacy_scrapecreators',
        [
            'provider_type' => 'scrapecreators',
            'name' => 'ScrapeCreators',
            'website_url' => 'https://scrapecreators.com/',
            'api_endpoint' =>
                'https://api.scrapecreators.com/v1/facebook/marketplace/item',
            'api_token' => $scrapeCreators,
            'config' => [
                'timeout_seconds' => (int)Setting::get(
                    'scrapecreators_timeout_seconds',
                    '20'
                ),
            ],
        ],
        $adminId,
        Setting::get('scrapecreators_enabled', '0') === '1'
    );
}

Setting::set('provider_registry_enabled', '1', $adminId);

echo "Provider Registry migration complete.\n";
echo "Imported provider credentials: " . count($imported) . "\n";
echo "Provider Manager is now active.\n";
