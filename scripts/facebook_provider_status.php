<?php
/**
 * File / 文件：scripts/facebook_provider_status.php
 * EN: CLI maintenance/deployment script for facebook provider status.
 * 中文：用于 facebook provider status 的命令行维护/部署脚本。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
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
