<?php
$config=require __DIR__.'/config.php';
date_default_timezone_set($config['app']['timezone']);
spl_autoload_register(function($class){$prefix='App\\';if(strncmp($class,$prefix,strlen($prefix))!==0)return;$relative=substr($class,strlen($prefix));$file=dirname(__DIR__).'/app/'.str_replace('\\','/',$relative).'.php';if(is_file($file))require$file;});
if($config['app']['enforce_host']&&$config['app']['host']){$requestHost=strtolower(preg_replace('/:\\d+$/','',$_SERVER['HTTP_HOST']??''));if($requestHost!==strtolower($config['app']['host'])){http_response_code(421);exit('Wrong application host.');}}
if(session_status()!==PHP_SESSION_ACTIVE){session_name($config['security']['session_name']);$cookie=['httponly'=>true,'samesite'=>'Lax','secure'=>(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'),'path'=>$config['app']['base_path']?:'/'];if($config['security']['cookie_domain']!=='')$cookie['domain']=$config['security']['cookie_domain'];session_set_cookie_params($cookie);session_start();}
return$config;
