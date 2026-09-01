-- File / 文件：database/migrations/014_fix_aug28_post_dates.sql
-- EN: Database migration for a versioned schema/data change.
-- 中文：该文件用于版本化数据库结构或数据迁移。
-- Maintenance / 维护：Keep migrations deterministic and safe to re-check before deployment. / 迁移必须可预测，部署前可安全复核。
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
