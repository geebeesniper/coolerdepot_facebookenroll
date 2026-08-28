<?php

$config = require dirname(__DIR__) . '/config/bootstrap.php';

use App\Models\Setting;
use App\Services\BrightDataMarketplaceProvider;

try {
    $configured = (new BrightDataMarketplaceProvider())->configured();
    $dataset = Setting::get(
        'brightdata_marketplace_dataset_id',
        BrightDataMarketplaceProvider::DEFAULT_DATASET_ID
    );

    echo "Bright Data enabled/configured: " . ($configured ? "YES" : "NO") . PHP_EOL;
    echo "Dataset ID: " . $dataset . PHP_EOL;
    echo "API token: " . (Setting::has('brightdata_api_token') ? "STORED (hidden)" : "NOT STORED") . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
