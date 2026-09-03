<?php
/** V0.2.105 contract: Save & Wait closes after AJAX accept; paired controls share exact heights. */
$root=dirname(__DIR__);
$fails=0;
$check=function(bool $ok,string $label)use(&$fails){printf("[%s] %s\n",$ok?'PASS':'FAIL',$label);if(!$ok)$fails++;};
$version=trim((string)@file_get_contents($root.'/VERSION'));
$js=(string)@file_get_contents($root.'/public/assets/app.js');
$css=(string)@file_get_contents($root.'/public/assets/app.css');
$check(version_compare($version,'0.2.105','>='),'VERSION is >= 0.2.105');
$start=strpos($js,"$('#saveWaitButton').on('click'");
$end=$start===false?false:strpos($js,"$('#bulkQueueButton').on('click'",$start);
$block=($start!==false&&$end!==false)?substr($js,$start,$end-$start):'';
$check($block!==''&&str_contains($block,"vqPost('/api/verification-queue/enqueue'"),'Save & Wait remains AJAX queue enqueue');
$check(str_contains($block,'if(resp.accepted===false)')&&str_contains($block,'closeSalesSubmitModal();'),'accepted Save & Wait closes Submit popup, rejected item stays visible for correction');
$rejectPos=strpos($block,'if(resp.accepted===false)');
$returnPos=strpos($block,'return;',$rejectPos===false?0:$rejectPos);
$closePos=strpos($block,'closeSalesSubmitModal();');
$check($rejectPos!==false&&$returnPos!==false&&$closePos!==false&&$returnPos<$closePos,'rejected enqueue does not close the popup');
$check(!str_contains($block,'window.location')&&!str_contains($block,'location.reload'),'Save & Wait does not navigate or reload the Dashboard');
$check(str_contains($css,'.sales-verification-queue-head-actions > .btn.compact,')&&str_contains($css,'.sales-verification-queue-head-actions > .sales-verification-queue-collapse{')&&str_contains($css,"height:30px;\n    min-height:30px;"),'Verification Queue Refresh/collapse controls have equal 30px height');
$check(str_contains($css,'.sales-submit-cta-cluster > .sales-submit-cta{')&&str_contains($css,"height:38px;\n    min-height:38px;"),'Bulk Submit and Submit Post keep equal desktop height');
$check(str_contains($css,'.sales-vq-status{')&&str_contains($css,'width:72px;')&&str_contains($css,'height:22px;'),'queue card status badges remain equal size');
if($fails){fwrite(STDERR,"V0.2.105 contract failed: {$fails} check(s).\n");exit(1);} 
printf("V0.2.105 Save & Wait/button-height contract passed.\n");
