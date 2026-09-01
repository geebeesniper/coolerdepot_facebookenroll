<?php
/**
 * File / 文件：scripts/migrate_comment_attachments.php
 * EN: Operations/deployment/diagnostics script owned by this project.
 * 中文：该文件是本项目自有的运维、部署或诊断脚本。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
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
