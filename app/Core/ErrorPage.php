<?php
/**
 * File / 文件：app/Core/ErrorPage.php
 * EN: Defines the shared ErrorPage core infrastructure component.
 * 中文：定义全应用共享的 ErrorPage 核心基础设施组件。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Core;

/**
 * EN: Core infrastructure component that provides error page behavior shared across the application.
 * 中文：提供全应用共享 error page 能力的核心基础设施组件。
 */
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
     * EN: Render the render core operation provided by error page.
     * 中文：渲染 error page 提供的“render”核心操作。
     *
     * @param int $status Status value applied or evaluated by the operation. / 本操作设置或判断的状态值。
     * @param ?string $message Human-readable message associated with the result or log entry. / 与结果或日志记录关联的可读消息。
     * @param ?string $primaryPath Primary path value used by this operation. / 本操作使用的“primary path”参数值。
     * @param ?string $primaryLabel Primary label value used by this operation. / 本操作使用的“primary label”参数值。
     * @param ?string $location Location value used by this operation. / 本操作使用的“location”参数值。
     *
     * @return void No value is returned. / 无返回值。
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
     * EN: Render the render json core operation provided by error page.
     * 中文：渲染 error page 提供的“render json”核心操作。
     *
     * @param int $status Status value applied or evaluated by the operation. / 本操作设置或判断的状态值。
     * @param ?string $message Human-readable message associated with the result or log entry. / 与结果或日志记录关联的可读消息。
     *
     * @return void No value is returned. / 无返回值。
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
        ApiRequest::securityHeaders();
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
     * EN: Check or validate the is api request core operation provided by error page.
     * 中文：检查或验证 error page 提供的“is api request”核心操作。
     *
     * @return bool True when the requested condition is satisfied; otherwise false. / 请求条件满足时返回 true，否则返回 false。
     */
    public static function isApiRequest(): bool
    {
        global $config;
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = rtrim((string)($config['app']['base_path'] ?? ''), '/');

        if ($base !== '' && strncmp($path, $base, strlen($base)) === 0) {
            $path = substr($path, strlen($base)) ?: '/';
        }

        return strncmp($path, '/api/', 5) === 0 || $path === '/graphql';
    }

    /**
     * EN: Perform the url core operation provided by error page.
     * 中文：执行 error page 提供的“url”核心操作。
     *
     * @param string $base Base URL path removed before route matching. / 路由匹配前需要移除的基础 URL 路径。
     * @param string $path Filesystem, route, or data path used by the operation. / 本操作使用的文件、路由或数据路径。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
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
     * EN: Perform the e core operation provided by error page.
     * 中文：执行 error page 提供的“e”核心操作。
     *
     * @param string $value Value processed or stored by this operation. / 本操作处理或保存的值。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
     */
    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
