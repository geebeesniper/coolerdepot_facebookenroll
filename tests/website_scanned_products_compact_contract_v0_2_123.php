<?php
$css = file_get_contents(__DIR__ . '/../public/assets/app.css');
if ($css === false) {
    fwrite(STDERR, "Unable to read app.css\n");
    exit(1);
}
$checks = [
    'v0.2.123 marker' => str_contains($css, 'v0.2.123 — compact Scanned Products row'),
    'toggle min height removed' => str_contains($css, ".website-scanned-products-toggle{\n    min-height:0;"),
    'toggle vertical padding compact' => str_contains($css, 'padding:6px 14px;'),
    'arrow fixed to second column' => str_contains($css, ".website-scanned-products-toggle .website-tool-arrow{\n    grid-column:2;"),
    'arrow fixed to first row' => str_contains($css, 'grid-row:1;'),
    'arrow vertically centered' => str_contains($css, 'align-self:center;'),
    'mobile remains scoped' => str_contains($css, '@media(max-width:760px)'),
];
foreach ($checks as $name => $ok) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$name}\n");
        exit(1);
    }
}
echo "OK compact Scanned Products v0.2.123\n";
