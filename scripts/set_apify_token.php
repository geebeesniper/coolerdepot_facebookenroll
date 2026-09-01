<?php
/**
 * File / 文件：scripts/set_apify_token.php
 * EN: Operations/deployment/diagnostics script owned by this project.
 * 中文：该文件是本项目自有的运维、部署或诊断脚本。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
$config = require dirname(__DIR__) . '/config/bootstrap.php';

use App\Core\Database;
use App\Models\Setting;

$token = trim((string)getenv('APIFY_TOKEN'));

if ($token === '') {
    fwrite(STDERR, "APIFY_TOKEN environment variable is empty.\n");
    exit(1);
}

$pdo = Database::connection();

$adminId = (int)$pdo->query(
    "SELECT id
     FROM cdsp_users
     WHERE role='admin' AND active=1
     ORDER BY id
     LIMIT 1"
)->fetchColumn();

if ($adminId <= 0) {
    fwrite(STDERR, "No active Admin user was found.\n");
    exit(1);
}

Setting::set('apify_api_token', $token, $adminId, true);
Setting::set('apify_enabled', '1', $adminId);
Setting::set('apify_timeout_seconds', '90', $adminId);

echo "Apify fallback enabled. API token stored encrypted.\n";
