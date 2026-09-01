<?php
/**
 * File / 文件：app/Core/ApiAuth.php
 * EN: Defines the shared ApiAuth core infrastructure component.
 * 中文：定义全应用共享的 ApiAuth 核心基础设施组件。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Core;

/**
 * EN: Core infrastructure component that provides api auth behavior shared across the application.
 * 中文：提供全应用共享 api auth 能力的核心基础设施组件。
 */
class ApiAuth
{
    /**
     * EN: Issue a short-lived API Bearer token for an authenticated user and store only its hash.
     * 中文：为已认证用户签发短期 API Bearer Token，并仅保存其哈希值。
     *
     * @param array $user User value used by this operation. / 本操作使用的“user”参数值。
     * @param string $source Source value used by this operation. / 本操作使用的“source”参数值。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
    public static function issue(array $user, string $source = 'signed_exchange'): array
    {
        global $config;
        $hours = max(1, min(24, (int)($config['api']['token_hours'] ?? 1)));
        $raw = 'cdsp_at_' . self::base64Url(random_bytes(32));
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "INSERT INTO cdsp_api_tokens
             (user_id,token_hash,source,ip_address,user_agent,created_at,last_used_at,expires_at)
             VALUES(?,?,?,?,?,NOW(),NOW(),DATE_ADD(NOW(),INTERVAL ? HOUR))"
        );
        $stmt->execute([
            (int)$user['id'],
            hash('sha256', $raw),
            substr($source, 0, 50),
            substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
            $hours,
        ]);

        return [
            'access_token' => $raw,
            'token_type' => 'Bearer',
            'expires_in' => $hours * 3600,
            'expires_at' => gmdate('c', time() + ($hours * 3600)),
        ];
    }

    /**
     * EN: Perform the user core operation provided by api auth.
     * 中文：执行 api auth 提供的“user”核心操作。
     *
     * @return ?array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     *
     * @throws ApiException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    public static function user(): ?array
    {
        $raw = ApiRequest::bearerToken();
        if ($raw === '') {
            return null;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "SELECT u.id,u.sales_id,u.external_user_id,u.username,u.display_name,u.role,u.active,u.daily_post_target,u.auth_source,
                    t.id api_token_id,t.expires_at
             FROM cdsp_api_tokens t
             JOIN cdsp_users u ON u.id=t.user_id
             WHERE t.token_hash=? AND t.revoked_at IS NULL AND t.expires_at>NOW() AND u.active=1
             LIMIT 1"
        );
        $stmt->execute([hash('sha256', $raw)]);
        $user = $stmt->fetch();
        if (!$user) {
            throw new ApiException(401, 'invalid_or_expired_token', 'The access token is invalid, expired, or revoked.');
        }

        $touch = $pdo->prepare('UPDATE cdsp_api_tokens SET last_used_at=NOW() WHERE id=?');
        $touch->execute([(int)$user['api_token_id']]);
        Logger::setUserContext($user);
        return $user;
    }

    /**
     * EN: Require a valid API Bearer token and return the authenticated user.
     * 中文：要求提供有效的 API Bearer Token，并返回已认证用户。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     *
     * @throws ApiException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    public static function requireLogin(): array
    {
        $user = self::user();
        if (!$user) {
            throw new ApiException(401, 'bearer_token_required', 'A Bearer access token is required.');
        }
        return $user;
    }

    /**
     * EN: Require API authentication and enforce the requested server-side role.
     * 中文：要求 API 已认证，并在服务器端强制检查指定角色。
     *
     * @param string $role Required or assigned application role. / 要求或分配的应用角色。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     *
     * @throws ApiException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    public static function requireRole(string $role): array
    {
        $user = self::requireLogin();
        if (($user['role'] ?? '') !== $role) {
            throw new ApiException(403, 'forbidden_role', 'This API operation is not available to your role.');
        }
        return $user;
    }

    /**
     * EN: Update the revoke current core operation provided by api auth.
     * 中文：更新 api auth 提供的“revoke current”核心操作。
     *
     * @return void No value is returned. / 无返回值。
     *
     * @throws ApiException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    public static function revokeCurrent(): void
    {
        $raw = ApiRequest::bearerToken();
        if ($raw === '') {
            throw new ApiException(401, 'bearer_token_required', 'A Bearer access token is required.');
        }
        $stmt = Database::connection()->prepare(
            'UPDATE cdsp_api_tokens SET revoked_at=NOW() WHERE token_hash=? AND revoked_at IS NULL'
        );
        $stmt->execute([hash('sha256', $raw)]);
        if ($stmt->rowCount() < 1) {
            throw new ApiException(401, 'invalid_or_expired_token', 'The access token is invalid, expired, or revoked.');
        }
    }

    /**
     * EN: Perform the public user core operation provided by api auth.
     * 中文：执行 api auth 提供的“public user”核心操作。
     *
     * @param array $user User value used by this operation. / 本操作使用的“user”参数值。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
    public static function publicUser(array $user): array
    {
        return [
            'id' => (int)$user['id'],
            'externalUserId' => $user['external_user_id'] !== null ? (string)$user['external_user_id'] : null,
            'salesId' => $user['sales_id'] !== null ? (int)$user['sales_id'] : null,
            'displayName' => (string)$user['display_name'],
            'role' => (string)$user['role'],
            'dailyPostTarget' => isset($user['daily_post_target']) ? (int)$user['daily_post_target'] : null,
            'authSource' => (string)($user['auth_source'] ?? ''),
        ];
    }

    /**
     * EN: Build the base64 url core operation provided by api auth.
     * 中文：构建 api auth 提供的“base64 url”核心操作。
     *
     * @param string $bytes Bytes value used by this operation. / 本操作使用的“bytes”参数值。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
     */
    private static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
