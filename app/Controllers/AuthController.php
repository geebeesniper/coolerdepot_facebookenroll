<?php
/**
 * File / 文件：app/Controllers/AuthController.php
 * EN: HTTP controller for request validation, orchestration, and responses.
 * 中文：该文件负责 HTTP 请求校验、业务编排与响应。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\ErrorPage;
use App\Core\Logger;
use App\Models\User;

/**
 * Local authentication controller.
 *
 * Production normally authenticates through the signed CoolerDepot handoff.
 * Local login remains an explicit opt-in maintenance path and security-related
 * rejections are logged without ever recording submitted passwords.
 */
class AuthController
{
    /**
     * EN: Implements the application operation `home` (home).
     * 中文：实现应用操作 `home`（home）。
     */
    public function home(): void
    {
        $user = Auth::user();
        global $config;

        if (!$user) {
            header('Location: ' . $config['app']['base_path'] . '/login');
            exit;
        }

        header(
            'Location: '
            . $config['app']['base_path']
            . ($user['role'] === 'admin' ? '/admin' : '/sales')
        );
        exit;
    }

    /**
     * EN: Implements the application operation `login` (login).
     * 中文：实现应用操作 `login`（login）。
     */
    public function login(): void
    {
        global $config;

        if (Auth::user()) {
            $this->home();
            return;
        }

        if (!$config['auth']['allow_local_login']) {
            $this->renderView('auth/access_required');
            return;
        }

        $this->renderView('auth/login', ['error' => null]);
    }

    /**
     * EN: Implements the application operation `authenticate` (authenticate).
     * 中文：实现应用操作 `authenticate`（authenticate）。
     */
    public function authenticate(): void
    {
        global $config;

        if (!$config['auth']['allow_local_login']) {
            Logger::warning(
                'Blocked local-login attempt while local login is disabled.',
                ['event' => 'local_login_disabled'],
                'security'
            );
            ErrorPage::render(403, 'Local login is disabled.');
        }

        Csrf::verify($_POST['_csrf'] ?? null);

        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $user = User::loginRow($username);

        if (
            !$user
            || !$user['password_hash']
            || !password_verify($password, $user['password_hash'])
        ) {
            // Record the rejected account name only; the password is never
            // copied into diagnostics or exception context.
            Logger::warning(
                'Local login rejected.',
                [
                    'event' => 'local_login_rejected',
                    'username' => substr($username, 0, 191),
                ],
                'security'
            );
            $this->renderView(
                'auth/login',
                ['error' => 'Invalid username or password.']
            );
            return;
        }

        Auth::login($user, 'local_login');
        Logger::info(
            'Local login accepted.',
            ['event' => 'local_login_accepted', 'user_id' => (int)$user['id']],
            'auth'
        );

        header(
            'Location: '
            . $config['app']['base_path']
            . ($user['role'] === 'admin' ? '/admin' : '/sales')
        );
        exit;
    }

    /**
     * EN: Implements the application operation `logout` (logout).
     * 中文：实现应用操作 `logout`（logout）。
     */
    public function logout(): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        Auth::logout();

        global $config;
        header('Location: ' . $config['app']['base_path'] . '/login');
        exit;
    }

    /**
     * EN: Builds, formats, or transforms data for `renderView` (render View).
     * 中文：为 `renderView`（render View）构建、格式化或转换数据。
     */
    private function renderView(string $view, array $data = []): void
    {
        global $config;
        extract($data, EXTR_SKIP);
        require dirname(__DIR__) . '/Views/layout/header.php';
        require dirname(__DIR__) . '/Views/' . $view . '.php';
        require dirname(__DIR__) . '/Views/layout/footer.php';
    }
}
