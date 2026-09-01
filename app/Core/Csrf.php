<?php
/**
 * File / 文件：app/Core/Csrf.php
 * EN: Defines the shared Csrf core infrastructure component.
 * 中文：定义全应用共享的 Csrf 核心基础设施组件。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Core;

/**
 * EN: Core infrastructure component that provides csrf behavior shared across the application.
 * 中文：提供全应用共享 csrf 能力的核心基础设施组件。
 */
class Csrf
{
    /**
     * EN: Return the current CSRF token, creating a cryptographically random token when needed.
     * 中文：返回当前 CSRF Token；如不存在则创建加密安全的随机 Token。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
     */
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['_csrf'];
    }

    /**
     * EN: Verify a submitted CSRF token against the active session token.
     * 中文：将提交的 CSRF Token 与当前 Session Token 进行验证。
     *
     * @param ?string $token Authentication, inspection, or operation token being processed. / 正在处理的认证、检查或操作 Token。
     *
     * @return void No value is returned. / 无返回值。
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
