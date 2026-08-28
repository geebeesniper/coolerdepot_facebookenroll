-- CoolerDepot Sales Post Tracker demo data
-- DEMO CREDENTIALS
-- Admin: admin / AdminDemo!2026
-- Sales: 100006, 100010, 100013 / SalesDemo!2026
-- Delete these cdsp_users/posts before production use.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

DELETE FROM cdsp_review_attachments WHERE entity_type IN ('post_review','daily_review','period_review');
DELETE FROM cdsp_period_sales_reviews;
DELETE FROM cdsp_daily_sales_reviews;
DELETE FROM cdsp_post_reviews;
DELETE FROM cdsp_deletion_requests;
DELETE FROM cdsp_sales_posts WHERE external_post_id LIKE 'demo-%' OR external_post_id LIKE '90000000000%';
DELETE FROM cdsp_users WHERE username IN ('admin','100006','100010','100013');

INSERT INTO cdsp_users
(sales_id, username, password_hash, display_name, role, active, created_at, updated_at)
VALUES
(NULL, 'admin', '$2y$12$poKqRX2Z7gU084mWHTI/VOzTcZSSaOZOPuSQkoR4wKRILXi5UQlnS', 'Demo Administrator', 'admin', 1, NOW(), NOW()),
(100006, '100006', '$2y$12$rS2uwR1KbHl/.PWi2M9yN.dRMRRpXl4HG1nx5cwn1/is8rMmvbBzm', 'David', 'sales', 1, NOW(), NOW()),
(100010, '100010', '$2y$12$rS2uwR1KbHl/.PWi2M9yN.dRMRRpXl4HG1nx5cwn1/is8rMmvbBzm', 'May He (AMY)', 'sales', 1, NOW(), NOW()),
(100013, '100013', '$2y$12$rS2uwR1KbHl/.PWi2M9yN.dRMRRpXl4HG1nx5cwn1/is8rMmvbBzm', 'Andrew Ramirez', 'sales', 1, NOW(), NOW());

INSERT INTO cdsp_sales_posts
(sales_user_id, platform, submitted_url, resolved_url, canonical_url, canonical_url_hash,
 external_post_id, title, normalized_title_hash, description, description_hash,
 published_at, published_date, fetched_at, verification_status, admin_review_status,
 created_at, updated_at)
SELECT id, 'facebook', 'https://www.facebook.com/marketplace/item/900000000001', 'https://www.facebook.com/marketplace/item/900000000001', 'https://www.facebook.com/marketplace/item/900000000001', '32078bbd609938ad09ad482e3476abeae54006f15e0e7743407a54cea0108fca',
       '900000000001', 'Commercial Refrigerator Three Door', '14fd412b1ae7f3eb6ff10cffe4573f5996ce101c0eae008e8b310cd0e15ebb6d',
       'Commercial refrigerator in good working condition ready for pickup.', '20153c150c64dc16c91170e179ebb184ec7fc73314f2e38de690adb62f22455a',
       DATE_SUB(NOW(), INTERVAL 17 MINUTE), CURDATE(),
       DATE_SUB(NOW(), INTERVAL 16 MINUTE),
       'verified', 'pending', DATE_SUB(NOW(), INTERVAL 16 MINUTE), NOW()
FROM cdsp_users WHERE sales_id=100006 LIMIT 1;

INSERT INTO cdsp_sales_posts
(sales_user_id, platform, submitted_url, resolved_url, canonical_url, canonical_url_hash,
 external_post_id, title, normalized_title_hash, description, description_hash,
 published_at, published_date, fetched_at, verification_status, admin_review_status,
 created_at, updated_at)
SELECT id, 'craigslist', 'https://orangecounty.craigslist.org/app/d/demo-refrigerator/900000000002.html', 'https://orangecounty.craigslist.org/app/d/demo-refrigerator/900000000002.html', 'https://orangecounty.craigslist.org/app/d/demo-refrigerator/900000000002.html', 'fe6bdfacc4545799470c5837538f0bed1853050a6cd3460de745a036f360a9e0',
       '900000000002', 'Restaurant Refrigerator Stainless Steel', '18ed4225127dd2eb7934c22fb0a818021122e490f700c9b7474f4b1bef70a3c0',
       'Stainless steel restaurant refrigerator available today.', '9994f4fceb5c6dbc8c668b2d40f07014b62912ce247e6e8df870a276a9d05861',
       DATE_SUB(NOW(), INTERVAL 24 MINUTE), CURDATE(),
       DATE_SUB(NOW(), INTERVAL 23 MINUTE),
       'verified', 'pending', DATE_SUB(NOW(), INTERVAL 23 MINUTE), NOW()
FROM cdsp_users WHERE sales_id=100006 LIMIT 1;

INSERT INTO cdsp_sales_posts
(sales_user_id, platform, submitted_url, resolved_url, canonical_url, canonical_url_hash,
 external_post_id, title, normalized_title_hash, description, description_hash,
 published_at, published_date, fetched_at, verification_status, admin_review_status,
 created_at, updated_at)
SELECT id, 'offerup', 'https://offerup.com/item/detail/demo-900000000003', 'https://offerup.com/item/detail/demo-900000000003', 'https://offerup.com/item/detail/demo-900000000003', 'c8ecba39bde36ec1109c5263667c9a2b641c4943a2b5eb04634b8212c28d9e60',
       'demo-900000000003', 'Two Door Commercial Freezer', 'cbf06aac9134ab36b835593bafa6a1eab0d1654a1b3c692b4efa8eecc48bf79f',
       'Two door commercial freezer tested and ready.', '8be5abf6ef8c29a8b435fed4909ecebd95a43f0dd332aa6ca0a567e96a8c365a',
       DATE_SUB(NOW(), INTERVAL 31 MINUTE), CURDATE(),
       DATE_SUB(NOW(), INTERVAL 30 MINUTE),
       'verified', 'pending', DATE_SUB(NOW(), INTERVAL 30 MINUTE), NOW()
FROM cdsp_users WHERE sales_id=100010 LIMIT 1;

INSERT INTO cdsp_sales_posts
(sales_user_id, platform, submitted_url, resolved_url, canonical_url, canonical_url_hash,
 external_post_id, title, normalized_title_hash, description, description_hash,
 published_at, published_date, fetched_at, verification_status, admin_review_status,
 created_at, updated_at)
SELECT id, 'facebook', 'https://www.facebook.com/marketplace/item/900000000004', 'https://www.facebook.com/marketplace/item/900000000004', 'https://www.facebook.com/marketplace/item/900000000004', '85e02896d565fd3bf5529503ab702e791fc83adec524677be3f680009f0bbecd',
       '900000000004', 'Glass Door Merchandiser Cooler', 'e332a1716e6ef88e757e3421bcb9d91dc746c94dfe2759fe2cecb430a00b7ded',
       'Glass door merchandiser cooler clean and working.', 'a9b0dcffd8d3338e7c91d01539a7d71d7a890df6158b3dd22f9cd34d8888a1b3',
       DATE_SUB(NOW(), INTERVAL 38 MINUTE), CURDATE(),
       DATE_SUB(NOW(), INTERVAL 37 MINUTE),
       'verified', 'pending', DATE_SUB(NOW(), INTERVAL 37 MINUTE), NOW()
FROM cdsp_users WHERE sales_id=100010 LIMIT 1;

INSERT INTO cdsp_sales_posts
(sales_user_id, platform, submitted_url, resolved_url, canonical_url, canonical_url_hash,
 external_post_id, title, normalized_title_hash, description, description_hash,
 published_at, published_date, fetched_at, verification_status, admin_review_status,
 created_at, updated_at)
SELECT id, 'craigslist', 'https://orangecounty.craigslist.org/bfs/d/demo-freezer/900000000005.html', 'https://orangecounty.craigslist.org/bfs/d/demo-freezer/900000000005.html', 'https://orangecounty.craigslist.org/bfs/d/demo-freezer/900000000005.html', '9de8a299dd65c74f5785a0dbc417912abf5b9d2132cdbe4b5390aaf59fafd636',
       '900000000005', 'Commercial Chest Freezer', '3b994f4c387a2913d6cc30957773798d335d1f41ab424029e9e0185688253465',
       'Commercial chest freezer available for local pickup.', '2816d26cb162cee43283186adf9dc790c5d5061b09a337413c82fc52e4f0920c',
       DATE_SUB(NOW(), INTERVAL 45 MINUTE), CURDATE(),
       DATE_SUB(NOW(), INTERVAL 44 MINUTE),
       'verified', 'pending', DATE_SUB(NOW(), INTERVAL 44 MINUTE), NOW()
FROM cdsp_users WHERE sales_id=100013 LIMIT 1;

INSERT INTO cdsp_sales_posts
(sales_user_id, platform, submitted_url, resolved_url, canonical_url, canonical_url_hash,
 external_post_id, title, normalized_title_hash, description, description_hash,
 published_at, published_date, fetched_at, verification_status, admin_review_status,
 created_at, updated_at)
SELECT id, 'offerup', 'https://offerup.com/item/detail/demo-900000000006', 'https://offerup.com/item/detail/demo-900000000006', 'https://offerup.com/item/detail/demo-900000000006', 'b3cbc1e9b38522661bf4264a229eb7d49d8a01ec3f6db52c102e7e3b2f48747c',
       'demo-900000000006', 'Prep Table Refrigerator', '0c1715e1e021b859dc91bbe439198e46bba602bd6bac1095d47812f810d10b66',
       'Prep table refrigerator with stainless work surface.', '15fbefa2bf04173d1683b0a109638d232780b4468b1f3c172713a2d039538521',
       DATE_SUB(NOW(), INTERVAL 52 MINUTE), CURDATE(),
       DATE_SUB(NOW(), INTERVAL 51 MINUTE),
       'verified', 'pending', DATE_SUB(NOW(), INTERVAL 51 MINUTE), NOW()
FROM cdsp_users WHERE sales_id=100013 LIMIT 1;

INSERT INTO cdsp_post_reviews
(post_id, admin_user_id, decision, rating, note, reviewed_at, created_at, updated_at)
SELECT p.id, a.id, 'approved', 5, 'Demo review: verified and approved.', NOW(), NOW(), NOW()
FROM cdsp_sales_posts p
JOIN cdsp_users s ON s.id=p.sales_user_id
JOIN cdsp_users a ON a.username='admin' AND a.role='admin'
WHERE s.sales_id=100006 AND p.external_post_id='900000000001'
LIMIT 1;
UPDATE cdsp_sales_posts
SET admin_review_status='approved'
WHERE external_post_id='900000000001';

INSERT INTO cdsp_post_reviews
(post_id, admin_user_id, decision, rating, note, reviewed_at, created_at, updated_at)
SELECT p.id, a.id, 'rejected', 2, 'Demo review: needs better listing content.', NOW(), NOW(), NOW()
FROM cdsp_sales_posts p
JOIN cdsp_users s ON s.id=p.sales_user_id
JOIN cdsp_users a ON a.username='admin' AND a.role='admin'
WHERE s.sales_id=100010 AND p.external_post_id='demo-900000000003'
LIMIT 1;
UPDATE cdsp_sales_posts
SET admin_review_status='rejected'
WHERE external_post_id='demo-900000000003';

INSERT INTO cdsp_daily_sales_reviews
(sales_user_id, work_date, admin_user_id, rating, note, reviewed_at, created_at, updated_at)
SELECT s.id, CURDATE(), a.id, 4, 'Demo daily review: good posting volume.', NOW(), NOW(), NOW()
FROM cdsp_users s JOIN cdsp_users a ON a.username='admin' AND a.role='admin'
WHERE s.sales_id=100006 LIMIT 1;

INSERT INTO cdsp_period_sales_reviews
(sales_user_id, period_type, period_start, period_end, admin_user_id, rating, note,
 reviewed_at, created_at, updated_at)
SELECT s.id, 'week',
       DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY),
       DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 6 DAY),
       a.id, 4, 'Demo weekly review.', NOW(), NOW(), NOW()
FROM cdsp_users s JOIN cdsp_users a ON a.username='admin' AND a.role='admin'
WHERE s.sales_id=100006 LIMIT 1;

SET FOREIGN_KEY_CHECKS=1;
SELECT 'Demo data installed' AS result;
