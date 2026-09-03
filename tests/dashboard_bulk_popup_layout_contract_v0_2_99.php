<?php
/** V0.2.99 contract: restore approved Submit layout and make Bulk Submit a matching popup peer. */
$root=dirname(__DIR__);
$version=trim((string)@file_get_contents($root.'/VERSION'));
$dashboard=(string)@file_get_contents($root.'/app/Views/sales/dashboard.php');
$bulkPartial=(string)@file_get_contents($root.'/app/Views/sales/_bulk_submit_form.php');
$css=(string)@file_get_contents($root.'/public/assets/app.css');
$js=(string)@file_get_contents($root.'/public/assets/app.js');
$header=(string)@file_get_contents($root.'/app/Views/layout/header.php');
$checks=[];
$checks['VERSION is >= 0.2.99']=version_compare($version,'0.2.99','>=');
$bulkPos=strpos($dashboard,'data-open-sales-bulk-submit');
$submitPos=strpos($dashboard,'data-open-sales-submit');
$checks['Bulk button is immediately before original Submit slot so Submit remains right-most']=is_int($bulkPos)&&is_int($submitPos)&&$bulkPos<$submitPos;
$checks['Original Submit button keeps approved primary classes']=str_contains($dashboard,'class="btn primary sales-submit-cta"')&&str_contains($dashboard,'data-open-sales-submit');
$checks['Bulk button copies approved primary button treatment']=str_contains($dashboard,'class="btn primary sales-submit-cta sales-bulk-submit-cta"');
$checks['Bulk button is one-line peer control']=str_contains($css,'.sales-submit-cta,')&&str_contains($css,'white-space:nowrap');
$checks['Bulk uses the same approved modal shell as Submit']=str_contains($dashboard,'id="salesBulkSubmitModal"')&&substr_count($dashboard,'<section class="sales-submit-modal"')>=2;
$checks['Bulk modal has its own close control']=str_contains($dashboard,'id="salesBulkSubmitModalClose"');
$checks['Bulk modal uses shared form with CSRF']=str_contains($dashboard,"/_bulk_submit_form.php")&&str_contains($bulkPartial,'id="salesBulkCsrf"');
$checks['Bulk popup opens and closes in JS']=str_contains($js,'function openSalesBulkSubmitModal()')&&str_contains($js,'function closeSalesBulkSubmitModal()')&&str_contains($js,"$('#salesBulkSubmitModalClose').on('click'");
$checks['Navigation can open popup on dashboard while preserving route fallback']=str_contains($header,'data-open-sales-bulk-submit')&&str_contains($header,'/sales/bulk-submit');
$checks['Obsolete v0.2.98 peer wrapper is gone']=!str_contains($css,'.sales-submit-actions{')&&!str_contains($dashboard,'sales-submit-actions');
$checks['No Bulk Edit text exists']=stripos($dashboard.$bulkPartial.$header.$js,'Bulk Edit')===false;
$failed=[];
foreach($checks as $name=>$ok){echo ($ok?'[PASS] ':'[FAIL] ').$name.PHP_EOL;if(!$ok)$failed[]=$name;}
if($failed){fwrite(STDERR,'V0.2.99 dashboard bulk popup/layout contract failed: '.implode(', ',$failed).PHP_EOL);exit(1);} 
echo count($checks).' V0.2.99 dashboard bulk popup/layout checks passed.'.PHP_EOL;
