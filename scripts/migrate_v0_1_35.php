<?php

$config = require dirname(__DIR__) . '/config/bootstrap.php';

use App\Core\Database;

$pdo = Database::connection();

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS cdsp_post_review_history (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      post_id BIGINT UNSIGNED NOT NULL,
      admin_user_id INT UNSIGNED NOT NULL,
      decision ENUM('good','bad') NOT NULL,
      legacy_review_id BIGINT UNSIGNED NULL,
      created_at DATETIME NOT NULL,
      PRIMARY KEY(id),
      UNIQUE KEY uq_review_history_legacy(legacy_review_id),
      KEY idx_review_history_post(post_id,created_at,id),
      CONSTRAINT fk_review_history_post
        FOREIGN KEY(post_id) REFERENCES cdsp_sales_posts(id),
      CONSTRAINT fk_review_history_admin
        FOREIGN KEY(admin_user_id) REFERENCES cdsp_users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$backfilled = $pdo->exec(
    "INSERT IGNORE INTO cdsp_post_review_history(
       post_id,
       admin_user_id,
       decision,
       legacy_review_id,
       created_at
     )
     SELECT
       post_id,
       admin_user_id,
       decision,
       id,
       COALESCE(reviewed_at,updated_at,created_at,NOW())
     FROM cdsp_post_reviews
     WHERE decision IN ('good','bad')"
);

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $s = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE()
           AND TABLE_NAME=?
           AND COLUMN_NAME=?"
    );
    $s->execute([$table,$column]);

    return (int)$s->fetchColumn() > 0;
}

function indexExists(PDO $pdo, string $table, string $index): bool
{
    $s = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA=DATABASE()
           AND TABLE_NAME=?
           AND INDEX_NAME=?"
    );
    $s->execute([$table,$index]);

    return (int)$s->fetchColumn() > 0;
}

function fkExists(PDO $pdo, string $table, string $constraint): bool
{
    $s = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA=DATABASE()
           AND TABLE_NAME=?
           AND CONSTRAINT_NAME=?
           AND CONSTRAINT_TYPE='FOREIGN KEY'"
    );
    $s->execute([$table,$constraint]);

    return (int)$s->fetchColumn() > 0;
}

if (!columnExists($pdo,'cdsp_review_attachments','deleted_at')) {
    $pdo->exec(
        "ALTER TABLE cdsp_review_attachments
         ADD COLUMN deleted_at DATETIME NULL AFTER created_at"
    );
    echo "Added attachment deleted_at." . PHP_EOL;
}

if (!columnExists($pdo,'cdsp_review_attachments','deleted_by')) {
    $pdo->exec(
        "ALTER TABLE cdsp_review_attachments
         ADD COLUMN deleted_by INT UNSIGNED NULL AFTER deleted_at"
    );
    echo "Added attachment deleted_by." . PHP_EOL;
}

if (!indexExists($pdo,'cdsp_review_attachments','idx_attachment_deleted')) {
    $pdo->exec(
        "ALTER TABLE cdsp_review_attachments
         ADD KEY idx_attachment_deleted(deleted_at)"
    );
}

if (!fkExists($pdo,'cdsp_review_attachments','fk_attachment_deleted_by')) {
    $pdo->exec(
        "ALTER TABLE cdsp_review_attachments
         ADD CONSTRAINT fk_attachment_deleted_by
         FOREIGN KEY(deleted_by) REFERENCES cdsp_users(id)"
    );
}

echo "Review history is ready." . PHP_EOL;
echo "Existing reviews backfilled: " . (int)$backfilled . PHP_EOL;
echo "Attachment soft-delete audit is ready." . PHP_EOL;
echo "v0.1.35 migration complete." . PHP_EOL;
