<?php
/**
 * File / 文件：app/Core/Router.php
 * EN: Defines the shared Router core infrastructure component.
 * 中文：定义全应用共享的 Router 核心基础设施组件。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Core;

/**
 * EN: Core infrastructure component that provides router behavior shared across the application.
 * 中文：提供全应用共享 router 能力的核心基础设施组件。
 */
class Router
{
    private $base;
    private $routes = [];

    /**
     * EN: Initialize Router with the dependencies and configuration required by later operations.
     * 中文：初始化 Router，保存后续操作所需的依赖与配置。
     *
     * @param string $base Base URL path removed before route matching. / 路由匹配前需要移除的基础 URL 路径。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function __construct(string $base = '')
    {
        $this->base = rtrim($base, '/');
    }

    /**
     * EN: Register a GET route and its handler in the application router.
     * 中文：在应用路由器中注册 GET 路由及其处理器。
     *
     * @param string $p Route path to register or normalize. / 需要注册或规范化的路由路径。
     * @param array $h Route handler represented as a callable class/action pair. / 以可调用 class/action 组合表示的路由处理器。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function get(string $p, array $h): void
    {
        $this->routes['GET'][$this->n($p)] = $h;
    }

    /**
     * EN: Register a POST route and its handler in the application router.
     * 中文：在应用路由器中注册 POST 路由及其处理器。
     *
     * @param string $p Route path to register or normalize. / 需要注册或规范化的路由路径。
     * @param array $h Route handler represented as a callable class/action pair. / 以可调用 class/action 组合表示的路由处理器。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function post(string $p, array $h): void
    {
        $this->routes['POST'][$this->n($p)] = $h;
    }


    /**
     * EN: Register an OPTIONS route used for CORS preflight handling.
     * 中文：注册用于 CORS 预检处理的 OPTIONS 路由。
     *
     * @param string $p Route path to register or normalize. / 需要注册或规范化的路由路径。
     * @param array $h Route handler represented as a callable class/action pair. / 以可调用 class/action 组合表示的路由处理器。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function options(string $p, array $h): void
    {
        $this->routes['OPTIONS'][$this->n($p)] = $h;
    }

    /**
     * EN: Dispatch an HTTP request to the matching registered route handler.
     * 中文：将 HTTP 请求分发到匹配的已注册路由处理器。
     *
     * @param string $method HTTP or operation method being processed. / 正在处理的 HTTP 或操作方法。
     * @param string $uri Request URI to dispatch or process. / 需要分发或处理的请求 URI。
     *
     * @return void No value is returned. / 无返回值。
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
     * EN: Normalize a route path into the canonical router path format.
     * 中文：将路由路径规范化为路由器使用的标准格式。
     *
     * @param string $p Route path to register or normalize. / 需要注册或规范化的路由路径。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
     */
    private function n(string $p): string
    {
        $p = '/' . trim($p, '/');
        return $p === '//' ? '/' : $p;
    }
}
