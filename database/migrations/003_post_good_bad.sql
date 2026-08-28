-- Convert post review terminology:
-- pending -> NULL
-- approved -> good
-- rejected -> bad
--
-- Deletion request workflow status is intentionally NOT changed.

ALTER TABLE cdsp_sales_posts
    MODIFY admin_review_status ENUM('pending','approved','rejected','good','bad') NULL DEFAULT NULL;

UPDATE cdsp_sales_posts
SET admin_review_status = CASE admin_review_status
    WHEN 'approved' THEN 'good'
    WHEN 'rejected' THEN 'bad'
    WHEN 'pending' THEN NULL
    ELSE admin_review_status
END;

ALTER TABLE cdsp_sales_posts
    MODIFY admin_review_status ENUM('good','bad') NULL DEFAULT NULL;

ALTER TABLE cdsp_post_reviews
    MODIFY decision ENUM('approved','rejected','good','bad') NOT NULL;

UPDATE cdsp_post_reviews
SET decision = CASE decision
    WHEN 'approved' THEN 'good'
    WHEN 'rejected' THEN 'bad'
    ELSE decision
END;

ALTER TABLE cdsp_post_reviews
    MODIFY decision ENUM('good','bad') NOT NULL;
