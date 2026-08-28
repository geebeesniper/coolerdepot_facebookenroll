<?php
namespace App\Core;
class Csrf {
    public static function token():string{if(empty($_SESSION['_csrf']))$_SESSION['_csrf']=bin2hex(random_bytes(32));return $_SESSION['_csrf'];}
    public static function verify(?string $t):void{
        if(!$t||empty($_SESSION['_csrf'])||!hash_equals($_SESSION['_csrf'],$t)){http_response_code(419);exit('CSRF validation failed');}
    }
}
