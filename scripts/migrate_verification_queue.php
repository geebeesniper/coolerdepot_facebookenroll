<?php
/** V0.2.95 idempotent verification queue migration. */
$config = require dirname(__DIR__) . '/config/bootstrap.php';
use App\Core\Database;

$sql = (string)file_get_contents(dirname(__DIR__) . '/database/migrations/030_verification_queue.sql');
$pdo = Database::connection();
foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [] as $statement) {
    $statement = trim($statement);
    if ($statement === '' || strncmp($statement, '--', 2) === 0 && strpos($statement, "\n") === false) {
        continue;
    }
    // Strip leading comment-only lines while preserving SQL below them.
    $statement = preg_replace('/^(?:\s*--[^\n]*\n)+/', '', $statement) ?? $statement;
    $statement = trim($statement);
    if ($statement !== '') {
        $pdo->exec($statement);
    }
}
printf("V0.2.95 verification queue schema ready.\n");
