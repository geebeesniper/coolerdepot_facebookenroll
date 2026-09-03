-- V0.2.29 Marketplace verification process locks.
-- V0.2.29 Marketplace 验证进程锁。
CREATE TABLE IF NOT EXISTS cdsp_inspection_locks (
    sales_user_id INT UNSIGNED NOT NULL PRIMARY KEY,
    lock_token CHAR(64) NOT NULL,
    platform VARCHAR(32) NULL,
    url_hash CHAR(64) NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_cdsp_inspection_locks_started_at (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
