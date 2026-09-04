<?php
$root = dirname(__DIR__);
$css = file_get_contents($root . '/public/assets/app.css');
$js = file_get_contents($root . '/public/assets/app.js');
$version = trim(file_get_contents($root . '/VERSION'));
$needles = [
    '#salesPostSearchPanel{',
    'border:0;',
    'background:transparent;',
    'box-shadow:none;',
    '.sales-chart-review-trend-star',
    'content:"★";',
];
if ($version !== '0.2.130') {
    fwrite(STDERR, "Unexpected VERSION: {$version}\n"); exit(1);
}
foreach ($needles as $needle) {
    if (strpos($css, $needle) === false) {
        fwrite(STDERR, "Missing CSS contract: {$needle}\n"); exit(1);
    }
}
if (strpos($js, 'sales-chart-review-trend-star') === false || strpos($js, '>★</text>') === false) {
    fwrite(STDERR, "Missing SVG star review marker\n"); exit(1);
}
if (strpos($js, 'sales-chart-review-trend-halo') !== false && strpos($js, 'class="sales-chart-review-trend-halo"') !== false) {
    fwrite(STDERR, "Old circle marker still rendered\n"); exit(1);
}
echo "OK Sales unframed search + Daily Review star v0.2.130\n";
