<?php
/** V0.2.54: allow different Sales users to save the same marketplace URL/item ID. */
require dirname(__DIR__).'/config/bootstrap.php';

use App\Core\Database;

$pdo = Database::connection();

function indexExists(PDO $pdo, string $name): bool {
    $s=$pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cdsp_sales_posts' AND INDEX_NAME=?");
    $s->execute([$name]);
    return (int)$s->fetchColumn() > 0;
}

if (indexExists($pdo,'uq_post_canonical')) {
    $pdo->exec('ALTER TABLE cdsp_sales_posts DROP INDEX uq_post_canonical');
}
if (indexExists($pdo,'uq_post_external')) {
    $pdo->exec('ALTER TABLE cdsp_sales_posts DROP INDEX uq_post_external');
}
if (!indexExists($pdo,'idx_post_canonical_hash')) {
    $pdo->exec('ALTER TABLE cdsp_sales_posts ADD KEY idx_post_canonical_hash(canonical_url_hash)');
}
if (!indexExists($pdo,'idx_post_external')) {
    $pdo->exec('ALTER TABLE cdsp_sales_posts ADD KEY idx_post_external(platform,external_post_id)');
}
