<?php
namespace App\Core;

use Throwable;

/**
 * Central application diagnostics logger.
 *
 * The file sink is the source of truth because it still works when MySQL is
 * unavailable. Each record is one JSON object (JSONL) and carries the same
 * request/correlation id across PHP, HTTP and browser-side diagnostics.
 *
 * Security rule: credentials, cookies, session values and token-like fields
 * are redacted before anything is written. Logging must never throw back into
 * the request that is already failing.
 */
final class Logger
{
    private const LEVEL_WEIGHT = [
        'debug' => 10,
        'info' => 20,
        'notice' => 30,
        'warning' => 40,
        'error' => 50,
        'critical' => 60,
    ];

    private static bool $initialized = false;
    private static bool $writing = false;
    private static array $config = [];
    private static string $requestId = '';
    private static array $userContext = [];

    public static function init(array $config): void
    {
        if (self::$initialized) {
            return;
        }

        self::$config = $config;
        self::$requestId = self::makeRequestId();
        self::$initialized = true;

        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            header('X-CDSP-Request-ID: ' . self::$requestId);
        }

        self::installPhpErrorHandler();
        self::installShutdownHandler();
        self::maybePruneOldLogs();
    }

    /**
     * Attach authenticated user identity to later records in this request.
     * Do not store the session token or any credential here.
     */
    public static function setUserContext(?array $user): void
    {
        if (!$user) {
            self::$userContext = [];
            return;
        }

        self::$userContext = array_filter([
            'user_id' => isset($user['id']) ? (int)$user['id'] : null,
            'sales_id' => isset($user['sales_id']) ? (int)$user['sales_id'] : null,
            'role' => isset($user['role']) ? (string)$user['role'] : null,
        ], static fn($value) => $value !== null && $value !== '');
    }

    public static function requestId(): string
    {
        if (self::$requestId === '') {
            self::$requestId = self::makeRequestId();
        }

        return self::$requestId;
    }

    public static function currentLogFile(): string
    {
        return rtrim(self::logDirectory(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'app-' . date('Y-m-d') . '.log';
    }

    public static function debug(string $message, array $context = [], string $channel = 'app'): void
    {
        self::log('debug', $message, $context, $channel);
    }

    public static function info(string $message, array $context = [], string $channel = 'app'): void
    {
        self::log('info', $message, $context, $channel);
    }

    public static function warning(string $message, array $context = [], string $channel = 'app'): void
    {
        self::log('warning', $message, $context, $channel);
    }

    public static function error(string $message, array $context = [], string $channel = 'app'): void
    {
        self::log('error', $message, $context, $channel);
    }

    public static function critical(string $message, array $context = [], string $channel = 'app'): void
    {
        self::log('critical', $message, $context, $channel);
    }

    /**
     * Record an exception with type, origin and a bounded stack trace.
     */
    public static function exception(
        Throwable $e,
        string $channel = 'app',
        array $context = [],
        string $level = 'error'
    ): void {
        $context = array_merge($context, [
            'exception_class' => get_class($e),
            'exception_message' => $e->getMessage(),
            'exception_file' => $e->getFile(),
            'exception_line' => $e->getLine(),
            'trace' => self::boundedTrace($e),
        ]);

        self::log(
            $level,
            $context['event'] ?? 'Exception',
            $context,
            $channel
        );
    }

    /**
     * Record a non-success HTTP response. Query strings are deliberately not
     * logged because handoff URLs and provider callbacks can contain secrets.
     */
    public static function httpStatus(int $status, array $context = []): void
    {
        if ($status < 400) {
            return;
        }

        $level = $status >= 500 ? 'error' : 'warning';
        self::log(
            $level,
            'HTTP ' . $status,
            array_merge(['status' => $status], $context),
            'http'
        );
    }

    public static function log(
        string $level,
        string $message,
        array $context = [],
        string $channel = 'app'
    ): void {
        if (!self::$initialized) {
            self::init($GLOBALS['config'] ?? []);
        }

        $level = strtolower($level);
        if (!isset(self::LEVEL_WEIGHT[$level])) {
            $level = 'error';
        }

        if (!self::shouldLog($level) || self::$writing) {
            return;
        }

        self::$writing = true;

        try {
            $record = [
                'timestamp' => date('c'),
                'level' => $level,
                'channel' => $channel,
                'request_id' => self::requestId(),
                'message' => self::redactString($message),
                'request' => self::requestContext(),
                'user' => self::$userContext,
                'context' => self::sanitize($context),
            ];

            $json = json_encode(
                $record,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_INVALID_UTF8_SUBSTITUTE
            );

            if (!is_string($json)) {
                $json = '{"timestamp":"' . date('c') . '","level":"error","message":"Logger JSON encoding failed"}';
            }

            $written = self::writeLine($json . PHP_EOL);

            // Keep error/critical events visible in Docker/PHP logs as well.
            if (!$written || in_array($level, ['error', 'critical'], true)) {
                error_log(
                    '[CDSP][' . self::requestId() . '][' . strtoupper($level) . '] '
                    . self::redactString($message)
                );
            }
        } catch (Throwable $ignored) {
            // Diagnostics must never turn one application failure into another.
        } finally {
            self::$writing = false;
        }
    }

    private static function installPhpErrorHandler(): void
    {
        set_error_handler(static function (
            int $severity,
            string $message,
            string $file,
            int $line
        ): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }

            $level = in_array(
                $severity,
                [E_USER_ERROR, E_RECOVERABLE_ERROR],
                true
            ) ? 'error' : 'warning';

            self::log(
                $level,
                self::phpSeverityName($severity) . ': ' . $message,
                [
                    'severity' => $severity,
                    'file' => $file,
                    'line' => $line,
                ],
                'php'
            );

            // Preserve PHP's normal behavior after the central log entry.
            return false;
        });
    }

    private static function installShutdownHandler(): void
    {
        register_shutdown_function(static function (): void {
            $last = error_get_last();
            if (!$last) {
                return;
            }

            $fatal = [
                E_ERROR,
                E_PARSE,
                E_CORE_ERROR,
                E_COMPILE_ERROR,
                E_USER_ERROR,
                E_RECOVERABLE_ERROR,
            ];

            if (!in_array((int)$last['type'], $fatal, true)) {
                return;
            }

            self::critical(
                self::phpSeverityName((int)$last['type']) . ': ' . (string)$last['message'],
                [
                    'severity' => (int)$last['type'],
                    'file' => (string)$last['file'],
                    'line' => (int)$last['line'],
                ],
                'php-fatal'
            );
        });
    }

    private static function requestContext(): array
    {
        if (PHP_SAPI === 'cli') {
            // CLI helper scripts can receive provider tokens as positional
            // arguments. Never copy argv into diagnostics; only record the
            // executable script identity and argument count.
            $argv = $_SERVER['argv'] ?? [];
            return [
                'sapi' => 'cli',
                'script' => basename((string)($argv[0] ?? '')),
                'arg_count' => max(0, count($argv) - 1),
                'version' => (string)(self::$config['app']['version'] ?? 'dev'),
            ];
        }

        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        return [
            'method' => (string)($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            'path' => self::redactString($path),
            'ip' => substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            'user_agent' => substr(
                self::redactString((string)($_SERVER['HTTP_USER_AGENT'] ?? '')),
                0,
                500
            ),
            'version' => (string)(self::$config['app']['version'] ?? 'dev'),
        ];
    }

    private static function sanitize($value, ?string $key = null, int $depth = 0)
    {
        if ($depth > 6) {
            return '[depth-limit]';
        }

        if ($key !== null && self::sensitiveKey($key)) {
            return '[redacted]';
        }

        if (is_array($value)) {
            $clean = [];
            $count = 0;
            foreach ($value as $k => $v) {
                if (++$count > 100) {
                    $clean['_truncated'] = true;
                    break;
                }
                $clean[$k] = self::sanitize($v, (string)$k, $depth + 1);
            }
            return $clean;
        }

        if (is_object($value)) {
            return '[object ' . get_class($value) . ']';
        }

        if (is_resource($value)) {
            return '[resource]';
        }

        if (is_string($value)) {
            return self::redactString($value);
        }

        return $value;
    }

    private static function sensitiveKey(string $key): bool
    {
        return (bool)preg_match(
            '/(?:pass(?:word)?|secret|token|authorization|cookie|session|api[_-]?key|private[_-]?key|credential)/i',
            $key
        );
    }

    private static function redactString(string $value): string
    {
        $value = preg_replace(
            '/(authorization\s*:\s*(?:bearer|basic)\s+)[^\s]+/i',
            '$1[redacted]',
            $value
        ) ?? $value;
        $value = preg_replace(
            '/([?&](?:token|access_token|api_key|key|secret|password)=)[^&\s]+/i',
            '$1[redacted]',
            $value
        ) ?? $value;
        $value = preg_replace(
            '/((?:token|api[_-]?key|secret|password)\s*[=:]\s*)[^\s,;]+/i',
            '$1[redacted]',
            $value
        ) ?? $value;

        if (strlen($value) > 4000) {
            $value = substr($value, 0, 4000) . '…[truncated]';
        }

        return $value;
    }

    private static function boundedTrace(Throwable $e): array
    {
        $trace = [];
        foreach (array_slice($e->getTrace(), 0, 20) as $frame) {
            $trace[] = array_filter([
                'file' => $frame['file'] ?? null,
                'line' => $frame['line'] ?? null,
                'class' => $frame['class'] ?? null,
                'type' => $frame['type'] ?? null,
                'function' => $frame['function'] ?? null,
            ], static fn($value) => $value !== null && $value !== '');
        }
        return $trace;
    }

    private static function writeLine(string $line): bool
    {
        $dir = self::logDirectory();
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            return false;
        }

        $file = self::currentLogFile();
        self::rotateIfNeeded($file, strlen($line));

        $written = @file_put_contents(
            $file,
            $line,
            FILE_APPEND | LOCK_EX
        ) !== false;

        if ($written) {
            // Keep diagnostics readable by the PHP/operations group, not world.
            @chmod($file, 0640);
        }

        return $written;
    }


    /**
     * Keep a single error storm from filling the disk before daily rotation.
     * Rotation is best-effort: if rename fails, logging continues to the active
     * file rather than losing the original application error.
     */
    private static function rotateIfNeeded(string $file, int $incomingBytes): void
    {
        $maxBytes = max(
            1024 * 1024,
            (int)(self::$config['logging']['max_bytes'] ?? (25 * 1024 * 1024))
        );
        $size = @filesize($file);

        if ($size === false || ($size + $incomingBytes) < $maxBytes) {
            return;
        }

        $archive = dirname($file)
            . DIRECTORY_SEPARATOR
            . 'app-'
            . date('Y-m-d-His')
            . '-'
            . substr(self::requestId(), 0, 8)
            . '.log';

        @rename($file, $archive);
    }

    private static function logDirectory(): string
    {
        $configured = trim((string)(self::$config['logging']['path'] ?? ''));
        if ($configured !== '') {
            if ($configured[0] === DIRECTORY_SEPARATOR) {
                return rtrim($configured, DIRECTORY_SEPARATOR);
            }
            return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . trim($configured, '/\\');
        }

        return dirname(__DIR__, 2) . '/storage/logs';
    }

    private static function shouldLog(string $level): bool
    {
        $minimum = strtolower((string)(self::$config['logging']['level'] ?? 'warning'));
        if (!isset(self::LEVEL_WEIGHT[$minimum])) {
            $minimum = 'warning';
        }

        return self::LEVEL_WEIGHT[$level] >= self::LEVEL_WEIGHT[$minimum];
    }

    private static function maybePruneOldLogs(): void
    {
        $days = max(1, (int)(self::$config['logging']['retention_days'] ?? 30));

        try {
            // Keep hot-path overhead tiny; cleanup is best-effort only.
            if (random_int(1, 100) !== 1) {
                return;
            }

            $cutoff = time() - ($days * 86400);
            foreach (glob(self::logDirectory() . '/app-*.log') ?: [] as $file) {
                $mtime = @filemtime($file);
                if ($mtime !== false && $mtime < $cutoff) {
                    @unlink($file);
                }
            }
        } catch (Throwable $ignored) {
        }
    }

    private static function makeRequestId(): string
    {
        try {
            return bin2hex(random_bytes(8));
        } catch (Throwable $ignored) {
            return str_replace('.', '', uniqid('cdsp', true));
        }
    }

    private static function phpSeverityName(int $severity): string
    {
        $map = [
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
        ];

        return $map[$severity] ?? ('PHP_ERROR_' . $severity);
    }
}
