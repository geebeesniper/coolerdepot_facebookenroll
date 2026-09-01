<?php
/**
 * File / 文件：scripts/migrate_brightdata_provider.php
 * EN: CLI maintenance/deployment script for migrate brightdata provider.
 * 中文：用于 migrate brightdata provider 的命令行维护/部署脚本。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
$config = require dirname(__DIR__) . '/config/bootstrap.php';

use App\Core\Database;

$pdo = Database::connection();

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS cdsp_settings (
        setting_key VARCHAR(100) NOT NULL,
        setting_value MEDIUMTEXT NOT NULL,
        is_secret TINYINT(1) NOT NULL DEFAULT 0,
        updated_by INT UNSIGNED NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY(setting_key),
        CONSTRAINT fk_setting_updated_by
            FOREIGN KEY(updated_by) REFERENCES cdsp_users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS cdsp_fetch_jobs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        requested_by_user_id INT UNSIGNED NOT NULL,
        platform ENUM('facebook','offerup','craigslist') NOT NULL,
        submitted_url TEXT NOT NULL,
        external_post_id VARCHAR(120) NULL,
        provider VARCHAR(50) NOT NULL,
        provider_job_id VARCHAR(191) NULL,
        status ENUM('starting','running','ready','failed') NOT NULL DEFAULT 'starting',
        provider_http_status SMALLINT UNSIGNED NULL,
        response_json MEDIUMTEXT NULL,
        error_message VARCHAR(1000) NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        completed_at DATETIME NULL,
        PRIMARY KEY(id),
        KEY idx_fetch_jobs_item(platform,external_post_id,status,completed_at),
        KEY idx_fetch_jobs_status(status,created_at),
        CONSTRAINT fk_fetch_jobs_user
            FOREIGN KEY(requested_by_user_id) REFERENCES cdsp_users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

echo "Bright Data provider migration complete.\n";
