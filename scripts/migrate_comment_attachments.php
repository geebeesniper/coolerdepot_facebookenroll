<?php
$config = require dirname(__DIR__) . '/config/bootstrap.php';
use App\Core\Database;

$pdo = Database::connection();
$pdo->exec(
    "ALTER TABLE cdsp_review_attachments
     MODIFY entity_type
     ENUM('post_review','daily_review','period_review','post_note','post_comment')
     NOT NULL"
);

$moved = $pdo->exec(
    "UPDATE cdsp_review_attachments a
     JOIN cdsp_post_review_comments c
       ON c.legacy_review_id = a.entity_id
     SET a.entity_type = 'post_comment',
         a.entity_id = c.id
     WHERE a.entity_type = 'post_review'"
);

echo "Comment attachment type enabled." . PHP_EOL;
echo "Legacy review images moved into comment history: " . (int)$moved . PHP_EOL;
