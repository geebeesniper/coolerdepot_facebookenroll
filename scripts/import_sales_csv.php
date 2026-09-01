<?php
/**
 * File / 文件：scripts/import_sales_csv.php
 * EN: CLI maintenance/deployment script for import sales csv.
 * 中文：用于 import sales csv 的命令行维护/部署脚本。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
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
