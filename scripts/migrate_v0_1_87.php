<?php
require dirname(__DIR__) . '/config/bootstrap.php';

use App\Core\Database;

$pdo = Database::connection();
$dbName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();

$columnExists = static function (string $table, string $column) use ($pdo, $dbName): bool {
    $q = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?"
    );
    $q->execute([$dbName, $table, $column]);
    return (int)$q->fetchColumn() > 0;
};

$indexExists = static function (string $table, string $index) use ($pdo, $dbName): bool {
    $q = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME=?"
    );
    $q->execute([$dbName, $table, $index]);
    return (int)$q->fetchColumn() > 0;
};

$constraintExists = static function (string $table, string $constraint) use ($pdo, $dbName): bool {
    $q = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA=? AND TABLE_NAME=? AND CONSTRAINT_NAME=?"
    );
    $q->execute([$dbName, $table, $constraint]);
    return (int)$q->fetchColumn() > 0;
};

if (!$columnExists('cdsp_sales_review_history', 'deleted_at')) {
    $pdo->exec(
        "ALTER TABLE cdsp_sales_review_history
         ADD COLUMN deleted_at DATETIME NULL AFTER created_at"
    );
    echo "Added cdsp_sales_review_history.deleted_at.\n";
} else {
    echo "cdsp_sales_review_history.deleted_at already exists.\n";
}

if (!$columnExists('cdsp_sales_review_history', 'deleted_by')) {
    $pdo->exec(
        "ALTER TABLE cdsp_sales_review_history
         ADD COLUMN deleted_by INT UNSIGNED NULL AFTER deleted_at"
    );
    echo "Added cdsp_sales_review_history.deleted_by.\n";
} else {
    echo "cdsp_sales_review_history.deleted_by already exists.\n";
}

if (!$indexExists('cdsp_sales_review_history', 'idx_sales_review_history_deleted')) {
    $pdo->exec(
        "ALTER TABLE cdsp_sales_review_history
         ADD INDEX idx_sales_review_history_deleted(deleted_at)"
    );
    echo "Added idx_sales_review_history_deleted.\n";
} else {
    echo "idx_sales_review_history_deleted already exists.\n";
}

if (!$constraintExists('cdsp_sales_review_history', 'fk_sales_review_history_deleted_by')) {
    $pdo->exec(
        "ALTER TABLE cdsp_sales_review_history
         ADD CONSTRAINT fk_sales_review_history_deleted_by
         FOREIGN KEY(deleted_by) REFERENCES cdsp_users(id)"
    );
    echo "Added fk_sales_review_history_deleted_by.\n";
} else {
    echo "fk_sales_review_history_deleted_by already exists.\n";
}

// v0.1.86 temporarily soft-deleted Person Review attachments. Person Review
// attachments now use the same permanent-delete behavior as Post Review
// attachments. Remove any already-soft-deleted Person Review attachment rows
// and their files so old "Removed" tombstones do not survive this migration.
$soft = $pdo->query(
    "SELECT id,stored_path
     FROM cdsp_review_attachments
     WHERE entity_type IN ('daily_review','period_review')
       AND deleted_at IS NOT NULL
     ORDER BY id"
)->fetchAll(PDO::FETCH_ASSOC);

$uploadBase = dirname(__DIR__) . '/storage/uploads';
$uploadBaseReal = realpath($uploadBase);
$cleaned = 0;
$skipped = 0;

foreach ($soft as $attachment) {
    $id = (int)$attachment['id'];
    $storedPath = ltrim(str_replace('\\', '/', (string)$attachment['stored_path']), '/');
    $file = $uploadBase . '/' . $storedPath;
    $fileReal = is_file($file) ? realpath($file) : false;

    if ($fileReal !== false) {
        $safe = $uploadBaseReal === false
            || str_starts_with(
                $fileReal,
                rtrim($uploadBaseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            );

        if (!$safe || !@unlink($fileReal)) {
            $skipped++;
            echo "Warning: attachment {$id} file could not be removed from storage; database reference will still be deleted.\n";
        }
    }

    $d = $pdo->prepare("DELETE FROM cdsp_review_attachments WHERE id=?");
    $d->execute([$id]);
    $cleaned += $d->rowCount();
}

echo "Permanently removed {$cleaned} legacy soft-deleted Person Review attachment(s).\n";
if ($skipped > 0) {
    echo "{$skipped} orphan file(s) may remain on disk because storage deletion failed.\n";
}

echo "v0.1.87 Person Review delete controls and permanent attachments ready.\n";
