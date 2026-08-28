<?php
namespace App\Core;
class Router {
    private $base; private $routes=[];
    public function __construct(string $base=''){ $this->base=rtrim($base,'/'); }
    public function get(string $p,array $h):void{$this->routes['GET'][$this->n($p)]=$h;}
    public function post(string $p,array $h):void{$this->routes['POST'][$this->n($p)]=$h;}
    public function dispatch(string $m,string $uri):void{
        $p=parse_url($uri,PHP_URL_PATH) ?: '/';
        if($this->base && strpos($p,$this->base)===0) $p=substr($p,strlen($this->base)) ?: '/';
        $h=$this->routes[$m][$this->n($p)]??null;
        if(!$h){http_response_code(404);echo'404 Not Found';return;}
        [$c,$a]=$h;(new $c())->$a();
    }
    private function n(string $p):string{$p='/'.trim($p,'/');return $p==='//'?'/':$p;}
}
