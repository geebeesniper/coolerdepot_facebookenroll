<?php
/**
 * V0.2.98 contract: the original Submit Post action must remain visible on the
 * Sales dashboard and Bulk Submit Post must be added beside it, not replace it.
 */
$root=dirname(__DIR__);
$checks=[];
$version=trim((string)@file_get_contents($root.'/VERSION'));
$dashboard=(string)@file_get_contents($root.'/app/Views/sales/dashboard.php');
$css=(string)@file_get_contents($root.'/public/assets/app.css');
$js=(string)@file_get_contents($root.'/public/assets/app.js');

$checks['VERSION is >= 0.2.98']=version_compare($version,'0.2.98','>=');
$checks['Dashboard still contains original Submit Post button']=
    str_contains($dashboard,'data-open-sales-submit') &&
    str_contains($dashboard,'data-sales-i18n="submitPost"');
$checks['Dashboard contains Bulk Submit Post peer link']=
    str_contains($dashboard,'data-sales-i18n="bulkSubmitPost"') &&
    str_contains($dashboard,'/sales/bulk-submit');
$checks['Both dashboard submit actions share one peer action group']=
    str_contains($dashboard,'class="sales-submit-actions"') &&
    substr_count($dashboard,'sales-submit-actions')===1;
$checks['Peer action group keeps both controls side by side on desktop']=
    str_contains($css,'.sales-submit-actions{') &&
    str_contains($css,'min-width:324px') &&
    str_contains($css,'.sales-submit-actions .sales-submit-cta');
$checks['Original Submit Post click handler still exists']=
    str_contains($js,"$(document).on('click','[data-open-sales-submit]'");

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
