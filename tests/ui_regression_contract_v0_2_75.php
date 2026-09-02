<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$cssPath = $root . '/public/assets/app.css';
$css = is_file($cssPath) ? (string) file_get_contents($cssPath) : '';

$checks = [
    'desktop_split_marker' => str_contains($css, 'v0.2.75: keep Sales Post Details side-by-side on desktop'),
    'desktop_breakpoint' => str_contains($css, '@media (min-width:721px)'),
    'desktop_two_columns' => str_contains($css, 'grid-template-columns:minmax(280px,42%) minmax(0,1fr);'),
    'image_left_column' => str_contains($css, 'grid-column:1;') && str_contains($css, '.sales-post-detail-image-button'),
    'content_right_column' => preg_match('/\.sales-post-detail-content\s*\{[^}]*grid-column:2;[^}]*grid-row:1;/s', $css) === 1,
    'desktop_modal_width' => str_contains($css, 'width:min(1040px,calc(100vw - 36px));'),
    'mobile_breakpoint' => str_contains($css, '@media (max-width:720px)'),
    'mobile_stack' => preg_match('/@media \(max-width:720px\)\s*\{.*?\.sales-post-detail-scroll\s*\{\s*display:block;/s', $css) === 1,
];

$failed = false;
foreach ($checks as $name => $ok) {
    printf('[%s] %s%s', $ok ? 'PASS' : 'FAIL', $name, PHP_EOL);
    $failed = $failed || !$ok;
}

if ($failed) {
    fwrite(STDERR, "V0.2.75 UI contract failed.\n");
    exit(1);
}

fwrite(STDOUT, "V0.2.75 UI contract passed.\n");
