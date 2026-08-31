-- v0.1.55
-- Correct the three Facebook Marketplace posts that were grouped under
-- 2026-08-26 even though their posting date is 2026-08-28.
--
-- Keep each post's existing time-of-day and only replace the date part.

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
WHERE platform = 'facebook'
  AND external_post_id IN (
      '970768882088732',
      '1556421559266266',
      '1994325934606833'
  );
