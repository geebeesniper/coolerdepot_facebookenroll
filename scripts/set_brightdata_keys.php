<?php
/**
 * File / 文件：scripts/set_brightdata_keys.php
 * EN: CLI maintenance/deployment script for set brightdata keys.
 * 中文：用于 set brightdata keys 的命令行维护/部署脚本。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
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
