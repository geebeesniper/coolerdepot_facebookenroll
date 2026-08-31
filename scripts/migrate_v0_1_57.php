<?php

$config = require dirname(__DIR__) . '/config/bootstrap.php';

use App\Core\Database;

$pdo = Database::connection();

$ids = [
    '1546388710570410',
    '3813795918762562',
    '1606074697620900',
];

$placeholders = implode(',', array_fill(0, count($ids), '?'));

$before = $pdo->prepare(
    "SELECT
        external_post_id,
        title,
        published_at,
        published_date
     FROM cdsp_sales_posts
     WHERE LOWER(platform)='facebook'
       AND external_post_id IN ($placeholders)
     ORDER BY external_post_id"
);

$before->execute($ids);
$beforeRows = $before->fetchAll(PDO::FETCH_ASSOC);

$update = $pdo->prepare(
    "UPDATE cdsp_sales_posts
     SET
        published_at=TIMESTAMP(
            '2026-08-28',
            TIME(
                COALESCE(
                    published_at,
                    '2026-08-28 12:00:00'
                )
            )
        ),
        published_date='2026-08-28',
        updated_at=NOW()
     WHERE LOWER(platform)='facebook'
       AND external_post_id IN ($placeholders)"
);

$update->execute($ids);

$after = $pdo->prepare(
    "SELECT
        external_post_id,
        title,
        published_at,
        published_date
     FROM cdsp_sales_posts
     WHERE LOWER(platform)='facebook'
       AND external_post_id IN ($placeholders)
     ORDER BY external_post_id"
);

$after->execute($ids);
$afterRows = $after->fetchAll(PDO::FETCH_ASSOC);

echo "v0.1.57 remaining Aug-28 correction complete." . PHP_EOL;
echo "Matched posts: " . count($afterRows) . PHP_EOL;
echo "Rows updated: " . $update->rowCount() . PHP_EOL;

foreach ($afterRows as $row) {
    echo sprintf(
        "%s | %s | %s | %s",
        $row['external_post_id'],
        $row['published_date'],
        $row['published_at'],
        $row['title'] ?? ''
    ) . PHP_EOL;
}
