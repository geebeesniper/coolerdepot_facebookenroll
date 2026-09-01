<?php
namespace App\Core;

class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['_csrf'];
    }

    public static function verify(?string $token): void
    {
        if (
            !$token
            || empty($_SESSION['_csrf'])
            || !hash_equals((string)$_SESSION['_csrf'], $token)
        ) {
            Logger::warning(
                'CSRF validation failed.',
                ['event' => 'csrf_failed'],
                'security'
            );

            $expectsJson = ErrorPage::isApiRequest()
                || strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
                || stripos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false;

            if ($expectsJson) {
                ErrorPage::renderJson(419, 'CSRF validation failed. Refresh the page and try again.');
            }

            ErrorPage::render(419, 'CSRF validation failed. Refresh the page and try again.');
        }
    }
}
