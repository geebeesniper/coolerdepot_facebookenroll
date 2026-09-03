<?php
$root=dirname(__DIR__);
$js=file_get_contents($root.'/public/assets/app.js');
$css=file_get_contents($root.'/public/assets/app.css');
$svc=file_get_contents($root.'/app/Services/WebsiteScanJob.php');
$checks=[
    'stale threshold'=>str_contains($svc,'private const STALE_AFTER_SECONDS = 55;'),
    'server stale recovery'=>str_contains($svc,'private static function pauseStaleJobs'),
    'stale status hook'=>str_contains($svc,'self::pauseStaleJobs($host);'),
    'stale global hook'=>str_contains($svc,'self::pauseStaleJobs();'),
    'lock-safe stale recovery'=>str_contains($svc,'SELECT IS_FREE_LOCK(?)'),
    'stale becomes resumable'=>str_contains($svc,"SET status='stopped',last_error=?"),
    'refresh aborts stale poll'=>str_contains($js,"activityRequest.abort();"),
    'refresh hides notice immediately'=>str_contains($js,"\$notice.addClass('hidden');"),
    'refresh gates activity notice'=>str_contains($js,'noticeShown = true;'),
    'refresh rechecks after load'=>str_contains($js,'checkDashboardActivity();'),
    'view-posts-size chevron'=>str_contains($css,'width:10px;')&&str_contains($css,'height:10px;'),
    'strong two pixel chevron'=>str_contains($css,'border-right:2px solid currentColor;')&&str_contains($css,'border-bottom:2px solid currentColor;'),
    'expanded chevron rotation'=>str_contains($css,'transform:rotate(225deg);'),
];
$failed=[];
foreach($checks as $name=>$ok){if(!$ok){$failed[]=$name;}}
if($failed){fwrite(STDERR,"V0.2.40 contract failed: ".implode(', ',$failed)."\n");exit(1);} 
echo "V0.2.40 contract OK (".count($checks)." checks)\n";
