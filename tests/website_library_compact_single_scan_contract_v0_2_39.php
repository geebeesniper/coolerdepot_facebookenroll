<?php
$root=dirname(__DIR__);
$css=(string)file_get_contents($root.'/public/assets/app.css');
$js=(string)file_get_contents($root.'/public/assets/app.js');
$view=(string)file_get_contents($root.'/app/Views/admin/settings.php');
$service=(string)file_get_contents($root.'/app/Services/WebsiteScanJob.php');
$controller=(string)file_get_contents($root.'/app/Controllers/AdminSettingsController.php');
$checks=[
    'version marker' => trim((string)file_get_contents($root.'/VERSION'))==='0.2.39',
    'css marker' => strpos($css,'v0.2.39 — compact Website Library controls')!==false,
    'tool arrows no glyph' => substr_count($view,'class="website-tool-arrow" aria-hidden="true"></span>')===3,
    'saved source arrow no glyph' => strpos($view,'class="website-source-expand-arrow" aria-hidden="true"></span>')!==false,
    'css chevron pseudo element' => strpos($css,'.website-tool-arrow::before')!==false && strpos($css,'rotate(45deg)')!==false,
    'website add row desktop' => strpos($css,'grid-template-columns:auto minmax(0,1fr) auto;')!==false,
    'saved website one row actions' => (bool)preg_match('/\.website-product-source-actions\s*\{[^}]*display:flex;[^}]*flex-wrap:nowrap;/s',$css),
    'compact saved site buttons' => (bool)preg_match('/\.website-product-source-actions \.btn,[^{]+\{[^}]*font-size:8\.5px;/s',$css),
    'responsive 1120' => strpos($css,'@media(max-width:1120px)')!==false,
    'responsive 680' => strpos($css,'@media(max-width:680px)')!==false,
    'responsive 420' => strpos($css,'@media(max-width:420px)')!==false,
    'server running hosts query' => strpos($service,'public static function runningHosts(): array')!==false,
    'server global start lock' => strpos($service,"cdsp-webscan-global-start")!==false,
    'server blocks second host' => strpos($service,'Another website is currently scanning:')!==false,
    'resume also globally guarded' => substr_count($service,"cdsp-webscan-global-start")>=2,
    'controller passes running hosts' => strpos($controller,"'websiteRunningScanHosts'")!==false,
    'delete blocked while any scan runs' => strpos($controller,'Stop the active website scan before deleting any website.')!==false,
    'view carries running state' => strpos($view,'data-running-hosts=')!==false,
    'view pre-disables new scan button' => strpos($view,'data-global-scan-disabled="1"')!==false,
    'client global state' => strpos($js,'const runningHosts={};')!==false && strpos($js,'syncGlobalScanControls')!==false,
    'client blocks second scan' => strpos($js,'Another website is already scanning:')!==false,
];
$failed=[];
foreach($checks as $name=>$ok){if(!$ok)$failed[]=$name;}
if($failed){fwrite(STDERR,'V0.2.39 contract failed: '.implode(', ',$failed)."\n");return 1;}
printf("V0.2.39 website library contract: %d checks passed.\n",count($checks));
return 0;
