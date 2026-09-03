<?php
$root = dirname(__DIR__);
$cssPath = $root . '/public/assets/app.css';
$css = file_get_contents($cssPath);
if ($css === false) { fwrite(STDERR, "Cannot read app.css\n"); exit(1); }
$checks = [
    'version marker' => 'v0.2.41 — Website Library layout/refponsive pass.',
    'desktop 3-column tools' => '.website-tools-grid{grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;align-items:stretch;}',
    'readable card title' => '.website-tool-card-copy strong{font-size:15px;line-height:1.25;}',
    'frameless chevron' => 'border-right:2px solid currentColor;',
    'chevron expansion' => '.website-source-expand[aria-expanded="true"] .website-source-expand-arrow{transform:rotate(225deg);}',
    'desktop step 1 form' => 'grid-template-columns:minmax(0,1fr) 150px;',
    'desktop saved website row' => 'grid-template-columns:minmax(230px,1.15fr) minmax(290px,1fr) auto;',
    'history wrapper owns overflow' => 'overflow-x:auto;overflow-y:hidden;',
    'history minimum table width' => '.website-history-table{width:100%;min-width:760px;',
    'desktop CSV layout' => '.website-tool-detail-two .website-tool-form{grid-template-columns:minmax(180px,.75fr) minmax(280px,1.25fr) auto;}',
    'desktop sitemap layout' => '.website-tool-detail-three .website-tool-form{grid-template-columns:minmax(190px,.75fr) minmax(300px,1.35fr) 130px;}',
    'tablet breakpoint' => '@media(max-width:860px)',
    'phone breakpoint' => '@media(max-width:620px)',
    'small phone breakpoint' => '@media(max-width:420px)',
    'mobile one-column tools' => '.website-tools-grid{grid-template-columns:1fr;gap:9px;}',
    'mobile source actions' => '.website-product-source-actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));width:100%;gap:6px;}',
    'mobile library padding' => '.website-library{padding:14px;}',
];
foreach ($checks as $label => $needle) {
    if (strpos($css, $needle) === false) {
        fwrite(STDERR, "Missing CSS contract: $label\n"); exit(1);
    }
}
foreach ([
    'v0.2.38 — Company Website Library visual refinement.',
    'v0.2.39 — compact Website Library controls',
    'v0.2.40 — use the same strong, frameless chevron language'
] as $legacy) {
    if (strpos($css, $legacy) !== false) {
        fwrite(STDERR, "Legacy conflicting Website Library override still present: $legacy\n"); exit(1);
    }
}
echo "V0.2.41 Website Library layout contract passed (" . count($checks) . " checks).\n";
