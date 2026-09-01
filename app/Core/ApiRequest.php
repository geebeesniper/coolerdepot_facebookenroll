<?php
/**
 * File / 文件：app/Core/ApiRequest.php
 * EN: Defines the shared ApiRequest core infrastructure component.
 * 中文：定义全应用共享的 ApiRequest 核心基础设施组件。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Core;

/**
 * EN: Core infrastructure component that provides api request behavior shared across the application.
 * 中文：提供全应用共享 api request 能力的核心基础设施组件。
 */
class ApiRequest
{
    /**
     * EN: Perform the prepare cors core operation provided by api request.
     * 中文：执行 api request 提供的“prepare cors”核心操作。
     *
     * @param bool $preflight Preflight value used by this operation. / 本操作使用的“preflight”参数值。
     *
     * @return void No value is returned. / 无返回值。
     *
     * @throws ApiException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    public static function prepareCors(bool $preflight = false): void
    {
        global $config;
        $origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
        $allowed = $config['api']['allowed_origins'] ?? [];

        if ($origin !== '') {
            $allowOrigin = null;
            if (in_array('*', $allowed, true)) {
                $allowOrigin = '*';
            } elseif (in_array($origin, $allowed, true)) {
                $allowOrigin = $origin;
            }

            if ($allowOrigin !== null) {
                header('Access-Control-Allow-Origin: ' . $allowOrigin);
                header('Vary: Origin', false);
                header('Access-Control-Allow-Headers: Authorization, Content-Type');
                header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
                header('Access-Control-Max-Age: 600');
            } elseif ($preflight) {
                throw new ApiException(403, 'cors_origin_denied', 'This origin is not allowed to call the API.');
            }
        }
    }

    /**
     * EN: Read and decode the current JSON request body after enforcing API body-size limits.
     * 中文：在执行 API 请求体大小限制后读取并解析当前 JSON 请求体。
     *
     * @param bool $allowEmpty Boolean flag controlling the requested behavior. / 控制所请求行为的布尔标志。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     *
     * @throws ApiException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    public static function jsonBody(bool $allowEmpty = false): array
    {
        global $config;
        self::requireJsonContentType();
        $max = max(1024, (int)($config['api']['max_body_bytes'] ?? 1048576));
        $declared = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($declared > $max) {
            throw new ApiException(413, 'request_too_large', 'The API request body is too large.');
        }

        $raw = (string)file_get_contents('php://input');
        if (strlen($raw) > $max) {
            throw new ApiException(413, 'request_too_large', 'The API request body is too large.');
        }
        if (trim($raw) === '') {
            if ($allowEmpty) {
                return [];
            }
            throw new ApiException(400, 'invalid_json', 'A JSON request body is required.');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new ApiException(400, 'invalid_json', 'The request body must be a JSON object.');
        }
        return $decoded;
    }


    /**
     * EN: Perform the require json content type core operation provided by api request.
     * 中文：执行 api request 提供的“require json content type”核心操作。
     *
     * @return void No value is returned. / 无返回值。
     *
     * @throws ApiException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    public static function requireJsonContentType(): void
    {
        $raw = trim((string)($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''));
        $mediaType = strtolower(trim(explode(';', $raw, 2)[0] ?? ''));
        if ($mediaType !== 'application/json' && !str_ends_with($mediaType, '+json')) {
            throw new ApiException(415, 'unsupported_media_type', 'Use Content-Type: application/json.');
        }
    }

    /**
     * EN: Perform the security headers core operation provided by api request.
     * 中文：执行 api request 提供的“security headers”核心操作。
     *
     * @return void No value is returned. / 无返回值。
     */
    public static function securityHeaders(): void
    {
        header('Cache-Control: no-store');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
    }

    /**
     * EN: Perform the authorization header core operation provided by api request.
     * 中文：执行 api request 提供的“authorization header”核心操作。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
     */
    public static function authorizationHeader(): string
    {
        $candidates = [
            $_SERVER['HTTP_AUTHORIZATION'] ?? '',
            $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '',
        ];

        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (is_array($headers)) {
                $candidates[] = $headers['Authorization'] ?? $headers['authorization'] ?? '';
            }
        }

        foreach ($candidates as $value) {
            $value = trim((string)$value);
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }

    /**
     * EN: Extract the Bearer token from the current Authorization header.
     * 中文：从当前 Authorization Header 中提取 Bearer Token。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
     *
     * @throws ApiException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    public static function bearerToken(): string
    {
        $header = self::authorizationHeader();
        if ($header === '') {
            return '';
        }
        if (!preg_match('/^Bearer\s+([^\s]+)$/i', $header, $match)) {
            throw new ApiException(401, 'invalid_authorization_header', 'Use Authorization: Bearer <access_token>.');
        }
        return (string)$match[1];
    }
}
