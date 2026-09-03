<?php
/**
 * V0.2.91 contract: scan processing rows are compact, History collapse is
 * user-owned, and Scanned Products is an independent top-level tool.
 */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);$fail=[];
$js=(string)file_get_contents($root.'/public/assets/app.js');
$css=(string)file_get_contents($root.'/public/assets/app.css');
$view=(string)file_get_contents($root.'/app/Views/admin/settings.php');
$version=trim((string)file_get_contents($root.'/VERSION'));
$check=static function(bool $ok,string $message)use(&$fail):void{
    echo ($ok?'[PASS] ':'[FAIL] ').$message.PHP_EOL;
    if(!$ok){$fail[]=$message;}
};

$check(version_compare($version,'0.2.91','>='),'VERSION is >= 0.2.91');
$check(str_contains($view,'data-website-tool-toggle="website-tool-panel-4"'),'Scanned Products has its own top-level tool toggle');
$check(str_contains($view,'id="website-tool-panel-4"'),'Scanned Products has its own independent panel');
$check(str_contains($view,'class="website-products-host-select"'),'Scanned Products can choose a saved website independently');
$check(str_contains($js,"$(document).on('click','.website-source-expand',function(){toggleWebsiteSourceCard"),'website-card click only toggles its scan card');
$check(!str_contains($js,'function openInlineSource($card){'),'old website-card -> scanned-products coupled opener is removed');
$check(!str_contains($js,"const panelHtml='<section class=\"website-source-inline-panel\""),'website click no longer dynamically inserts Scanned Products');
$check(str_contains($js,'ensureWebsiteSourceCardOpen($existing,true);'),'starting an existing website scan opens only its scan card');

$check(str_contains($js,'const historyUserCollapsed={}'),'History tracks manual user collapse state');
$check(str_contains($js,'historyUserCollapsed[id]=true'),'closing a History row records manual collapse');
$check(str_contains($js,'if(!historyUserCollapsed[historyId])'),'live scan polling respects manual History collapse');

$check(!str_contains($view,'<span>Type</span><span>URL</span><span>Details</span>'),'server processing header no longer contains Type');
$check(!str_contains($js,'<span>Type</span><span>URL</span><span>Details</span>'),'dynamic processing header no longer contains Type');
$check(!str_contains($js,'website-history-processing-kind'),'dynamic processing rows no longer render a Type cell');
$check(str_contains($js,'function compactUrlLabel(value){'),'processing URLs use a compact display label');
$check(str_contains($js,'title="\'+escapeHtml(url)+\'"'),'full processing URL remains available while display text is shortened');

$check(str_contains($css,'grid-template-columns:112px 64px minmax(180px,.8fr) minmax(340px,1.7fr);'),'processing log is a four-column layout');
$check(str_contains($css,'padding:2px 8px;'),'scan rows use compact vertical padding');
$check(str_contains($css,'padding:1px 5px;'),'processing status badge uses 1px vertical padding');
$check(str_contains($css,'.website-history-processing-message{overflow-wrap:normal}'),'processing Details stays on one line with ellipsis');
$check(str_contains($css,'.website-scan-history-table .website-history-main-row>td{'),'main scan status row has explicit compact alignment');

if($fail){fwrite(STDERR,"V0.2.91 compact/products contract failed: ".implode(', ',$fail).PHP_EOL);exit(1);}
echo "V0.2.91 compact/products contract passed.\n";
