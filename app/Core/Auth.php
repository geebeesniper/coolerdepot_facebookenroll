<?php
namespace App\Core;
class Auth{
 public static function user():?array{
  $raw=$_SESSION['auth_db_token']??'';if(!is_string($raw)||$raw==='')return null;
  $s=Database::connection()->prepare("SELECT u.id,u.sales_id,u.external_user_id,u.username,u.display_name,u.role,u.active,u.auth_source,s.id auth_session_id,s.expires_at FROM auth_sessions s JOIN users u ON u.id=s.user_id WHERE s.token_hash=? AND s.revoked_at IS NULL AND s.expires_at>NOW() AND u.active=1 LIMIT 1");$s->execute([hash('sha256',$raw)]);$u=$s->fetch();if(!$u){unset($_SESSION['auth_db_token']);return null;}
  $t=Database::connection()->prepare("UPDATE auth_sessions SET last_seen_at=NOW() WHERE id=?");$t->execute([(int)$u['auth_session_id']]);return$u;
 }
 public static function login(array $u,string $source='handoff'):void{global$config;session_regenerate_id(true);$raw=bin2hex(random_bytes(32));$s=Database::connection()->prepare("INSERT INTO auth_sessions(user_id,token_hash,source,ip_address,user_agent,created_at,last_seen_at,expires_at) VALUES(?,?,?,?,?,NOW(),NOW(),DATE_ADD(NOW(),INTERVAL ? HOUR))");$s->execute([(int)$u['id'],hash('sha256',$raw),$source,substr((string)($_SERVER['REMOTE_ADDR']??''),0,45),substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500),max(1,(int)$config['auth']['session_hours'])]);$_SESSION['auth_db_token']=$raw;}
 public static function logout():void{$raw=$_SESSION['auth_db_token']??'';if(is_string($raw)&&$raw!==''){$s=Database::connection()->prepare("UPDATE auth_sessions SET revoked_at=NOW() WHERE token_hash=? AND revoked_at IS NULL");$s->execute([hash('sha256',$raw)]);}$_SESSION=[];if(session_status()===PHP_SESSION_ACTIVE)session_destroy();}
 public static function requireLogin():array{$u=self::user();if(!$u){global$config;header('Location: '.$config['app']['base_path'].'/login');exit;}return$u;}
 public static function requireRole(string $r):array{$u=self::requireLogin();if(($u['role']??'')!==$r){http_response_code(403);exit('Forbidden');}return$u;}
}
