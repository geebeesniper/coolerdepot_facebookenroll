<?php

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
