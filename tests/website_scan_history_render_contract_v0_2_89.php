<?php
/**
 * V0.2.89 contract: clicking Scan Website must render the task in the exact
 * website card immediately and a persisted run must never continue invisibly.
 */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);$fail=[];
$js=(string)file_get_contents($root.'/public/assets/app.js');
$version=trim((string)file_get_contents($root.'/VERSION'));
$check=static function(bool $ok,string $message)use(&$fail):void{
    echo ($ok?'[PASS] ':'[FAIL] ').$message.PHP_EOL;
    if(!$ok){$fail[]=$message;}
};

$check(version_compare($version,'0.2.89','>='),'VERSION is >= 0.2.89');
$check(str_contains($js,'function sourceCard(host){'),'sourceCard helper exists');
$check(str_contains($js,"return $('.website-product-source').filter(function(){"),'website card lookup compares literal DOM data instead of a generated host selector');
$check(!str_contains($js,"$('.website-product-source[data-website-source=\"'+cssEscape(host)+'\"]')"),'old CSS-escaped website-card selector is removed');
$check(str_contains($js,'function scanHistoryWrap(host,$context){'),'History wrapper accepts the clicked-card context');
$check(str_contains($js,'function scanHistoryBody(host,$context){'),'History body has a dedicated direct-card locator');
$check(str_contains($js,'let $body=$card.find(\'[data-scan-history-body]\').first();'),'clicked card -> History tbody is the authoritative lookup path');
$check(str_contains($js,'function startHistoryPlaceholder(host,website,$button){'),'placeholder receives the exact Scan Website button context');
$check(str_contains($js,'const placeholderVisible=startHistoryPlaceholder(requestedHost,website,$button);'),'start path verifies immediate History visibility before sending an existing-card scan');
$check(str_contains($js,"showToast('Scan History could not be opened for this website. The scan was not started.',true);"),'existing website scan fails closed if its History area cannot render');
$check(str_contains($js,'const $historyRow=ensureHistoryRow(state,$button);'),'persisted History row uses the same clicked-card context');
$check(str_contains($js,'removeStartHistoryPlaceholder(requestedHost,$button);'),'temporary row is removed only through the same card context');
$check(str_contains($js,"mode:'pause',history_id:Number(state.history_id||0)"),'a persisted run is paused if its real History row cannot render');
$check(str_contains($js,'Create the persisted row before removing the temporary row.'),'placeholder-to-persisted-row transition is explicitly atomic');
$check(str_contains($js,'const loops={}; // host => history_id'),'V0.2.88 exact History worker ownership remains present');
$check(str_contains($js,"previousRequest.xhr.abort('scan-superseded')"),'old same-host scan request is still superseded');

if($fail){fwrite(STDERR,"V0.2.89 website scan History render contract failed: ".implode(', ',$fail).PHP_EOL);exit(1);}
echo "V0.2.89 website scan History render contract passed.\n";
