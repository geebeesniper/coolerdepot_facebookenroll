<?php

$config = require dirname(__DIR__) . '/config/bootstrap.php';

use App\Core\Database;

$sql = file_get_contents(
    dirname(__DIR__) . '/database/migrations/006_remove_review_ratings.sql'
);

if ($sql === false) {
    fwrite(STDERR, "Could not read review-rating migration SQL.\n");
    exit(1);
}

Database::connection()->exec($sql);

echo "Review rating migration complete.\n";
echo "Rating UI is retired; legacy rating columns are nullable.\n";
