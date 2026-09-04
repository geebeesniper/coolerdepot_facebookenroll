<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$jsPath = $root . '/public/assets/app.js';
$versionPath = $root . '/VERSION';

function fail(string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

$js = @file_get_contents($jsPath);
if (!is_string($js) || $js === '') {
    fail('public/assets/app.js could not be read');
}

$version = trim((string)@file_get_contents($versionPath));
if ($version !== '0.2.135') {
    fail('VERSION must be 0.2.135');
}

$required = [
    'function vqRevealAfterSubmission()',
    'if(!$panel.hasClass(\'is-collapsed\'))return;',
    '$panel.attr(\'data-vq-current-filter\',\'all\');',
    '$panel.find(\'[data-vq-filter="all"]\').addClass(\'active\');',
    'vqSetCollapsed($panel,false,true);',
    "function vqShowAcceptedItem(item,counts){\n        if(!item||!item.id)return;\n        vqRevealAfterSubmission();",
    "$('#salesBulkUrls').val('');\n            vqRevealAfterSubmission();\n            vqLoadAll(false);",
];

foreach ($required as $needle) {
    if (strpos($js, $needle) === false) {
        fail('missing queue auto-reveal contract fragment: ' . $needle);
    }
}

$helperPos = strpos($js, 'function vqRevealAfterSubmission()');
$acceptedPos = strpos($js, 'function vqShowAcceptedItem(item,counts)');
$bulkPos = strpos($js, "$('#bulkQueueButton').on('click'");
if ($helperPos === false || $acceptedPos === false || $bulkPos === false || !($helperPos < $acceptedPos && $acceptedPos < $bulkPos)) {
    fail('queue auto-reveal helper/order contract is invalid');
}

echo "v0.2.135 verification queue auto-reveal contract OK\n";
