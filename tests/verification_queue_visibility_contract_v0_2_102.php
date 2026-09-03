<?php
/** V0.2.102 contract: Save & Wait queue rows remain visible and counters/list share one snapshot. */
$root=dirname(__DIR__);
$checks=[];
$check=function(bool $ok,string $label)use(&$checks):void{$checks[]=[$ok,$label];printf('[%s] %s\n',$ok?'PASS':'FAIL',$label);};
$version=trim((string)@file_get_contents($root.'/VERSION'));
$model=(string)@file_get_contents($root.'/app/Models/VerificationQueue.php');
$controller=(string)@file_get_contents($root.'/app/Controllers/VerificationQueueController.php');
$js=(string)@file_get_contents($root.'/public/assets/app.js');
$dashboard=(string)@file_get_contents($root.'/app/Views/sales/dashboard.php');

$check(version_compare($version,'0.2.102','>='),'VERSION is >= 0.2.102');
$check(str_contains($model,'function snapshotForSales')&&str_contains($model,"return ['counts'=>\$counts,'items'=>\$items]"),'queue model exposes one-snapshot counts + rows');
$posSnapshot=strpos($controller,'snapshotForSales');
$posKick=strpos($controller,'VerificationQueueWorker::kick()');
$check($posSnapshot!==false&&$posKick!==false&&$posSnapshot<$posKick,'queue snapshot is captured before worker kick');
$check(str_contains($controller,"'items'=>\$items"),'queue API returns rows from the same snapshot');
$check(str_contains($js,'function vqNormalizeItems')&&str_contains($js,'function vqShowAcceptedItem'),'queue browser renderer normalizes rows and supports immediate accepted-item display');
$check(str_contains($js,'vqShowAcceptedItem(resp.item,resp.counts);'),'Save & Wait immediately paints the accepted queue item');
$check(str_contains($js,"Verification Queue row render failed")&&str_contains($js,"sales-vq-row"),'one display-field error cannot blank the whole queue list');
$check(str_contains($dashboard,'data-open-sales-submit')&&str_contains($dashboard,'data-open-sales-bulk-submit'),'existing Submit Post and Bulk Submit Post dashboard actions remain present');

$failed=array_filter($checks,static fn($r)=>!$r[0]);
if($failed){fwrite(STDERR,"V0.2.102 verification queue visibility contract failed.\n");exit(1);} 
printf("V0.2.102 verification queue visibility contract passed.\n");
