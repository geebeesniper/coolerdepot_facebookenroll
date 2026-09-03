<?php
/** v0.2.81 Website Scan UI/recovery regression contract. */
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
$routes=$read('index.php');

$check(version_compare($version,'0.2.80','>='),'source/target version compatible');
$check(str_contains($routes,"/admin/website/products/scan-resume"),'scan-resume route exists');
$check(str_contains($history,'public static function find(int $id): ?array'),'legacy History lookup exists');
$check(str_contains($job,'Legacy paused scan restarted from website entry points'),'old paused History can restart instead of dead grey Play');
$check(str_contains($job,"WHERE action='product_scan' AND status='paused'"),'all paused product scan History rows are resumable controls');
$check(str_contains($controller,'startWebsiteProductScan') && substr_count($controller,'session_write_close()')>=4,'long scan endpoints release PHP session lock');
$check(str_contains($view,'$websiteScanIcon') && str_contains($view,"\$websiteScanIcon('pause')") && str_contains($view,"\$websiteScanIcon('play')"),'server History uses SVG vector controls');
$check(!str_contains($view,'>Ⅱ</button>') && !str_contains($view,'>▶</button>') && !str_contains($view,'>■</span>'),'server History has no text-glyph controls');
$check(str_contains($js,'function websiteScanIcon(name)') && str_contains($js,"websiteScanIcon('pause')") && str_contains($js,"websiteScanIcon('play')"),'live History uses SVG vector controls');
$check(!str_contains($js,'Loading processing log…'),'Loading processing log placeholder removed');
$check(str_contains($js,'No per-URL processing records were stored for this older scan.'),'legacy scans terminate processing-log loading cleanly');
$check(str_contains($js,'function acceptStartedState(state)') && str_contains($js,'recoveryTimer=window.setInterval'),'Starting state has persisted-status recovery');
$check(str_contains($js,'timeout:6000'),'start AJAX cannot remain pending forever');
$check(str_contains($css,'border:0 !important') && str_contains($css,'background:transparent !important'),'scan icons are borderless and transparent');
$check(str_contains($css,'.website-history-control svg'),'scan controls size SVG vectors explicitly');

if($fail){fwrite(STDERR,'v0.2.81 Website Scan UI contract failed: '.implode(', ',$fail).PHP_EOL);exit(1);} 
echo "v0.2.81 Website Scan UI contract passed.\n";
