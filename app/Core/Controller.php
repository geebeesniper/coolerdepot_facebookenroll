<?php
namespace App\Core;
class Controller {
    protected function render(string $view,array $data=[]):void{
        global $config; extract($data,EXTR_SKIP);
        require dirname(__DIR__).'/Views/layout/header.php';
        require dirname(__DIR__).'/Views/'.$view.'.php';
        require dirname(__DIR__).'/Views/layout/footer.php';
    }
    protected function redirect(string $p):void{
        global $config; header('Location: '.rtrim($config['app']['base_path'],'/').$p); exit;
    }
    protected function json(array $data,int $status=200):void{
        http_response_code($status); header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); exit;
    }
}
