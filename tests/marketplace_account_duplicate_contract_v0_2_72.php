<?php
/** V0.2.72 regression contract: provider account persistence + account-scoped duplicates. */
$root=dirname(__DIR__);
$fail=[];
$pass=[];
$check=function(bool $ok,string $name)use(&$fail,&$pass):void{
    if($ok){$pass[]=$name;}else{$fail[]=$name;}
    echo ($ok?'[PASS] ':'[FAIL] ').$name."\n";
};

$version=trim((string)file_get_contents($root.'/VERSION'));
$check($version==='0.2.72','version');

require_once $root.'/app/Services/MarketplaceAccount.php';
use App\Services\MarketplaceAccount;

$facebook=MarketplaceAccount::fromProviderResult('facebook',[
    'raw'=>[
        'marketplace_listing_seller'=>[
            'id'=>'123456789',
            'name'=>'Seller One',
            'profile_url'=>'https://www.facebook.com/profile.php?id=123456789',
        ],
    ],
]);
$check(is_array($facebook) && ($facebook['id']??'')==='123456789','facebook seller id extracted');
$check(is_array($facebook) && ($facebook['name']??'')==='Seller One','facebook seller name extracted');
$check(is_array($facebook) && preg_match('/^[a-f0-9]{64}$/',(string)($facebook['key_hash']??''))===1,'stable account hash generated');

$offerup=MarketplaceAccount::fromProviderResult('offerup',[
    'raw'=>[
        'sellerId'=>'ou-77',
        'sellerName'=>'Offer Seller',
        'sellerProfileUrl'=>'https://offerup.com/p/12345/',
    ],
]);
$check(is_array($offerup) && ($offerup['id']??'')==='ou-77','offerup seller fields extracted');
$check(MarketplaceAccount::fromProviderResult('facebook',['raw'=>['seller'=>null]])===null,'missing api account stays null');
$nameOnly=MarketplaceAccount::fromProviderResult('facebook',['raw'=>['seller'=>['name'=>'Common Seller']]]);
$check(is_array($nameOnly) && ($nameOnly['name']??'')==='Common Seller','provider seller name is still displayable');
$check(($nameOnly['key_hash']??null)===null,'name-only seller does not create a false same-account duplicate key');

$duplicate=(string)file_get_contents($root.'/app/Services/DuplicateIndex.php');
$post=(string)file_get_contents($root.'/app/Models/Post.php');
$inspector=(string)file_get_contents($root.'/app/Services/PostInspector.php');
$api=(string)file_get_contents($root.'/app/Controllers/ApiController.php');
$schema=(string)file_get_contents($root.'/database/schema.sql');
$migration=(string)file_get_contents($root.'/scripts/migrate_v0_2_72_marketplace_accounts.php');
$submit=(string)file_get_contents($root.'/app/Views/sales/_submit_form.php');
$salesDashboard=(string)file_get_contents($root.'/app/Views/sales/dashboard.php');
$daily=(string)file_get_contents($root.'/app/Views/sales/_daily_post_section.php');
$range=(string)file_get_contents($root.'/app/Views/sales/_post_range_section.php');
$adminDashboard=(string)file_get_contents($root.'/app/Views/admin/dashboard.php');
$header=(string)file_get_contents($root.'/app/Views/layout/header.php');
$js=(string)file_get_contents($root.'/public/assets/app.js');
$css=(string)file_get_contents($root.'/public/assets/app.css');

$check(str_contains($schema,'platform_account_key_hash CHAR(64) NULL'),'schema persists account identity hash');
$check(str_contains($schema,'idx_post_platform_account(platform,platform_account_key_hash)'),'schema indexes platform account');
$check(str_contains($migration,"COLUMN_NAME=?") && str_contains($migration,'idx_post_platform_account'),'migration is additive/idempotent');
$check(str_contains($post,'platform_account_id,platform_account_name,platform_account_url,platform_account_key_hash'),'post insert persists account fields');
$check(str_contains($post,'$assets,$platformAccount'),'save-time comparison receives account');
$check(str_contains($inspector,"MarketplaceAccount::fromProviderResult('facebook', \$item)") && str_contains($inspector,"\$raw['platform_account']"),'facebook provider account reaches inspection metadata');
$check(str_contains($inspector,'MarketplaceAccount::fromProviderResult($platform, $provider)'),'blocked marketplace provider account reaches inspection metadata');
$check(str_contains($api,"'platform_account' => \$platformAccount"),'verify API returns account');

$start=strpos($duplicate,'private static function findAccountExactTitle');
$end=strpos($duplicate,'private static function findAccountExactImage',$start?:0);
$titleHelper=$start!==false&&$end!==false?substr($duplicate,$start,$end-$start):'';
$start2=strpos($duplicate,'private static function findAccountExactImage');
$end2=strpos($duplicate,'Find an exact first-image fingerprint',$start2?:0);
$imageHelper=$start2!==false&&$end2!==false?substr($duplicate,$start2,$end2-$start2):'';
$check(str_contains($titleHelper,'LOWER(platform)=?') && str_contains($titleHelper,'platform_account_key_hash=?') && str_contains($titleHelper,'BINARY title=BINARY ?'),'same platform + account + exact title duplicate');
$check(!str_contains($titleHelper,'sales_user_id'),'account title duplicate crosses internal Sales users');
$check(str_contains($imageHelper,'LOWER(p.platform)=?') && str_contains($imageHelper,'p.platform_account_key_hash=?') && str_contains($imageHelper,'f.sha256=?'),'same platform + account + exact image duplicate');
$check(!str_contains($imageHelper,'sales_user_id'),'account image duplicate crosses internal Sales users');
$check(str_contains($duplicate,'findOwnPlatformExactImage($pdo,$salesUserId,$platformScope'),'existing own-Sales/platform image rule preserved');
$check(str_contains($duplicate,"'same_account_title'") && str_contains($duplicate,"'same_account_image'"),'account duplicate match kinds exposed');

$check(str_contains($submit,'resultPlatformAccountFact') && str_contains($submit,'resultPlatformAccount'),'verify result shows account only when available');
$check(str_contains($salesDashboard,'salesPostDetailAccountFact'),'sales post detail has account field');
$check(str_contains($daily,'data-sales-post-account-name') && str_contains($range,'data-sales-post-account-name'),'saved post cards carry account metadata');
$check(str_contains($adminDashboard,'dashboardReviewAccountFact'),'admin review shows account');
$check(str_contains($header,'adminDeleteRequestAccountFact') && str_contains($js,'adminDeleteRequestAccount'),'admin delete request post details show account');
$check(str_contains($js,'renderMarketplaceAccount') && str_contains($js,'same_account_title') && str_contains($js,'same_account_image'),'client renders account and account duplicate labels');
$check(str_contains($css,'.duplicate-comparison-warnings{') && str_contains($css,'font-size:9px') && str_contains($css,'#verificationBanner.warning{font-size:9px'),'verify warning text is smaller');

// Description remains display-only; it must not become a duplicate key again.
$postDuplicate=substr($post,0,(int)strpos($post,'public static function create'));
$check(!str_contains($postDuplicate,'description_hash') && !str_contains($postDuplicate,'BINARY description'),'description duplicate remains disabled');

if($fail){
    fwrite(STDERR,"V0.2.72 contract failed: ".implode(', ',$fail)."\n");
    exit(1);
}
echo count($pass)." V0.2.72 account/duplicate checks passed.\n";
