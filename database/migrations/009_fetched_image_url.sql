-- File / 文件：database/migrations/009_fetched_image_url.sql
-- EN: Database migration for a versioned schema/data change.
-- 中文：该文件用于版本化数据库结构或数据迁移。
-- Maintenance / 维护：Keep migrations deterministic and safe to re-check before deployment. / 迁移必须可预测，部署前可安全复核。
ALTER TABLE cdsp_sales_posts
    ADD COLUMN fetched_image_url TEXT NULL
    AFTER fetched_at;
