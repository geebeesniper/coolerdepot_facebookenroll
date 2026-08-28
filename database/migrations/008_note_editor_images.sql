ALTER TABLE cdsp_review_attachments
    MODIFY entity_type
    ENUM('post_review','daily_review','period_review','post_note')
    NOT NULL;
