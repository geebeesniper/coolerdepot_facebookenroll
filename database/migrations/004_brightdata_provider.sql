CREATE TABLE IF NOT EXISTS cdsp_settings (
 setting_key VARCHAR(100) NOT NULL,
 setting_value MEDIUMTEXT NOT NULL,
 is_secret TINYINT(1) NOT NULL DEFAULT 0,
 updated_by INT UNSIGNED NOT NULL,
 updated_at DATETIME NOT NULL,
 PRIMARY KEY(setting_key),
 CONSTRAINT fk_setting_updated_by FOREIGN KEY(updated_by) REFERENCES cdsp_users(id)
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
