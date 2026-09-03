<?php
/** V0.2.100 contract: Bulk Submit Post must be visible beside the approved Submit Post slot. */
$root=dirname(__DIR__);
$version=trim((string)@file_get_contents($root.'/VERSION'));
$dashboard=(string)@file_get_contents($root.'/app/Views/sales/dashboard.php');
$css=(string)@file_get_contents($root.'/public/assets/app.css');
$js=(string)@file_get_contents($root.'/public/assets/app.js');
$checks=[];
$checks['VERSION is >= 0.2.100']=version_compare($version,'0.2.100','>=');
$checks['Dashboard has one action cluster for Bulk + Submit']=str_contains($dashboard,'class="sales-submit-cta-cluster"')&&str_contains($dashboard,'data-sales-cta-cluster');
$bulkPos=strpos($dashboard,'data-open-sales-bulk-submit');
$submitPos=strpos($dashboard,'data-open-sales-submit');
$checks['Bulk remains immediately left of Submit']=is_int($bulkPos)&&is_int($submitPos)&&$bulkPos<$submitPos;
$checks['Both actions keep approved primary CTA classes']=str_contains($dashboard,'class="btn primary sales-submit-cta sales-bulk-submit-cta"')&&str_contains($dashboard,'class="btn primary sales-submit-cta"');
$checks['Desktop action deck returns to three approved slots']=str_contains($css,'.sales-portal-head-actions{')&&str_contains($css,'grid-template-columns:auto auto auto;');
$checks['Action cluster aligns on original Submit baseline']=str_contains($css,'.sales-submit-cta-cluster{')&&str_contains($css,'margin-top:18px;')&&str_contains($css,'.sales-submit-cta-cluster .sales-submit-cta');
$checks['Bulk modal still opens from dashboard']=str_contains($dashboard,'id="salesBulkSubmitModal"')&&str_contains($js,"[data-open-sales-bulk-submit]")&&str_contains($js,'openSalesBulkSubmitModal()');
$checks['Submit modal still opens from dashboard']=str_contains($dashboard,'id="salesSubmitModal"')&&str_contains($js,"[data-open-sales-submit]")&&str_contains($js,'openSalesSubmitModal()');
$checks['No Bulk Edit text exists']=stripos($dashboard.$js,'Bulk Edit')===false;
$failed=[];
foreach($checks as $name=>$ok){echo ($ok?'[PASS] ':'[FAIL] ').$name.PHP_EOL;if(!$ok)$failed[]=$name;}
if($failed){fwrite(STDERR,'V0.2.100 dashboard bulk visibility contract failed: '.implode(', ',$failed).PHP_EOL);exit(1);}
echo count($checks).' V0.2.100 dashboard bulk visibility checks passed.'.PHP_EOL;
