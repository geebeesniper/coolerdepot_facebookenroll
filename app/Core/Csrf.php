<?php
/**
 * File / 文件：app/Core/Csrf.php
 * EN: Core runtime/infrastructure component used across the application.
 * 中文：该文件是应用全局复用的核心运行时或基础设施组件。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
namespace App\Core;

class Csrf
{
    /**
     * EN: Implements the application operation `token` (token).
     * 中文：实现应用操作 `token`（token）。
     */
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['_csrf'];
    }

    /**
     * EN: Checks or validates the condition represented by `verify` (verify).
     * 中文：检查或校验 `verify`（verify）所表示的条件。
     */
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
