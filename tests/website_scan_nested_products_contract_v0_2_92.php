<?php
/**
 * V0.2.92 contract: Scanned Products is presented as a detailed child shortcut
 * directly under Website Scan, while its work panel remains an independent
 * sibling panel in the Website Library accordion.
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

$check(version_compare($version,'0.2.92','>='),'VERSION is >= 0.2.92');
$check(str_contains($view,'class="website-tool-card-group website-tool-card-group-one"'),'Website Scan owns a grouped top-card area');
$check(str_contains($view,'class="website-tool-card website-tool-card-one website-tool-card-primary"'),'Website Scan remains the primary Step 1 trigger');
$check(str_contains($view,'class="website-tool-subcard website-tool-subcard-products"'),'Scanned Products is rendered as a child shortcut under Website Scan');
$check(str_contains($view,'data-website-tool-toggle="website-tool-panel-4"'),'Scanned Products keeps its own independent panel toggle');
$check(!str_contains($view,'class="website-tool-card website-tool-card-four"'),'old standalone Scanned Products top-level card is removed');
$check(str_contains($view,'Search, open, add or delete saved product URLs without opening a Website Scan card.'),'Scanned Products keeps the detailed explanatory copy');
$check(str_contains($view,'product reference<?= (int)($websiteStats[\'total\'] ?? 0)===1?\'\':\'s\' ?>'),'Scanned Products keeps the detailed product-reference count');
$check(str_contains($view,'id="website-tool-panel-4" data-website-tool-panel="website-tool-panel-4"'),'Scanned Products work panel remains a normal sibling accordion panel');
$check(str_contains($view,'class="website-tool-detail-title-no-step"'),'Scanned Products detail header is no longer presented as Step 4');
$check(!str_contains($view,'<span class="settings-step">4</span>'),'Scanned Products no longer has a top-level Step 4 badge');

$groupPos=strpos($view,'class="website-tool-card-group website-tool-card-group-one"');
$subPos=strpos($view,'class="website-tool-subcard website-tool-subcard-products"');
$groupClose=$subPos===false?false:strpos($view,"            </div>\n\n            <button type=\"button\" class=\"website-tool-card website-tool-card-two\"",$subPos);
$panel1Pos=strpos($view,'id="website-tool-panel-1"');
$panel4Pos=strpos($view,'id="website-tool-panel-4"');
$check($groupPos!==false && $subPos!==false && $groupPos<$subPos,'Scanned Products shortcut occurs below Website Scan inside the same group');
$check($groupClose!==false,'Website Scan group closes before URL CSV begins');
$check($panel1Pos!==false && $panel4Pos!==false && $panel4Pos>$panel1Pos && ($groupClose===false || $panel4Pos>$groupClose),'Scanned Products expanded panel is outside the Website Scan top-card group');

$check(str_contains($css,'.website-tool-card-group{'),'grouped Website Scan styling exists');
$check(str_contains($css,'.website-tool-subcard{'),'child Scanned Products styling exists');
$check(str_contains($css,'.website-tool-subcard[aria-expanded="true"] .website-tool-arrow::before'),'child shortcut has its own open/close arrow state');
$check(str_contains($css,'.website-tool-detail-four{border-color:#2563eb}'),'Scanned Products sibling panel uses the Website Scan family accent');
$check(str_contains($js,"$(document).on('click','[data-website-tool-toggle]',function()"),'all Website Library triggers still use the shared independent accordion handler');
$check(str_contains($js,"$(document).on('click','[data-website-tool-toggle=\"website-tool-panel-4\"]',function()"),'opening Scanned Products still loads its selected website products');

if($fail){fwrite(STDERR,"V0.2.92 nested products contract failed: ".implode(', ',$fail).PHP_EOL);exit(1);}
echo "V0.2.92 nested products contract passed.\n";
