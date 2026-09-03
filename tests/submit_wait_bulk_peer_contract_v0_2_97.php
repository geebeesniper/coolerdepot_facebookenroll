<?php
/**
 * V0.2.97+ contract: Save & Wait is offered after the two fast preflight checks
 * and before provider/fetch verification; Bulk Submit Post remains a peer workflow.
 */
$root=dirname(__DIR__);
$checks=[];
$version=trim((string)@file_get_contents($root.'/VERSION'));
$checks['VERSION is >= 0.2.97']=version_compare($version,'0.2.97','>=');

$submit=(string)@file_get_contents($root.'/app/Views/sales/_submit_form.php');
$dashboard=(string)@file_get_contents($root.'/app/Views/sales/dashboard.php');
$bulk=(string)@file_get_contents($root.'/app/Views/sales/bulk_submit.php');
$bulkPartial=(string)@file_get_contents($root.'/app/Views/sales/_bulk_submit_form.php');
$header=(string)@file_get_contents($root.'/app/Views/layout/header.php');
$controller=(string)@file_get_contents($root.'/app/Controllers/SalesController.php');
$routes=(string)@file_get_contents($root.'/index.php');
$js=(string)@file_get_contents($root.'/public/assets/app.js');

$checks['Submit form has explicit preflight decision gate']=str_contains($submit,'id="salesPreflightActions"');
$checks['Save & Wait exists only in the post preflight decision UI']=substr_count($submit,'id="saveWaitButton"')===1;
$checks['Continue Verification is paired with Save & Wait']=str_contains($submit,'id="continueVerifyButton"');
$checks['Bulk controls are not nested in Submit Post form']=!str_contains($submit,'salesBulkUrls')&&!str_contains($submit,'bulkQueueButton')&&!str_contains($submit,'bulkSubmitToggle');
$checks['Dashboard exposes peer Submit and Bulk Submit actions']=str_contains($dashboard,'data-open-sales-submit')&&str_contains($dashboard,'data-open-sales-bulk-submit')&&str_contains($dashboard,'data-sales-i18n="bulkSubmitPost"');
$checks['Shared Bulk Submit Post form exists']=str_contains($bulkPartial,'id="salesBulkUrls"')&&str_contains($bulkPartial,'id="bulkQueueButton"')&&str_contains($bulkPartial,'name="_csrf"');
$checks['Dedicated Bulk Submit Post fallback page still exists']=str_contains($bulk,"/_bulk_submit_form.php")&&str_contains($bulk,'Bulk Submit Post');
$checks['Bulk Submit button keeps the Bulk Submit Post label after requests']=str_contains($js,"text(salesTr('bulkSubmitPost'))");
$checks['Bulk Submit route remains as fallback']=str_contains($routes,"'/sales/bulk-submit'")&&str_contains($routes,"'bulkSubmitForm'");
$checks['Bulk Submit controller action exists']=str_contains($controller,'function bulkSubmitForm');
$checks['Bulk Submit remains a peer navigation item']=str_contains($header,"\$navActive['bulk_submit']")&&str_contains($header,'data-nav-i18n="bulkSubmit"');
$checks['Preflight success pauses before provider/fetch']=str_contains($js,'if(!continueImmediately)')&&str_contains($js,"$('#salesPreflightActions').removeClass('hidden')")&&str_contains($js,'BEFORE provider/fetch (step 3)');
$checks['Continue Verification explicitly resumes full verification']=str_contains($js,'salesContinueAfterPreflight=true')&&str_contains($js,"$('#inspectForm').trigger('submit')");
$checks['No Bulk Edit label exists']=stripos($submit.$dashboard.$bulk.$bulkPartial.$header.$js,'Bulk Edit')===false;

$failed=[];
foreach($checks as $name=>$ok){
    echo ($ok?'[PASS] ':'[FAIL] ').$name.PHP_EOL;
    if(!$ok)$failed[]=$name;
}
if($failed){
    fwrite(STDERR,'V0.2.97 submit/wait/bulk peer contract failed: '.implode(', ',$failed).PHP_EOL);
    exit(1);
}
echo count($checks).' V0.2.97 submit/wait/bulk peer checks passed.'.PHP_EOL;
