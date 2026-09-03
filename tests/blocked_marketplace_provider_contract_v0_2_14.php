<?php
/**
 * File / 文件：tests/blocked_marketplace_provider_contract_v0_2_14.php
 * EN: Regression contract for V0.2.14 Craigslist/OfferUp HTTP 403 provider fallback.
 * 中文：V0.2.14 Craigslist/OfferUp HTTP 403 Provider 自动回退流程回归契约测试。
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$failures = [];
$checks = 0;

/**
 * EN: Record one contract assertion.
 * 中文：记录一项契约断言。
 *
 * @param bool $condition Assertion condition. / 断言条件。
 * @param string $label Human-readable assertion label. / 可读断言说明。
 *
 * @return void No value is returned. / 无返回值。
 */
function v214Check(bool $condition, string $label): void
{
    global $failures, $checks;
    $checks++;
    if (!$condition) {
        $failures[] = $label;
        echo "FAIL {$label}\n";
        return;
    }
    echo "PASS {$label}\n";
}

$chainPath = $root . '/app/Services/BlockedMarketplaceProviderChain.php';
$postInspector = file_get_contents($root . '/app/Services/PostInspector.php');
$apiController = file_get_contents($root . '/app/Controllers/ApiController.php');
$inspection = file_get_contents($root . '/app/Models/Inspection.php');
$draft = file_get_contents($root . '/app/Services/MarketplaceProviderDraft.php');
$settings = file_get_contents($root . '/app/Views/admin/settings.php');
$salesView = file_get_contents($root . '/app/Views/sales/_submit_form.php');
$js = file_get_contents($root . '/public/assets/app.js');

v214Check(is_file($chainPath), 'blocked-marketplace provider chain service exists');
$chain = file_get_contents($chainPath);
v214Check(str_contains($chain, "'craigslist' => 'automation-lab~craigslist-scraper'"), 'Craigslist Apify actor is configured');
v214Check(str_contains($chain, "'offerup' => 'memo23~offerup-marketplace-scraper'"), 'OfferUp Apify actor is configured');
v214Check(str_contains($chain, "'listingUrls' => [\$url]"), 'Craigslist actor receives direct listingUrls input');
v214Check(str_contains($chain, "'startUrls' => [['url' => \$url]]"), 'OfferUp actor receives URL-object startUrls input');
v214Check(str_contains($chain, 'https://api.brightdata.com/request'), 'Bright Data Web Unlocker endpoint is configured');
v214Check(str_contains($chain, 'BRIGHTDATA_UNLOCKER_ZONE'), 'Bright Data Web Unlocker zone environment override is supported');
v214Check(str_contains($chain, 'CDSP_APIFY_CRAIGSLIST_ACTOR'), 'Craigslist Apify actor override is supported');
v214Check(str_contains($chain, 'CDSP_APIFY_OFFERUP_ACTOR'), 'OfferUp Apify actor override is supported');
v214Check(substr_count($postInspector, 'new BlockedMarketplaceProviderChain()') >= 2, 'Sales inspection and Admin refresh both use provider fallback');
v214Check(str_contains($postInspector, "in_array(\$platform, ['craigslist', 'offerup'], true)"), 'HTTP 403 fallback covers Craigslist and OfferUp');
v214Check(str_contains($postInspector, 'OFFERUP_REMOTE_BLOCKED'), 'OfferUp has a manual-required fallback code');
v214Check(str_contains($postInspector, 'finalizeMarketplaceManual'), 'generic marketplace manual finalizer exists');
v214Check(str_contains($apiController, 'manual_marketplace'), 'browser API accepts generic marketplace manual completion');
v214Check(str_contains($apiController, "['CRAIGSLIST_REMOTE_BLOCKED', 'OFFERUP_REMOTE_BLOCKED']"), 'browser API recognizes both manual-required codes');
v214Check(str_contains($inspection, "platform='offerup' AND failure_code='OFFERUP_REMOTE_BLOCKED'"), 'Inspection model accepts OfferUp manual candidates');
v214Check(str_contains($salesView, 'name="manual_marketplace"'), 'Sales manual form submits generic marketplace completion');
v214Check(str_contains($js, 'automatic provider fallback were unavailable'), 'Sales help explains provider attempt before manual entry');
v214Check(str_contains($js, "data.post.platform==='offerup'?'OfferUp':'Craigslist'"), 'Admin warning identifies OfferUp versus Craigslist');
v214Check(str_contains($draft, "'unlocker_zone' => \$unlockerZone"), 'Bright Data profile can store optional Web Unlocker zone');
v214Check(str_contains($settings, 'Web Unlocker Zone (optional)'), 'Admin provider form exposes optional Web Unlocker zone');

$version = trim((string)file_get_contents($root . '/VERSION'));
v214Check($version === '0.2.14', 'VERSION is 0.2.14');

if ($failures) {
    fwrite(STDERR, count($failures) . " contract failure(s).\n");
    exit(1);
}

echo "{$checks} V0.2.14 blocked-marketplace provider contract checks passed.\n";
