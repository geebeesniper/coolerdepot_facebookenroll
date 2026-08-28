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

INSERT IGNORE INTO cdsp_post_review_history(
 post_id,
 admin_user_id,
 decision,
 legacy_review_id,
 created_at
)
SELECT
 post_id,
 admin_user_id,
 decision,
 id,
 COALESCE(reviewed_at,updated_at,created_at,NOW())
FROM cdsp_post_reviews
WHERE decision IN ('good','bad');
