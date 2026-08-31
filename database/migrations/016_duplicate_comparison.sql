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
