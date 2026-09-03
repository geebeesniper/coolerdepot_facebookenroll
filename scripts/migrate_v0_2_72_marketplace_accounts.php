<?php
/**
 * V0.2.72: persist external marketplace account identity for provider-backed posts.
 * Safe/idempotent: adds nullable columns + lookup index only; no existing data is deleted.
 */
require dirname(__DIR__).'/config/bootstrap.php';

use App\Core\Database;

$pdo=Database::connection();

function cdspColumnExists(PDO $pdo,string $column):bool{
    $s=$pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE()
           AND TABLE_NAME='cdsp_sales_posts'
           AND COLUMN_NAME=?"
    );
    $s->execute([$column]);
    return (int)$s->fetchColumn()>0;
}

function cdspIndexExists(PDO $pdo,string $index):bool{
    $s=$pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA=DATABASE()
           AND TABLE_NAME='cdsp_sales_posts'
           AND INDEX_NAME=?"
    );
    $s->execute([$index]);
    return (int)$s->fetchColumn()>0;
}

$columns=[
    'platform_account_id'=>"VARCHAR(191) NULL AFTER external_post_id",
    'platform_account_name'=>"VARCHAR(255) NULL AFTER platform_account_id",
    'platform_account_url'=>"TEXT NULL AFTER platform_account_name",
    'platform_account_key_hash'=>"CHAR(64) NULL AFTER platform_account_url",
];

foreach($columns as $name=>$definition){
    if(!cdspColumnExists($pdo,$name)){
        $pdo->exec("ALTER TABLE cdsp_sales_posts ADD COLUMN {$name} {$definition}");
    }
}

if(!cdspIndexExists($pdo,'idx_post_platform_account')){
    $pdo->exec(
        'ALTER TABLE cdsp_sales_posts '
        .'ADD KEY idx_post_platform_account(platform,platform_account_key_hash)'
    );
}
