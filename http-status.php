<?php
/**
 * File / 文件：http-status.php
 * EN: Application PHP entry/helper file for http-status.
 * 中文：用于 http-status 的应用 PHP 入口/辅助文件。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
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
 * EN: Perform the e operation.
 * 中文：执行“e”操作。
 *
 * @param string $value Value processed or stored by this operation. / 本操作处理或保存的值。
 *
 * @return string String result produced by this operation. / 本操作生成的字符串结果。
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
