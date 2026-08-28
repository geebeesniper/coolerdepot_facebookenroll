<?php
namespace App\Core;

class Auth
{
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
            return null;
        }

        $touch = Database::connection()->prepare(
            "UPDATE cdsp_auth_sessions SET last_seen_at=NOW() WHERE id=?"
        );
        $touch->execute([(int)$user['auth_session_id']]);

        return $user;
    }

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
    }

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

        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

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
