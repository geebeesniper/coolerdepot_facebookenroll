<?php
/**
 * File / 文件：scripts/set_scrapecreators_key.php
 * EN: CLI maintenance/deployment script for set scrapecreators key.
 * 中文：用于 set scrapecreators key 的命令行维护/部署脚本。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
$config = require dirname(__DIR__) . '/config/bootstrap.php';

use App\Core\Database;
use App\Models\Setting;

$key = trim((string)getenv('SC_KEY'));

if ($key === '') {
    fwrite(STDERR, "SC_KEY environment variable is empty.\n");
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

Setting::set('scrapecreators_api_key', $key, $adminId, true);
Setting::set('scrapecreators_enabled', '1', $adminId);
Setting::set('scrapecreators_timeout_seconds', '20', $adminId);

echo "ScrapeCreators fallback enabled. API key stored encrypted.\n";
