<?php
/**
 * V0.2.77 current duplicate algorithm contract.
 * Stable provider/API account identity owns marketplace title/image scope.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root=dirname(__DIR__);
$fail=[];
$check=static function(bool $ok,string $name)use(&$fail):void{
    echo ($ok?'[PASS] ':'[FAIL] ').$name.PHP_EOL;
    if(!$ok){$fail[]=$name;}
};
$read=static fn(string $f):string=>(string)file_get_contents($root.'/'.$f);
$version=trim($read('VERSION'));
$post=$read('app/Models/Post.php');
$dup=$read('app/Services/DuplicateIndex.php');
$inspector=$read('app/Services/PostInspector.php');
$sales=$read('app/Controllers/SalesController.php');
$admin=$read('app/Controllers/AdminController.php');
$accountCode=$read('app/Services/MarketplaceAccount.php');
$js=$read('public/assets/app.js');
$css=$read('public/assets/app.css');

$check($version==='0.2.77','version');
$check(str_contains($accountCode,'public static function hasStableIdentity'),'stable account gate is centralized');
$check(str_contains($accountCode,'public static function sameStoredAccount'),'stored account equality is centralized');
$check(str_contains($accountCode,'normalizedAccountUrl'),'account URL is normalized before equality');
$check(str_contains($post,'DuplicateIndex::findMarketplaceAccountTitle'),'Post title check uses centralized account scope');
$check(str_contains($dup,'findMarketplaceAccountTitle(') && str_contains($dup,'MarketplaceAccount::sameStoredAccount'),'title candidates require same external account');
$check(str_contains($dup,'findMarketplaceAccountImage(') && str_contains($dup,'MarketplaceAccount::sameStoredAccount'),'image candidates require same external account');
$check(str_contains($dup,'if(!$blocked && $hasStableAccount)') && str_contains($dup,'elseif(!$blocked)'), 'legacy Sales/platform image scope is fallback only when account is unavailable');
$check(str_contains($post,'Provider did not return a stable external account identity'),'legacy Sales/platform title scope is fallback only when account is unavailable');
$check(str_contains($inspector,"'blocked_provider_html_fallback'") && str_contains($inspector,"\$listing['raw']['platform_account'] = \$platformAccount"),'HTML provider fallback preserves API account metadata');
$check(preg_match('/Post::duplicate\(.*?\$inspection\[\'description\'\]\?\?null,\s*\$platformAccount\s*\)/s',$sales)===1,'save-time duplicate reconstruction passes account');
$check(str_contains($post,'$i[\'description\'],$platformAccount'),'Post::create save-time recheck passes account');
$check(str_contains($dup,"'website_exact_title'") && str_contains($dup,"'website_exact_image'"),'website title/image duplicate source remains separate');
$check(!str_contains(substr($post,0,strpos($post,'public static function create')), 'description_hash'),'description is not a Post::duplicate key');
$check(!str_contains($js,"description:'Description duplicate'"),'description duplicate UI remains disabled');
$check(str_contains($admin,'DuplicateIndex::replacePostFingerprints'),'Admin Refresh Content replaces stale image fingerprints');
$check(str_contains($dup,'DELETE FROM cdsp_post_image_fingerprints WHERE post_id=?'),'old refreshed image fingerprints are removed');
$check(str_contains($css,'v0.2.75: keep Sales Post Details side-by-side on desktop'),'desktop left/right Post Details layout preserved');

require_once $root.'/app/Services/MarketplaceAccount.php';
use App\Services\MarketplaceAccount;

$currentA=[
    'id'=>'2828807560932703',
    'name'=>'Seller A',
    'url'=>'https://www.facebook.com/profile.php?id=2828807560932703&utm_source=x',
    'key_hash'=>hash('sha256',"facebook\nid:2828807560932703"),
    'source'=>'provider_api',
];
$storedA=[
    'platform_account_id'=>'2828807560932703',
    'platform_account_url'=>'https://www.facebook.com/profile.php?id=2828807560932703',
    'platform_account_key_hash'=>$currentA['key_hash'],
];
$storedB=[
    'platform_account_id'=>'38328161020161854',
    'platform_account_url'=>'https://www.facebook.com/profile.php?id=38328161020161854',
    'platform_account_key_hash'=>hash('sha256',"facebook\nid:38328161020161854"),
];
$urlOnly=[
    'id'=>null,
    'name'=>'Seller A',
    'url'=>'https://www.facebook.com/seller-a/?ref=marketplace',
    'key_hash'=>hash('sha256',"facebook\nurl:https://www.facebook.com/seller-a"),
    'source'=>'provider_api',
];
$storedUrl=[
    'platform_account_id'=>null,
    'platform_account_url'=>'https://www.facebook.com/seller-a',
    'platform_account_key_hash'=>$urlOnly['key_hash'],
];
$nameOnly=['id'=>null,'name'=>'Same Name','url'=>null,'key_hash'=>null,'source'=>'provider_api'];

$check(MarketplaceAccount::hasStableIdentity($currentA),'provider account ID is stable identity');
$check(MarketplaceAccount::sameStoredAccount($currentA,$storedA),'same account ID matches');
$check(!MarketplaceAccount::sameStoredAccount($currentA,$storedB),'different account ID never matches');
$legacyProfileHash=hash('sha256',"facebook\nurl:https://www.facebook.com/profile.php");
$legacyUrlA=['id'=>null,'name'=>'A','url'=>'https://www.facebook.com/profile.php?id=111','key_hash'=>$legacyProfileHash,'source'=>'provider_api'];
$legacyStoredB=['platform_account_id'=>null,'platform_account_url'=>'https://www.facebook.com/profile.php?id=222','platform_account_key_hash'=>$legacyProfileHash];
$check(!MarketplaceAccount::sameStoredAccount($legacyUrlA,$legacyStoredB),'legacy profile.php hash collision cannot override different profile IDs in URL');
$check(MarketplaceAccount::hasStableIdentity($urlOnly),'provider profile URL is stable identity');
$check(MarketplaceAccount::sameStoredAccount($urlOnly,$storedUrl),'same profile URL matches despite URL decoration');
$check(!MarketplaceAccount::hasStableIdentity($nameOnly),'display name alone never proves account identity');
$check(!MarketplaceAccount::sameStoredAccount($nameOnly,$storedA),'name-only account never scopes duplicate');

if($fail){fwrite(STDERR,'V0.2.77 algorithm contract failed: '.implode(', ',$fail).PHP_EOL);exit(1);}
echo 'V0.2.77 marketplace account algorithm contract passed.'.PHP_EOL;
