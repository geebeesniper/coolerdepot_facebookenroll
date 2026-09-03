<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$version = trim((string)@file_get_contents($root . '/VERSION'));
$js = (string)@file_get_contents($root . '/public/assets/app.js');
$css = (string)@file_get_contents($root . '/public/assets/app.css');

$fail = [];
$check = static function (bool $ok, string $message) use (&$fail): void {
    if (!$ok) { $fail[] = $message; }
};

$check(in_array($version, ['0.2.81', '0.2.82'], true), 'VERSION must be 0.2.81 or 0.2.82 during this incremental upgrade.');
$check(strpos($js, 'function salesDirectoryFilteringActive()') !== false, 'Missing centralized Sales directory filter-active check.');
$check(strpos($js, "salesDirectoryFilteringActive()\n            && salesDirectoryExpandedControlsReady") !== false, 'Filtering must close expanded Sales/Post details.');
$check(strpos($js, "if(salesDirectoryFilteringActive()){\n            closeExpandedPosts();\n            return;\n        }") !== false, 'Expanded Details must not reopen while Sales Search/Location filter is active.');
$check(strpos($js, "let salesDirectoryExpandedControlsReady=false;") !== false, 'Initial dashboard filter pass must be safe before expanded controls initialize.');
$check(strpos($css, '/* v0.2.82 — Location cards size independently while one card is being edited. */') !== false, 'Missing independent Location card sizing rule.');
$check((bool)preg_match('/\.sales-location-list\s*\{[^}]*align-items\s*:\s*start\s*;/s', $css), 'Location grid must align cards to start instead of stretching row height.');
$check((bool)preg_match('/\.sales-location-card\s*\{[^}]*align-self\s*:\s*start\s*;/s', $css), 'Each Location card must size independently.');

if ($fail) {
    fwrite(STDERR, "v0.2.82 Admin Sales filter contract: FAIL\n- " . implode("\n- ", $fail) . "\n");
    exit(1);
}

fwrite(STDOUT, "v0.2.82 Admin Sales filter contract passed.\n");
