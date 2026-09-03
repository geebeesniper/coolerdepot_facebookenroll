<?php
/**
 * File / 文件：config/bootstrap.php
 * EN: Application configuration/bootstrap file for bootstrap.
 * 中文：用于 bootstrap 的应用配置/启动文件。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
$config = require __DIR__ . '/config.php';

date_default_timezone_set($config['app']['timezone']);

spl_autoload_register(function ($class) {
    $prefix = 'App\\';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = dirname(__DIR__) . '/app/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

/*
 * Diagnostics is initialized before database/session work so bootstrap, PDO,
 * routing and shutdown failures all receive the same correlation id.
 */
\App\Core\Logger::init($config);

set_exception_handler(function (\Throwable $e) {
    \App\Core\Logger::exception(
        $e,
        'uncaught',
        ['event' => 'Uncaught exception'],
        'critical'
    );

    if (class_exists(\App\Core\ErrorPage::class)) {
        if (\App\Core\ErrorPage::isApiRequest()) {
            \App\Core\ErrorPage::renderJson(500);
        }
        \App\Core\ErrorPage::render(500);
    }

    http_response_code(500);
    echo 'Server Error';
    exit;
});

if ($config['app']['enforce_host'] && $config['app']['host']) {
    $requestHost = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));

    if ($requestHost !== strtolower($config['app']['host'])) {
        \App\Core\Logger::warning(
            'Request rejected because the host did not match APP_HOST.',
            ['request_host' => $requestHost],
            'security'
        );
        \App\Core\ErrorPage::render(421);
    }
}

/*
 * Only the external integration API is stateless.
 * Browser-owned endpoints under /api/client-log and /api/inspect still use
 * the normal browser session for Auth + CSRF and therefore must start PHP
 * session state. ErrorPage::isApiRequest() intentionally remains broader so
 * those endpoints still receive JSON-formatted HTTP errors.
 *
 * 只有对外集成 API 使用无 Session 模式。浏览器自己的 /api/client-log
 * 与 /api/inspect 仍依赖 Auth + CSRF，因此必须启动 PHP Session。
 * ErrorPage::isApiRequest() 继续保持更宽的判断范围，以确保这些接口的错误
 * 仍然返回 JSON。
 */
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$basePath = rtrim((string)($config['app']['base_path'] ?? ''), '/');

if ($basePath !== '' && strncmp($requestPath, $basePath, strlen($basePath)) === 0) {
    $requestPath = substr($requestPath, strlen($basePath)) ?: '/';
}

$isStatelessApiRequest = PHP_SAPI !== 'cli' && (
    strncmp($requestPath, '/api/v1/', 8) === 0
    || $requestPath === '/graphql'
);

if (!$isStatelessApiRequest && session_status() !== PHP_SESSION_ACTIVE) {
    session_name($config['security']['session_name']);
    $cookie = [
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'path' => $config['app']['base_path'] ?: '/',
    ];

    if ($config['security']['cookie_domain'] !== '') {
        $cookie['domain'] = $config['security']['cookie_domain'];
    }

    session_set_cookie_params($cookie);
    session_start();
}


/*
 * Direct-overlay compatibility / 直接覆盖升级兼容：
 * A V0.2.07-V0.2.12 database lacks the V0.2.13 manual_pending ENUM value.
 * The check is idempotent and only expands that ENUM on the first request.
 * V0.2.07-V0.2.12 数据库缺少 V0.2.13 的 manual_pending ENUM；
 * 此检查可重复执行，仅在首次需要时扩展该 ENUM，不删除或重写业务数据。
 */
\App\Core\SchemaCompatibility::ensureDirectOverlayCompatibility();

return $config;
