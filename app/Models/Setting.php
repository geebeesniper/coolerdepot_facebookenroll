<?php
/**
 * File / 文件：app/Models/Setting.php
 * EN: Database model and query layer for this domain.
 * 中文：该文件负责此业务域的数据模型与数据库查询。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
namespace App\Models;

use App\Core\Database;
use App\Services\SecretCrypto;

class Setting
{
    /**
     * EN: Retrieves or loads data for `get` (get).
     * 中文：读取或加载 `get`（get）所需的数据。
     */
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

    /**
     * EN: Checks or validates the condition represented by `has` (has).
     * 中文：检查或校验 `has`（has）所表示的条件。
     */
    public static function has(string $key): bool
    {
        $stmt = Database::connection()->prepare(
            "SELECT 1 FROM cdsp_settings WHERE setting_key = ? LIMIT 1"
        );
        $stmt->execute([$key]);

        return (bool)$stmt->fetchColumn();
    }

    /**
     * EN: Updates application state for `set` (set).
     * 中文：更新 `set`（set）对应的应用状态。
     */
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

    /**
     * EN: Removes or cleans data/state for `delete` (delete).
     * 中文：删除或清理 `delete`（delete）相关的数据或状态。
     */
    public static function delete(string $key): void
    {
        $stmt = Database::connection()->prepare(
            "DELETE FROM cdsp_settings WHERE setting_key = ?"
        );
        $stmt->execute([$key]);
    }
}
