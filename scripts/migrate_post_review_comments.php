<?php
/**
 * File / 文件：scripts/migrate_post_review_comments.php
 * EN: Operations/deployment/diagnostics script owned by this project.
 * 中文：该文件是本项目自有的运维、部署或诊断脚本。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
$config = require dirname(__DIR__) . '/config/bootstrap.php';

use App\Core\Database;

$pdo = Database::connection();

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS cdsp_post_review_comments (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      post_id BIGINT UNSIGNED NOT NULL,
      admin_user_id INT UNSIGNED NOT NULL,
      body_html MEDIUMTEXT NOT NULL,
      legacy_review_id BIGINT UNSIGNED NULL,
      updated_by INT UNSIGNED NULL,
      deleted_by INT UNSIGNED NULL,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      deleted_at DATETIME NULL,
      PRIMARY KEY(id),
      UNIQUE KEY uq_post_comment_legacy(legacy_review_id),
      KEY idx_post_comment_post(post_id,deleted_at,created_at),
      CONSTRAINT fk_post_comment_post
        FOREIGN KEY(post_id) REFERENCES cdsp_sales_posts(id),
      CONSTRAINT fk_post_comment_admin
        FOREIGN KEY(admin_user_id) REFERENCES cdsp_users(id),
      CONSTRAINT fk_post_comment_updated_by
        FOREIGN KEY(updated_by) REFERENCES cdsp_users(id),
      CONSTRAINT fk_post_comment_deleted_by
        FOREIGN KEY(deleted_by) REFERENCES cdsp_users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$migrated = $pdo->exec(
    "INSERT IGNORE INTO cdsp_post_review_comments(
       post_id,
       admin_user_id,
       body_html,
       legacy_review_id,
       created_at,
       updated_at
     )
     SELECT
       post_id,
       admin_user_id,
       note,
       id,
       COALESCE(created_at, reviewed_at, NOW()),
       COALESCE(updated_at, reviewed_at, NOW())
     FROM cdsp_post_reviews
     WHERE note IS NOT NULL
       AND TRIM(note) <> ''"
);

echo "Comment history table is ready." . PHP_EOL;
echo "Legacy review notes migrated: " . (int)$migrated . PHP_EOL;
