<?php
namespace App\Core;

class Controller
{
    protected function render(string $view, array $data = []): void
    {
        global $config;
        extract($data, EXTR_SKIP);
        require dirname(__DIR__) . '/Views/layout/header.php';
        require dirname(__DIR__) . '/Views/' . $view . '.php';
        require dirname(__DIR__) . '/Views/layout/footer.php';
    }

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

    protected function renderPartial(string $view, array $data = []): void
    {
        global $config;
        extract($data, EXTR_SKIP);
        require dirname(__DIR__) . '/Views/' . $view . '.php';
    }
}
