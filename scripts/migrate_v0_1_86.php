<?php
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
