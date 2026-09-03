<?php
/**
 * V0.2.93 contract: Scanned Products is not a top-card tool. It lives inside
 * the expanded Website Scan detail workspace, below Website Scan, with its own
 * local open/close control. Compact scan-log behavior from V0.2.91 is retained.
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

$check(version_compare($version,'0.2.93','>='),'VERSION is >= 0.2.93');
$check(str_contains($view,'class="website-tool-card website-tool-card-one" data-website-tool-toggle="website-tool-panel-1"'),'Website Scan is a normal top-level Step 1 card');
$check(!str_contains($view,'website-tool-card-group'),'Website Scan top card is no longer wrapped with Scanned Products');
$check(!str_contains($view,'website-tool-subcard'),'Scanned Products is removed from the top-card grid');
$check(!str_contains($view,'website-tool-panel-4'),'standalone Scanned Products panel 4 is removed');
$check(str_contains($view,'class="website-scanned-products-section" data-scanned-products-section'),'Scanned Products section exists inside Website Scan detail');
$check(str_contains($view,'data-scanned-products-toggle aria-expanded="false"'),'Scanned Products has its own collapsed local toggle');
$check(str_contains($view,'id="website-scanned-products-body" data-scanned-products-body'),'Scanned Products has its own local body');
$check(str_contains($view,'Search, open, add or delete saved product URLs without opening an individual Website Scan card.'),'Scanned Products keeps detailed explanatory copy');
$check(str_contains($view,'product reference<?= (int)($websiteStats[\'total\'] ?? 0)===1?\'\':\'s\' ?>'),'Scanned Products keeps full product-reference count');
$check(str_contains($view,'class="website-products-host-select"'),'Scanned Products can independently choose a saved website');

$panel1Pos=strpos($view,'id="website-tool-panel-1"');
$managerPos=strpos($view,'class="website-source-manager"',$panel1Pos===false?0:$panel1Pos);
$productsPos=strpos($view,'class="website-scanned-products-section"',$panel1Pos===false?0:$panel1Pos);
$panel2Pos=strpos($view,'id="website-tool-panel-2"');
$productsBodyPos=strpos($view,'data-products-library-panel',$productsPos===false?0:$productsPos);
$check($panel1Pos!==false && $managerPos!==false && $productsPos!==false && $panel1Pos<$managerPos && $managerPos<$productsPos,'Scanned Products is below Website Scan controls inside Step 1 detail');
$check($panel2Pos!==false && $productsPos<$panel2Pos,'Scanned Products remains inside the Website Scan detail before Step 2');
$check($productsBodyPos!==false && $productsBodyPos<$panel2Pos,'product library body is physically inside the Website Scan detail');

$check(str_contains($js,"$(document).on('click','[data-scanned-products-toggle]',function()"),'Scanned Products uses a dedicated local click handler');
$check(str_contains($js,"\$toggle.attr('aria-expanded','false');"),'local handler can close Scanned Products');
$check(str_contains($js,"\$toggle.attr('aria-expanded','true');"),'local handler can open Scanned Products');
$check(str_contains($js,"activateProductsHost(String(\$activePanel.find('.website-products-host-select').val()||''),true);"),'opening Scanned Products loads the selected website products');
$check(!str_contains($js,'data-website-tool-toggle="website-tool-panel-4"'),'Scanned Products no longer participates in the top-level accordion');
$check(str_contains($js,"$(document).on('click','[data-website-tool-toggle]',function()"),'Website Scan/CSV/Sitemap top-level accordion remains intact');

$check(str_contains($css,'.website-scanned-products-section{'),'Scanned Products has an in-detail sibling section style');
$check(str_contains($css,'.website-scanned-products-toggle{'),'Scanned Products has its own detail-row toggle style');
$check(str_contains($css,'.website-scanned-products-toggle[aria-expanded="true"] .website-tool-arrow::before'),'local toggle has its own arrow state');
$check(!str_contains($css,'.website-tool-subcard{'),'obsolete top-card Scanned Products styling is removed');

// Preserve the compact processing-log behavior requested in V0.2.91.
$check(!str_contains($view,'<span>Type</span><span>URL</span><span>Details</span>'),'processing header still has no Type column');
$check(str_contains($js,'function compactUrlLabel(value){'),'processing URLs remain compact');
$check(str_contains($css,'grid-template-columns:112px 64px minmax(180px,.8fr) minmax(340px,1.7fr);'),'processing log remains four columns');
$check(str_contains($css,'padding:2px 8px;'),'processing rows remain vertically compact');
$check(str_contains($css,'padding:1px 5px;'),'processing status badge remains 1px vertical padding');
$check(str_contains($css,'.website-history-processing-message{overflow-wrap:normal}'),'processing Details remains one line with ellipsis');
$check(str_contains($js,'const historyUserCollapsed={}'),'manual History collapse state remains tracked');
$check(str_contains($js,'if(!historyUserCollapsed[historyId])'),'live polling still respects manual History collapse');

if($fail){fwrite(STDERR,"V0.2.93 products-inside-detail contract failed: ".implode(', ',$fail).PHP_EOL);exit(1);}
echo "V0.2.93 products-inside-detail contract passed.\n";
