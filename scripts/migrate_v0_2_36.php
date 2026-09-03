<?php
/** V0.2.36 website activity history migration. Safe to run repeatedly. */
require dirname(__DIR__).'/config/bootstrap.php';

\App\Services\WebsiteActivityHistory::ensureTable();
\App\Services\WebsiteScanJob::ensureTable();

fwrite(STDOUT,"V0.2.36 website activity history ready.\n");
