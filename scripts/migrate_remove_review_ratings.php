<?php
/**
 * File / 文件：scripts/migrate_remove_review_ratings.php
 * EN: CLI maintenance/deployment script for migrate remove review ratings.
 * 中文：用于 migrate remove review ratings 的命令行维护/部署脚本。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
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
