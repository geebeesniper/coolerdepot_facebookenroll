<?php
/**
 * V0.2.88 contract: browser website-scan workers are owned by exact History runs.
 * V0.2.88 合同：浏览器网站扫描 worker 必须绑定到准确的 History 任务，旧任务不能阻止新任务。
 */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);$fail=[];
$js=(string)file_get_contents($root.'/public/assets/app.js');
$css=(string)file_get_contents($root.'/public/assets/app.css');
$version=trim((string)file_get_contents($root.'/VERSION'));
$check=static function(bool $ok,string $message)use(&$fail):void{
    echo ($ok?'[PASS] ':'[FAIL] ').$message.PHP_EOL;
    if(!$ok){$fail[]=$message;}
};

$check(version_compare($version,'0.2.88','>='),'VERSION is >= 0.2.88');
$check(str_contains($js,'const loops={}; // host => history_id'),'scan loop ownership stores History id instead of a host boolean');
$check(str_contains($js,'const stepRequests={}; // host => {historyId, xhr}'),'step requests carry their History id');
$check(str_contains($js,'const previousHistoryId=Number(loops[host]||0);'),'new scan detects an older same-host browser worker');
$check(str_contains($js,"previousRequest.xhr.abort('scan-superseded')"),'older same-host request is superseded by the new History run');
$check(str_contains($js,'function isCurrentRun(){return Number(loops[host]||0)===historyId;}'),'async callbacks are guarded by exact History ownership');
$check(str_contains($js,'if(!isCurrentRun()){return;}'),'late callbacks from an older History run cannot repaint/cancel the current run');
$check(str_contains($js,'if(Number(state.history_id||0)!==historyId)'),'scan-step response must match the requested History run');
$check(str_contains($js,"\$card.find('[data-website-source-detail]').first().stop(true,true).removeClass('hidden').show();"),'Scan Website immediately reveals the History task area');
$check(str_contains($css,'v0.2.88 — Website scan run ownership is History-scoped'),'V0.2.88 scan run ownership CSS marker exists');

if($fail){fwrite(STDERR,"V0.2.88 website scan run ownership contract failed: ".implode(', ',$fail).PHP_EOL);exit(1);}
echo "V0.2.88 website scan run ownership contract passed.\n";
