<?php

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
