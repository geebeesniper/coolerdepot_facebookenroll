-- File / 文件：database/migrations/020_craigslist_manual_verification.sql
-- EN: Database migration for the V0.2.13 Craigslist manual-verification status change.
-- 中文：该文件用于 V0.2.13 Craigslist 手动验证状态的数据库结构迁移。
-- Maintenance / 维护：Only expand ENUM values; do not delete or rewrite business rows. / 仅扩展 ENUM 可选值，不删除或重写业务数据。
ALTER TABLE cdsp_post_inspections
  MODIFY COLUMN verification_status ENUM('verified','manual_pending','failed') NOT NULL;

ALTER TABLE cdsp_sales_posts
  MODIFY COLUMN verification_status ENUM('verified','manual_pending','failed') NOT NULL DEFAULT 'verified';