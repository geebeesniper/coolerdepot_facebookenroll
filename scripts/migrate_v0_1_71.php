<?php
/**
 * File / 文件：scripts/migrate_v0_1_71.php
 * EN: CLI maintenance/deployment script for migrate v0 1 71.
 * 中文：用于 migrate v0 1 71 的命令行维护/部署脚本。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
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
