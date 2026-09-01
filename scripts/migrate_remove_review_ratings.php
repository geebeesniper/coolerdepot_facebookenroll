<?php
/**
 * File / 文件：scripts/migrate_remove_review_ratings.php
 * EN: Operations/deployment/diagnostics script owned by this project.
 * 中文：该文件是本项目自有的运维、部署或诊断脚本。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
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
