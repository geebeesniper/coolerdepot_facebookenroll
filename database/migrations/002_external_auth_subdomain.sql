-- Prefix-aware migration for cdsp_ tables.
-- ONE-TIME migration for an existing database. Fresh installs use database/schema.sql.
ALTER TABLE cdsp_users
 ADD COLUMN external_user_id VARCHAR(191) NULL AFTER sales_id,
 MODIFY COLUMN password_hash VARCHAR(255) NULL,
 ADD COLUMN auth_source VARCHAR(50) NOT NULL DEFAULT 'local' AFTER active,
 ADD COLUMN last_handoff_at DATETIME NULL AFTER auth_source,
 ADD UNIQUE KEY uq_users_external_user_id (external_user_id);

CREATE TABLE cdsp_auth_handoffs (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 nonce VARCHAR(128) NOT NULL,
 user_id INT UNSIGNED NULL,
 external_user_id VARCHAR(191) NOT NULL,
 sales_id INT UNSIGNED NULL,
 display_name VARCHAR(150) NOT NULL,
 role ENUM('sales','admin') NOT NULL,
 source_ip VARCHAR(45) NULL,
 payload_json TEXT NULL,
 accepted_at DATETIME NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_auth_handoff_nonce(nonce), KEY idx_auth_handoff_user(user_id), KEY idx_auth_handoff_external(external_user_id),
 CONSTRAINT fk_auth_handoff_user FOREIGN KEY(user_id) REFERENCES cdsp_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE cdsp_auth_sessions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 user_id INT UNSIGNED NOT NULL,
 token_hash CHAR(64) NOT NULL,
 source VARCHAR(50) NOT NULL,
 ip_address VARCHAR(45) NULL,
 user_agent VARCHAR(500) NULL,
 created_at DATETIME NOT NULL,
 last_seen_at DATETIME NOT NULL,
 expires_at DATETIME NOT NULL,
 revoked_at DATETIME NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_auth_session_token(token_hash), KEY idx_auth_session_user(user_id), KEY idx_auth_session_expiry(expires_at,revoked_at),
 CONSTRAINT fk_auth_session_user FOREIGN KEY(user_id) REFERENCES cdsp_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
