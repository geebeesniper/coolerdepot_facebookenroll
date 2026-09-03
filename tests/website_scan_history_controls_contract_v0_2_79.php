<?php
/** V0.2.79 Website Scan History live-control regression contract. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root=dirname(__DIR__);
$fail=[];
$check=static function(bool $ok,string $name)use(&$fail):void{
    echo ($ok?'[PASS] ':'[FAIL] ').$name.PHP_EOL;
    if(!$ok){$fail[]=$name;}
};
$read=static fn(string $f):string=>(string)file_get_contents($root.'/'.$f);
$version=trim($read('VERSION'));
$view=$read('app/Views/admin/settings.php');
$detailView=$read('app/Views/admin/website-source.php');
$js=$read('public/assets/app.js');
$css=$read('public/assets/app.css');
$controller=$read('app/Controllers/AdminSettingsController.php');
$job=$read('app/Services/WebsiteScanJob.php');
$history=$read('app/Services/WebsiteActivityHistory.php');

$check(version_compare($version,'0.2.79','>='),'version >= 0.2.79');
$check(!str_contains($view,'Continue Scanning') && !str_contains($detailView,'Continue Scanning') && !str_contains($js,'Continue Scanning'),'Continue Scanning UI is removed');
$check(!str_contains($view,'website-scan-continue') && !str_contains($detailView,'website-scan-continue'),'legacy Continue button markup is removed');
$check(str_contains($js,".text(active?'Stop Scanning':'Scan Website')"),'Scan Website changes to Stop Scanning while running');
$check(str_contains($js,'data-history-action="pause"') && str_contains($js,'>Ⅱ</button>'),'running history uses pause icon');
$check(str_contains($js,'data-history-action="resume"') && str_contains($js,'>▶</button>'),'paused history uses play icon');
$check(str_contains($js,'is-stopped is-static') && str_contains($js,'>■</span>'),'stopped history uses red square icon');
$check(str_contains($view,'data-scan-history-row') && str_contains($view,'data-history-detail-row'),'history rows have expandable detail rows');
$check(str_contains($js,"$(document).on('click','.website-history-main-row[data-scan-history-row]'") && str_contains($js,"closest('[data-history-scan-control],a,button')"),'row click expands details without stealing icon clicks');
$check(str_contains($js,'function ensureHistoryRow(state)') && str_contains($js,'function updateHistoryRow(state'),'live history row creation/update exists');
$check(str_contains($js,'updateHistoryRow(state);'),'each scan-state render updates history counters live');
$check(str_contains($controller,"WebsiteActivityHistory::allForActions(['product_scan'])"),'all product scan history is loaded');
$check(str_contains($history,'public static function allForActions'),'unbounded product scan history query exists');
$check(str_contains($controller,"\$mode==='stop'") && str_contains($controller,'WebsiteScanJob::terminate'),'top Stop Scanning has terminal stop path');
$check(str_contains($controller,'WebsiteScanJob::pause'),'history pause has separate pause path');
$check(str_contains($job,'public static function pause') && str_contains($job,'public static function terminate'),'pause and terminal stop are distinct backend states');
$check(str_contains($job,"WHERE source_host=? AND history_id=? AND status='paused'") || preg_match("/WHERE source_host=\? AND status='paused'/",$job)===1,'resume targets a paused persisted job');
$check(str_contains($css,'v0.2.79 — Website Scan History is a live control surface'),'scan history control CSS is present');
$check(str_contains($css,'.website-history-control.is-stopped') && str_contains($css,'#d92d20'),'stopped square is visibly red');

// Preserve cumulative fixes shipped before v0.2.79.
$check(str_contains($css,'v0.2.75: keep Sales Post Details side-by-side on desktop'),'desktop Post Details split remains');
$check(str_contains($css,'v0.2.78 — Application Settings compact control rows'),'Application Settings compact row remains');
$check(is_file($root.'/app/Services/MarketplaceAccount.php'),'marketplace account algorithm remains installed');

if($fail){fwrite(STDERR,'V0.2.79 website scan history contract failed: '.implode(', ',$fail).PHP_EOL);exit(1);}
echo 'V0.2.79 Website Scan History contract passed.'.PHP_EOL;
