<?php
/**
 * File / 文件：app/Core/Auth.php
 * EN: Defines the shared Auth core infrastructure component.
 * 中文：定义全应用共享的 Auth 核心基础设施组件。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Core;

/**
 * EN: Core infrastructure component that provides auth behavior shared across the application.
 * 中文：提供全应用共享 auth 能力的核心基础设施组件。
 */
class Auth
{
    /**
     * EN: Perform the user core operation provided by auth.
     * 中文：执行 auth 提供的“user”核心操作。
     *
     * @return ?array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
    public static function user(): ?array
    {
        $raw = $_SESSION['auth_db_token'] ?? '';

        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $stmt = Database::connection()->prepare(
            "SELECT u.id,u.sales_id,u.external_user_id,u.username,u.display_name,u.role,u.active,u.auth_source,
                    s.id auth_session_id,s.expires_at
             FROM cdsp_auth_sessions s
             JOIN cdsp_users u ON u.id=s.user_id
             WHERE s.token_hash=? AND s.revoked_at IS NULL AND s.expires_at>NOW() AND u.active=1
             LIMIT 1"
        );
        $stmt->execute([hash('sha256', $raw)]);
        $user = $stmt->fetch();

        if (!$user) {
            unset($_SESSION['auth_db_token']);
            Logger::setUserContext(null);
            return null;
        }

        $touch = Database::connection()->prepare(
            "UPDATE cdsp_auth_sessions SET last_seen_at=NOW() WHERE id=?"
        );
        $touch->execute([(int)$user['auth_session_id']]);
        Logger::setUserContext($user);

        return $user;
    }

    /**
     * EN: Authenticate a local user with a username and password and establish a browser session.
     * 中文：使用用户名和密码认证本地用户并建立浏览器 Session。
     *
     * @param array $user User value used by this operation. / 本操作使用的“user”参数值。
     * @param string $source Source value used by this operation. / 本操作使用的“source”参数值。
     *
     * @return void No value is returned. / 无返回值。
     */
    public static function login(array $user, string $source = 'handoff'): void
    {
        global $config;
        session_regenerate_id(true);
        $raw = bin2hex(random_bytes(32));

        $stmt = Database::connection()->prepare(
            "INSERT INTO cdsp_auth_sessions
             (user_id,token_hash,source,ip_address,user_agent,created_at,last_seen_at,expires_at)
             VALUES(?,?,?,?,?,NOW(),NOW(),DATE_ADD(NOW(),INTERVAL ? HOUR))"
        );
        $stmt->execute([
            (int)$user['id'],
            hash('sha256', $raw),
            $source,
            substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
            max(1, (int)$config['auth']['session_hours'])
        ]);

        $_SESSION['auth_db_token'] = $raw;
        Logger::setUserContext($user);
    }

    /**
     * EN: Revoke the active browser authentication session and clear local session state.
     * 中文：撤销当前浏览器认证 Session 并清理本地 Session 状态。
     *
     * @return void No value is returned. / 无返回值。
     */
    public static function logout(): void
    {
        $raw = $_SESSION['auth_db_token'] ?? '';

        if (is_string($raw) && $raw !== '') {
            $stmt = Database::connection()->prepare(
                "UPDATE cdsp_auth_sessions SET revoked_at=NOW()
                 WHERE token_hash=? AND revoked_at IS NULL"
            );
            $stmt->execute([hash('sha256', $raw)]);
        }

        Logger::info('User session logged out.', [], 'auth');
        Logger::setUserContext(null);
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /**
     * EN: Perform the require login core operation provided by auth.
     * 中文：执行 auth 提供的“require login”核心操作。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
    public static function requireLogin(): array
    {
        $user = self::user();

        if (!$user) {
            global $config;
            $location = rtrim($config['app']['base_path'], '/') . '/login';
            ErrorPage::render(302, 'Your session has ended. Please sign in again.', null, null, $location);
        }

        return $user;
    }

    /**
     * EN: Perform the require role core operation provided by auth.
     * 中文：执行 auth 提供的“require role”核心操作。
     *
     * @param string $role Required or assigned application role. / 要求或分配的应用角色。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
    public static function requireRole(string $role): array
    {
        $user = self::requireLogin();

        if (($user['role'] ?? '') !== $role) {
            $currentRole = (string)($user['role'] ?? '');
            $home = $currentRole === 'sales' ? '/sales' : ($currentRole === 'admin' ? '/admin' : '/');
            $label = $currentRole === 'sales' ? 'Sales Dashboard' : ($currentRole === 'admin' ? 'Admin Dashboard' : 'Go to Home');

            ErrorPage::render(
                403,
                'Your account does not have access to this area.',
                $home,
                $label
            );
        }

        return $user;
    }
}
