<?php
/**
 * File / 文件：scripts/migrate_note_editor_images.php
 * EN: CLI maintenance/deployment script for migrate note editor images.
 * 中文：用于 migrate note editor images 的命令行维护/部署脚本。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
$config = require dirname(__DIR__) . '/config/bootstrap.php';
use App\Core\Database;
Database::connection()->exec("ALTER TABLE cdsp_review_attachments MODIFY entity_type ENUM('post_review','daily_review','period_review','post_note') NOT NULL");
echo "Note editor image attachment type enabled." . PHP_EOL;
