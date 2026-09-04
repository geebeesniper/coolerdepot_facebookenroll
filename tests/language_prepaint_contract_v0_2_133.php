<?php
$root = dirname(__DIR__);
$header = file_get_contents($root . '/app/Views/layout/header.php');
$footer = file_get_contents($root . '/app/Views/layout/footer.php');
$checks = [
    "localStorage language prepaint" => "localStorage.getItem('cdsp-admin-language')",
    "pending language gate" => "data-cdsp-language-pending",
    "prepaint hidden body" => 'visibility:hidden!important',
    "footer reveal" => "removeAttribute('data-cdsp-language-pending')",
    "after app language scripts" => 'sales-dashboard.js',
];
foreach ($checks as $label => $needle) {
    $haystack = in_array($label, ['footer reveal','after app language scripts'], true) ? $footer : $header;
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "Missing {$label}: {$needle}\n");
        exit(1);
    }
}
if (strpos($footer, 'sales-dashboard.js') > strpos($footer, "removeAttribute('data-cdsp-language-pending')")) {
    fwrite(STDERR, "Reveal must be registered after sales-dashboard.js\n");
    exit(1);
}
echo "OK language prepaint v0.2.133\n";
