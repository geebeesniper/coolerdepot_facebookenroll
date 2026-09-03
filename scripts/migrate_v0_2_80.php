<?php
/** v0.2.80 website scan run-history migration. */
require dirname(__DIR__) . '/config/bootstrap.php';

use App\Services\WebsiteScanJob;

WebsiteScanJob::ensureTable();
