ALTER TABLE cdsp_review_attachments
    ADD COLUMN deleted_at DATETIME NULL AFTER created_at,
    ADD COLUMN deleted_by INT UNSIGNED NULL AFTER deleted_at,
    ADD KEY idx_attachment_deleted(deleted_at);

ALTER TABLE cdsp_review_attachments
    ADD CONSTRAINT fk_attachment_deleted_by
    FOREIGN KEY(deleted_by) REFERENCES cdsp_users(id);
