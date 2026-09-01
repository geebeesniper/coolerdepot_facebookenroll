<?php
/**
 * File / 文件：scripts/migrate_v0_1_86.php
 * EN: CLI maintenance/deployment script for migrate v0 1 86.
 * 中文：用于 migrate v0 1 86 的命令行维护/部署脚本。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
require dirname(__DIR__) . '/config/bootstrap.php';

use App\Core\Database;

$pdo = Database::connection();
$dbName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();

$pdo->exec(
    "ALTER TABLE cdsp_review_attachments
     MODIFY entity_type
     ENUM('post_review','daily_review','period_review','post_note','post_comment')
     NOT NULL"
);
echo "Person Review attachment entity types verified.\n";

$column = $pdo->prepare(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=? AND TABLE_NAME='cdsp_review_attachments' AND COLUMN_NAME='history_id'"
);
$column->execute([$dbName]);
if ((int)$column->fetchColumn() === 0) {
    $pdo->exec(
        "ALTER TABLE cdsp_review_attachments
         ADD COLUMN history_id BIGINT UNSIGNED NULL AFTER entity_id"
    );
    echo "Added cdsp_review_attachments.history_id.\n";
} else {
    echo "cdsp_review_attachments.history_id already exists.\n";
}

$index = $pdo->prepare(
    "SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA=? AND TABLE_NAME='cdsp_review_attachments' AND INDEX_NAME='idx_attachment_history'"
);
$index->execute([$dbName]);
if ((int)$index->fetchColumn() === 0) {
    $pdo->exec(
        "ALTER TABLE cdsp_review_attachments
         ADD INDEX idx_attachment_history(history_id)"
    );
    echo "Added idx_attachment_history.\n";
} else {
    echo "idx_attachment_history already exists.\n";
}

echo "v0.1.86 person-review attachment migration ready.\n";
