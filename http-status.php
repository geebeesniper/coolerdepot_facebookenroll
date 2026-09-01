<?php
/**
 * File / 文件：http-status.php
 * EN: Application HTTP entry/endpoint source.
 * 中文：该文件是应用 HTTP 入口或端点。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
$config = require __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/Core/Logger.php';
\App\Core\Logger::init($config);

$allowed = [
    400 => ['Bad Request', 'The request could not be understood.'],
    403 => ['Access Denied', 'You do not have permission to access this resource.'],
    404 => ['Page Not Found', 'The page you requested could not be found.'],
    405 => ['Method Not Allowed', 'This action is not available for this request method.'],
    408 => ['Request Timeout', 'The request took too long to complete.'],
    419 => ['Session Validation Failed', 'Your page security token is no longer valid. Refresh the page and try again.'],
    421 => ['Wrong Host', 'This application is not available on this host name.'],
    429 => ['Too Many Requests', 'Too many requests were received. Please try again shortly.'],
    500 => ['Server Error', 'Something went wrong while processing your request.'],
    502 => ['Provider Error', 'An upstream service returned an invalid response.'],
    503 => ['Temporarily Unavailable', 'This service is temporarily unavailable. Please try again.'],
];

$redirectStatus = (int)($_SERVER['REDIRECT_STATUS'] ?? 0);
$queryStatus = (int)($_GET['code'] ?? 0);
$status = isset($allowed[$redirectStatus])
    ? $redirectStatus
    : (isset($allowed[$queryStatus]) ? $queryStatus : 500);

[$title, $message] = $allowed[$status];
\App\Core\Logger::httpStatus($status, ['event' => 'apache_error_document']);

http_response_code($status);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

$base = rtrim((string)($config['app']['base_path'] ?? '/sales-posts'), '/');
$version = (string)($config['app']['version'] ?? 'dev');

/**
 * EN: Implements the application operation `e` (e).
 * 中文：实现应用操作 `e`（e）。
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$home = $base !== '' ? $base . '/' : '/';
$css = ($base !== '' ? $base : '') . '/public/assets/app.css?v=' . rawurlencode($version);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= (int)$status ?> <?= e($title) ?></title>
    <link rel="stylesheet" href="<?= e($css) ?>">
</head>
<body class="status-page-body">
<main class="status-page">
    <section class="status-card">
        <div class="status-code"><?= (int)$status ?></div>
        <h1><?= e($title) ?></h1>
        <p><?= e($message) ?></p>

        <div class="status-actions">
            <a class="btn primary" href="<?= e($home) ?>">Go Back</a>
        </div>
    </section>
</main>
</body>
</html>
