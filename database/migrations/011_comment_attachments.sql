-- File / 文件：database/migrations/011_comment_attachments.sql
-- EN: Database migration for a versioned schema/data change.
-- 中文：该文件用于版本化数据库结构或数据迁移。
-- Maintenance / 维护：Keep migrations deterministic and safe to re-check before deployment. / 迁移必须可预测，部署前可安全复核。
ALTER TABLE cdsp_review_attachments
    MODIFY entity_type
    ENUM('post_review','daily_review','period_review','post_note','post_comment')
    NOT NULL;

UPDATE cdsp_review_attachments a
JOIN cdsp_post_review_comments c
  ON c.legacy_review_id = a.entity_id
SET a.entity_type = 'post_comment',
    a.entity_id = c.id
WHERE a.entity_type = 'post_review';
