<?php
namespace App\Services;

use App\Core\Database;

/**
 * Verifies the signed authentication handoff from the parent CoolerDepot app.
 *
 * Security-sensitive inputs (signature/secret) are deliberately never logged
 * here. The controller records only the resulting exception and correlation id.
 */
class ExternalAuthService
{
    public static function canonicalPayload(array $payload): string
    {
        return implode("\n", [
            (string)($payload['uid'] ?? ''),
            (string)($payload['sales_id'] ?? ''),
            (string)($payload['name'] ?? ''),
            (string)($payload['role'] ?? ''),
            (string)($payload['ts'] ?? ''),
            (string)($payload['nonce'] ?? ''),
        ]);
    }

    public function accept(array $payload): array
    {
        global $config;

        $secret = (string)$config['auth']['handoff_secret'];
        if (strlen($secret) < 32) {
            throw new \RuntimeException('AUTH_HANDOFF_SECRET is not configured.');
        }

        $uid = trim((string)($payload['uid'] ?? ''));
        $salesId = trim((string)($payload['sales_id'] ?? ''));
        $name = trim((string)($payload['name'] ?? ''));
        $role = trim((string)($payload['role'] ?? ''));
        $timestamp = (int)($payload['ts'] ?? 0);
        $nonce = trim((string)($payload['nonce'] ?? ''));
        $signature = strtolower(trim((string)($payload['sig'] ?? '')));

        if (
            $uid === ''
            || $name === ''
            || $nonce === ''
            || $timestamp <= 0
            || $signature === ''
        ) {
            throw new \RuntimeException('Incomplete authentication handoff.');
        }

        if (!in_array($role, ['admin', 'sales'], true)) {
            throw new \RuntimeException('Invalid role.');
        }

        if ($role === 'sales' && ($salesId === '' || !ctype_digit($salesId))) {
            throw new \RuntimeException('Sales handoff requires numeric sales_id.');
        }

        if (
            abs(time() - $timestamp)
            > max(30, (int)$config['auth']['handoff_max_age_seconds'])
        ) {
            throw new \RuntimeException('Authentication handoff expired.');
        }

        if (!preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $nonce)) {
            throw new \RuntimeException('Invalid nonce.');
        }

        $expected = hash_hmac(
            'sha256',
            self::canonicalPayload([
                'uid' => $uid,
                'sales_id' => $salesId,
                'name' => $name,
                'role' => $role,
                'ts' => $timestamp,
                'nonce' => $nonce,
            ]),
            $secret
        );

        if (!hash_equals($expected, $signature)) {
            throw new \RuntimeException('Authentication signature is invalid.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            // The handoff nonce is inserted first so replaying the same signed
            // request is rejected by the database unique constraint.
            $handoff = $pdo->prepare(
                "INSERT INTO cdsp_auth_handoffs
                 (nonce,external_user_id,sales_id,display_name,role,source_ip,payload_json,accepted_at)
                 VALUES(?,?,?,?,?,?,?,NOW())"
            );
            $handoff->execute([
                $nonce,
                $uid,
                $role === 'sales' ? (int)$salesId : null,
                $name,
                $role,
                substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
                json_encode(
                    [
                        'uid' => $uid,
                        'sales_id' => $salesId,
                        'name' => $name,
                        'role' => $role,
                        'ts' => $timestamp,
                        'nonce' => $nonce,
                    ],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
            ]);

            $username = $role === 'sales'
                ? $salesId
                : 'ext_' . preg_replace('/[^A-Za-z0-9._-]+/', '_', $uid);

            $upsertUser = $pdo->prepare(
                "INSERT INTO cdsp_users
                 (sales_id,external_user_id,username,password_hash,display_name,role,active,auth_source,last_handoff_at,created_at,updated_at)
                 VALUES(?,?,?,NULL,?,?,1,'coolerdepot',NOW(),NOW(),NOW())
                 ON DUPLICATE KEY UPDATE
                    sales_id=VALUES(sales_id),
                    external_user_id=VALUES(external_user_id),
                    display_name=VALUES(display_name),
                    role=VALUES(role),
                    active=1,
                    auth_source='coolerdepot',
                    last_handoff_at=NOW(),
                    updated_at=NOW()"
            );
            $upsertUser->execute([
                $role === 'sales' ? (int)$salesId : null,
                $uid,
                $username,
                $name,
                $role,
            ]);

            $findUser = $pdo->prepare(
                "SELECT id,sales_id,external_user_id,username,display_name,role,active,auth_source
                 FROM cdsp_users
                 WHERE external_user_id=?
                 LIMIT 1"
            );
            $findUser->execute([$uid]);
            $user = $findUser->fetch();

            if (!$user) {
                throw new \RuntimeException('Could not create or resolve user.');
            }

            $link = $pdo->prepare(
                'UPDATE cdsp_auth_handoffs SET user_id=? WHERE nonce=?'
            );
            $link->execute([(int)$user['id'], $nonce]);

            $pdo->commit();
            return $user;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            // Keep the user-facing replay error stable without exposing raw
            // database details. AuthHandoffController records the exception.
            if (stripos($e->getMessage(), 'Duplicate') !== false) {
                throw new \RuntimeException(
                    'Authentication handoff was already used.'
                );
            }

            throw $e;
        }
    }
}
