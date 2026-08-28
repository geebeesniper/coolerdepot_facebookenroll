<?php

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
