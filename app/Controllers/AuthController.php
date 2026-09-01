<?php
/**
 * File / 文件：app/Controllers/AuthController.php
 * EN: Defines the AuthController HTTP controller and request/response actions.
 * 中文：定义 AuthController HTTP Controller 及其请求/响应操作。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\ErrorPage;
use App\Core\Logger;
use App\Models\User;

/**
 * EN: HTTP controller for auth requests, responses, and server-side authorization.
 * 中文：负责 auth 请求、响应及服务器端权限控制的 HTTP Controller。
 */
class AuthController
{
    /**
     * EN: Handle the home HTTP action for auth controller and return the appropriate response.
     * 中文：处理 auth controller 的“home”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
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
     * EN: Handle the login HTTP action for auth controller and return the appropriate response.
     * 中文：处理 auth controller 的“login”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
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
     * EN: Handle the authenticate HTTP action for auth controller and return the appropriate response.
     * 中文：处理 auth controller 的“authenticate”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
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
     * EN: Handle the logout HTTP action for auth controller and return the appropriate response.
     * 中文：处理 auth controller 的“logout”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
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
     * EN: Render the render view operation.
     * 中文：渲染“render view”操作。
     *
     * @param string $view View value used by this operation. / 本操作使用的“view”参数值。
     * @param array $data Structured input data processed by this operation. / 本操作处理的结构化输入数据。
     *
     * @return void No value is returned. / 无返回值。
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
