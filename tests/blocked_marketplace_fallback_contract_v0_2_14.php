<?php
/**
 * V0.2.14 blocked-marketplace fallback contract test.
 * V0.2.14 被阻 Marketplace 自动回退契约测试。
 */
$root = dirname(__DIR__);
$failures = [];

$expect = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$read = static function (string $relative) use ($root): string {
    $path = $root . '/' . $relative;
    $text = @file_get_contents($path);
    if ($text === false) {
        throw new RuntimeException('Could not read ' . $relative);
    }
    return $text;
};

$version = trim($read('VERSION'));
$expect(version_compare($version, '0.2.14', '>='), 'VERSION must be 0.2.14 or newer.');

$chain = $read('app/Services/BlockedMarketplaceProviderChain.php');
$expect(str_contains($chain, "'craigslist' => 'automation-lab~craigslist-scraper'"), 'Craigslist Apify actor missing.');
$expect(str_contains($chain, "'offerup' => 'abotapi~offerup-scraper'"), 'OfferUp Apify actor missing.');
$expect(str_contains($chain, "'listingUrls' => [\$url]"), 'Craigslist direct listing URL payload missing.');
$expect(str_contains($chain, "'mode' => 'url'"), 'OfferUp URL mode missing.');
$expect(str_contains($chain, "'urls' => [\$url]"), 'OfferUp direct item URL payload missing.');
$expect(str_contains($chain, "'apifyProxyCountry' => 'US'"), 'OfferUp US residential proxy request missing.');
$expect(str_contains($chain, 'https://api.brightdata.com/request'), 'Bright Data Web Unlocker endpoint missing.');
$expect(str_contains($chain, 'https://api.brightdata.com/zone/get_active_zones'), 'Bright Data Unlocker zone auto-discovery missing.');
$expect(str_contains($chain, "!== 'unblocker'"), 'Bright Data zone type filter missing.');

$inspector = $read('app/Services/PostInspector.php');
$expect(str_contains($inspector, "in_array(\$platform, ['craigslist', 'offerup'], true)"), 'Craigslist/OfferUp 403 branch missing.');
$expect(str_contains($inspector, 'new BlockedMarketplaceProviderChain()'), 'Automatic provider fallback invocation missing.');
$expect(str_contains($inspector, 'normalizeBlockedProviderResult'), 'Provider result normalization missing.');
$expect(str_contains($inspector, 'OFFERUP_REMOTE_BLOCKED'), 'OfferUp final manual fallback code missing.');
$expect(str_contains($inspector, 'finalizeMarketplaceManual'), 'Generic manual fallback finalizer missing.');

$model = $read('app/Models/Inspection.php');
$expect(str_contains($model, 'public static function manualCandidate'), 'Generic manual inspection lookup missing.');
$expect(str_contains($model, "platform='offerup' AND failure_code='OFFERUP_REMOTE_BLOCKED'"), 'OfferUp manual inspection lookup missing.');
$expect(str_contains($model, 'public static function updateManual'), 'Generic manual inspection update missing.');

$api = $read('app/Controllers/ApiController.php');
$expect(str_contains($api, 'inspectManualMarketplace'), 'Generic manual API handler missing.');
$expect(str_contains($api, "'OFFERUP_REMOTE_BLOCKED'"), 'OfferUp manual_required response support missing.');
$expect(str_contains($api, 'Inspection::manualCandidate'), 'API must use generic manual candidate lookup.');

$view = $read('app/Views/sales/_submit_form.php');
$expect(str_contains($view, 'name="manual_marketplace" value="1"'), 'Manual marketplace form flag missing.');

$js = $read('public/assets/app.js');
$expect(str_contains($js, "manualVerificationTitle:'Manual marketplace verification'"), 'Generic manual UI text missing.');
$expect(str_contains($js, "const manualRequired=Boolean(d.manual_required)"), 'Manual-required UI branch missing.');

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}

echo "V0.2.14 blocked marketplace fallback contract: PASS\n";
