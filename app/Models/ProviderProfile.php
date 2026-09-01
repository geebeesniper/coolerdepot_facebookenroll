<?php
/**
 * File / 文件：app/Models/ProviderProfile.php
 * EN: Database model and query layer for this domain.
 * 中文：该文件负责此业务域的数据模型与数据库查询。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
namespace App\Models;

use App\Core\Database;
use App\Services\SecretCrypto;

class ProviderProfile
{
    /**
     * EN: Implements the application operation `registryEnabled` (registry Enabled).
     * 中文：实现应用操作 `registryEnabled`（registry Enabled）。
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
     * EN: Implements the application operation `tableExists` (table Exists).
     * 中文：实现应用操作 `tableExists`（table Exists）。
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
     * EN: Implements the application operation `allAdmin` (all Admin).
     * 中文：实现应用操作 `allAdmin`（all Admin）。
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
     * EN: Implements the application operation `activeVerifiedWithSecrets` (active Verified With Secrets).
     * 中文：实现应用操作 `activeVerifiedWithSecrets`（active Verified With Secrets）。
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
     * EN: Retrieves or loads data for `findWithSecret` (find With Secret).
     * 中文：读取或加载 `findWithSecret`（find With Secret）所需的数据。
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
     * EN: Creates or persists the `createVerified` operation (create Verified).
     * 中文：创建或持久化 `createVerified`（create Verified）操作。
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
     * EN: Implements the application operation `importLegacy` (import Legacy).
     * 中文：实现应用操作 `importLegacy`（import Legacy）。
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
     * EN: Updates application state for `setEnabled` (set Enabled).
     * 中文：更新 `setEnabled`（set Enabled）对应的应用状态。
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
     * EN: Removes or cleans data/state for `deleteById` (delete By Id).
     * 中文：删除或清理 `deleteById`（delete By Id）相关的数据或状态。
     */
    public static function deleteById(int $id): void
    {
        $stmt = Database::connection()->prepare(
            "DELETE FROM cdsp_provider_profiles WHERE id=?"
        );
        $stmt->execute([$id]);
    }

    /**
     * EN: Implements the application operation `reorder` (reorder).
     * 中文：实现应用操作 `reorder`（reorder）。
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
     * EN: Implements the application operation `count` (count).
     * 中文：实现应用操作 `count`（count）。
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
     * EN: Implements the application operation `nextSortOrder` (next Sort Order).
     * 中文：实现应用操作 `nextSortOrder`（next Sort Order）。
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
     * EN: Implements the application operation `decodeConfig` (decode Config).
     * 中文：实现应用操作 `decodeConfig`（decode Config）。
     */
    private static function decodeConfig(string $json): array
    {
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    /**
     * EN: Implements the application operation `encryptToken` (encrypt Token).
     * 中文：实现应用操作 `encryptToken`（encrypt Token）。
     */
    private static function encryptToken(string $token): ?string
    {
        return $token !== '' ? SecretCrypto::encrypt($token) : null;
    }

    /**
     * EN: Implements the application operation `decryptToken` (decrypt Token).
     * 中文：实现应用操作 `decryptToken`（decrypt Token）。
     */
    private static function decryptToken(?string $encrypted): string
    {
        return $encrypted ? SecretCrypto::decrypt($encrypted) : '';
    }
}
