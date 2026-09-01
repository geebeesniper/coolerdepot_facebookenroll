<?php
/**
 * File / 文件：scripts/migrate_note_editor_images.php
 * EN: Operations/deployment/diagnostics script owned by this project.
 * 中文：该文件是本项目自有的运维、部署或诊断脚本。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
$config = require dirname(__DIR__) . '/config/bootstrap.php';
use App\Core\Database;
Database::connection()->exec("ALTER TABLE cdsp_review_attachments MODIFY entity_type ENUM('post_review','daily_review','period_review','post_note') NOT NULL");
echo "Note editor image attachment type enabled." . PHP_EOL;
