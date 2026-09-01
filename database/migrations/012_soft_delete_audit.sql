-- File / 文件：database/migrations/012_soft_delete_audit.sql
-- EN: Database migration for a versioned schema/data change.
-- 中文：该文件用于版本化数据库结构或数据迁移。
-- Maintenance / 维护：Keep migrations deterministic and safe to re-check before deployment. / 迁移必须可预测，部署前可安全复核。
ALTER TABLE cdsp_review_attachments
    ADD COLUMN deleted_at DATETIME NULL AFTER created_at,
    ADD COLUMN deleted_by INT UNSIGNED NULL AFTER deleted_at,
    ADD KEY idx_attachment_deleted(deleted_at);

ALTER TABLE cdsp_review_attachments
    ADD CONSTRAINT fk_attachment_deleted_by
    FOREIGN KEY(deleted_by) REFERENCES cdsp_users(id);
