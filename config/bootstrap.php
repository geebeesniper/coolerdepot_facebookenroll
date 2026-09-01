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

$isStatelessApiRequest = PHP_SAPI !== 'cli' && \App\Core\ErrorPage::isApiRequest();

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

return $config;
