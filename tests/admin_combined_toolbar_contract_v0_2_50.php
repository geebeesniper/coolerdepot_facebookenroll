<?php
$root = dirname(__DIR__);
$dashboard = file_get_contents($root . '/app/Views/admin/dashboard.php');
$css = file_get_contents($root . '/public/assets/app.css');
$checks = [
    'one directory toolbar' => substr_count($dashboard, 'id="adminSalesDirectoryTools"') === 1,
    'one sales search' => substr_count($dashboard, 'id="salesCardSearch"') === 1,
    'one location filter' => substr_count($dashboard, 'id="salesLocationFilter"') === 1,
    'toolbar moved to range bar' => strpos($dashboard, 'admin-sales-directory-tools-inline') !== false,
    'v0250 css marker' => strpos($css, 'V0.2.50') !== false,
    'desktop one row' => strpos($css, 'flex-wrap:nowrap !important') !== false,
    'location buttons stay one row desktop' => strpos($css, 'overflow-x:auto') !== false,
    'responsive breakpoint exists' => strpos($css, '@media(max-width:1180px)') !== false,
];
$failed = [];
foreach ($checks as $name => $ok) {
    if (!$ok) $failed[] = $name;
}
if ($failed) {
    fwrite(STDERR, "V0.2.50 contract failed: " . implode(', ', $failed) . PHP_EOL);
    return 1;
}
echo 'V0.2.50 contract checks passed: ' . count($checks) . PHP_EOL;
return 0;
