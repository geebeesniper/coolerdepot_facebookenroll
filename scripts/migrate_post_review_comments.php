<?php
/**
 * File / 文件：scripts/migrate_post_review_comments.php
 * EN: CLI maintenance/deployment script for migrate post review comments.
 * 中文：用于 migrate post review comments 的命令行维护/部署脚本。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
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
