<?php
/**
 * File / 文件：scripts/cleanup_malformed_fetch_jobs.php
 * EN: Operations/deployment/diagnostics script owned by this project.
 * 中文：该文件是本项目自有的运维、部署或诊断脚本。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
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
