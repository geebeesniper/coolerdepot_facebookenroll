<?php
$root = dirname(__DIR__);
$version = trim((string)file_get_contents($root . '/VERSION'));
if ($version !== '0.2.131') {
    fwrite(STDERR, "Expected VERSION 0.2.131, got {$version}\n");
    exit(1);
}
$view = (string)file_get_contents($root . '/app/Views/sales/dashboard.php');
$css = (string)file_get_contents($root . '/public/assets/app.css');
$checks = [
    'heading wrapper' => strpos($view, 'class="sales-post-search-heading"') !== false,
    'helper remains translated' => strpos($view, 'data-sales-i18n="salesPostSearchHelp"') !== false,
    'helper sits before input row' => strpos($view, 'sales-post-search-heading') < strpos($view, 'sales-post-search-input-row'),
    'desktop heading flex' => strpos($css, '#salesPostSearchPanel .sales-post-search-heading{') !== false,
    'desktop helper nowrap' => strpos($css, 'white-space:nowrap;') !== false,
    'mobile helper can wrap' => strpos($css, 'white-space:normal;') !== false,
    'mobile panel stacks' => strpos($css, '@media(max-width:560px)') !== false,
    'panel remains unframed' => strpos($css, 'border:0;') !== false && strpos($css, 'background:transparent;') !== false,
];
foreach ($checks as $name => $ok) {
    if (!$ok) {
        fwrite(STDERR, "Contract failed: {$name}\n");
        exit(1);
    }
}
echo "OK Sales compact search heading responsive v0.2.131\n";
