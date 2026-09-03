<?php
/**
 * File / 文件：scripts/migrate_v0_2_48.php
 * EN: Idempotent migration that adds the durable "paused" website-scan status.
 * 中文：可重复执行的迁移，为网站扫描任务增加可持久化的 paused 状态。
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    return;
}

require dirname(__DIR__) . '/config/bootstrap.php';

$pdo = \App\Core\Database::connection();
$dbName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();

$tableCheck = $pdo->prepare(
    "SELECT COUNT(*)
     FROM information_schema.TABLES
     WHERE TABLE_SCHEMA=? AND TABLE_NAME='cdsp_website_scan_jobs'"
);
$tableCheck->execute([$dbName]);

if ((int)$tableCheck->fetchColumn() > 0) {
    $column = $pdo->query("SHOW COLUMNS FROM cdsp_website_scan_jobs LIKE 'status'")->fetch();
    $type = strtolower((string)($column['Type'] ?? ''));
    if ($type !== '' && strpos($type, "'paused'") === false) {
        $pdo->exec(
            "ALTER TABLE cdsp_website_scan_jobs
             MODIFY COLUMN status ENUM('running','completed','paused','stopped','failed')
             NOT NULL DEFAULT 'running'"
        );
    }
}

fwrite(STDOUT, "V0.2.48 website scan pause state ready.\n");
