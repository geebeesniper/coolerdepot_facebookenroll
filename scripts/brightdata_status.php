<?php
/**
 * File / 文件：scripts/brightdata_status.php
 * EN: CLI maintenance/deployment script for brightdata status.
 * 中文：用于 brightdata status 的命令行维护/部署脚本。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
$config = require dirname(__DIR__) . '/config/bootstrap.php';

use App\Models\Setting;
use App\Services\BrightDataMarketplaceProvider;

try {
    $provider = new BrightDataMarketplaceProvider();
    $configured = $provider->configured();
    $status = $provider->credentialStatus();

    $dataset = Setting::get(
        'brightdata_marketplace_dataset_id',
        BrightDataMarketplaceProvider::DEFAULT_DATASET_ID
    );

    echo "Bright Data enabled/configured: "
        . ($configured ? "YES" : "NO")
        . PHP_EOL;

    echo "Dataset ID: " . $dataset . PHP_EOL;
    echo "Primary API token: "
        . ($status['primary'] ? "STORED (hidden)" : "NOT STORED")
        . PHP_EOL;
    echo "Secondary API token: "
        . ($status['secondary'] ? "STORED (hidden)" : "NOT STORED")
        . PHP_EOL;

    echo "Failover: Primary -> Secondary -> Apify -> ScrapeCreators"
        . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
