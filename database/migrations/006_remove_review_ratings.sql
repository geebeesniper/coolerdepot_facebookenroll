-- Rating has been removed from the Admin review UX.
-- Keep the legacy columns nullable for backwards-compatible historical data,
-- but new saves always write NULL.

ALTER TABLE cdsp_post_reviews
    MODIFY rating TINYINT UNSIGNED NULL DEFAULT NULL;

ALTER TABLE cdsp_daily_sales_reviews
    MODIFY rating TINYINT UNSIGNED NULL DEFAULT NULL;

ALTER TABLE cdsp_period_sales_reviews
    MODIFY rating TINYINT UNSIGNED NULL DEFAULT NULL;
