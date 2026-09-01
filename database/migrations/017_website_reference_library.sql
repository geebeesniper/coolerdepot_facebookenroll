-- File / 文件：database/migrations/017_website_reference_library.sql
-- EN: Database migration for a versioned schema/data change.
-- 中文：该文件用于版本化数据库结构或数据迁移。
-- Maintenance / 维护：Keep migrations deterministic and safe to re-check before deployment. / 迁移必须可预测，部署前可安全复核。
-- v0.1.71 website reference library
-- The PHP migration script adds this column conditionally for compatibility.
ALTER TABLE cdsp_website_references
    ADD COLUMN description MEDIUMTEXT NULL AFTER title;
