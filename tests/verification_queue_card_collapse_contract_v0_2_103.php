<?php
/** V0.2.103 contract: Verification Queue stays AJAX-driven, uses Post-style cards, and can collapse without changing business logic. */
declare(strict_types=1);

$root=dirname(__DIR__);
$version=trim((string)@file_get_contents($root.'/VERSION'));
$view=(string)@file_get_contents($root.'/app/Views/sales/_verification_queue.php');
$js=(string)@file_get_contents($root.'/public/assets/app.js');
$css=(string)@file_get_contents($root.'/public/assets/app.css');
$failed=false;
$check=static function(bool $ok,string $label)use(&$failed):void{
    printf('[%s] %s\n',$ok?'PASS':'FAIL',$label);
    if(!$ok)$failed=true;
};

$check(version_compare($version,'0.2.103','>='),'VERSION is >= 0.2.103');
$check(str_contains($view,'sales-verification-queue is-collapsed')&&str_contains($view,'data-vq-collapse-toggle')&&str_contains($view,'data-vq-collapse-body'),'Verification Queue is collapsible and starts compact');
$check(str_contains($view,'data-vq-filter="failed"')&&str_contains($view,'data-vq-filter="needs_action"'),'existing clickable queue filters remain');
$check(str_contains($css,'.sales-verification-queue-list')&&str_contains($css,'grid-template-columns:repeat(auto-fill,minmax(245px,1fr))'),'queue uses responsive card/grid layout instead of horizontal list');
$check(str_contains($css,'.sales-vq-row')&&str_contains($css,'flex-direction:column')&&str_contains($css,'border-top:4px solid var(--vq-accent)'),'queue item uses compact Post-style card presentation');
$check(str_contains($css,'.sales-verification-queue.is-collapsed .sales-verification-queue-body{display:none;}'),'collapsed queue body does not occupy Dashboard space');
$check(str_contains($js,"const vqCollapseStorageKey='cdspSalesVerificationQueueCollapsed'")&&str_contains($js,'function vqSetCollapsed')&&str_contains($js,'vqInitCollapseState();'),'manual collapse state is persistent and initialized independently of polling');
$check(str_contains($js,"url:window.CD_BASE_PATH+'/api/verification-queue'")&&str_contains($js,"method:'GET'")&&str_contains($js,'2500'),'queue refresh remains AJAX polling');
$check(str_contains($js,"vqPost('/api/verification-queue/enqueue'")&&str_contains($js,"method:'POST'")&&str_contains($js,'vqShowAcceptedItem(resp.item,resp.counts);'),'Save & Wait remains AJAX and immediately renders accepted items');
$check(str_contains($js,"url:window.CD_BASE_PATH+'/api/verification-queue/bulk'")&&str_contains($js,"method:'POST'"),'Bulk Submit remains AJAX');
$check(str_contains($js,"data-vq-action','edit'")&&str_contains($js,"data-vq-action','retry'")&&str_contains($js,"data-vq-action','delete'"),'failed/duplicate/invalid edit retry delete actions remain');

if($failed){fwrite(STDERR,"V0.2.103 Verification Queue card/collapse contract failed.\n");exit(1);} 
printf("V0.2.103 Verification Queue card/collapse contract passed.\n");
