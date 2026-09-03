<?php
/**
 * V0.2.98+ contract: original Submit Post must remain visible and functional;
 * Bulk Submit Post is added as a peer without replacing or restyling Submit.
 */
$root=dirname(__DIR__);
$checks=[];
$version=trim((string)@file_get_contents($root.'/VERSION'));
$dashboard=(string)@file_get_contents($root.'/app/Views/sales/dashboard.php');
$css=(string)@file_get_contents($root.'/public/assets/app.css');
$js=(string)@file_get_contents($root.'/public/assets/app.js');

$checks['VERSION is >= 0.2.98']=version_compare($version,'0.2.98','>=');
$checks['Dashboard still contains original Submit Post button']=str_contains($dashboard,'class="btn primary sales-submit-cta"')&&str_contains($dashboard,'data-open-sales-submit')&&str_contains($dashboard,'data-sales-i18n="submitPost"');
$checks['Dashboard contains Bulk Submit Post peer button']=str_contains($dashboard,'class="btn primary sales-submit-cta sales-bulk-submit-cta"')&&str_contains($dashboard,'data-open-sales-bulk-submit')&&str_contains($dashboard,'data-sales-i18n="bulkSubmitPost"');
$checks['Both peer buttons are direct dashboard header controls']=!str_contains($dashboard,'class="sales-submit-actions"');
$checks['Original Submit Post click handler still exists']=str_contains($js,"$(document).on('click','[data-open-sales-submit]'");
$checks['Bulk Submit popup click handler exists']=str_contains($js,"$(document).on('click','[data-open-sales-bulk-submit]'");
$checks['Desktop header reserves four peer control columns']=str_contains($css,'grid-template-columns:auto auto auto auto');

$failed=[];
foreach($checks as $name=>$ok){
    echo ($ok?'[PASS] ':'[FAIL] ').$name.PHP_EOL;
    if(!$ok)$failed[]=$name;
}
if($failed){
    fwrite(STDERR,'V0.2.98 dashboard submit/bulk visibility contract failed: '.implode(', ',$failed).PHP_EOL);
    exit(1);
}
echo count($checks).' V0.2.98 dashboard submit/bulk visibility checks passed.'.PHP_EOL;
