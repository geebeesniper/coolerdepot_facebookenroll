<?php
$css = file_get_contents(__DIR__ . '/../public/assets/app.css');
$checks = [
    'v0.2.38 marker' => strpos($css, 'v0.2.38 — Company Website Library visual refinement') !== false,
    'plain tool chevron' => (bool)preg_match('/\.website-tool-arrow\s*\{[^}]*border:0;[^}]*background:transparent;/s', $css),
    'plain saved-site chevron' => (bool)preg_match('/\.website-source-expand-arrow\s*\{[^}]*border:0;[^}]*background:transparent;/s', $css),
    'step number no fake square' => (bool)preg_match('/\.website-tool-card \.settings-step\s*\{[^}]*border:0;[^}]*background:transparent;/s', $css),
    'compact expanded border' => (bool)preg_match('/\.website-tool-detail\s*\{[^}]*border:1px solid;[^}]*border-top-width:3px;/s', $css),
    'compact empty state' => (bool)preg_match('/\.website-source-empty,\s*\.website-history-empty\s*\{[^}]*border:0;[^}]*text-align:left;/s', $css),
    'desktop three columns retained' => strpos($css, '.website-tools-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr))') !== false,
    'tablet response' => strpos($css, '@media(max-width:980px)') !== false,
    'phone response' => strpos($css, '@media(max-width:680px)') !== false,
    'small phone response' => strpos($css, '@media(max-width:420px)') !== false,
];
$failed = [];
foreach ($checks as $name => $ok) {
    if (!$ok) $failed[] = $name;
}
if ($failed) {
    fwrite(STDERR, "V0.2.38 website-library visual contract failed: " . implode(', ', $failed) . "\n");
    exit(1);
}
echo "V0.2.38 website-library visual contract: " . count($checks) . " checks passed.\n";
