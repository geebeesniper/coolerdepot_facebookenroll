<?php
$config = require dirname(__DIR__) . '/config/bootstrap.php';
use App\Core\Database;
Database::connection()->exec("ALTER TABLE cdsp_review_attachments MODIFY entity_type ENUM('post_review','daily_review','period_review','post_note') NOT NULL");
echo "Note editor image attachment type enabled." . PHP_EOL;
