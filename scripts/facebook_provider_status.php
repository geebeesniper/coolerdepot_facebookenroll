<?php
/**
 * File / 文件：scripts/facebook_provider_status.php
 * EN: Operations/deployment/diagnostics script owned by this project.
 * 中文：该文件是本项目自有的运维、部署或诊断脚本。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
$config = require dirname(__DIR__) . '/config/bootstrap.php';

use App\Models\ProviderProfile;

if (!ProviderProfile::registryEnabled()) {
    echo "Provider Registry: NOT ENABLED\n";
    echo "Run scripts/migrate_provider_registry.php\n";
    exit(1);
}

$providers = ProviderProfile::allAdmin();

echo "Provider Registry: ENABLED\n";
echo "Failover order:\n";

foreach ($providers as $index => $provider) {
    echo ($index + 1) . '. '
        . $provider['name']
        . ' [' . $provider['provider_type'] . ']'
        . ' — ' . ((int)$provider['enabled'] === 1 ? 'ENABLED' : 'DISABLED')
        . ' — ' . (!empty($provider['verified_at']) ? 'TESTED' : 'NOT TESTED')
        . ' — token ' . (!empty($provider['token_configured']) ? 'STORED' : 'NONE')
        . PHP_EOL;
}

if (!$providers) {
    echo "(no providers)\n";
}
