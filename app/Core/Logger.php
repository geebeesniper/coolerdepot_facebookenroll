<?php
/**
 * File / 文件：app/Core/Logger.php
 * EN: Defines the shared Logger core infrastructure component.
 * 中文：定义全应用共享的 Logger 核心基础设施组件。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Core;

use Throwable;

/**
 * EN: Core infrastructure component that provides logger behavior shared across the application.
 * 中文：提供全应用共享 logger 能力的核心基础设施组件。
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

    /**
     * EN: Perform the init core operation provided by logger.
     * 中文：执行 logger 提供的“init”核心操作。
     *
     * @param array $config Configuration values used by this operation. / 本操作使用的配置数据。
     *
     * @return void No value is returned. / 无返回值。
     */
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
    /**
     * EN: Update the set user context core operation provided by logger.
     * 中文：更新 logger 提供的“set user context”核心操作。
     *
     * @param ?array $user User value used by this operation. / 本操作使用的“user”参数值。
     *
     * @return void No value is returned. / 无返回值。
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

    /**
     * EN: Send or process the request id core operation provided by logger.
     * 中文：发送或处理 logger 提供的“request id”核心操作。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
     */
    public static function requestId(): string
    {
        if (self::$requestId === '') {
            self::$requestId = self::makeRequestId();
        }

        return self::$requestId;
    }

    /**
     * EN: Retrieve the current log file core operation provided by logger.
     * 中文：读取 logger 提供的“current log file”核心操作。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
     */
    public static function currentLogFile(): string
    {
        return rtrim(self::logDirectory(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'app-' . date('Y-m-d') . '.log';
    }

    /**
     * EN: Record the debug core operation provided by logger.
     * 中文：记录 logger 提供的“debug”核心操作。
     *
     * @param string $message Human-readable message associated with the result or log entry. / 与结果或日志记录关联的可读消息。
     * @param array $context Additional execution context associated with the operation. / 与本操作关联的附加执行上下文。
     * @param string $channel Channel value used by this operation. / 本操作使用的“channel”参数值。
     *
     * @return void No value is returned. / 无返回值。
     */
    public static function debug(string $message, array $context = [], string $channel = 'app'): void
    {
        self::log('debug', $message, $context, $channel);
    }

    /**
     * EN: Record the info core operation provided by logger.
     * 中文：记录 logger 提供的“info”核心操作。
     *
     * @param string $message Human-readable message associated with the result or log entry. / 与结果或日志记录关联的可读消息。
     * @param array $context Additional execution context associated with the operation. / 与本操作关联的附加执行上下文。
     * @param string $channel Channel value used by this operation. / 本操作使用的“channel”参数值。
     *
     * @return void No value is returned. / 无返回值。
     */
    public static function info(string $message, array $context = [], string $channel = 'app'): void
    {
        self::log('info', $message, $context, $channel);
    }

    /**
     * EN: Record the warning core operation provided by logger.
     * 中文：记录 logger 提供的“warning”核心操作。
     *
     * @param string $message Human-readable message associated with the result or log entry. / 与结果或日志记录关联的可读消息。
     * @param array $context Additional execution context associated with the operation. / 与本操作关联的附加执行上下文。
     * @param string $channel Channel value used by this operation. / 本操作使用的“channel”参数值。
     *
     * @return void No value is returned. / 无返回值。
     */
    public static function warning(string $message, array $context = [], string $channel = 'app'): void
    {
        self::log('warning', $message, $context, $channel);
    }

    /**
     * EN: Record the error core operation provided by logger.
     * 中文：记录 logger 提供的“error”核心操作。
     *
     * @param string $message Human-readable message associated with the result or log entry. / 与结果或日志记录关联的可读消息。
     * @param array $context Additional execution context associated with the operation. / 与本操作关联的附加执行上下文。
     * @param string $channel Channel value used by this operation. / 本操作使用的“channel”参数值。
     *
     * @return void No value is returned. / 无返回值。
     */
    public static function error(string $message, array $context = [], string $channel = 'app'): void
    {
        self::log('error', $message, $context, $channel);
    }

    /**
     * EN: Record the critical core operation provided by logger.
     * 中文：记录 logger 提供的“critical”核心操作。
     *
     * @param string $message Human-readable message associated with the result or log entry. / 与结果或日志记录关联的可读消息。
     * @param array $context Additional execution context associated with the operation. / 与本操作关联的附加执行上下文。
     * @param string $channel Channel value used by this operation. / 本操作使用的“channel”参数值。
     *
     * @return void No value is returned. / 无返回值。
     */
    public static function critical(string $message, array $context = [], string $channel = 'app'): void
    {
        self::log('critical', $message, $context, $channel);
    }

    /**
     * Record an exception with type, origin and a bounded stack trace.
     */
    /**
     * EN: Record the exception core operation provided by logger.
     * 中文：记录 logger 提供的“exception”核心操作。
     *
     * @param Throwable $e Exception being handled or logged. / 正在处理或记录的异常对象。
     * @param string $channel Channel value used by this operation. / 本操作使用的“channel”参数值。
     * @param array $context Additional execution context associated with the operation. / 与本操作关联的附加执行上下文。
     * @param string $level Level value used by this operation. / 本操作使用的“level”参数值。
     *
     * @return void No value is returned. / 无返回值。
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
    /**
     * EN: Perform the http status core operation provided by logger.
     * 中文：执行 logger 提供的“http status”核心操作。
     *
     * @param int $status Status value applied or evaluated by the operation. / 本操作设置或判断的状态值。
     * @param array $context Additional execution context associated with the operation. / 与本操作关联的附加执行上下文。
     *
     * @return void No value is returned. / 无返回值。
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

    /**
     * EN: Record the log core operation provided by logger.
     * 中文：记录 logger 提供的“log”核心操作。
     *
     * @param string $level Level value used by this operation. / 本操作使用的“level”参数值。
     * @param string $message Human-readable message associated with the result or log entry. / 与结果或日志记录关联的可读消息。
     * @param array $context Additional execution context associated with the operation. / 与本操作关联的附加执行上下文。
     * @param string $channel Channel value used by this operation. / 本操作使用的“channel”参数值。
     *
     * @return void No value is returned. / 无返回值。
     */
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

    /**
     * EN: Perform the install php error handler core operation provided by logger.
     * 中文：执行 logger 提供的“install php error handler”核心操作。
     *
     * @return void No value is returned. / 无返回值。
     */
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

    /**
     * EN: Perform the install shutdown handler core operation provided by logger.
     * 中文：执行 logger 提供的“install shutdown handler”核心操作。
     *
     * @return void No value is returned. / 无返回值。
     */
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

    /**
     * EN: Send or process the request context core operation provided by logger.
     * 中文：发送或处理 logger 提供的“request context”核心操作。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
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

    /**
     * EN: Normalize or format the sanitize core operation provided by logger.
     * 中文：规范化或格式化 logger 提供的“sanitize”核心操作。
     *
     * @param mixed $value Value processed or stored by this operation. / 本操作处理或保存的值。
     * @param ?string $key Key used to identify the requested value. / 用于标识目标值的键。
     * @param int $depth Depth value used by this operation. / 本操作使用的“depth”参数值。
     *
     * @return mixed Result produced by this operation; the concrete type depends on the execution path. / 本操作生成的结果；具体类型取决于执行路径。
     */
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

    /**
     * EN: Perform the sensitive key core operation provided by logger.
     * 中文：执行 logger 提供的“sensitive key”核心操作。
     *
     * @param string $key Key used to identify the requested value. / 用于标识目标值的键。
     *
     * @return bool True when the requested condition is satisfied; otherwise false. / 请求条件满足时返回 true，否则返回 false。
     */
    private static function sensitiveKey(string $key): bool
    {
        return (bool)preg_match(
            '/(?:pass(?:word)?|secret|token|authorization|cookie|session|api[_-]?key|private[_-]?key|credential)/i',
            $key
        );
    }

    /**
     * EN: Normalize or format the redact string core operation provided by logger.
     * 中文：规范化或格式化 logger 提供的“redact string”核心操作。
     *
     * @param string $value Value processed or stored by this operation. / 本操作处理或保存的值。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
     */
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

    /**
     * EN: Perform the bounded trace core operation provided by logger.
     * 中文：执行 logger 提供的“bounded trace”核心操作。
     *
     * @param Throwable $e Exception being handled or logged. / 正在处理或记录的异常对象。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
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

    /**
     * EN: Perform the write line core operation provided by logger.
     * 中文：执行 logger 提供的“write line”核心操作。
     *
     * @param string $line Line value used by this operation. / 本操作使用的“line”参数值。
     *
     * @return bool True when the requested condition is satisfied; otherwise false. / 请求条件满足时返回 true，否则返回 false。
     */
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
    /**
     * EN: Perform the rotate if needed core operation provided by logger.
     * 中文：执行 logger 提供的“rotate if needed”核心操作。
     *
     * @param string $file File path or uploaded file metadata being processed. / 正在处理的文件路径或上传文件信息。
     * @param int $incomingBytes Incoming bytes value used by this operation. / 本操作使用的“incoming bytes”参数值。
     *
     * @return void No value is returned. / 无返回值。
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

    /**
     * EN: Record the log directory core operation provided by logger.
     * 中文：记录 logger 提供的“log directory”核心操作。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
     */
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

    /**
     * EN: Perform the should log core operation provided by logger.
     * 中文：执行 logger 提供的“should log”核心操作。
     *
     * @param string $level Level value used by this operation. / 本操作使用的“level”参数值。
     *
     * @return bool True when the requested condition is satisfied; otherwise false. / 请求条件满足时返回 true，否则返回 false。
     */
    private static function shouldLog(string $level): bool
    {
        $minimum = strtolower((string)(self::$config['logging']['level'] ?? 'warning'));
        if (!isset(self::LEVEL_WEIGHT[$minimum])) {
            $minimum = 'warning';
        }

        return self::LEVEL_WEIGHT[$level] >= self::LEVEL_WEIGHT[$minimum];
    }

    /**
     * EN: Perform the maybe prune old logs core operation provided by logger.
     * 中文：执行 logger 提供的“maybe prune old logs”核心操作。
     *
     * @return void No value is returned. / 无返回值。
     */
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

    /**
     * EN: Build the make request id core operation provided by logger.
     * 中文：构建 logger 提供的“make request id”核心操作。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
     */
    private static function makeRequestId(): string
    {
        try {
            return bin2hex(random_bytes(8));
        } catch (Throwable $ignored) {
            return str_replace('.', '', uniqid('cdsp', true));
        }
    }

    /**
     * EN: Perform the php severity name core operation provided by logger.
     * 中文：执行 logger 提供的“php severity name”核心操作。
     *
     * @param int $severity Severity value used by this operation. / 本操作使用的“severity”参数值。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
     */
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
