<?php
/** v0.2.80 exact-run + Magento-style website scan processing regression contract. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);$fail=[];
$check=static function(bool $ok,string $name)use(&$fail):void{echo ($ok?'[PASS] ':'[FAIL] ').$name.PHP_EOL;if(!$ok){$fail[]=$name;}};
$read=static fn(string $f):string=>(string)file_get_contents($root.'/'.$f);
$version=trim($read('VERSION'));
$job=$read('app/Services/WebsiteScanJob.php');
$history=$read('app/Services/WebsiteActivityHistory.php');
$controller=$read('app/Controllers/AdminSettingsController.php');
$view=$read('app/Views/admin/settings.php');
$js=$read('public/assets/app.js');
$css=$read('public/assets/app.css');

$check($version==='0.2.80','version');
$check(str_contains($history,'cdsp_website_scan_history_items'),'per-URL scan history table exists');
$check(str_contains($history,'public static function addScanItem') && str_contains($history,'public static function scanItems'),'per-URL append/read API exists');
$check(!str_contains($job,'UNIQUE KEY uq_cdsp_website_scan_host (source_host)'),'scan jobs are no longer unique by website host');
$check(str_contains($job,'DROP INDEX uq_cdsp_website_scan_host'),'legacy unique-host index is migrated away');
$check(str_contains($job,'KEY idx_cdsp_website_scan_history (history_id,id)'),'History id is indexed for exact persisted-run lookup');
$check(str_contains($job,"INSERT INTO cdsp_website_scan_jobs") && !str_contains($job,'ON DUPLICATE KEY UPDATE'),'new Scan Website creates a new job instead of overwriting history');
$check(str_contains($job,'public static function statusByHistory'),'exact History run lookup exists');
$check(str_contains($job,'public static function resume(string $host,int $historyId)') && str_contains($job,"WHERE source_host=? AND history_id=? AND status='paused'"),'Play resumes only the clicked paused run');
$check(str_contains($job,'public static function resumableHistoryIds') && str_contains($controller,"'websiteResumableScanHistoryIds'"),'server exposes exactly which paused History runs still have saved queues');
$check(str_contains($view,'Historical paused run; its saved queue is no longer available.'),'old paused rows without persisted queues are visibly non-actionable');
$check(str_contains($job,'public static function step(string $host,int $historyId=0)') && str_contains($job,'source_host=? AND history_id=? LIMIT 1'),'scan step is bound to exact History run');
$check(substr_count($job,"WHERE id=? AND status='running'")>=3,'running scan mutations update the exact job row');
$check(str_contains($job,"'skipped','product'") && str_contains($job,'foreach((array)($result[\'results\']??[]) as $pageResult)'),'skipped and processed URLs are logged individually');
$check(str_contains($controller,"\$historyId=(int)(\$_POST['history_id']??0)") && str_contains($controller,"\$afterItemId=max(0,(int)(\$_POST['after_item_id']??0))"),'AJAX step carries exact run id and incremental log cursor');
$check(str_contains($controller,'WebsiteActivityHistory::scanItems'),'scan responses return processing records');
$check(str_contains($view,'website-history-details-summary') && str_contains($view,'data-history-detail-summary'),'original visible Details column remains');
$check(str_contains($view,'data-history-processing-log') && str_contains($view,'Each scanned URL is recorded here as it finishes.'),'expanded History row contains processing log');
$check(str_contains($js,'function historyItemHtml(item)') && str_contains($js,'function appendHistoryItems(state,replaceAll)'),'client appends per-URL processing rows');
$check(str_contains($js,'function scanLoop(host,historyId)') && str_contains($js,'history_id:historyId,after_item_id:Number(historyItemLast[historyId]||0)'),'live scan loop polls exact run and only new processing rows');
$check(str_contains($js,"data:{_csrf:csrf,host:host,mode:mode,history_id:historyId}"),'Pause/Stop is bound to clicked History run');
$check(str_contains($js,"data:{_csrf:csrf,host:host,history_id:historyId}"),'Play is bound to clicked History run');
$check(!str_contains($js,'if(inputSelector&&revealExistingSource(requestedHost,inputSelector)){return;}'),'top Scan Website no longer refuses to start a new run for an existing saved website');
$check(str_contains($js,'ensureHistoryRow(state);updateHistoryRow(state);'),'new History row is inserted immediately when start returns');
$check(str_contains($js,"loadHistoryItems(String(\$row.data('history-source-host')||''),id)"),'clicking History row loads its own processing log');
$check(str_contains($css,'v0.2.80 — Magento-style per-URL website scan processing history'),'processing UI CSS exists');

if($fail){fwrite(STDERR,'v0.2.80 scan processing contract failed: '.implode(', ',$fail).PHP_EOL);exit(1);}
echo 'v0.2.80 website scan processing contract passed.'.PHP_EOL;
