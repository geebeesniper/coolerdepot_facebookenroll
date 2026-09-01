<?php
/**
 * File / 文件：app/Models/Setting.php
 * EN: Defines the Setting database model and its persistence/query helpers.
 * 中文：定义 Setting 数据库模型及其持久化与查询辅助逻辑。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Models;

use App\Core\Database;
use App\Services\SecretCrypto;

/**
 * EN: Database model for setting records, queries, and persistence operations.
 * 中文：负责 setting 记录、查询及持久化操作的数据库 Model。
 */
class Setting
{
    /**
     * EN: Get an application setting by key and decrypt it when it is stored as a secret.
     * 中文：按键读取应用设置；若该设置以敏感值保存，则在返回前进行解密。
     *
     * @param string $key Key used to identify the requested value. / 用于标识目标值的键。
     * @param ?string $default Fallback value used when the requested value is unavailable. / 目标值不可用时使用的默认值。
     *
     * @return ?string String result produced by this operation, or null when no value is available. / 本操作生成的字符串结果；无可用值时返回 null。
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
     * EN: Check whether an application setting exists for the supplied key.
     * 中文：检查指定键的应用设置是否存在。
     *
     * @param string $key Key used to identify the requested value. / 用于标识目标值的键。
     *
     * @return bool True when the requested condition is satisfied; otherwise false. / 请求条件满足时返回 true，否则返回 false。
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
     * EN: Create or update an application setting and encrypt secret values before storage.
     * 中文：创建或更新应用设置，并在保存敏感值前进行加密。
     *
     * @param string $key Key used to identify the requested value. / 用于标识目标值的键。
     * @param string $value Value processed or stored by this operation. / 本操作处理或保存的值。
     * @param int $updatedBy User ID responsible for the update. / 执行本次更新的用户 ID。
     * @param bool $secret Whether the value must be handled as encrypted secret data. / 是否将该值作为加密敏感数据处理。
     *
     * @return void No value is returned. / 无返回值。
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
     * EN: Delete an application setting identified by its key.
     * 中文：删除指定键对应的应用设置。
     *
     * @param string $key Key used to identify the requested value. / 用于标识目标值的键。
     *
     * @return void No value is returned. / 无返回值。
     */
    public static function delete(string $key): void
    {
        $stmt = Database::connection()->prepare(
            "DELETE FROM cdsp_settings WHERE setting_key = ?"
        );
        $stmt->execute([$key]);
    }
}
