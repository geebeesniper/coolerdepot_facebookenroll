<?php
/**
 * File / 文件：scripts/migrate_post_good_bad.php
 * EN: CLI maintenance/deployment script for migrate post good bad.
 * 中文：用于 migrate post good bad 的命令行维护/部署脚本。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
$config = require dirname(__DIR__) . '/config/bootstrap.php';

use App\Core\Database;

$pdo = Database::connection();

$pdo->beginTransaction();

try {
    $pdo->exec(
        "ALTER TABLE cdsp_sales_posts
         MODIFY admin_review_status
         ENUM('pending','approved','rejected','good','bad')
         NULL DEFAULT NULL"
    );

    $pdo->exec(
        "UPDATE cdsp_sales_posts
         SET admin_review_status = CASE admin_review_status
             WHEN 'approved' THEN 'good'
             WHEN 'rejected' THEN 'bad'
             WHEN 'pending' THEN NULL
             ELSE admin_review_status
         END"
    );

    $pdo->exec(
        "ALTER TABLE cdsp_sales_posts
         MODIFY admin_review_status ENUM('good','bad') NULL DEFAULT NULL"
    );

    $pdo->exec(
        "ALTER TABLE cdsp_post_reviews
         MODIFY decision ENUM('approved','rejected','good','bad') NOT NULL"
    );

    $pdo->exec(
        "UPDATE cdsp_post_reviews
         SET decision = CASE decision
             WHEN 'approved' THEN 'good'
             WHEN 'rejected' THEN 'bad'
             ELSE decision
         END"
    );

    $pdo->exec(
        "ALTER TABLE cdsp_post_reviews
         MODIFY decision ENUM('good','bad') NOT NULL"
    );

    $pdo->commit();
    echo "Post status migration complete: approved/rejected -> good/bad; pending -> NULL.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, "Migration failed: " . $e->getMessage() . "\n");
    exit(1);
}
