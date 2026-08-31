<?php

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
