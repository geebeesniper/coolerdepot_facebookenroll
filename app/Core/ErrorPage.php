<?php
/**
 * File / 文件：app/Core/ErrorPage.php
 * EN: Core runtime/infrastructure component used across the application.
 * 中文：该文件是应用全局复用的核心运行时或基础设施组件。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
namespace App\Core;

class ErrorPage
{
    private const STATUS = [
        301 => ['Moved Permanently', 'This page has moved to a new address.'],
        302 => ['Redirecting', 'You are being redirected to another page.'],
        400 => ['Bad Request', 'The request could not be understood.'],
        401 => ['Sign In Required', 'Please sign in to continue.'],
        403 => ['Access Denied', 'Your account does not have access to this area.'],
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

    /**
     * EN: Builds, formats, or transforms data for `render` (render).
     * 中文：为 `render`（render）构建、格式化或转换数据。
     */
    public static function render(
        int $status,
        ?string $message = null,
        ?string $primaryPath = null,
        ?string $primaryLabel = null,
        ?string $location = null
    ): void {
        global $config;

        $meta = self::STATUS[$status] ?? ['Request Error', 'The request could not be completed.'];
        $title = $meta[0];
        $body = $message ?: $meta[1];
        $base = rtrim((string)($config['app']['base_path'] ?? ''), '/');
        $version = (string)($config['app']['version'] ?? 'dev');

        if ($status >= 400) {
            Logger::httpStatus($status, [
                'event' => 'error_page',
                'message' => $body,
            ]);
        }

        // Status pages always return through the application root.
        // The root route decides whether the logged-in user goes to Admin or Sales dashboard.
        $primaryUrl = self::url($base, '/');

        if ($location !== null && $location !== '') {
            header('Location: ' . $location, true, $status);
        } else {
            http_response_code($status);
        }

        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');

        $safeTitle = self::e($title);
        $safeBody = self::e($body);
        $safePrimaryUrl = self::e($primaryUrl);
        $safeBase = self::e($base);
        $safeLocation = $location ? self::e($location) : '';
        $statusText = self::e((string)$status);
        $isRedirect = in_array($status, [301, 302], true) && $location;

        if ($isRedirect) {
            echo '<!doctype html><html><head><meta charset="utf-8">';
            echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
            echo '<meta http-equiv="refresh" content="1;url=' . $safeLocation . '">';
            echo '<title>' . $statusText . ' ' . $safeTitle . '</title>';
            echo '<link rel="stylesheet" href="' . $safeBase . '/public/assets/app.css?v=' . rawurlencode($version) . '">';
            echo '</head><body class="status-page-body">';
            echo '<main class="status-page"><section class="status-card">';
            echo '<div class="status-code">' . $statusText . '</div>';
            echo '<h1>' . $safeTitle . '</h1><p>' . $safeBody . '</p>';
            echo '<div class="status-actions"><a class="btn primary" href="' . $safeLocation . '">Continue</a></div>';
            echo '</section></main></body></html>';
            exit;
        }

        echo '<!doctype html><html><head><meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>' . $statusText . ' ' . $safeTitle . '</title>';
        echo '<link rel="stylesheet" href="' . $safeBase . '/public/assets/app.css?v=' . rawurlencode($version) . '">';
        echo '</head><body class="status-page-body">';
        echo '<main class="status-page"><section class="status-card">';
        echo '<div class="status-code">' . $statusText . '</div>';
        echo '<h1>' . $safeTitle . '</h1><p>' . $safeBody . '</p>';
        echo '<div class="status-actions">';
        echo '<a class="btn primary" href="' . $safePrimaryUrl . '">Go Back</a>';
        echo '</div>';
        echo '</section></main></body></html>';
        exit;
    }

    /**
     * EN: Builds, formats, or transforms data for `renderJson` (render Json).
     * 中文：为 `renderJson`（render Json）构建、格式化或转换数据。
     */
    public static function renderJson(int $status, ?string $message = null): void
    {
        $meta = self::STATUS[$status] ?? ['Request Error', 'The request could not be completed.'];
        if ($status >= 400) {
            Logger::httpStatus($status, [
                'event' => 'json_error_page',
                'message' => $message ?: $meta[1],
            ]);
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode([
            'ok' => false,
            'status' => $status,
            'error' => $meta[0],
            'message' => $message ?: $meta[1],
            'request_id' => Logger::requestId(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * EN: Checks or validates the condition represented by `isApiRequest` (is Api Request).
     * 中文：检查或校验 `isApiRequest`（is Api Request）所表示的条件。
     */
    public static function isApiRequest(): bool
    {
        global $config;
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = rtrim((string)($config['app']['base_path'] ?? ''), '/');

        if ($base !== '' && strncmp($path, $base, strlen($base)) === 0) {
            $path = substr($path, strlen($base)) ?: '/';
        }

        return strncmp($path, '/api/', 5) === 0;
    }

    /**
     * EN: Implements the application operation `url` (url).
     * 中文：实现应用操作 `url`（url）。
     */
    private static function url(string $base, string $path): string
    {
        if ($path === '') {
            return $base !== '' ? $base : '/';
        }

        if (preg_match('~^https?://~i', $path)) {
            return $path;
        }

        return $base . '/' . ltrim($path, '/');
    }

    /**
     * EN: Implements the application operation `e` (e).
     * 中文：实现应用操作 `e`（e）。
     */
    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
