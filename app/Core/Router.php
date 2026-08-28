<?php
namespace App\Core;

class Router
{
    private $base;
    private $routes = [];

    public function __construct(string $base = '')
    {
        $this->base = rtrim($base, '/');
    }

    public function get(string $p, array $h): void
    {
        $this->routes['GET'][$this->n($p)] = $h;
    }

    public function post(string $p, array $h): void
    {
        $this->routes['POST'][$this->n($p)] = $h;
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        if ($this->base && strpos($path, $this->base) === 0) {
            $path = substr($path, strlen($this->base)) ?: '/';
        }

        $path = $this->n($path);
        $lookupMethod = strtoupper($method) === 'HEAD' ? 'GET' : strtoupper($method);
        $handler = $this->routes[$lookupMethod][$path] ?? null;

        if (!$handler) {
            $pathExists = false;

            foreach ($this->routes as $routes) {
                if (isset($routes[$path])) {
                    $pathExists = true;
                    break;
                }
            }

            $status = $pathExists ? 405 : 404;

            if (ErrorPage::isApiRequest()) {
                ErrorPage::renderJson($status);
            }

            ErrorPage::render($status);
        }

        try {
            [$class, $action] = $handler;
            (new $class())->$action();
        } catch (\Throwable $e) {
            error_log(sprintf(
                '[CDSP] Unhandled exception %s: %s in %s:%d',
                get_class($e),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));

            if (ErrorPage::isApiRequest()) {
                ErrorPage::renderJson(500);
            }

            ErrorPage::render(500);
        }
    }

    private function n(string $p): string
    {
        $p = '/' . trim($p, '/');
        return $p === '//' ? '/' : $p;
    }
}
