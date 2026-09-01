<?php
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

    public function logout(): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        Auth::logout();

        global $config;
        header('Location: ' . $config['app']['base_path'] . '/login');
        exit;
    }

    private function renderView(string $view, array $data = []): void
    {
        global $config;
        extract($data, EXTR_SKIP);
        require dirname(__DIR__) . '/Views/layout/header.php';
        require dirname(__DIR__) . '/Views/' . $view . '.php';
        require dirname(__DIR__) . '/Views/layout/footer.php';
    }
}
