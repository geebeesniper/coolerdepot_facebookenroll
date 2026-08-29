<?php
$config = require dirname(__DIR__) . '/config/bootstrap.php';
use App\Core\Database;
$pdo=Database::connection();
$pdo->exec("CREATE TABLE IF NOT EXISTS cdsp_sales_review_history (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$daily=$pdo->exec("INSERT INTO cdsp_sales_review_history(sales_user_id,period_type,period_start,period_end,admin_user_id,rating,note,created_at)
 SELECT r.sales_user_id,'day',r.work_date,r.work_date,r.admin_user_id,r.rating,r.note,COALESCE(r.reviewed_at,r.updated_at,r.created_at,NOW())
 FROM cdsp_daily_sales_reviews r
 WHERE NOT EXISTS (SELECT 1 FROM cdsp_sales_review_history h WHERE h.sales_user_id=r.sales_user_id AND h.period_type='day' AND h.period_start=r.work_date)");
$period=$pdo->exec("INSERT INTO cdsp_sales_review_history(sales_user_id,period_type,period_start,period_end,admin_user_id,rating,note,created_at)
 SELECT r.sales_user_id,r.period_type,r.period_start,r.period_end,r.admin_user_id,r.rating,r.note,COALESCE(r.reviewed_at,r.updated_at,r.created_at,NOW())
 FROM cdsp_period_sales_reviews r
 WHERE NOT EXISTS (SELECT 1 FROM cdsp_sales_review_history h WHERE h.sales_user_id=r.sales_user_id AND h.period_type=r.period_type AND h.period_start=r.period_start)");
echo 'Sales review history table is ready.'.PHP_EOL;
echo 'Legacy daily reviews backfilled: '.(int)$daily.PHP_EOL;
echo 'Legacy weekly/monthly reviews backfilled: '.(int)$period.PHP_EOL;
echo 'v0.1.43 migration complete.'.PHP_EOL;
