<?php
$config=require dirname(__DIR__).'/config/bootstrap.php';
use App\Core\Database;
$sql=file_get_contents(dirname(__DIR__).'/database/schema.sql');
$pdo=Database::connection();
foreach(preg_split('/;\s*(?:\r?\n|$)/',$sql) as $stmt){
    $stmt=trim($stmt);
    if($stmt!=='')$pdo->exec($stmt);
}
echo "Database schema installed.\n";
