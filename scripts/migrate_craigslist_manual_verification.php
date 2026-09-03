<?php
/**
 * File / 文件：scripts/migrate_craigslist_manual_verification.php
 * EN: Applies the V0.2.13 Craigslist manual-verification status migration.
 * 中文：执行 V0.2.13 Craigslist 手动验证状态数据库迁移。
 * Maintenance / 维护：This migration only expands ENUM values; it does not delete or rewrite business rows.
 * 维护要求：本迁移仅扩展 ENUM 可选值，不删除或重写业务数据。
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$config = require dirname(__DIR__) . '/config/bootstrap.php';

use App\Core\Database;

$pdo = Database::connection();
$driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

if ($driver !== 'mysql') {
    fwrite(STDERR, "V0.2.13 migration requires MySQL/MariaDB; detected {$driver}.\n");
    exit(1);
}

$sql = file_get_contents(
    dirname(__DIR__) . '/database/migrations/020_craigslist_manual_verification.sql'
);

if ($sql === false) {
    fwrite(STDERR, "Could not read V0.2.13 migration SQL.\n");
    exit(1);
}

$pdo->exec($sql);

$required = [
    'cdsp_post_inspections' => 'verification_status',
    'cdsp_sales_posts' => 'verification_status',
];

foreach ($required as $table => $column) {
    $stmt = $pdo->query(
        "SHOW COLUMNS FROM `{$table}` LIKE " . $pdo->quote($column)
    );
    $row = $stmt ? $stmt->fetch() : false;
    $type = strtolower((string)($row['Type'] ?? ''));

    if (!$row || strpos($type, "'manual_pending'") === false) {
        fwrite(
            STDERR,
            "Migration verification failed: {$table}.{$column} does not contain manual_pending.\n"
        );
        exit(1);
    }
}

echo "V0.2.13 Craigslist manual verification migration complete.\n";
echo "Verified manual_pending support on inspections and sales posts.\n";
echo "Existing posts and inspections were preserved.\n";
