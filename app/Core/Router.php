<?php
/**
 * File / 文件：app/Core/Router.php
 * EN: Core runtime/infrastructure component used across the application.
 * 中文：该文件是应用全局复用的核心运行时或基础设施组件。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
namespace App\Core;

class Router
{
    private $base;
    private $routes = [];

    /**
     * EN: `__construct` initializes this object and its required dependencies/state.
     * 中文：`__construct` 用于初始化当前对象及其所需依赖与状态。
     */
    public function __construct(string $base = '')
    {
        $this->base = rtrim($base, '/');
    }

    /**
     * EN: Retrieves or loads data for `get` (get).
     * 中文：读取或加载 `get`（get）所需的数据。
     */
    public function get(string $p, array $h): void
    {
        $this->routes['GET'][$this->n($p)] = $h;
    }

    /**
     * EN: Implements the application operation `post` (post).
     * 中文：实现应用操作 `post`（post）。
     */
    public function post(string $p, array $h): void
    {
        $this->routes['POST'][$this->n($p)] = $h;
    }

    /**
     * EN: Handles the workflow/event for `dispatch` (dispatch).
     * 中文：处理 `dispatch`（dispatch）对应的流程或事件。
     */
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

            // ErrorPage records the HTTP status centrally. Logging it here as
            // well would create duplicate 404/405 rows with the same request id.
            if (ErrorPage::isApiRequest()) {
                ErrorPage::renderJson($status);
            }

            ErrorPage::render($status);
        }

        try {
            [$class, $action] = $handler;
            (new $class())->$action();
        } catch (\Throwable $e) {
            Logger::exception(
                $e,
                'router',
                [
                    'event' => 'Route handler failed',
                    'handler_class' => $class ?? null,
                    'handler_action' => $action ?? null,
                ],
                'error'
            );

            if (ErrorPage::isApiRequest()) {
                ErrorPage::renderJson(500);
            }

            ErrorPage::render(500);
        }
    }

    /**
     * EN: Implements the application operation `n` (n).
     * 中文：实现应用操作 `n`（n）。
     */
    private function n(string $p): string
    {
        $p = '/' . trim($p, '/');
        return $p === '//' ? '/' : $p;
    }
}
