<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$version = trim((string) @file_get_contents($root . '/VERSION'));
$dashboard = (string) @file_get_contents($root . '/app/Views/sales/dashboard.php');
$css = (string) @file_get_contents($root . '/public/assets/app.css');

$failed = false;
$check = static function (bool $ok, string $label) use (&$failed): void {
    if ($ok) {
        echo "[PASS] {$label}\n";
        return;
    }
    $failed = true;
    echo "[FAIL] {$label}\n";
};

$check(version_compare($version, '0.2.101', '>='), 'VERSION is >= 0.2.101');
$check(str_contains($dashboard, 'data-open-sales-submit'), 'original Submit Post trigger remains');
$check(str_contains($dashboard, 'data-open-sales-bulk-submit'), 'Bulk Submit Post trigger remains');
$check(str_contains($dashboard, 'sales-submit-cta-cluster'), 'Submit actions remain in the existing shared action cluster');
$check(str_contains($css, 'grid-template-areas:'), 'desktop header uses explicit grid areas');
$check(str_contains($css, '"period dates"') && str_contains($css, '". submit-actions"'), 'Period/date row and right-side submit row are restored');
$check(str_contains($css, 'grid-area:submit-actions') && str_contains($css, 'justify-self:end'), 'Submit action cluster is right-aligned below dates');
$check(str_contains($css, 'grid-area:period') && str_contains($css, 'grid-area:dates'), 'Period and date controls retain separate approved positions');

if ($failed) {
    fwrite(STDERR, "V0.2.101 Sales header layout restore contract failed.\n");
    exit(1);
}

echo "V0.2.101 Sales header layout restore contract passed.\n";
