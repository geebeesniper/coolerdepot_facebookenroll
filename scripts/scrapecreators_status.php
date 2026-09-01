<?php
/**
 * File / 文件：scripts/scrapecreators_status.php
 * EN: CLI maintenance/deployment script for scrapecreators status.
 * 中文：用于 scrapecreators status 的命令行维护/部署脚本。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
$config = require dirname(__DIR__) . '/config/bootstrap.php';

use App\Models\Setting;
use App\Services\ScrapeCreatorsMarketplaceProvider;

$provider = new ScrapeCreatorsMarketplaceProvider();

echo "ScrapeCreators configured: "
    . ($provider->configured() ? "YES" : "NO")
    . PHP_EOL;

echo "API key: "
    . (Setting::has('scrapecreators_api_key') ? "STORED (hidden)" : "NOT STORED")
    . PHP_EOL;
