<?php
/**
 * File / 文件：scripts/create_user.php
 * EN: CLI maintenance/deployment script for create user.
 * 中文：用于 create user 的命令行维护/部署脚本。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
$config=require dirname(__DIR__).'/config/bootstrap.php';
use App\Core\Database;
if($argc<5){
    echo "Usage: php scripts/create_user.php <role> <username> <display_name> <password> [sales_id]\n";
    exit(1);
}
$role=$argv[1];$username=$argv[2];$display=$argv[3];$password=$argv[4];$salesId=$argv[5]??null;
if(!in_array($role,['sales','admin'],true))exit("Role must be sales or admin.\n");
if($role==='sales'&&!$salesId)exit("Sales requires sales_id.\n");
$s=Database::connection()->prepare("INSERT INTO cdsp_users(sales_id,username,password_hash,display_name,role,active,created_at,updated_at)
VALUES(?,?,?,?,?,1,NOW(),NOW()) ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash),display_name=VALUES(display_name),role=VALUES(role),active=1,updated_at=NOW()");
$s->execute([$role==='sales'?(int)$salesId:null,$username,password_hash($password,PASSWORD_DEFAULT),$display,$role]);
echo "User saved: {$username} ({$role})\n";
