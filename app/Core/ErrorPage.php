<?php
namespace App\Core;

class ErrorPage
{
    private const STATUS = [
        301 => ['Moved Permanently', 'This page has moved to a new address.'],
        302 => ['Redirecting', 'You are being redirected to another page.'],
        400 => ['Bad Request', 'The request could not be understood.'],
        401 => ['Sign In Required', 'Please sign in to continue.'],
        403 => ['Access Denied', 'You do not have permission to view this page.'],
        404 => ['Page Not Found', 'The page you requested could not be found.'],
        405 => ['Method Not Allowed', 'This action is not available for this request method.'],
        408 => ['Request Timeout', 'The request took too long to complete.'],
        421 => ['Wrong Host', 'This application is not available on this host name.'],
        429 => ['Too Many Requests', 'Too many requests were received. Please try again shortly.'],
        500 => ['Server Error', 'Something went wrong while processing your request.'],
        502 => ['Provider Error', 'An upstream service returned an invalid response.'],
        503 => ['Temporarily Unavailable', 'This service is temporarily unavailable. Please try again.'],
    ];

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

        if ($primaryPath === null) {
            $primaryPath = '/';
        }

        if ($primaryLabel === null) {
            $primaryLabel = 'Go to Home';
        }

        $primaryUrl = self::url($base, $primaryPath);

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
        $safePrimaryLabel = self::e($primaryLabel);
        $safeBase = self::e($base);
        $safeVersion = self::e($version);
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
            echo '<div class="eyebrow">CoolerDepot Sales Post Tracker</div>';
            echo '<h1>' . $safeTitle . '</h1><p>' . $safeBody . '</p>';
            echo '<div class="status-actions"><a class="btn primary" href="' . $safeLocation . '">Continue</a></div>';
            echo '<div class="status-version">v' . $safeVersion . '</div>';
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
        echo '<div class="eyebrow">CoolerDepot Sales Post Tracker</div>';
        echo '<h1>' . $safeTitle . '</h1><p>' . $safeBody . '</p>';
        echo '<div class="status-actions">';
        echo '<a class="btn primary" href="' . $safePrimaryUrl . '">' . $safePrimaryLabel . '</a>';
        echo '<button type="button" class="btn" onclick="history.back()">Go Back</button>';
        echo '</div>';
        echo '<div class="status-help">If this keeps happening, contact your administrator.</div>';
        echo '<div class="status-version">v' . $safeVersion . '</div>';
        echo '</section></main></body></html>';
        exit;
    }

    public static function renderJson(int $status, ?string $message = null): void
    {
        $meta = self::STATUS[$status] ?? ['Request Error', 'The request could not be completed.'];
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode([
            'ok' => false,
            'status' => $status,
            'error' => $meta[0],
            'message' => $message ?: $meta[1],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

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

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
