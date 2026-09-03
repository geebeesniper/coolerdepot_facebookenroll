<?php
/**
 * File / 文件：tests/admin_maintenance_contract_v0_2_19.php
 * EN: Static contract checks for the browser-only Admin database maintenance feature.
 * 中文：浏览器 Admin 数据库维护功能的静态契约检查。
 */
$root = dirname(__DIR__);
$checks = [
    'routes' => [
        $root . '/index.php',
        ["/admin/maintenance'", "/admin/maintenance/repairs'", "/admin/maintenance/query'"],
    ],
    'controller' => [
        $root . '/app/Controllers/AdminMaintenanceController.php',
        ["Auth::requireRole('admin')", 'Csrf::verify', 'RUN WRITE SQL'],
    ],
    'service' => [
        $root . '/app/Services/DatabaseMaintenance.php',
        ['runRecommendedRepairs', 'provider_registry_enabled', 'manual_pending', 'Only one SQL statement'],
    ],
    'view' => [
        $root . '/app/Views/admin/maintenance.php',
        ['Database Maintenance', 'Run Recommended Repairs', 'Run One Query'],
    ],
];

$failures = [];
foreach ($checks as $name => [$file, $needles]) {
    $text = is_file($file) ? file_get_contents($file) : false;
    if (!is_string($text)) {
        $failures[] = $name . ': missing file';
        continue;
    }
    foreach ($needles as $needle) {
        if (strpos($text, $needle) === false) {
            $failures[] = $name . ': missing ' . $needle;
        }
    }
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Admin maintenance contract v0.2.19: PASS\n";
