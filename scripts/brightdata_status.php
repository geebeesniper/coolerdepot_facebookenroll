<?php

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
