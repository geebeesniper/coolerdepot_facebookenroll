<?php
/** V0.2.104 contract: self-healing AJAX queue worker + clickable queue cards. */
$root=dirname(__DIR__);
$fails=0;
$check=function(bool $ok,string $label)use(&$fails){printf("[%s] %s\n",$ok?'PASS':'FAIL',$label);if(!$ok)$fails++;};
$version=trim((string)@file_get_contents($root.'/VERSION'));
$controller=(string)@file_get_contents($root.'/app/Controllers/VerificationQueueController.php');
$worker=(string)@file_get_contents($root.'/app/Services/VerificationQueueWorker.php');
$view=(string)@file_get_contents($root.'/app/Views/sales/_verification_queue.php');
$js=(string)@file_get_contents($root.'/public/assets/app.js');
$css=(string)@file_get_contents($root.'/public/assets/app.css');
$check(version_compare($version,'0.2.104','>='),'VERSION is >= 0.2.104');
$check(str_contains($worker,'public static function deferOne(): void'),'worker exposes post-response fallback');
$check(str_contains($worker,"register_shutdown_function")&&str_contains($worker,"fastcgi_finish_request")&&str_contains($worker,'session_write_close')&&str_contains($worker,'self::run(1)'),'fallback releases session and runs one worker after AJAX response');
$check(substr_count($controller,'VerificationQueueWorker::deferOne()')>=5,'list/enqueue/bulk/retry/edit all self-heal Waiting queue');
$check(str_contains($view,'data-vq-detail-modal')&&str_contains($view,'data-vq-detail-open'),'queue detail popup exists');
$check(str_contains($js,".attr({\n                'data-vq-id'")&&str_contains($js,"'role':'button'")&&str_contains($js,"function vqOpenDetail"),'queue cards are keyboard/click openable');
$check(str_contains($js,"$(document).on('click','.sales-vq-row'")&&str_contains($js,"closest('a,button,input,textarea,select,label')"),'card click does not steal link/action clicks');
$check(str_contains($css,'.sales-vq-status{')&&str_contains($css,'width:72px;')&&str_contains($css,'height:22px;'),'top-right status controls have fixed equal size');
$check(!str_contains($view,'sales-vq-list-row'),'queue stays card/grid, not reverted to horizontal list');
if($fails){fwrite(STDERR,"V0.2.104 contract failed: {$fails} check(s).\n");exit(1);} 
printf("V0.2.104 queue worker/card contract passed.\n");
