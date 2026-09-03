<?php
$root = dirname(__DIR__);
$cssPath = $root . '/public/assets/app.css';
$versionPath = $root . '/VERSION';
$css = @file_get_contents($cssPath);
$version = trim((string) @file_get_contents($versionPath));
$fail = static function (string $message): void {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};
if ($version !== '0.2.44') $fail('VERSION must be 0.2.44');
if (!is_string($css) || $css === '') $fail('app.css missing');
$required = [
    '/* V0.2.44: clean right-edge chevrons.',
    '.website-tool-arrow::before,',
    '.website-source-expand-arrow::before{',
    'border:0!important;',
    'border-radius:0!important;',
    'border-right:2px solid currentColor;',
    'border-bottom:2px solid currentColor;',
    '.website-tool-card[aria-expanded="true"] .website-tool-arrow::before,',
    'display:inline-flex!important;',
    'align-items:center!important;',
    'justify-content:center!important;',
    '.website-tool-field input[type="file"]::file-selector-button{',
    'line-height:1!important;',
];
foreach ($required as $needle) {
    if (strpos($css, $needle) === false) $fail('missing CSS contract: ' . $needle);
}
// The active consolidated arrow must explicitly reset all legacy border/radius styles.
$marker = strpos($css, '/* V0.2.44: clean right-edge chevrons.');
if ($marker === false) $fail('V0.2.44 arrow marker missing');
$segment = substr($css, $marker, 2200);
if (strpos($segment, 'border:0!important;') === false || strpos($segment, 'border-radius:0!important;') === false) {
    $fail('arrow container does not reset legacy circle styling');
}
echo "V0.2.44 Website Library UI contract checks passed.\n";
