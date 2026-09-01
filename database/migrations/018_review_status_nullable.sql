-- File / 文件：database/migrations/018_review_status_nullable.sql
-- EN: Database migration for a versioned schema/data change.
-- 中文：该文件用于版本化数据库结构或数据迁移。
-- Maintenance / 维护：Keep migrations deterministic and safe to re-check before deployment. / 迁移必须可预测，部署前可安全复核。
-- v0.1.72 compatibility fix for production databases that still have the
-- legacy NOT NULL review status and/or pending/approved/rejected enum values.
ALTER TABLE cdsp_sales_posts
    MODIFY admin_review_status
    ENUM('pending','approved','rejected','good','bad') NULL DEFAULT NULL;

UPDATE cdsp_sales_posts
SET admin_review_status = CASE admin_review_status
    WHEN 'approved' THEN 'good'
    WHEN 'rejected' THEN 'bad'
    WHEN 'pending' THEN NULL
    ELSE admin_review_status
END;

ALTER TABLE cdsp_sales_posts
    MODIFY admin_review_status ENUM('good','bad') NULL DEFAULT NULL;
