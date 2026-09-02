<?php
/** V0.2.74 regression: optional marketplace account enrichment must never block listing verification. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root=dirname(__DIR__);
$fail=[];
$check=static function(bool $ok,string $name)use(&$fail):void{
    echo ($ok?'[PASS] ':'[FAIL] ').$name.PHP_EOL;
    if(!$ok){$fail[]=$name;}
};
$version=trim((string)file_get_contents($root.'/VERSION'));
$account=(string)file_get_contents($root.'/app/Services/MarketplaceAccount.php');
$inspector=(string)file_get_contents($root.'/app/Services/PostInspector.php');
$admin=(string)file_get_contents($root.'/app/Controllers/AdminController.php');
$duplicate=(string)file_get_contents($root.'/app/Services/DuplicateIndex.php');
$post=(string)file_get_contents($root.'/app/Models/Post.php');
$js=(string)file_get_contents($root.'/public/assets/app.js');

$check($version==='0.2.74','version');
$check(str_contains($account,'public static function safeFromProviderResult'),'safe account extractor exists');
$check(str_contains($account,'catch (\\Throwable $error)'),'safe account extractor catches provider-shape errors');
$check(str_contains($account,'listing verification continues'),'account extraction failure is explicitly fail-open');
$check(str_contains($account,'$visited >= 500') && str_contains($account,'count($out) >= 40'),'provider account scan is bounded');
$check(substr_count($inspector,'MarketplaceAccount::safeFromProviderResult(')>=3,'all inspection account enrichment uses fail-open extractor');
$check(!str_contains($inspector,'MarketplaceAccount::fromProviderResult('),'inspection has no unsafe direct account extraction');
$check(str_contains($admin,'MarketplaceAccount::safeFromProviderResult('),'admin refresh account enrichment is fail-open');
$check(str_contains($duplicate,"'same_account_title'") && str_contains($duplicate,"'same_account_image'"),'same-account title/image duplicate rules preserved');
$check(str_contains($post,'platform_account_key_hash'),'account persistence preserved');
$check(!str_contains($js,"description:'Description duplicate'"),'description duplicate remains disabled');
$check(str_contains($js,'function openDailyReviewOnly($card)'),'v0.2.73 isolated daily review behavior preserved');

require_once $root.'/app/Services/MarketplaceAccount.php';
use App\Services\MarketplaceAccount;
$check(MarketplaceAccount::safeFromProviderResult('facebook',null)===null,'non-array provider account data is ignored');
$sample=MarketplaceAccount::safeFromProviderResult('facebook',[
    'raw'=>['seller'=>['id'=>'seller-123','name'=>'Seller One','profile_url'=>'https://www.facebook.com/seller-123']]
]);
$check(is_array($sample) && ($sample['id']??'')==='seller-123','valid provider account data is still extracted');

if($fail){fwrite(STDERR,'V0.2.74 contract failed: '.implode(', ',$fail).PHP_EOL);exit(1);} 
echo 'V0.2.74 fail-open account regression passed.'.PHP_EOL;
