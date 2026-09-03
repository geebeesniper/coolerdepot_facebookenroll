<?php
/**
 * EN: Static regression contract for v0.2.26 Sales calendar removal and Admin product-detail layout.
 * 中文：v0.2.26 Sales 临时日历移除与 Admin Product Detail 排版静态回归契约。
 */
$root = dirname(__DIR__);
$salesView = file_get_contents($root . '/app/Views/sales/dashboard.php');
$salesJs = file_get_contents($root . '/public/assets/sales-dashboard.js');
$adminView = file_get_contents($root . '/app/Views/admin/dashboard.php');
$css = file_get_contents($root . '/public/assets/app.css');

$checks = [
    'Sales calendar toggle removed' => strpos($salesView, 'salesPostCalendarToggle') === false,
    'Sales calendar panel removed' => strpos($salesView, 'salesPostCalendarGrid') === false,
    'Sales calendar JS removed' => strpos($salesJs, 'renderPostCalendar') === false,
    'Admin product detail wrapper present' => strpos($adminView, 'class="review-product-detail"') !== false,
    'Admin product info wrapper present' => strpos($adminView, 'class="review-product-info"') !== false,
    'Product detail desktop grid present' => strpos($css, 'grid-template-columns:minmax(170px,220px) minmax(0,1fr)') !== false,
    'Product detail phone stack present' => strpos($css, '@media(max-width:480px)') !== false
        && preg_match('/@media\(max-width:480px\)\{[\s\S]*?\.review-product-detail\{[\s\S]*?grid-template-columns:1fr;/', $css) === 1,
];

$failed = [];
foreach ($checks as $label => $ok) {
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $label . PHP_EOL;
    if (!$ok) {
        $failed[] = $label;
    }
}

exit($failed ? 1 : 0);
