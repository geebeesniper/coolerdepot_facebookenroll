<?php
/**
 * File / 文件：scripts/apify_status.php
 * EN: CLI maintenance/deployment script for apify status.
 * 中文：用于 apify status 的命令行维护/部署脚本。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
$config = require dirname(__DIR__) . '/config/bootstrap.php';

use App\Models\Setting;
use App\Services\ApifyMarketplaceProvider;

$provider = new ApifyMarketplaceProvider();

echo "Apify configured: "
    . ($provider->configured() ? "YES" : "NO")
    . PHP_EOL;

echo "API token: "
    . (Setting::has('apify_api_token') ? "STORED (hidden)" : "NOT STORED")
    . PHP_EOL;

echo "Provider position: 2 (Bright Data -> Apify -> ScrapeCreators)"
    . PHP_EOL;
