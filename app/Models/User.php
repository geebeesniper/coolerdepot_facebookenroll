<?php
namespace App\Models;use App\Core\Database;
class User{
 public static function find(int$id):?array{$s=Database::connection()->prepare("SELECT id,sales_id,external_user_id,username,password_hash,display_name,role,active,auth_source,last_handoff_at FROM users WHERE id=? LIMIT 1");$s->execute([$id]);return$s->fetch()?:null;}
 public static function loginRow(string$username):?array{$s=Database::connection()->prepare("SELECT * FROM users WHERE username=? AND active=1 LIMIT 1");$s->execute([$username]);return$s->fetch()?:null;}
 public static function allSales():array{return Database::connection()->query("SELECT id,sales_id,external_user_id,username,display_name,last_handoff_at FROM users WHERE role='sales' AND active=1 ORDER BY display_name")->fetchAll();}
}
