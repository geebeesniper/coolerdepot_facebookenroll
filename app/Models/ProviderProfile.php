<?php
/**
 * File / 文件：app/Models/ProviderProfile.php
 * EN: Defines the ProviderProfile database model and its persistence/query helpers.
 * 中文：定义 ProviderProfile 数据库模型及其持久化与查询辅助逻辑。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Models;

use App\Core\Database;
use App\Services\SecretCrypto;

/**
 * EN: Database model for provider profile records, queries, and persistence operations.
 * 中文：负责 provider profile 记录、查询及持久化操作的数据库 Model。
 */
class ProviderProfile
{
    /**
     * EN: Retrieve the registry enabled data for provider profile.
     * 中文：读取 provider profile 的“registry enabled”数据。
     *
     * @return bool True when the requested condition is satisfied; otherwise false. / 请求条件满足时返回 true，否则返回 false。
     */
    public static function registryEnabled(): bool
    {
        try {
            return Setting::get('provider_registry_enabled', '0') === '1'
                && self::tableExists();
        } catch (\Throwable $e) {
            \App\Core\Logger::exception(
                $e,
                'provider-registry',
                ['event' => 'Provider registry availability check failed'],
                'warning'
            );
            return false;
        }
    }

    /**
     * EN: Perform the table exists data for provider profile.
     * 中文：执行 provider profile 的“table exists”数据。
     *
     * @return bool True when the requested condition is satisfied; otherwise false. / 请求条件满足时返回 true，否则返回 false。
     */
    public static function tableExists(): bool
    {
        try {
            $stmt = Database::connection()->query(
                "SHOW TABLES LIKE 'cdsp_provider_profiles'"
            );
            return (bool)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            \App\Core\Logger::exception(
                $e,
                'provider-registry',
                ['event' => 'Provider registry table check failed'],
                'warning'
            );
            return false;
        }
    }

    /**
     * EN: Retrieve the all admin data for provider profile in the application database.
     * 中文：读取 provider profile 的“all admin”数据，并访问应用数据库。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
    public static function allAdmin(): array
    {
        if (!self::tableExists()) {
            return [];
        }

        $rows = Database::connection()->query(
            "SELECT
                id, provider_type, name, website_url, api_endpoint,
                config_json, enabled, sort_order, verified_at,
                last_tested_at, last_test_ok, last_test_message,
                token_encrypted IS NOT NULL AND token_encrypted <> '' AS token_configured,
                created_at, updated_at
             FROM cdsp_provider_profiles
             ORDER BY sort_order ASC, id ASC"
        )->fetchAll();

        foreach ($rows as &$row) {
            $row['config'] = self::decodeConfig((string)$row['config_json']);
            unset($row['config_json']);
        }

        return $rows;
    }

    /**
     * EN: Retrieve the active verified with secrets data for provider profile in the application database.
     * 中文：读取 provider profile 的“active verified with secrets”数据，并访问应用数据库。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
    public static function activeVerifiedWithSecrets(): array
    {
        if (!self::registryEnabled()) {
            return [];
        }

        $stmt = Database::connection()->query(
            "SELECT *
             FROM cdsp_provider_profiles
             WHERE enabled=1
               AND verified_at IS NOT NULL
             ORDER BY sort_order ASC, id ASC"
        );

        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['config'] = self::decodeConfig((string)$row['config_json']);
            $row['api_token'] = self::decryptToken($row['token_encrypted'] ?? null);
            unset($row['token_encrypted'], $row['config_json']);
        }

        return $rows;
    }

    /**
     * EN: Retrieve the find with secret data for provider profile in the application database.
     * 中文：读取 provider profile 的“find with secret”数据，并访问应用数据库。
     *
     * @param int $id Identifier of the record record or entity. / record 记录或实体的标识 ID。
     *
     * @return ?array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
    public static function findWithSecret(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM cdsp_provider_profiles WHERE id=? LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $row['config'] = self::decodeConfig((string)$row['config_json']);
        $row['api_token'] = self::decryptToken($row['token_encrypted'] ?? null);
        unset($row['token_encrypted'], $row['config_json']);

        return $row;
    }

    /**
     * EN: Create or store the create verified data for provider profile in the application database.
     * 中文：创建或保存 provider profile 的“create verified”数据，并访问应用数据库。
     *
     * @param array $profile Profile value used by this operation. / 本操作使用的“profile”参数值。
     * @param int $userId Application user identifier. / 应用用户 ID。
     * @param string $testMessage Test message value used by this operation. / 本操作使用的“test message”参数值。
     *
     * @return int Numeric result produced by this operation. / 本操作生成的数字结果。
     */
    public static function createVerified(
        array $profile,
        int $userId,
        string $testMessage
    ): int {
        $sortOrder = self::nextSortOrder();
        $tokenEncrypted = self::encryptToken(
            (string)($profile['api_token'] ?? '')
        );

        $stmt = Database::connection()->prepare(
            "INSERT INTO cdsp_provider_profiles
             (
                provider_type, name, website_url, api_endpoint,
                token_encrypted, config_json, enabled, sort_order,
                verified_at, last_tested_at, last_test_ok, last_test_message,
                created_by, updated_by, created_at, updated_at
             )
             VALUES (?, ?, ?, ?, ?, ?, 1, ?, NOW(), NOW(), 1, ?, ?, ?, NOW(), NOW())"
        );

        $stmt->execute([
            $profile['provider_type'],
            $profile['name'],
            $profile['website_url'] ?: null,
            $profile['api_endpoint'] ?: null,
            $tokenEncrypted,
            json_encode(
                $profile['config'] ?? [],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ),
            $sortOrder,
            substr($testMessage, 0, 1000),
            $userId,
            $userId,
        ]);

        return (int)Database::connection()->lastInsertId();
    }

    /**
     * EN: Create or store the import legacy data for provider profile in the application database.
     * 中文：创建或保存 provider profile 的“import legacy”数据，并访问应用数据库。
     *
     * @param string $sourceKey Source key value used by this operation. / 本操作使用的“source key”参数值。
     * @param array $profile Profile value used by this operation. / 本操作使用的“profile”参数值。
     * @param int $userId Application user identifier. / 应用用户 ID。
     * @param bool $enabled Boolean flag controlling the requested behavior. / 控制所请求行为的布尔标志。
     *
     * @return int Numeric result produced by this operation. / 本操作生成的数字结果。
     */
    public static function importLegacy(
        string $sourceKey,
        array $profile,
        int $userId,
        bool $enabled
    ): int {
        $stmt = Database::connection()->prepare(
            "SELECT id FROM cdsp_provider_profiles WHERE source_key=? LIMIT 1"
        );
        $stmt->execute([$sourceKey]);
        $existing = (int)$stmt->fetchColumn();

        if ($existing > 0) {
            return $existing;
        }

        $stmt = Database::connection()->prepare(
            "INSERT INTO cdsp_provider_profiles
             (
                source_key, provider_type, name, website_url, api_endpoint,
                token_encrypted, config_json, enabled, sort_order,
                verified_at, last_tested_at, last_test_ok, last_test_message,
                created_by, updated_by, created_at, updated_at
             )
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 1, ?, ?, ?, NOW(), NOW())"
        );

        $stmt->execute([
            $sourceKey,
            $profile['provider_type'],
            $profile['name'],
            $profile['website_url'] ?: null,
            $profile['api_endpoint'] ?: null,
            self::encryptToken((string)($profile['api_token'] ?? '')),
            json_encode(
                $profile['config'] ?? [],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ),
            $enabled ? 1 : 0,
            self::nextSortOrder(),
            'Imported from legacy API settings during provider registry migration.',
            $userId,
            $userId,
        ]);

        return (int)Database::connection()->lastInsertId();
    }

    /**
     * EN: Update the set enabled data for provider profile in the application database.
     * 中文：更新 provider profile 的“set enabled”数据，并访问应用数据库。
     *
     * @param int $id Identifier of the record record or entity. / record 记录或实体的标识 ID。
     * @param bool $enabled Boolean flag controlling the requested behavior. / 控制所请求行为的布尔标志。
     * @param int $userId Application user identifier. / 应用用户 ID。
     *
     * @return void No value is returned. / 无返回值。
     *
     * @throws \RuntimeException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    public static function setEnabled(int $id, bool $enabled, int $userId): void
    {
        if ($enabled) {
            $stmt = Database::connection()->prepare(
                "SELECT verified_at FROM cdsp_provider_profiles WHERE id=?"
            );
            $stmt->execute([$id]);

            if (!$stmt->fetchColumn()) {
                throw new \RuntimeException(
                    'This provider must pass a test before it can be enabled.'
                );
            }
        }

        $stmt = Database::connection()->prepare(
            "UPDATE cdsp_provider_profiles
             SET enabled=?, updated_by=?, updated_at=NOW()
             WHERE id=?"
        );
        $stmt->execute([$enabled ? 1 : 0, $userId, $id]);
    }

    /**
     * EN: Delete or clean the delete by id data for provider profile in the application database.
     * 中文：删除或清理 provider profile 的“delete by id”数据，并访问应用数据库。
     *
     * @param int $id Identifier of the record record or entity. / record 记录或实体的标识 ID。
     *
     * @return void No value is returned. / 无返回值。
     */
    public static function deleteById(int $id): void
    {
        $stmt = Database::connection()->prepare(
            "DELETE FROM cdsp_provider_profiles WHERE id=?"
        );
        $stmt->execute([$id]);
    }

    /**
     * EN: Update the reorder data for provider profile in the application database.
     * 中文：更新 provider profile 的“reorder”数据，并访问应用数据库。
     *
     * @param array $ids Ids value used by this operation. / 本操作使用的“ids”参数值。
     * @param int $userId Application user identifier. / 应用用户 ID。
     *
     * @return void No value is returned. / 无返回值。
     *
     * @throws \RuntimeException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     * @throws \Throwable When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    public static function reorder(array $ids, int $userId): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $all = self::allAdmin();
        $existing = array_map(fn($row) => (int)$row['id'], $all);

        if (count($ids) !== count($existing)
            || array_diff($ids, $existing)
            || array_diff($existing, $ids)) {
            throw new \RuntimeException(
                'Provider order is stale. Refresh the page and try again.'
            );
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                "UPDATE cdsp_provider_profiles
                 SET sort_order=?, updated_by=?, updated_at=NOW()
                 WHERE id=?"
            );

            foreach ($ids as $index => $id) {
                $stmt->execute([($index + 1) * 10, $userId, $id]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * EN: Perform the count data for provider profile in the application database.
     * 中文：执行 provider profile 的“count”数据，并访问应用数据库。
     *
     * @return int Numeric result produced by this operation. / 本操作生成的数字结果。
     */
    public static function count(): int
    {
        if (!self::tableExists()) {
            return 0;
        }

        return (int)Database::connection()
            ->query("SELECT COUNT(*) FROM cdsp_provider_profiles")
            ->fetchColumn();
    }

    /**
     * EN: Perform the next sort order data for provider profile in the application database.
     * 中文：执行 provider profile 的“next sort order”数据，并访问应用数据库。
     *
     * @return int Numeric result produced by this operation. / 本操作生成的数字结果。
     */
    private static function nextSortOrder(): int
    {
        if (!self::tableExists()) {
            return 10;
        }

        $max = (int)Database::connection()
            ->query(
                "SELECT COALESCE(MAX(sort_order),0)
                 FROM cdsp_provider_profiles"
            )
            ->fetchColumn();

        return $max + 10;
    }

    /**
     * EN: Parse or extract the decode config data for provider profile.
     * 中文：解析或提取 provider profile 的“decode config”数据。
     *
     * @param string $json Json value used by this operation. / 本操作使用的“json”参数值。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
    private static function decodeConfig(string $json): array
    {
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    /**
     * EN: Perform the encrypt token data for provider profile.
     * 中文：执行 provider profile 的“encrypt token”数据。
     *
     * @param string $token Authentication, inspection, or operation token being processed. / 正在处理的认证、检查或操作 Token。
     *
     * @return ?string String result produced by this operation, or null when no value is available. / 本操作生成的字符串结果；无可用值时返回 null。
     */
    private static function encryptToken(string $token): ?string
    {
        return $token !== '' ? SecretCrypto::encrypt($token) : null;
    }

    /**
     * EN: Perform the decrypt token data for provider profile.
     * 中文：执行 provider profile 的“decrypt token”数据。
     *
     * @param ?string $encrypted Encrypted value used by this operation. / 本操作使用的“encrypted”参数值。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
     */
    private static function decryptToken(?string $encrypted): string
    {
        return $encrypted ? SecretCrypto::decrypt($encrypted) : '';
    }
}
