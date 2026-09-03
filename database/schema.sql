-- File / 文件：database/schema.sql
-- EN: Database schema or database-support source.
-- 中文：该文件提供数据库结构或数据库辅助定义。
-- Maintenance / 维护：Keep migrations deterministic and safe to re-check before deployment. / 迁移必须可预测，部署前可安全复核。
-- CoolerDepot Sales Post Tracker
-- Table prefix: cdsp_

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS cdsp_users (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT,
 sales_id INT UNSIGNED NULL,
 external_user_id VARCHAR(191) NULL,
 username VARCHAR(100) NOT NULL,
 password_hash VARCHAR(255) NULL,
 display_name VARCHAR(150) NOT NULL DEFAULT '',
 role ENUM('sales','admin') NOT NULL DEFAULT 'sales',
 active TINYINT(1) NOT NULL DEFAULT 1,
 daily_post_target SMALLINT UNSIGNED NOT NULL DEFAULT 10,
 auth_source VARCHAR(50) NOT NULL DEFAULT 'local',
 last_handoff_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 PRIMARY KEY(id),
 UNIQUE KEY uq_users_username(username),
 UNIQUE KEY uq_users_sales_id(sales_id),
 UNIQUE KEY uq_users_external_user_id(external_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS cdsp_auth_handoffs (
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
 PRIMARY KEY(id),
 UNIQUE KEY uq_auth_handoff_nonce(nonce),
 KEY idx_auth_handoff_user(user_id),
 KEY idx_auth_handoff_external(external_user_id),
 CONSTRAINT fk_auth_handoff_user FOREIGN KEY(user_id) REFERENCES cdsp_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cdsp_auth_sessions (
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
 PRIMARY KEY(id),
 UNIQUE KEY uq_auth_session_token(token_hash),
 KEY idx_auth_session_user(user_id),
 KEY idx_auth_session_expiry(expires_at,revoked_at),
 CONSTRAINT fk_auth_session_user FOREIGN KEY(user_id) REFERENCES cdsp_users(id)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cdsp_api_tokens (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cdsp_post_inspections (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 token CHAR(64) NOT NULL,
 sales_user_id INT UNSIGNED NOT NULL,
 platform ENUM('facebook','offerup','craigslist') NOT NULL,
 submitted_url TEXT NOT NULL,
 resolved_url TEXT NULL,
 canonical_url TEXT NULL,
 external_post_id VARCHAR(120) NULL,
 title VARCHAR(500) NULL,
 description MEDIUMTEXT NULL,
 published_at DATETIME NULL,
 published_date DATE NULL,
 fetched_at DATETIME NULL,
 verification_status ENUM('verified','manual_pending','failed') NOT NULL,
 failure_code VARCHAR(80) NULL,
 failure_message VARCHAR(500) NULL,
 raw_meta_json MEDIUMTEXT NULL,
 created_at DATETIME NOT NULL,
 expires_at DATETIME NOT NULL,
 consumed_at DATETIME NULL,
 PRIMARY KEY(id),
 UNIQUE KEY uq_inspection_token(token),
 KEY idx_inspection_user(sales_user_id,verification_status),
 CONSTRAINT fk_inspection_user FOREIGN KEY(sales_user_id) REFERENCES cdsp_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cdsp_sales_posts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 sales_user_id INT UNSIGNED NOT NULL,
 platform ENUM('facebook','offerup','craigslist') NOT NULL,
 submitted_url TEXT NOT NULL,
 resolved_url TEXT NOT NULL,
 canonical_url TEXT NOT NULL,
 canonical_url_hash CHAR(64) NOT NULL,
 external_post_id VARCHAR(120) NULL,
 platform_account_id VARCHAR(191) NULL,
 platform_account_name VARCHAR(255) NULL,
 platform_account_url TEXT NULL,
 platform_account_key_hash CHAR(64) NULL,
 title VARCHAR(500) NOT NULL,
 normalized_title_hash CHAR(64) NOT NULL,
 description MEDIUMTEXT NULL,
 description_hash CHAR(64) NOT NULL,
 published_at DATETIME NOT NULL,
 published_date DATE NOT NULL,
 fetched_at DATETIME NOT NULL,
 fetched_image_url TEXT NULL,
 verification_status ENUM('verified','manual_pending','failed') NOT NULL DEFAULT 'verified',
 admin_review_status ENUM('good','bad') NULL DEFAULT NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 deleted_at DATETIME NULL,
 deleted_by INT UNSIGNED NULL,
 PRIMARY KEY(id),
 KEY idx_post_canonical_hash(canonical_url_hash),
 KEY idx_post_external(platform,external_post_id),
 KEY idx_post_platform_account(platform,platform_account_key_hash),
 KEY idx_post_sales_date(sales_user_id,created_at),
 KEY idx_post_title(sales_user_id,platform,normalized_title_hash),
 KEY idx_post_desc(sales_user_id,platform,description_hash),
 KEY idx_post_review(admin_review_status,created_at),
 CONSTRAINT fk_post_sales FOREIGN KEY(sales_user_id) REFERENCES cdsp_users(id),
 CONSTRAINT fk_post_deleted_by FOREIGN KEY(deleted_by) REFERENCES cdsp_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cdsp_post_reviews (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 post_id BIGINT UNSIGNED NOT NULL,
 admin_user_id INT UNSIGNED NOT NULL,
 decision ENUM('good','bad') NOT NULL,
 rating TINYINT UNSIGNED NULL DEFAULT NULL,
 note TEXT NULL,
 reviewed_at DATETIME NOT NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 PRIMARY KEY(id),
 UNIQUE KEY uq_post_review(post_id),
 CONSTRAINT fk_review_post FOREIGN KEY(post_id) REFERENCES cdsp_sales_posts(id),
 CONSTRAINT fk_review_admin FOREIGN KEY(admin_user_id) REFERENCES cdsp_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cdsp_post_review_history (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 post_id BIGINT UNSIGNED NOT NULL,
 admin_user_id INT UNSIGNED NOT NULL,
 decision ENUM('good','bad') NOT NULL,
 legacy_review_id BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL,
 PRIMARY KEY(id),
 UNIQUE KEY uq_review_history_legacy(legacy_review_id),
 KEY idx_review_history_post(post_id,created_at,id),
 CONSTRAINT fk_review_history_post
   FOREIGN KEY(post_id) REFERENCES cdsp_sales_posts(id),
 CONSTRAINT fk_review_history_admin
   FOREIGN KEY(admin_user_id) REFERENCES cdsp_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cdsp_post_review_comments (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 post_id BIGINT UNSIGNED NOT NULL,
 admin_user_id INT UNSIGNED NOT NULL,
 body_html MEDIUMTEXT NOT NULL,
 legacy_review_id BIGINT UNSIGNED NULL,
 updated_by INT UNSIGNED NULL,
 deleted_by INT UNSIGNED NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 deleted_at DATETIME NULL,
 PRIMARY KEY(id),
 UNIQUE KEY uq_post_comment_legacy(legacy_review_id),
 KEY idx_post_comment_post(post_id,deleted_at,created_at),
 CONSTRAINT fk_post_comment_post
   FOREIGN KEY(post_id) REFERENCES cdsp_sales_posts(id),
 CONSTRAINT fk_post_comment_admin
   FOREIGN KEY(admin_user_id) REFERENCES cdsp_users(id),
 CONSTRAINT fk_post_comment_updated_by
   FOREIGN KEY(updated_by) REFERENCES cdsp_users(id),
 CONSTRAINT fk_post_comment_deleted_by
   FOREIGN KEY(deleted_by) REFERENCES cdsp_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cdsp_daily_sales_reviews (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 sales_user_id INT UNSIGNED NOT NULL,
 work_date DATE NOT NULL,
 admin_user_id INT UNSIGNED NOT NULL,
 rating TINYINT UNSIGNED NULL DEFAULT NULL,
 note TEXT NULL,
 reviewed_at DATETIME NOT NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 PRIMARY KEY(id),
 UNIQUE KEY uq_daily_review(sales_user_id,work_date),
 CONSTRAINT fk_daily_sales FOREIGN KEY(sales_user_id) REFERENCES cdsp_users(id),
 CONSTRAINT fk_daily_admin FOREIGN KEY(admin_user_id) REFERENCES cdsp_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cdsp_period_sales_reviews (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 sales_user_id INT UNSIGNED NOT NULL,
 period_type ENUM('week','month') NOT NULL,
 period_start DATE NOT NULL,
 period_end DATE NOT NULL,
 admin_user_id INT UNSIGNED NOT NULL,
 rating TINYINT UNSIGNED NULL DEFAULT NULL,
 note TEXT NULL,
 reviewed_at DATETIME NOT NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 PRIMARY KEY(id),
 UNIQUE KEY uq_period_review(sales_user_id,period_type,period_start),
 CONSTRAINT fk_period_sales FOREIGN KEY(sales_user_id) REFERENCES cdsp_users(id),
 CONSTRAINT fk_period_admin FOREIGN KEY(admin_user_id) REFERENCES cdsp_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cdsp_sales_review_history (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 sales_user_id INT UNSIGNED NOT NULL,
 period_type ENUM('day','week','month') NOT NULL,
 period_start DATE NOT NULL,
 period_end DATE NOT NULL,
 admin_user_id INT UNSIGNED NOT NULL,
 rating TINYINT UNSIGNED NULL DEFAULT NULL,
 note TEXT NULL,
 created_at DATETIME NOT NULL,
 deleted_at DATETIME NULL,
 deleted_by INT UNSIGNED NULL,
 PRIMARY KEY(id),
 KEY idx_sales_review_history_period(sales_user_id,period_type,period_start,created_at,id),
 KEY idx_sales_review_history_deleted(deleted_at),
 CONSTRAINT fk_sales_review_history_sales FOREIGN KEY(sales_user_id) REFERENCES cdsp_users(id),
 CONSTRAINT fk_sales_review_history_admin FOREIGN KEY(admin_user_id) REFERENCES cdsp_users(id),
 CONSTRAINT fk_sales_review_history_deleted_by FOREIGN KEY(deleted_by) REFERENCES cdsp_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cdsp_review_attachments (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 entity_type ENUM('post_review','daily_review','period_review','post_note','post_comment') NOT NULL,
 entity_id BIGINT UNSIGNED NOT NULL,
 history_id BIGINT UNSIGNED NULL,
 uploaded_by INT UNSIGNED NOT NULL,
 original_name VARCHAR(255) NOT NULL,
 stored_path VARCHAR(500) NOT NULL,
 mime_type VARCHAR(100) NOT NULL,
 size_bytes INT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL,
 deleted_at DATETIME NULL,
 deleted_by INT UNSIGNED NULL,
 PRIMARY KEY(id),
 KEY idx_attachment_entity(entity_type,entity_id),
 KEY idx_attachment_history(history_id),
 KEY idx_attachment_deleted(deleted_at),
 CONSTRAINT fk_attachment_user FOREIGN KEY(uploaded_by) REFERENCES cdsp_users(id),
 CONSTRAINT fk_attachment_deleted_by FOREIGN KEY(deleted_by) REFERENCES cdsp_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cdsp_deletion_requests (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 post_id BIGINT UNSIGNED NOT NULL,
 requested_by INT UNSIGNED NOT NULL,
 reason VARCHAR(1000) NOT NULL,
 status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
 reviewed_by INT UNSIGNED NULL,
 reviewed_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 PRIMARY KEY(id),
 KEY idx_deletion_status(status,created_at),
 CONSTRAINT fk_delete_post FOREIGN KEY(post_id) REFERENCES cdsp_sales_posts(id),
 CONSTRAINT fk_delete_requester FOREIGN KEY(requested_by) REFERENCES cdsp_users(id),
 CONSTRAINT fk_delete_reviewer FOREIGN KEY(reviewed_by) REFERENCES cdsp_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



CREATE TABLE IF NOT EXISTS cdsp_settings (
 setting_key VARCHAR(100) NOT NULL,
 setting_value MEDIUMTEXT NOT NULL,
 is_secret TINYINT(1) NOT NULL DEFAULT 0,
 updated_by INT UNSIGNED NOT NULL,
 updated_at DATETIME NOT NULL,
 PRIMARY KEY(setting_key),
 CONSTRAINT fk_setting_updated_by FOREIGN KEY(updated_by) REFERENCES cdsp_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cdsp_provider_profiles (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT,
 source_key VARCHAR(100) NULL,
 provider_type VARCHAR(50) NOT NULL,
 name VARCHAR(100) NOT NULL,
 website_url VARCHAR(500) NULL,
 api_endpoint VARCHAR(1000) NULL,
 token_encrypted MEDIUMTEXT NULL,
 config_json MEDIUMTEXT NOT NULL,
 enabled TINYINT(1) NOT NULL DEFAULT 1,
 sort_order INT NOT NULL DEFAULT 10,
 verified_at DATETIME NULL,
 last_tested_at DATETIME NULL,
 last_test_ok TINYINT(1) NULL,
 last_test_message VARCHAR(1000) NULL,
 created_by INT UNSIGNED NOT NULL,
 updated_by INT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 PRIMARY KEY(id),
 UNIQUE KEY uq_provider_source(source_key),
 KEY idx_provider_chain(enabled,verified_at,sort_order,id),
 CONSTRAINT fk_provider_created_by FOREIGN KEY(created_by) REFERENCES cdsp_users(id),
 CONSTRAINT fk_provider_updated_by FOREIGN KEY(updated_by) REFERENCES cdsp_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cdsp_fetch_jobs (
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
 CONSTRAINT fk_fetch_jobs_user FOREIGN KEY(requested_by_user_id) REFERENCES cdsp_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE IF NOT EXISTS cdsp_post_image_fingerprints (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 post_id BIGINT UNSIGNED NOT NULL,
 image_url TEXT NOT NULL,
 image_url_hash CHAR(64) NOT NULL,
 sha256 CHAR(64) NOT NULL,
 dhash CHAR(16) NULL,
 checked_at DATETIME NOT NULL,
 PRIMARY KEY(id),
 UNIQUE KEY uq_post_image(post_id,image_url_hash),
 KEY idx_image_sha256(sha256),
 KEY idx_image_post(post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cdsp_website_references (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 source_host VARCHAR(191) NOT NULL,
 page_url TEXT NOT NULL,
 page_url_hash CHAR(64) NOT NULL,
 title VARCHAR(500) NOT NULL,
 description MEDIUMTEXT NULL,
 title_hash CHAR(64) NOT NULL,
 image_url TEXT NULL,
 sha256 CHAR(64) NULL,
 dhash CHAR(16) NULL,
 imported_at DATETIME NOT NULL,
 checked_at DATETIME NULL,
 PRIMARY KEY(id),
 UNIQUE KEY uq_website_page(page_url_hash),
 KEY idx_website_title(title_hash),
 KEY idx_website_image(sha256)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Marketplace verification process lock registry. / Marketplace 验证进程锁注册表。
CREATE TABLE IF NOT EXISTS cdsp_inspection_locks (
 sales_user_id INT UNSIGNED NOT NULL,
 lock_token CHAR(64) NOT NULL,
 platform VARCHAR(32) NULL,
 url_hash CHAR(64) NULL,
 started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(sales_user_id),
 KEY idx_cdsp_inspection_locks_started_at(started_at),
 CONSTRAINT fk_inspection_lock_sales_user FOREIGN KEY(sales_user_id) REFERENCES cdsp_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- V0.2.95 background Marketplace verification queue. / V0.2.95 Marketplace 后台验证队列。
CREATE TABLE IF NOT EXISTS cdsp_post_verification_queue (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 sales_user_id INT UNSIGNED NOT NULL,
 platform ENUM('facebook','offerup','craigslist') NULL,
 submitted_url TEXT NOT NULL,
 canonical_url TEXT NULL,
 canonical_url_hash CHAR(64) NULL,
 external_post_id VARCHAR(120) NULL,
 status ENUM('waiting','verifying','passed','failed','duplicate','invalid') NOT NULL DEFAULT 'waiting',
 attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
 worker_token CHAR(64) NULL,
 result_title VARCHAR(500) NULL,
 result_description MEDIUMTEXT NULL,
 result_published_at DATETIME NULL,
 result_published_date DATE NULL,
 result_image_url TEXT NULL,
 result_platform_account_name VARCHAR(255) NULL,
 failure_code VARCHAR(80) NULL,
 failure_message VARCHAR(1000) NULL,
 duplicate_url TEXT NULL,
 duplicate_kind VARCHAR(80) NULL,
 result_json MEDIUMTEXT NULL,
 post_id BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL,
 queued_at DATETIME NOT NULL,
 started_at DATETIME NULL,
 finished_at DATETIME NULL,
 updated_at DATETIME NOT NULL,
 PRIMARY KEY(id),
 KEY idx_vq_sales_status(sales_user_id,status,updated_at),
 KEY idx_vq_status(status,queued_at),
 KEY idx_vq_external(platform,external_post_id,status),
 KEY idx_vq_url(sales_user_id,platform,canonical_url_hash,status),
 KEY idx_vq_post(post_id),
 CONSTRAINT fk_vq_sales FOREIGN KEY(sales_user_id) REFERENCES cdsp_users(id) ON DELETE CASCADE,
 CONSTRAINT fk_vq_post FOREIGN KEY(post_id) REFERENCES cdsp_sales_posts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cdsp_post_verification_queue_history (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 queue_id BIGINT UNSIGNED NOT NULL,
 actor_user_id INT UNSIGNED NULL,
 event_type VARCHAR(80) NOT NULL,
 from_status VARCHAR(32) NULL,
 to_status VARCHAR(32) NULL,
 message VARCHAR(1000) NULL,
 created_at DATETIME NOT NULL,
 PRIMARY KEY(id),
 KEY idx_vqh_queue(queue_id,created_at),
 CONSTRAINT fk_vqh_queue FOREIGN KEY(queue_id) REFERENCES cdsp_post_verification_queue(id) ON DELETE CASCADE,
 CONSTRAINT fk_vqh_actor FOREIGN KEY(actor_user_id) REFERENCES cdsp_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
