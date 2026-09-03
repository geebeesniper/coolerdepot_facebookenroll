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

$check(version_compare($version, '0.2.82', '>='), 'VERSION must be 0.2.82 or newer.');
$check(strpos($js, 'function salesDirectoryFilteringActive()') !== false, 'Missing centralized Sales directory filter-active check.');

// V0.2.96 supersedes the original V0.2.82 list-only behavior: filtering may
// narrow the Sales cards, but View Posts must still work for a visible card.
$check(
    strpos($js, "if(salesDirectoryFilteringActive()){\n            closeExpandedPosts();\n            return;\n        }") === false,
    'Filtered Sales cards must no longer be blocked from opening View Posts.'
);
$check(
    strpos($js, "if(\$card.hasClass('sales-directory-hidden')){\n            return;\n        }") !== false,
    'Only a Sales card hidden by the active directory filter may be prevented from opening.'
);
$check(strpos($js, "let salesDirectoryExpandedControlsReady=false;") !== false, 'Initial dashboard filter pass must be safe before expanded controls initialize.');
$check(strpos($css, '/* v0.2.82 — Location cards size independently while one card is being edited. */') !== false, 'Missing independent Location card sizing rule.');
$check((bool)preg_match('/\.sales-location-list\s*\{[^}]*align-items\s*:\s*start\s*;/s', $css), 'Location grid must align cards to start instead of stretching row height.');
$check((bool)preg_match('/\.sales-location-card\s*\{[^}]*align-self\s*:\s*start\s*;/s', $css), 'Each Location card must size independently.');

if ($fail) {
    fwrite(STDERR, "v0.2.82 Admin Sales filter contract: FAIL\n- " . implode("\n- ", $fail) . "\n");
    exit(1);
}

fwrite(STDOUT, "v0.2.82 Admin Sales filter contract passed.\n");
