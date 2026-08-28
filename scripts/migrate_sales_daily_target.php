<?php

$config = require dirname(__DIR__) . '/config/bootstrap.php';

use App\Core\Database;

$pdo = Database::connection();

$stmt = $pdo->query(
    "SELECT COUNT(*)
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'cdsp_users'
       AND COLUMN_NAME = 'daily_post_target'"
);

$exists = (int)$stmt->fetchColumn() > 0;

if (!$exists) {
    $pdo->exec(
        "ALTER TABLE cdsp_users
         ADD COLUMN daily_post_target
         SMALLINT UNSIGNED NOT NULL DEFAULT 10
         AFTER active"
    );

    echo "Added cdsp_users.daily_post_target (default 10)." . PHP_EOL;
} else {
    echo "cdsp_users.daily_post_target already exists." . PHP_EOL;
}

$pdo->exec(
    "UPDATE cdsp_users
     SET daily_post_target = 10
     WHERE role='sales'
       AND (daily_post_target IS NULL OR daily_post_target < 1)"
);

echo "Sales daily target migration complete." . PHP_EOL;
