<?php
/**
 * File / 文件：scripts/cleanup_malformed_fetch_jobs.php
 * EN: CLI maintenance/deployment script for cleanup malformed fetch jobs.
 * 中文：用于 cleanup malformed fetch jobs 的命令行维护/部署脚本。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
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
