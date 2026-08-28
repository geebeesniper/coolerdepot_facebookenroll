<?php
namespace App\Models;

use App\Core\Database;
use App\Services\SecretCrypto;

class Setting
{
    public static function get(string $key, ?string $default = null): ?string
    {
        $stmt = Database::connection()->prepare(
            "SELECT setting_value, is_secret
             FROM cdsp_settings
             WHERE setting_key = ?
             LIMIT 1"
        );
        $stmt->execute([$key]);
        $row = $stmt->fetch();

        if (!$row) {
            return $default;
        }

        if ((int)$row['is_secret'] === 1) {
            return SecretCrypto::decrypt((string)$row['setting_value']);
        }

        return (string)$row['setting_value'];
    }

    public static function has(string $key): bool
    {
        $stmt = Database::connection()->prepare(
            "SELECT 1 FROM cdsp_settings WHERE setting_key = ? LIMIT 1"
        );
        $stmt->execute([$key]);

        return (bool)$stmt->fetchColumn();
    }

    public static function set(string $key, string $value, int $updatedBy, bool $secret = false): void
    {
        $stored = $secret ? SecretCrypto::encrypt($value) : $value;

        $stmt = Database::connection()->prepare(
            "INSERT INTO cdsp_settings
             (setting_key, setting_value, is_secret, updated_by, updated_at)
             VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                is_secret = VALUES(is_secret),
                updated_by = VALUES(updated_by),
                updated_at = NOW()"
        );

        $stmt->execute([$key, $stored, $secret ? 1 : 0, $updatedBy]);
    }

    public static function delete(string $key): void
    {
        $stmt = Database::connection()->prepare(
            "DELETE FROM cdsp_settings WHERE setting_key = ?"
        );
        $stmt->execute([$key]);
    }
}
