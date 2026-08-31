-- v0.1.57
-- Move the remaining three Facebook Marketplace posts from 2026-08-27
-- to 2026-08-28 while preserving each original time-of-day.

UPDATE cdsp_sales_posts
SET
    published_at = TIMESTAMP(
        '2026-08-28',
        TIME(
            COALESCE(
                published_at,
                '2026-08-28 12:00:00'
            )
        )
    ),
    published_date = '2026-08-28',
    updated_at = NOW()
WHERE LOWER(platform) = 'facebook'
  AND external_post_id IN (
      '1546388710570410',
      '3813795918762562',
      '1606074697620900'
  );
