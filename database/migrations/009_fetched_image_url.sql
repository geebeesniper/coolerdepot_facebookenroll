ALTER TABLE cdsp_sales_posts
    ADD COLUMN fetched_image_url TEXT NULL
    AFTER fetched_at;
