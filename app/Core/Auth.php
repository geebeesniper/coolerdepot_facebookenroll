<?php
/**
 * File / 文件：app/Core/Auth.php
 * EN: Core runtime/infrastructure component used across the application.
 * 中文：该文件是应用全局复用的核心运行时或基础设施组件。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
namespace App\Core;

class Auth
{
    /**
     * EN: Implements the application operation `user` (user).
     * 中文：实现应用操作 `user`（user）。
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
     * EN: Implements the application operation `login` (login).
     * 中文：实现应用操作 `login`（login）。
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
     * EN: Implements the application operation `logout` (logout).
     * 中文：实现应用操作 `logout`（logout）。
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
     * EN: Implements the application operation `requireLogin` (require Login).
     * 中文：实现应用操作 `requireLogin`（require Login）。
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
     * EN: Implements the application operation `requireRole` (require Role).
     * 中文：实现应用操作 `requireRole`（require Role）。
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
