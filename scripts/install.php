<?php
/**
 * File / 文件：scripts/install.php
 * EN: Operations/deployment/diagnostics script owned by this project.
 * 中文：该文件是本项目自有的运维、部署或诊断脚本。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
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
