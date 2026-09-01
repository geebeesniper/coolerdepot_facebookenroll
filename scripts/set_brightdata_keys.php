<?php
/**
 * File / 文件：scripts/set_brightdata_keys.php
 * EN: Operations/deployment/diagnostics script owned by this project.
 * 中文：该文件是本项目自有的运维、部署或诊断脚本。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
$config = require dirname(__DIR__) . '/config/bootstrap.php';

use App\Core\Database;
use App\Models\Setting;

$primary = trim((string)getenv('BD_PRIMARY_KEY'));
$secondary = trim((string)getenv('BD_SECONDARY_KEY'));

if ($primary === '' && $secondary === '') {
    fwrite(
        STDERR,
        "BD_PRIMARY_KEY and BD_SECONDARY_KEY are both empty.\n"
    );
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

if ($primary !== '') {
    Setting::set(
        'brightdata_api_token',
        $primary,
        $adminId,
        true
    );
}

if ($secondary !== '') {
    Setting::set(
        'brightdata_api_token_secondary',
        $secondary,
        $adminId,
        true
    );
}

Setting::set('brightdata_enabled', '1', $adminId);

echo "Bright Data credentials saved encrypted.\n";
echo "Primary: "
    . (Setting::has('brightdata_api_token') ? 'STORED' : 'NOT STORED')
    . PHP_EOL;
echo "Secondary: "
    . (
        Setting::has('brightdata_api_token_secondary')
            ? 'STORED'
            : 'NOT STORED'
    )
    . PHP_EOL;
