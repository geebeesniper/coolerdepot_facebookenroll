ALTER TABLE cdsp_review_attachments
  ADD COLUMN history_id BIGINT UNSIGNED NULL AFTER entity_id,
  ADD INDEX idx_attachment_history(history_id);
