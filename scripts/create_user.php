<?php
/**
 * File / 文件：scripts/create_user.php
 * EN: Operations/deployment/diagnostics script owned by this project.
 * 中文：该文件是本项目自有的运维、部署或诊断脚本。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
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
