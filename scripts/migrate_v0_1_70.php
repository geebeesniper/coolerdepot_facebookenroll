<?php
/**
 * File / 文件：scripts/migrate_v0_1_70.php
 * EN: Operations/deployment/diagnostics script owned by this project.
 * 中文：该文件是本项目自有的运维、部署或诊断脚本。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$config=require dirname(__DIR__).'/config/bootstrap.php';
use App\Core\Database;
$pdo=Database::connection();
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/016_duplicate_comparison.sql');
foreach(preg_split('/;\s*(?:\r?\n|$)/',$sql) as $stmt){if(trim($stmt)!==''){$pdo->exec($stmt);}}
echo "v0.1.70 comparison tables ready. Existing post ID/URL unique constraints are unchanged.\n";
echo "Next: php scripts/index_duplicate_images.php --limit=200\n";
