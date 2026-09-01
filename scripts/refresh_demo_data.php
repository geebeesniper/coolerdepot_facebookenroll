<?php
/**
 * File / 文件：scripts/refresh_demo_data.php
 * EN: Operations/deployment/diagnostics script owned by this project.
 * 中文：该文件是本项目自有的运维、部署或诊断脚本。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
$config = require dirname(__DIR__) . '/config/bootstrap.php';

use App\Core\Database;

$pdo = Database::connection();
$sql = file_get_contents(dirname(__DIR__) . '/database/demo.sql');

if ($sql === false) {
    fwrite(STDERR, "Could not read database/demo.sql\n");
    exit(1);
}

$sql = preg_replace('/^\s*--.*$/m', '', $sql);
$statements = preg_split('/;\s*(?:\r?\n|$)/', $sql);

foreach ($statements as $statement) {
    $statement = trim($statement);

    if ($statement === '') {
        continue;
    }

    $pdo->exec($statement);
}

echo "Demo data refreshed to v0.1.55
";
