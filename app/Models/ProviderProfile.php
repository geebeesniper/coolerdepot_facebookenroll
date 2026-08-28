<?php
namespace App\Models;

use App\Core\Database;
use App\Services\SecretCrypto;

class ProviderProfile
{
    public static function registryEnabled(): bool
    {
        try {
            return Setting::get('provider_registry_enabled', '0') === '1'
                && self::tableExists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function tableExists(): bool
    {
        try {
            $stmt = Database::connection()->query(
                "SHOW TABLES LIKE 'cdsp_provider_profiles'"
            );
            return (bool)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }

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

    public static function deleteById(int $id): void
    {
        $stmt = Database::connection()->prepare(
            "DELETE FROM cdsp_provider_profiles WHERE id=?"
        );
        $stmt->execute([$id]);
    }

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

    public static function count(): int
    {
        if (!self::tableExists()) {
            return 0;
        }

        return (int)Database::connection()
            ->query("SELECT COUNT(*) FROM cdsp_provider_profiles")
            ->fetchColumn();
    }

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

    private static function decodeConfig(string $json): array
    {
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    private static function encryptToken(string $token): ?string
    {
        return $token !== '' ? SecretCrypto::encrypt($token) : null;
    }

    private static function decryptToken(?string $encrypted): string
    {
        return $encrypted ? SecretCrypto::decrypt($encrypted) : '';
    }
}
