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

    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function renderPartial(string $view, array $data = []): void
    {
        global $config;
        extract($data, EXTR_SKIP);
        require dirname(__DIR__) . '/Views/' . $view . '.php';
    }
}
