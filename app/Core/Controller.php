<?php
/**
 * File / 文件：app/Core/Controller.php
 * EN: Core runtime/infrastructure component used across the application.
 * 中文：该文件是应用全局复用的核心运行时或基础设施组件。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
namespace App\Core;

class Controller
{
    /**
     * EN: Builds, formats, or transforms data for `render` (render).
     * 中文：为 `render`（render）构建、格式化或转换数据。
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
     * EN: Implements the application operation `redirect` (redirect).
     * 中文：实现应用操作 `redirect`（redirect）。
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
     * EN: Implements the application operation `json` (json).
     * 中文：实现应用操作 `json`（json）。
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
        header('Cache-Control: no-store');
        echo json_encode(
            $data,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_INVALID_UTF8_SUBSTITUTE
        );
        exit;
    }

    /**
     * EN: Builds, formats, or transforms data for `renderPartial` (render Partial).
     * 中文：为 `renderPartial`（render Partial）构建、格式化或转换数据。
     */
    protected function renderPartial(string $view, array $data = []): void
    {
        global $config;
        extract($data, EXTR_SKIP);
        require dirname(__DIR__) . '/Views/' . $view . '.php';
    }
}
