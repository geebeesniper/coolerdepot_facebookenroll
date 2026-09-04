<?php
/**
 * V0.2.132 contract: Help/manuals must describe the current Bulk Submit-only Sales workflow.
 */
$root = dirname(__DIR__);
$version = trim((string) @file_get_contents($root . '/VERSION'));
if ($version !== '0.2.132') {
    fwrite(STDERR, "Expected VERSION 0.2.132, got {$version}\n");
    exit(1);
}

$files = [
    'in_app_sales' => $root . '/app/Views/help/sales.php',
    'help_shell' => $root . '/app/Views/help.php',
    'sales_html' => $root . '/docs/user-guides/sales.html',
    'admin_html' => $root . '/docs/user-guides/admin.html',
];
$text = [];
foreach ($files as $key => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing {$key}: {$path}\n");
        exit(1);
    }
    $text[$key] = (string) file_get_contents($path);
}

foreach (['in_app_sales', 'sales_html'] as $key) {
    foreach (['Bulk Submit Post', 'Verification Queue', 'Edit &amp; Re-verify', 'Post Search', 'Unavailable'] as $needle) {
        if (strpos($text[$key], $needle) === false) {
            fwrite(STDERR, "{$key} missing current workflow text: {$needle}\n");
            exit(1);
        }
    }
}

$obsolete = [
    'single-item Submit Post',
    'single Submit Post',
    'retired single Submit Post',
    'Save &amp; Wait',
    '<h4>Check Post</h4>',
    'Run Check Post again',
    'Save Verified Post',
    'Open Submit Marketplace Post',
    "'Submit Post':",
    "'Check Post':",
];
foreach ($text as $key => $body) {
    foreach ($obsolete as $needle) {
        if (strpos($body, $needle) !== false) {
            fwrite(STDERR, "{$key} still contains obsolete Help text: {$needle}\n");
            exit(1);
        }
    }
}

foreach (['sales_html', 'admin_html'] as $key) {
    if (strpos($text[$key], 'V0.2.132') === false) {
        fwrite(STDERR, "{$key} version not synchronized to V0.2.132\n");
        exit(1);
    }
}

echo "OK Help/manual Bulk Submit-only workflow v0.2.132\n";
