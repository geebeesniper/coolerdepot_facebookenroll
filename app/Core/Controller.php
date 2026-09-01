<?php
/**
 * File / 文件：app/Core/Controller.php
 * EN: Defines the shared Controller core infrastructure component.
 * 中文：定义全应用共享的 Controller 核心基础设施组件。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Core;

/**
 * EN: HTTP controller for application requests, responses, and server-side authorization.
 * 中文：负责 应用 请求、响应及服务器端权限控制的 HTTP Controller。
 */
class Controller
{
    /**
     * EN: Render the render core operation provided by controller.
     * 中文：渲染 controller 提供的“render”核心操作。
     *
     * @param string $view View value used by this operation. / 本操作使用的“view”参数值。
     * @param array $data Structured input data processed by this operation. / 本操作处理的结构化输入数据。
     *
     * @return void No value is returned. / 无返回值。
     */
    protected function render(string $view, array $data = []): void
    {
        global $config;
        extract($data, EXTR_SKIP);
        require dirname(__DIR__) . '/Views/layout/header.php';
        require dirname(__DIR__) . '/Views/' . $view . '.php';
        require dirname(__DIR__) . '/Views/layout/footer.php';
    }

    /**
     * EN: Perform the redirect core operation provided by controller.
     * 中文：执行 controller 提供的“redirect”核心操作。
     *
     * @param string $path Filesystem, route, or data path used by the operation. / 本操作使用的文件、路由或数据路径。
     * @param int $status Status value applied or evaluated by the operation. / 本操作设置或判断的状态值。
     *
     * @return void No value is returned. / 无返回值。
     */
    protected function redirect(string $path, int $status = 302): void
    {
        global $config;
        $location = rtrim($config['app']['base_path'], '/') . $path;

        if (in_array($status, [301, 302], true)) {
            ErrorPage::render($status, null, null, null, $location);
        }

        header('Location: ' . $location, true, $status);
        exit;
    }

    /**
     * Emit a JSON response and centrally record every non-success application
     * response. This catches handled failures that would otherwise disappear
     * because the controller converted an exception into user-facing JSON.
     */
    /**
     * EN: Perform the json core operation provided by controller.
     * 中文：执行 controller 提供的“json”核心操作。
     *
     * @param array $data Structured input data processed by this operation. / 本操作处理的结构化输入数据。
     * @param int $status Status value applied or evaluated by the operation. / 本操作设置或判断的状态值。
     *
     * @return void No value is returned. / 无返回值。
     */
    protected function json(array $data, int $status = 200): void
    {
        $failed = $status >= 400 || (($data['ok'] ?? true) === false);
        if ($failed) {
            Logger::log(
                $status >= 500 ? 'error' : 'warning',
                'JSON response reported failure',
                [
                    'status' => $status,
                    'message' => $data['message'] ?? null,
                    'error' => $data['error'] ?? null,
                    'failure_code' => $data['failure_code'] ?? null,
                ],
                'http'
            );
        }

        if ($status >= 400 && !isset($data['request_id'])) {
            $data['request_id'] = Logger::requestId();
        }

        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        ApiRequest::securityHeaders();
        echo json_encode(
            $data,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_INVALID_UTF8_SUBSTITUTE
        );
        exit;
    }

    /**
     * EN: Render the render partial core operation provided by controller.
     * 中文：渲染 controller 提供的“render partial”核心操作。
     *
     * @param string $view View value used by this operation. / 本操作使用的“view”参数值。
     * @param array $data Structured input data processed by this operation. / 本操作处理的结构化输入数据。
     *
     * @return void No value is returned. / 无返回值。
     */
    protected function renderPartial(string $view, array $data = []): void
    {
        global $config;
        extract($data, EXTR_SKIP);
        require dirname(__DIR__) . '/Views/' . $view . '.php';
    }
}
