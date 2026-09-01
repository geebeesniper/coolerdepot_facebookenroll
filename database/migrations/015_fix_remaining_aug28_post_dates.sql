-- File / 文件：database/migrations/015_fix_remaining_aug28_post_dates.sql
-- EN: Database migration for a versioned schema/data change.
-- 中文：该文件用于版本化数据库结构或数据迁移。
-- Maintenance / 维护：Keep migrations deterministic and safe to re-check before deployment. / 迁移必须可预测，部署前可安全复核。
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
