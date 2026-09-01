-- File / 文件：database/migrations/006_remove_review_ratings.sql
-- EN: Database migration for a versioned schema/data change.
-- 中文：该文件用于版本化数据库结构或数据迁移。
-- Maintenance / 维护：Keep migrations deterministic and safe to re-check before deployment. / 迁移必须可预测，部署前可安全复核。
-- Rating has been removed from the Admin review UX.
-- Keep the legacy columns nullable for backwards-compatible historical data,
-- but new saves always write NULL.

ALTER TABLE cdsp_post_reviews
    MODIFY rating TINYINT UNSIGNED NULL DEFAULT NULL;

ALTER TABLE cdsp_daily_sales_reviews
    MODIFY rating TINYINT UNSIGNED NULL DEFAULT NULL;

ALTER TABLE cdsp_period_sales_reviews
    MODIFY rating TINYINT UNSIGNED NULL DEFAULT NULL;
