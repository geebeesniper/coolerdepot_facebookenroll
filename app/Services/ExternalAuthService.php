<?php
/**
 * File / 文件：app/Services/ExternalAuthService.php
 * EN: Defines the ExternalAuthService service used by application business, security, or provider integration flows.
 * 中文：定义 ExternalAuthService 服务，用于应用业务、安全或 Provider 集成流程。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Services;

use App\Core\Database;

/**
 * EN: Application service that encapsulates external auth service business, security, or integration behavior.
 * 中文：封装 external auth service 业务、安全或外部集成行为的应用服务。
 */
class ExternalAuthService
{
    /**
     * EN: Build the canonical newline-delimited payload used for HMAC authentication signatures.
     * 中文：构建用于 HMAC 认证签名的标准换行分隔载荷。
     *
     * @param array $payload Input payload supplied to this operation. / 传入本操作的输入载荷。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
     */
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

    /**
     * EN: Validate and accept a signed external authentication handoff, reject replay attempts, and resolve the local user.
     * 中文：验证并接受外部系统签名的认证交接，拒绝重放请求，并解析或创建本地用户。
     *
     * @param array $payload Input payload supplied to this operation. / 传入本操作的输入载荷。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     *
     * @throws \RuntimeException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     * @throws \Throwable When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
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
        $timestampRaw = $payload['ts'] ?? 0;
        $timestamp = is_int($timestampRaw) ? $timestampRaw : (ctype_digit((string)$timestampRaw) ? (int)$timestampRaw : 0);
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

        // Canonical HMAC fields are newline-delimited. Reject embedded control/newline
        // characters so two different field layouts can never canonicalize ambiguously.
        if (strlen($uid) > 191 || preg_match('/[\x00-\x1F\x7F]/', $uid)) {
            throw new \RuntimeException('Invalid external user id.');
        }
        if (self::characterLength($name) > 150 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $name)) {
            throw new \RuntimeException('Invalid display name.');
        }
        if (!preg_match('/^[a-f0-9]{64}$/', $signature)) {
            throw new \RuntimeException('Authentication signature is invalid.');
        }

        if ($role === 'sales') {
            if ($salesId === '' || !ctype_digit($salesId)) {
                throw new \RuntimeException('Sales handoff requires numeric sales_id.');
            }
            $salesNumeric = (int)$salesId;
            if ($salesNumeric < 1 || $salesNumeric > 4294967295) {
                throw new \RuntimeException('Sales handoff sales_id is out of range.');
            }
        } elseif ($salesId !== '' && (!ctype_digit($salesId) || (int)$salesId > 4294967295)) {
            throw new \RuntimeException('Invalid admin sales_id.');
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
                "SELECT id,sales_id,external_user_id,username,display_name,role,active,daily_post_target,auth_source
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

    /**
     * EN: Perform the character length operation implemented by external auth service.
     * 中文：执行 external auth service 实现的“character length”操作。
     *
     * @param string $value Value processed or stored by this operation. / 本操作处理或保存的值。
     *
     * @return int Numeric result produced by this operation. / 本操作生成的数字结果。
     */
    private static function characterLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return \mb_strlen($value, 'UTF-8');
        }
        $count = preg_match_all('/./us', $value, $matches);
        return $count === false ? strlen($value) : $count;
    }

}
