<?php
/**
 * V0.2.17 compact horizontal mobile-menu contract test.
 * V0.2.17 窄屏横向紧凑菜单契约测试。
 */
$root = dirname(__DIR__);
$css = file_get_contents($root . '/public/assets/responsive.css');
$version = trim((string)file_get_contents($root . '/VERSION'));
$failures = [];
$expect = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) { $failures[] = $message; }
};
$expect(version_compare($version, '0.2.17', '>='), 'VERSION must be V0.2.17 or newer.');
$expect(str_contains($css, '.topbar[data-user-role="sales"] .app-nav-menu'), 'Sales mobile grid selector missing.');
$expect(str_contains($css, 'grid-template-columns:repeat(4,minmax(0,1fr)) !important;'), 'Sales four-column mobile menu missing.');
$expect(str_contains($css, '.topbar[data-user-role="admin"] .app-nav-menu'), 'Admin mobile grid selector missing.');
$expect(str_contains($css, 'grid-template-columns:repeat(5,minmax(0,1fr)) !important;'), 'Admin five-column mobile menu missing.');
$expect(str_contains($css, 'grid-column:1 / -1 !important;'), 'Mobile user row must span all action columns.');
$expect(str_contains($css, 'justify-content:center !important;'), 'Mobile action labels must be centered.');
if ($failures) {
    fwrite(STDERR, "V0.2.17 mobile horizontal menu contract: FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "V0.2.17 mobile horizontal menu contract: PASS\n";
