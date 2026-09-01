<?php
/**
 * File / 文件：config/bootstrap.php
 * EN: Application configuration source.
 * 中文：该文件提供应用配置。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
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

if (session_status() !== PHP_SESSION_ACTIVE) {
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
