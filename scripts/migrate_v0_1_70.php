<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$config=require dirname(__DIR__).'/config/bootstrap.php';
use App\Core\Database;
$pdo=Database::connection();
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/016_duplicate_comparison.sql');
foreach(preg_split('/;\s*(?:\r?\n|$)/',$sql) as $stmt){if(trim($stmt)!==''){$pdo->exec($stmt);}}
echo "v0.1.70 comparison tables ready. Existing post ID/URL unique constraints are unchanged.\n";
echo "Next: php scripts/index_duplicate_images.php --limit=200\n";
