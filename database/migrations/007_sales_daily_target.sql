ALTER TABLE cdsp_users
    ADD COLUMN daily_post_target SMALLINT UNSIGNED NOT NULL DEFAULT 10
    AFTER active;
