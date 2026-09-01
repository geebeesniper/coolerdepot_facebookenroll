-- File / 文件：database/demo.sql
-- EN: Database schema or database-support source.
-- 中文：该文件提供数据库结构或数据库辅助定义。
-- Maintenance / 维护：Keep migrations deterministic and safe to re-check before deployment. / 迁移必须可预测，部署前可安全复核。
-- CoolerDepot Sales Post Tracker demo data v0.1.57
-- Uses the 10 real Facebook Marketplace URLs supplied for testing.
-- Titles/descriptions below are demo labels only; live verification must fetch real metadata.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- Remove legacy fake demo post/review rows.
DELETE pr FROM cdsp_post_reviews pr JOIN cdsp_sales_posts p ON p.id=pr.post_id WHERE p.external_post_id IN ('900000000001','900000000002','demo-900000000003','900000000004','900000000005','demo-900000000006');
DELETE FROM cdsp_sales_posts WHERE external_post_id IN ('900000000001','900000000002','demo-900000000003','900000000004','900000000005','demo-900000000006');

-- Ensure demo Sales account David exists. Existing imported/user data is preserved.
INSERT INTO cdsp_users
(sales_id, external_user_id, username, password_hash, display_name, role, active, auth_source, created_at, updated_at)
VALUES
(100006, NULL, '100006', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', 'David', 'sales', 1, 'local', NOW(), NOW())
ON DUPLICATE KEY UPDATE display_name='David', role='sales', active=1, updated_at=NOW();

INSERT INTO cdsp_sales_posts
(sales_user_id, platform, submitted_url, resolved_url, canonical_url, canonical_url_hash,
 external_post_id, title, normalized_title_hash, description, description_hash,
 published_at, published_date, fetched_at, verification_status, admin_review_status,
 created_at, updated_at)
SELECT id, 'facebook', 'https://www.facebook.com/marketplace/item/1612547780491408', 'https://www.facebook.com/marketplace/item/1612547780491408', 'https://www.facebook.com/marketplace/item/1612547780491408', '4a2bb1179e2b11d1087b31f3d95a1a8b491dbd37235003270abc95c0d51deadc',
       '1612547780491408', 'Facebook Marketplace Sample #1', 'f4a95c7bd1220134fefecd4ebe5de893eddd98e92a311dd929850f89af643d7c',
       'Real Facebook Marketplace URL supplied for Sales Post Tracker testing.', 'b94fef7e15d17d443ab70094d9326c4a7cd08afb67431e6fa14a38d0c3584261',
       DATE_SUB(DATE_SUB(NOW(), INTERVAL 0 DAY), INTERVAL 28 MINUTE),
       DATE(DATE_SUB(NOW(), INTERVAL 0 DAY)),
       DATE_SUB(NOW(), INTERVAL 28 MINUTE),
       'verified', 'good', NOW(), NOW()
FROM cdsp_users
WHERE sales_id=100006
  AND NOT EXISTS (
      SELECT 1 FROM cdsp_sales_posts x
      WHERE x.platform='facebook' AND x.external_post_id='1612547780491408'
  )
LIMIT 1;

INSERT INTO cdsp_sales_posts
(sales_user_id, platform, submitted_url, resolved_url, canonical_url, canonical_url_hash,
 external_post_id, title, normalized_title_hash, description, description_hash,
 published_at, published_date, fetched_at, verification_status, admin_review_status,
 created_at, updated_at)
SELECT id, 'facebook', 'https://www.facebook.com/marketplace/item/1578098323791707', 'https://www.facebook.com/marketplace/item/1578098323791707', 'https://www.facebook.com/marketplace/item/1578098323791707', '2ce11ea7227dc4c9c61f0477ce1a358c75e14c11d79e0b469839905810cdc1ea',
       '1578098323791707', 'Facebook Marketplace Sample #2', '7869351acd82b9d8b17bb9c93b60cc4317cc53c9f4d1b134e30092dfca3bc51a',
       'Real Facebook Marketplace URL supplied for Sales Post Tracker testing.', 'b94fef7e15d17d443ab70094d9326c4a7cd08afb67431e6fa14a38d0c3584261',
       DATE_SUB(DATE_SUB(NOW(), INTERVAL 0 DAY), INTERVAL 36 MINUTE),
       DATE(DATE_SUB(NOW(), INTERVAL 0 DAY)),
       DATE_SUB(NOW(), INTERVAL 36 MINUTE),
       'verified', 'good', NOW(), NOW()
FROM cdsp_users
WHERE sales_id=100006
  AND NOT EXISTS (
      SELECT 1 FROM cdsp_sales_posts x
      WHERE x.platform='facebook' AND x.external_post_id='1578098323791707'
  )
LIMIT 1;

INSERT INTO cdsp_sales_posts
(sales_user_id, platform, submitted_url, resolved_url, canonical_url, canonical_url_hash,
 external_post_id, title, normalized_title_hash, description, description_hash,
 published_at, published_date, fetched_at, verification_status, admin_review_status,
 created_at, updated_at)
SELECT id, 'facebook', 'https://www.facebook.com/marketplace/item/1754865915754719', 'https://www.facebook.com/marketplace/item/1754865915754719', 'https://www.facebook.com/marketplace/item/1754865915754719', 'c04606bf732403940d5b8e4fb3350f79d8629d68cda018d00ba8b4eb7e5a165a',
       '1754865915754719', 'Facebook Marketplace Sample #3', '7ef65e10c672973f390d9a113d4580e5e6521b1acc56491ba899c531cad2f7b4',
       'Real Facebook Marketplace URL supplied for Sales Post Tracker testing.', 'b94fef7e15d17d443ab70094d9326c4a7cd08afb67431e6fa14a38d0c3584261',
       DATE_SUB(DATE_SUB(NOW(), INTERVAL 0 DAY), INTERVAL 44 MINUTE),
       DATE(DATE_SUB(NOW(), INTERVAL 0 DAY)),
       DATE_SUB(NOW(), INTERVAL 44 MINUTE),
       'verified', NULL, NOW(), NOW()
FROM cdsp_users
WHERE sales_id=100006
  AND NOT EXISTS (
      SELECT 1 FROM cdsp_sales_posts x
      WHERE x.platform='facebook' AND x.external_post_id='1754865915754719'
  )
LIMIT 1;

INSERT INTO cdsp_sales_posts
(sales_user_id, platform, submitted_url, resolved_url, canonical_url, canonical_url_hash,
 external_post_id, title, normalized_title_hash, description, description_hash,
 published_at, published_date, fetched_at, verification_status, admin_review_status,
 created_at, updated_at)
SELECT id, 'facebook', 'https://www.facebook.com/marketplace/item/1609835460847233', 'https://www.facebook.com/marketplace/item/1609835460847233', 'https://www.facebook.com/marketplace/item/1609835460847233', 'e19552cb9a7bdeb5abfa7ccb0aca209188ecbed05067675ee72cd7b3960b7672',
       '1609835460847233', 'Facebook Marketplace Sample #4', 'a8ea57fc234e30436a22d7453655473957219aad76e0afc509e9fb9122f61142',
       'Real Facebook Marketplace URL supplied for Sales Post Tracker testing.', 'b94fef7e15d17d443ab70094d9326c4a7cd08afb67431e6fa14a38d0c3584261',
       DATE_SUB(DATE_SUB(NOW(), INTERVAL 0 DAY), INTERVAL 52 MINUTE),
       DATE(DATE_SUB(NOW(), INTERVAL 0 DAY)),
       DATE_SUB(NOW(), INTERVAL 52 MINUTE),
       'verified', NULL, NOW(), NOW()
FROM cdsp_users
WHERE sales_id=100006
  AND NOT EXISTS (
      SELECT 1 FROM cdsp_sales_posts x
      WHERE x.platform='facebook' AND x.external_post_id='1609835460847233'
  )
LIMIT 1;

INSERT INTO cdsp_sales_posts
(sales_user_id, platform, submitted_url, resolved_url, canonical_url, canonical_url_hash,
 external_post_id, title, normalized_title_hash, description, description_hash,
 published_at, published_date, fetched_at, verification_status, admin_review_status,
 created_at, updated_at)
SELECT id, 'facebook', 'https://www.facebook.com/marketplace/item/1546388710570410', 'https://www.facebook.com/marketplace/item/1546388710570410', 'https://www.facebook.com/marketplace/item/1546388710570410', '26b6905b31fc2b3fa9c8cb56e1f20093f393a465789cb7f40dc3993a2f157462',
       '1546388710570410', 'Facebook Marketplace Sample #5', '167d490ae3828db8a03763d27483000f9228186fe28c023b41e6ebff431a5575',
       'Real Facebook Marketplace URL supplied for Sales Post Tracker testing.', 'b94fef7e15d17d443ab70094d9326c4a7cd08afb67431e6fa14a38d0c3584261',
       TIMESTAMP('2026-08-28 18:29:54'),
       DATE('2026-08-28'),
       DATE_SUB(NOW(), INTERVAL 60 MINUTE),
       'verified', 'good', NOW(), NOW()
FROM cdsp_users
WHERE sales_id=100006
  AND NOT EXISTS (
      SELECT 1 FROM cdsp_sales_posts x
      WHERE x.platform='facebook' AND x.external_post_id='1546388710570410'
  )
LIMIT 1;

INSERT INTO cdsp_sales_posts
(sales_user_id, platform, submitted_url, resolved_url, canonical_url, canonical_url_hash,
 external_post_id, title, normalized_title_hash, description, description_hash,
 published_at, published_date, fetched_at, verification_status, admin_review_status,
 created_at, updated_at)
SELECT id, 'facebook', 'https://www.facebook.com/marketplace/item/3813795918762562', 'https://www.facebook.com/marketplace/item/3813795918762562', 'https://www.facebook.com/marketplace/item/3813795918762562', '88d3fc5684ba5d7730a63809d3c528eeeb5d6e9ae97e6e3a81eb56507fca8a85',
       '3813795918762562', 'Facebook Marketplace Sample #6', '7577d285896bcafd43f67b7d23b2640ff685f33642b8159e9b937d1a25bcda55',
       'Real Facebook Marketplace URL supplied for Sales Post Tracker testing.', 'b94fef7e15d17d443ab70094d9326c4a7cd08afb67431e6fa14a38d0c3584261',
       TIMESTAMP('2026-08-28 18:21:54'),
       DATE('2026-08-28'),
       DATE_SUB(NOW(), INTERVAL 68 MINUTE),
       'verified', NULL, NOW(), NOW()
FROM cdsp_users
WHERE sales_id=100006
  AND NOT EXISTS (
      SELECT 1 FROM cdsp_sales_posts x
      WHERE x.platform='facebook' AND x.external_post_id='3813795918762562'
  )
LIMIT 1;

INSERT INTO cdsp_sales_posts
(sales_user_id, platform, submitted_url, resolved_url, canonical_url, canonical_url_hash,
 external_post_id, title, normalized_title_hash, description, description_hash,
 published_at, published_date, fetched_at, verification_status, admin_review_status,
 created_at, updated_at)
SELECT id, 'facebook', 'https://www.facebook.com/marketplace/item/1606074697620900', 'https://www.facebook.com/marketplace/item/1606074697620900', 'https://www.facebook.com/marketplace/item/1606074697620900', '12bb9e74578c719315fb33cb7f41377d1a4fcc0c2414594a04778673b0580050',
       '1606074697620900', 'Facebook Marketplace Sample #7', 'a49e95b9bfdd1826a8d8928feb9f63a04bb6ae2982970758e93e441e337ab77b',
       'Real Facebook Marketplace URL supplied for Sales Post Tracker testing.', 'b94fef7e15d17d443ab70094d9326c4a7cd08afb67431e6fa14a38d0c3584261',
       TIMESTAMP('2026-08-28 18:13:54'),
       DATE('2026-08-28'),
       DATE_SUB(NOW(), INTERVAL 76 MINUTE),
       'verified', 'bad', NOW(), NOW()
FROM cdsp_users
WHERE sales_id=100006
  AND NOT EXISTS (
      SELECT 1 FROM cdsp_sales_posts x
      WHERE x.platform='facebook' AND x.external_post_id='1606074697620900'
  )
LIMIT 1;

INSERT INTO cdsp_sales_posts
(sales_user_id, platform, submitted_url, resolved_url, canonical_url, canonical_url_hash,
 external_post_id, title, normalized_title_hash, description, description_hash,
 published_at, published_date, fetched_at, verification_status, admin_review_status,
 created_at, updated_at)
SELECT id, 'facebook', 'https://www.facebook.com/marketplace/item/970768882088732', 'https://www.facebook.com/marketplace/item/970768882088732', 'https://www.facebook.com/marketplace/item/970768882088732', '57b201621206959fbd7d2305fdeb1e83335bcea74198eded545ea668e416d86f',
       '970768882088732', 'Facebook Marketplace Sample #8', '29229057108ed200b3cdd13e6493912611981674f54ca13492b02d2c563d8b07',
       'Real Facebook Marketplace URL supplied for Sales Post Tracker testing.', 'b94fef7e15d17d443ab70094d9326c4a7cd08afb67431e6fa14a38d0c3584261',
       TIMESTAMP('2026-08-28 18:05:54'),
       DATE('2026-08-28'),
       DATE_SUB(NOW(), INTERVAL 84 MINUTE),
       'verified', NULL, NOW(), NOW()
FROM cdsp_users
WHERE sales_id=100006
  AND NOT EXISTS (
      SELECT 1 FROM cdsp_sales_posts x
      WHERE x.platform='facebook' AND x.external_post_id='970768882088732'
  )
LIMIT 1;

INSERT INTO cdsp_sales_posts
(sales_user_id, platform, submitted_url, resolved_url, canonical_url, canonical_url_hash,
 external_post_id, title, normalized_title_hash, description, description_hash,
 published_at, published_date, fetched_at, verification_status, admin_review_status,
 created_at, updated_at)
SELECT id, 'facebook', 'https://www.facebook.com/marketplace/item/1556421559266266', 'https://www.facebook.com/marketplace/item/1556421559266266', 'https://www.facebook.com/marketplace/item/1556421559266266', 'd0e58293088baf9855784b4f2cf1de685424836bb7f287f880dd789c4b1ede56',
       '1556421559266266', 'Facebook Marketplace Sample #9', 'd89b798f342575f180f3d2fd127c35d9f2c3bea70e5c76576cf7bd0666687e7a',
       'Real Facebook Marketplace URL supplied for Sales Post Tracker testing.', 'b94fef7e15d17d443ab70094d9326c4a7cd08afb67431e6fa14a38d0c3584261',
       TIMESTAMP('2026-08-28 17:57:54'),
       DATE('2026-08-28'),
       DATE_SUB(NOW(), INTERVAL 92 MINUTE),
       'verified', NULL, NOW(), NOW()
FROM cdsp_users
WHERE sales_id=100006
  AND NOT EXISTS (
      SELECT 1 FROM cdsp_sales_posts x
      WHERE x.platform='facebook' AND x.external_post_id='1556421559266266'
  )
LIMIT 1;

INSERT INTO cdsp_sales_posts
(sales_user_id, platform, submitted_url, resolved_url, canonical_url, canonical_url_hash,
 external_post_id, title, normalized_title_hash, description, description_hash,
 published_at, published_date, fetched_at, verification_status, admin_review_status,
 created_at, updated_at)
SELECT id, 'facebook', 'https://www.facebook.com/marketplace/item/1994325934606833', 'https://www.facebook.com/marketplace/item/1994325934606833', 'https://www.facebook.com/marketplace/item/1994325934606833', '09a351591f0f689085ef0f3fece4867fa373b3a01fdc62b2885bd8b0073ff4c1',
       '1994325934606833', 'Facebook Marketplace Sample #10', '5d7823670a39569b3fcb314c50e43e92845f674ee461017d6841efa5a36a3571',
       'Real Facebook Marketplace URL supplied for Sales Post Tracker testing.', 'b94fef7e15d17d443ab70094d9326c4a7cd08afb67431e6fa14a38d0c3584261',
       TIMESTAMP('2026-08-28 17:49:54'),
       DATE('2026-08-28'),
       DATE_SUB(NOW(), INTERVAL 100 MINUTE),
       'verified', NULL, NOW(), NOW()
FROM cdsp_users
WHERE sales_id=100006
  AND NOT EXISTS (
      SELECT 1 FROM cdsp_sales_posts x
      WHERE x.platform='facebook' AND x.external_post_id='1994325934606833'
  )
LIMIT 1;

SET FOREIGN_KEY_CHECKS=1;
SELECT 'Demo data v0.1.1 installed' AS result;
