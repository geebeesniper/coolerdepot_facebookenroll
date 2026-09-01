<?php
/**
 * File / 文件：scripts/migrate_v0_1_72.php
 * EN: Operations/deployment/diagnostics script owned by this project.
 * 中文：该文件是本项目自有的运维、部署或诊断脚本。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
require dirname(__DIR__).'/config/bootstrap.php';

use App\Core\Database;

$pdo = Database::connection();

$column = $pdo->query("SHOW COLUMNS FROM cdsp_sales_posts LIKE 'admin_review_status'")->fetch(PDO::FETCH_ASSOC);
if (!$column) {
    throw new RuntimeException('cdsp_sales_posts.admin_review_status does not exist. Run the base schema migrations first.');
}

// Some older production databases still have admin_review_status as NOT NULL
// and/or the old pending/approved/rejected enum. Normalize it safely before
// narrowing to the current good/bad nullable model.
$pdo->exec(
    "ALTER TABLE cdsp_sales_posts
     MODIFY admin_review_status
     ENUM('pending','approved','rejected','good','bad') NULL DEFAULT NULL"
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

$verified = $pdo->query("SHOW COLUMNS FROM cdsp_sales_posts LIKE 'admin_review_status'")->fetch(PDO::FETCH_ASSOC);
if (!$verified || strtoupper((string)($verified['Null'] ?? '')) !== 'YES') {
    throw new RuntimeException('admin_review_status is still NOT NULL after migration.');
}

echo "cdsp_sales_posts.admin_review_status is nullable and normalized to good/bad.\n";
echo "v0.1.72 save compatibility migration ready.\n";
