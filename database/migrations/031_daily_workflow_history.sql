-- File / 文件：database/migrations/031_daily_workflow_history.sql
-- EN: Add date-effective Sales daily targets and Admin daily completion tracking.
-- 中文：新增按日期生效的 Sales Daily Target 历史与 Admin 每日任务完成记录。
CREATE TABLE IF NOT EXISTS cdsp_sales_daily_target_history (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 sales_user_id INT UNSIGNED NOT NULL,
 effective_date DATE NOT NULL,
 daily_post_target SMALLINT UNSIGNED NOT NULL,
 changed_by INT UNSIGNED NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 PRIMARY KEY(id),
 UNIQUE KEY uq_sales_daily_target_date(sales_user_id,effective_date),
 KEY idx_sales_daily_target_lookup(sales_user_id,effective_date),
 CONSTRAINT fk_sales_daily_target_user FOREIGN KEY(sales_user_id) REFERENCES cdsp_users(id),
 CONSTRAINT fk_sales_daily_target_admin FOREIGN KEY(changed_by) REFERENCES cdsp_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cdsp_daily_sales_completions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 sales_user_id INT UNSIGNED NOT NULL,
 work_date DATE NOT NULL,
 admin_user_id INT UNSIGNED NOT NULL,
 completed_at DATETIME NOT NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 PRIMARY KEY(id),
 UNIQUE KEY uq_daily_sales_completion(sales_user_id,work_date),
 KEY idx_daily_sales_completion_date(work_date,sales_user_id),
 CONSTRAINT fk_daily_sales_completion_sales FOREIGN KEY(sales_user_id) REFERENCES cdsp_users(id),
 CONSTRAINT fk_daily_sales_completion_admin FOREIGN KEY(admin_user_id) REFERENCES cdsp_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO cdsp_sales_daily_target_history(
 sales_user_id,effective_date,daily_post_target,changed_by,created_at,updated_at
)
SELECT id,'1970-01-01',COALESCE(NULLIF(daily_post_target,0),10),NULL,NOW(),NOW()
FROM cdsp_users
WHERE role='sales';
