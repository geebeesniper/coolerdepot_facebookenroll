<?php
/** Static V0.2.95 contract: Save & Wait / Bulk background queue and Sales repair actions. */
$root=dirname(__DIR__);
$fail=[];
$check=function(bool $ok,string $label)use(&$fail){printf("[%s] %s\n",$ok?'PASS':'FAIL',$label);if(!$ok)$fail[]=$label;};
$read=static function(string $path)use($root):string{return (string)@file_get_contents($root.'/'.$path);};
$version=trim($read('VERSION'));
$routes=$read('index.php');
$view=$read('app/Views/sales/_submit_form.php');
$qview=$read('app/Views/sales/_verification_queue.php');
$bulkview=$read('app/Views/sales/bulk_submit.php');
$js=$read('public/assets/app.js');
$css=$read('public/assets/app.css');
$model=$read('app/Models/VerificationQueue.php');
$worker=$read('app/Services/VerificationQueueWorker.php');
$api=$read('app/Controllers/ApiController.php');
$schema=$read('database/migrations/030_verification_queue.sql');

$check(version_compare($version,'0.2.95','>='),'VERSION is >= 0.2.95');
$check(strpos($routes,"/api/verification-queue/enqueue")!==false&&strpos($routes,"/api/verification-queue/bulk")!==false,'queue enqueue + bulk routes exist');
$check(strpos($view,'id="saveWaitButton"')!==false&&(strpos($view,'id="bulkSubmitToggle"')!==false||strpos($bulkview,'id="bulkQueueButton"')!==false),'Sales UI exposes Save & Wait + Bulk Submit');
$check(strpos($qview,'data-vq-filter="failed"')!==false&&strpos($qview,'data-vq-filter="invalid"')!==false&&strpos($qview,'data-vq-filter="needs_action"')!==false,'clickable Failed + Invalid + Needs Action filters exist');
$check(strpos($js,"data-vq-action','edit")!==false&&strpos($js,"data-vq-action','retry")!==false&&strpos($js,"data-vq-action','delete")!==false,'failed/duplicate edit retry delete actions are wired');
$check(strpos($js,"queueNotCounted")!==false,'failed queue UI explicitly says it is not counted');
$check(strpos($model,'recordPreflightIssue')!==false&&strpos($model,"'duplicate':'invalid'")!==false,'preflight duplicate/invalid items persist for edit/delete');
$check(strpos($css,'.sales-vq-status.passed')!==false&&strpos($css,'.sales-vq-status.failed')!==false&&strpos($css,'.sales-vq-status.duplicate')!==false&&strpos($css,'.sales-vq-status.invalid')!==false,'Passed/Failed/Duplicate/Invalid status color classes exist');
$check(strpos($model,"status IN ('waiting','verifying')")!==false&&strpos($model,'external_post_id=?')!==false,'waiting/verifying queue reserves global platform Post ID');
$check(strpos($api,'VerificationQueue::reservationDuplicate')!==false,'normal Check Post preflight also honors queued hard-ID reservations');
$check(strpos($worker,'nohup ')!==false&&strpos($worker,'process_verification_queue.php')!==false,'Save & Wait launches detached CLI worker');
$check(strpos($worker,"Post::create")!==false&&strpos($worker,"markPassed")!==false,'only passed worker result is promoted into formal Posts');
$check(strpos($schema,'cdsp_post_verification_queue_history')!==false,'queue status/edit history table exists');
$check(strpos($schema,"ENUM('waiting','verifying','passed','failed','duplicate','invalid')")!==false,'queue schema has required statuses including Invalid');

if($fail){fwrite(STDERR,"V0.2.95 verification queue contract failed: ".implode('; ',$fail)."\n");exit(1);} 
printf("V0.2.95 verification queue contract passed.\n");
