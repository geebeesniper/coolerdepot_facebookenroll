<?php

$config = require dirname(__DIR__) . '/config/bootstrap.php';

use App\Core\Database;

$pdo = Database::connection();

$stmt = $pdo->prepare(
    "DELETE FROM cdsp_fetch_jobs
     WHERE platform='facebook'
       AND submitted_url REGEXP 'https?://.*https?://'"
);

$stmt->execute();

echo "Removed malformed duplicated-URL fetch jobs: " . $stmt->rowCount() . PHP_EOL;
