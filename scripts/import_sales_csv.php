<?php
$config=require dirname(__DIR__).'/config/bootstrap.php';
use App\Core\Database;
if($argc<2)exit("Usage: php scripts/import_sales_csv.php /path/to/SALES-LIST.csv\n");
$f=$argv[1];if(!is_file($f))exit("CSV not found.\n");
$h=fopen($f,'rb');fgetcsv($h);
$s=Database::connection()->prepare("INSERT INTO cdsp_users(sales_id,username,password_hash,display_name,role,active,created_at,updated_at)
VALUES(?,?,?,?,'sales',0,NOW(),NOW()) ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),updated_at=NOW()");
$n=0;
while(($r=fgetcsv($h))!==false){
    $id=trim((string)($r[0]??''));$name=trim((string)($r[1]??''));
    if($id===''||$name==='')continue;
    $s->execute([(int)$id,$id,password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT),$name]);
    $n++;
}
fclose($h);
echo "Imported/updated {$n} sales cdsp_users. Accounts remain disabled until password assignment.\n";
