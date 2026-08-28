<?php
namespace App\Controllers;use App\Core\Controller;use App\Core\Auth;use App\Core\Csrf;use App\Models\User;
class AuthController{
 public function home():void{$u=Auth::user();if(!$u){global$config;header('Location: '.$config['app']['base_path'].'/login');exit;}global$config;header('Location: '.$config['app']['base_path'].($u['role']==='admin'?'/admin':'/sales'));exit;}
 public function login():void{global$config;if(Auth::user()){$this->home();return;}if(!$config['auth']['allow_local_login']){$this->renderView('auth/access_required');return;}$this->renderView('auth/login',['error'=>null]);}
 public function authenticate():void{global$config;if(!$config['auth']['allow_local_login']){http_response_code(403);exit('Local login is disabled.');}Csrf::verify($_POST['_csrf']??null);$u=User::loginRow(trim((string)($_POST['username']??'')));$p=(string)($_POST['password']??'');if(!$u||!$u['password_hash']||!password_verify($p,$u['password_hash'])){$this->renderView('auth/login',['error'=>'Invalid username or password.']);return;}Auth::login($u,'local_login');header('Location: '.$config['app']['base_path'].($u['role']==='admin'?'/admin':'/sales'));exit;}
 public function logout():void{Csrf::verify($_POST['_csrf']??null);Auth::logout();global$config;header('Location: '.$config['app']['base_path'].'/login');exit;}
 private function renderView(string$view,array$data=[]):void{global$config;extract($data,EXTR_SKIP);require dirname(__DIR__).'/Views/layout/header.php';require dirname(__DIR__).'/Views/'.$view.'.php';require dirname(__DIR__).'/Views/layout/footer.php';}
}
