-- File / 文件：database/migrations/019_person_review_attachment_history.sql
-- EN: Database migration for a versioned schema/data change.
-- 中文：该文件用于版本化数据库结构或数据迁移。
-- Maintenance / 维护：Keep migrations deterministic and safe to re-check before deployment. / 迁移必须可预测，部署前可安全复核。
ALTER TABLE cdsp_review_attachments
  ADD COLUMN history_id BIGINT UNSIGNED NULL AFTER entity_id,
  ADD INDEX idx_attachment_history(history_id);
