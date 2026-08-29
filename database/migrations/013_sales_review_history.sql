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
 PRIMARY KEY(id),
 KEY idx_sales_review_history_period(sales_user_id,period_type,period_start,created_at,id),
 CONSTRAINT fk_sales_review_history_sales FOREIGN KEY(sales_user_id) REFERENCES cdsp_users(id),
 CONSTRAINT fk_sales_review_history_admin FOREIGN KEY(admin_user_id) REFERENCES cdsp_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

