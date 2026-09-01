<?php
/**
 * File / 文件：scripts/migrate_v0_1_70.php
 * EN: CLI maintenance/deployment script for migrate v0 1 70.
 * 中文：用于 migrate v0 1 70 的命令行维护/部署脚本。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$config=require dirname(__DIR__).'/config/bootstrap.php';
use App\Core\Database;
$pdo=Database::connection();
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/016_duplicate_comparison.sql');
foreach(preg_split('/;\s*(?:\r?\n|$)/',$sql) as $stmt){if(trim($stmt)!==''){$pdo->exec($stmt);}}
echo "v0.1.70 comparison tables ready. Existing post ID/URL unique constraints are unchanged.\n";
echo "Next: php scripts/index_duplicate_images.php --limit=200\n";
