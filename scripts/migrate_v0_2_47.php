<?php
/**
 * File / 文件：scripts/migrate_v0_2_47.php
 * EN: Idempotent MySQL migration that adds Admin-managed Sales locations and the user location assignment column.
 * 中文：可重复执行的 MySQL 迁移，用于新增 Admin 管理的 Sales Location 与用户 Location 分配字段。
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    return;
}

require dirname(__DIR__) . '/config/bootstrap.php';

$pdo = \App\Core\Database::connection();
$dbName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS cdsp_locations (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(120) NOT NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY(id),
        UNIQUE KEY uq_locations_name(name),
        KEY idx_locations_active_sort(active,sort_order,name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$columnCheck = $pdo->prepare(
    "SELECT COUNT(*)
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=?
       AND TABLE_NAME='cdsp_users'
       AND COLUMN_NAME='location_id'"
);
$columnCheck->execute([$dbName]);
if ((int)$columnCheck->fetchColumn() === 0) {
    $pdo->exec(
        "ALTER TABLE cdsp_users
         ADD COLUMN location_id INT UNSIGNED NULL AFTER daily_post_target"
    );
}

$indexCheck = $pdo->prepare(
    "SELECT COUNT(*)
     FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA=?
       AND TABLE_NAME='cdsp_users'
       AND INDEX_NAME='idx_users_location'"
);
$indexCheck->execute([$dbName]);
if ((int)$indexCheck->fetchColumn() === 0) {
    $pdo->exec(
        "ALTER TABLE cdsp_users
         ADD KEY idx_users_location(location_id)"
    );
}

fwrite(STDOUT, "V0.2.47 Sales locations ready.\n");
