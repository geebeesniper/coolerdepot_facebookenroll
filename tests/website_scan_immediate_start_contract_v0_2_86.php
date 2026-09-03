<?php
/** V0.2.86 contract: Start creates the run immediately; remote HTTP only happens in scan-step. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);$fail=[];
$read=static fn(string $rel):string=>(string)file_get_contents($root.'/'.$rel);
$check=static function(bool $ok,string $message)use(&$fail):void{echo ($ok?'[PASS] ':'[FAIL] ').$message.PHP_EOL;if(!$ok){$fail[]=$message;}};
$version=trim($read('VERSION'));
$job=$read('app/Services/WebsiteScanJob.php');
$history=$read('app/Services/WebsiteActivityHistory.php');
$controller=$read('app/Controllers/AdminSettingsController.php');
$js=$read('public/assets/app.js');
$view=$read('app/Views/admin/settings.php');
$css=$read('public/assets/app.css');

$startPos=strpos($job,'public static function start(string $website, int $adminId): array');
$nextPos=strpos($job,'/** Return one persisted job state.',(int)$startPos);
$start=$startPos!==false&&$nextPos!==false?substr($job,$startPos,$nextPos-$startPos):'';
$controllerStartPos=strpos($controller,'public function startWebsiteProductScan(): void');
$controllerNextPos=strpos($controller,'public function stepWebsiteProductScan(): void',(int)$controllerStartPos);
$controllerStart=$controllerStartPos!==false&&$controllerNextPos!==false?substr($controller,$controllerStartPos,$controllerNextPos-$controllerStartPos):'';

$check(version_compare($version,'0.2.86','>='),'VERSION is >= 0.2.86');
$check(str_contains($job,"SELECT GET_LOCK(?,0)"),'start advisory lock fails immediately instead of waiting five seconds');
$check(!str_contains($start,'self::runningHosts()'),'start path does not recurse through runningHosts');
$check(!str_contains($start,'self::statusByHistory('),'start path does not do post-create status/stale work');
$check(!str_contains($start,'scanProductBatch('),'start path performs no remote page scan');
$check(str_contains($start,"'next_url'=>(string)(\$queue[0]??'')"),'fresh start response exposes the next URL immediately');
$check(!str_contains($controllerStart,'WebsiteCatalog::sourceStats()'),'start controller does not run library aggregation before returning');
$check(!str_contains($controllerStart,'WebsiteActivityHistory::scanItems'),'start controller does not query processing rows before returning');
$check(str_contains($history,'private static bool $schemaReady = false'),'history schema probes are guarded in one request');
$check(str_contains($job,'private static bool $schemaReady = false'),'scan-job schema probes are guarded in one request');
$check(str_contains($job,'information_schema.tables'),'scan job uses a cheap existing-schema fast path');
$check(str_contains($history,'information_schema.tables'),'history uses a cheap existing-schema fast path');

$check(str_contains($js,'function startHistoryPlaceholder(host,website)'),'click inserts an immediate History run placeholder');
$check(str_contains($js,'startHistoryPlaceholder(requestedHost,website);'),'start click shows History before waiting for the HTTP response');
$check(str_contains($js,'removeStartHistoryPlaceholder(requestedHost);'),'temporary row is reconciled with the persisted run');
$check(str_contains($js,'function updateActiveProcessingRow(state)'),'current run has an in-flight URL renderer');
$check(str_contains($js,'Request in progress…'),'processing log visibly shows the URL currently being fetched');
$check(str_contains($js,"const nextUrl=String(state.next_url||'').trim();"),'in-flight row follows persisted queue state');
$check(str_contains($js,'scanLoop(host,Number(state.history_id||0));'),'first scan step starts immediately after the run is accepted');
$check(!str_contains($js,'Waiting for the first scanned URL…'),'old indefinite waiting copy is removed from live JS');
$check(!str_contains($view,'Waiting for the first scanned URL…'),'old indefinite waiting copy is removed from server History view');
$check(str_contains($view,'Preparing first URL…'),'server History uses an explicit preparing state');
$check(str_contains($css,'v0.2.86 — Immediate scan-run visibility and live in-flight URL row.'),'V0.2.86 scan visibility CSS marker exists');

if($fail){fwrite(STDERR,"V0.2.86 immediate scan-start contract failed: ".implode(', ',$fail).PHP_EOL);exit(1);} 
echo "V0.2.86 immediate scan-start contract passed.\n";
