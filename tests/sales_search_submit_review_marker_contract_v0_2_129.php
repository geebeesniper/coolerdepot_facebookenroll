<?php
/**
 * V0.2.129 static contract: Sales Post Search + Bulk Submit share a row and
 * Admin Daily Review trend markers remain clearly visible over stacked bars.
 */
$root = dirname(__DIR__);
$dashboard = file_get_contents($root . '/app/Views/sales/dashboard.php');
$css = file_get_contents($root . '/public/assets/app.css');
$js = file_get_contents($root . '/public/assets/app.js');
$version = trim((string) file_get_contents($root . '/VERSION'));

$checks = [
    'version' => $version === '0.2.129',
    'search_panel_exists' => strpos($dashboard, 'id="salesPostSearchPanel"') !== false,
    'bulk_inside_search_panel' => preg_match('/id="salesPostSearchPanel"[\s\S]*?sales-post-search-submit[\s\S]*?data-open-sales-bulk-submit[\s\S]*?<\/section>/', $dashboard) === 1,
    'header_bulk_removed' => preg_match('/class="sales-portal-head-actions"[\s\S]*?data-open-sales-bulk-submit[\s\S]*?<\/div>\s*<\/div>\s*<section class="sales-post-search-panel"/', $dashboard) !== 1,
    'compact_desktop_grid' => strpos($css, 'grid-template-columns:minmax(280px,720px) max-content;') !== false,
    'mobile_stack' => strpos($css, '@media(max-width:560px)') !== false && strpos($css, '#salesPostSearchPanel .sales-post-search-submit .sales-submit-cta') !== false,
    'review_halo' => strpos($js, 'sales-chart-review-trend-halo') !== false && strpos($js, 'r="7"') !== false,
    'review_dot' => strpos($js, 'sales-chart-review-trend-dot') !== false && strpos($js, 'r="4.5"') !== false,
    'legend_clearer' => strpos($css, '.admin-sales-activity-panel .admin-daily-rating-legend i') !== false && strpos($css, 'width:24px;') !== false,
];

$failed = array_keys(array_filter($checks, static fn($ok) => !$ok));
if ($failed) {
    fwrite(STDERR, "V0.2.129 contract failed: " . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo "OK Sales search/submit row + Daily Review marker v0.2.129\n";
