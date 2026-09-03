<?php
/** V0.2.108 compatibility contract: Passed queue rows remain linked to their promoted formal Post. */
$root = dirname(__DIR__);
$version = trim((string)@file_get_contents($root . '/VERSION'));
$js = (string)@file_get_contents($root . '/public/assets/app.js');
$checks = [];
$check = static function (bool $ok, string $label) use (&$checks): void {
    $checks[] = [$ok, $label];
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
};
$check($version !== '' && version_compare($version, '0.2.108', '>='), 'VERSION is >= 0.2.108');
$check(strpos($js, 'function vqRevealPassedPost(item,$panel)') !== false, 'Passed row has a dedicated queue-to-formal-Post reveal handler');
$check(strpos($js, '.sales-self-post-card[data-sales-post-id=') !== false, 'Passed row targets the existing formal Post card by saved post_id');
$check(strpos($js, "loadSalesRange({from:published,to:published},'custom'") !== false, 'Passed reveal uses the existing AJAX range loader when the formal Post is outside the loaded day');
$check(strpos($js, "' · Post #'+String(item.post_id)") !== false, 'Passed row visibly identifies the created formal Post');
$failed = array_filter($checks, static fn(array $row): bool => !$row[0]);
if ($failed) {
    fwrite(STDERR, "V0.2.108 Passed/formal Post compatibility contract failed.\n");
    exit(1);
}
echo "V0.2.108 Passed/formal Post compatibility contract passed.\n";
