<?php
/**
 * File / 文件：scripts/apify_status.php
 * EN: Operations/deployment/diagnostics script owned by this project.
 * 中文：该文件是本项目自有的运维、部署或诊断脚本。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
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
