<?php
/**
 * File / 文件：scripts/migrate_v0_2_06_api.php
 * EN: CLI maintenance/deployment script for migrate v0 2 06 api.
 * 中文：用于 migrate v0 2 06 api 的命令行维护/部署脚本。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
$config = require dirname(__DIR__) . '/config/bootstrap.php';

use App\Core\Database;

/**
 * EN: Execute the run api migration v0206 helper used by this database migration.
 * 中文：执行 当前数据库迁移使用的“run api migration v0206”辅助操作。
 *
 * @return void No value is returned. / 无返回值。
 */
function runApiMigrationV0206(): void
{
    $pdo = Database::connection();
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS cdsp_api_tokens (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL,
            source VARCHAR(50) NOT NULL DEFAULT 'signed_exchange',
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(500) NULL,
            created_at DATETIME NOT NULL,
            last_used_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            revoked_at DATETIME NULL,
            PRIMARY KEY(id),
            UNIQUE KEY uq_api_token_hash(token_hash),
            KEY idx_api_token_user(user_id),
            KEY idx_api_token_expiry(expires_at,revoked_at),
            CONSTRAINT fk_api_token_user FOREIGN KEY(user_id) REFERENCES cdsp_users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

runApiMigrationV0206();
echo "v0.2.06 API migration complete. Existing business data was not changed.\n";
