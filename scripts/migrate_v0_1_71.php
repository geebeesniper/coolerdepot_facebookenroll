<?php
/**
 * File / 文件：scripts/migrate_v0_1_71.php
 * EN: Operations/deployment/diagnostics script owned by this project.
 * 中文：该文件是本项目自有的运维、部署或诊断脚本。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
require dirname(__DIR__).'/config/bootstrap.php';

use App\Core\Database;
use App\Models\Post;

$pdo=Database::connection();
$column=$pdo->query("SHOW COLUMNS FROM cdsp_website_references LIKE 'description'")->fetch();
if(!$column){
    $pdo->exec("ALTER TABLE cdsp_website_references ADD COLUMN description MEDIUMTEXT NULL AFTER title");
    echo "Added cdsp_website_references.description.\n";
}else{
    echo "cdsp_website_references.description already exists.\n";
}
$legacyDeleted=$pdo->query("SELECT id FROM cdsp_sales_posts WHERE deleted_at IS NOT NULL ORDER BY id")->fetchAll(\PDO::FETCH_COLUMN);
$purged=0;
foreach($legacyDeleted as $postId){
    Post::hardDelete((int)$postId);
    $purged++;
}
echo "Purged {$purged} legacy soft-deleted post(s).\n";
echo "v0.1.71 website reference library and hard-delete cleanup ready.\n";
