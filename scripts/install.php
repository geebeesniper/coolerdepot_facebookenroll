<?php
/**
 * File / 文件：scripts/install.php
 * EN: CLI maintenance/deployment script for install.
 * 中文：用于 install 的命令行维护/部署脚本。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
$config=require dirname(__DIR__).'/config/bootstrap.php';
use App\Core\Database;
$sql=file_get_contents(dirname(__DIR__).'/database/schema.sql');
$pdo=Database::connection();
foreach(preg_split('/;\s*(?:\r?\n|$)/',$sql) as $stmt){
    $stmt=trim($stmt);
    if($stmt!=='')$pdo->exec($stmt);
}
echo "Database schema installed.\n";
