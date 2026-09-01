<?php
/**
 * File / 文件：scripts/migrate_comment_attachments.php
 * EN: CLI maintenance/deployment script for migrate comment attachments.
 * 中文：用于 migrate comment attachments 的命令行维护/部署脚本。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
$config = require dirname(__DIR__) . '/config/bootstrap.php';
use App\Core\Database;

$pdo = Database::connection();
$pdo->exec(
    "ALTER TABLE cdsp_review_attachments
     MODIFY entity_type
     ENUM('post_review','daily_review','period_review','post_note','post_comment')
     NOT NULL"
);

$moved = $pdo->exec(
    "UPDATE cdsp_review_attachments a
     JOIN cdsp_post_review_comments c
       ON c.legacy_review_id = a.entity_id
     SET a.entity_type = 'post_comment',
         a.entity_id = c.id
     WHERE a.entity_type = 'post_review'"
);

echo "Comment attachment type enabled." . PHP_EOL;
echo "Legacy review images moved into comment history: " . (int)$moved . PHP_EOL;
