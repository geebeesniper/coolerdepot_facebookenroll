<?php
/**
 * File / 文件：scripts/migrate_post_review_history.php
 * EN: CLI maintenance/deployment script for migrate post review history.
 * 中文：用于 migrate post review history 的命令行维护/部署脚本。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
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

echo "Post review history table is ready." . PHP_EOL;
echo "Existing reviews backfilled: " . (int)$backfilled . PHP_EOL;
