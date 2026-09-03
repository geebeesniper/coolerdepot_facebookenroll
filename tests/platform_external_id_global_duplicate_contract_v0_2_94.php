<?php
/**
 * V0.2.94 contract: marketplace external/listing IDs are global per platform.
 * Facebook, OfferUp and Craigslist listing identity must never be scoped to an
 * internal Sales user or seller account. Softer title/image rules stay scoped.
 */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);
$post=(string)file_get_contents($root.'/app/Models/Post.php');
$platformUrl=(string)file_get_contents($root.'/app/Services/PlatformUrl.php');
$api=(string)file_get_contents($root.'/app/Controllers/ApiController.php');
$inspector=(string)file_get_contents($root.'/app/Services/PostInspector.php');
$sales=(string)file_get_contents($root.'/app/Controllers/SalesController.php');
$version=trim((string)file_get_contents($root.'/VERSION'));
$checks=[];
$checks['VERSION is >= 0.2.94']=version_compare($version,'0.2.94','>=');
$checks['external ID lookup is platform + external ID only']=
    str_contains($post,'WHERE platform=? AND external_post_id=?') &&
    !str_contains($post,'WHERE platform=? AND external_post_id=? AND sales_user_id=?');
$checks['external ID query no longer binds Sales user']=str_contains($post,'$s->execute([$platform,trim($eid)]);');
$checks['external ID duplicate remains active-record only']=
    str_contains($post,'WHERE platform=? AND external_post_id=?') &&
    str_contains($post,'AND deleted_at IS NULL LIMIT 1');
$checks['external ID duplicate kind is preserved']=str_contains($post,"\$r['kind']='external_id';");
$checks['duplicate message is system-global']=str_contains($post,'Post ID has already been submitted in the system.');
$checks['canonical URL rule stays Sales scoped']=str_contains($post,'WHERE sales_user_id=? AND platform=? AND canonical_url_hash=?');
$checks['fallback exact-title rule stays Sales scoped']=str_contains($post,'WHERE sales_user_id=? AND platform=? AND BINARY title=BINARY ?');
$checks['stable-account title rule remains account scoped']=str_contains($post,'DuplicateIndex::findMarketplaceAccountTitle');
$checks['facebook external IDs are extracted']=str_contains($platformUrl,"'facebook' =>") && str_contains($platformUrl,'facebook\\.com/marketplace/item/(\\d+)');
$checks['offerup external IDs are extracted']=str_contains($platformUrl,"'offerup' =>") && str_contains($platformUrl,'/item/detail/([a-z0-9-]+)');
$checks['craigslist external IDs are extracted']=str_contains($platformUrl,"'craigslist' =>") && str_contains($platformUrl,'/(\\d{8,})\\.html');
$checks['URL preflight uses the centralized global ID check']=str_contains($api,'Post::duplicate((int)$u[\'id\'],$platform,$normalizedUrl,$externalId,null,null)');
$checks['provider inspection rechecks external ID before save']=str_contains($inspector,'Post::duplicate($uid, $platform, $canonical, $eid, $title, $desc, $platformAccount)');
$checks['save-time create rechecks duplicates']=str_contains($post,"if(\$duplicate=self::duplicate((int)\$i['sales_user_id']") && str_contains($post,"\$i['external_post_id']");
$checks['same-platform save lock protects race window']=str_contains($sales,"\$lockName='cdsp-save-'") && str_contains($sales,"':'.\$inspection['platform']");
$failed=[];
foreach($checks as $label=>$ok){
    echo ($ok?'[PASS] ':'[FAIL] ').$label.PHP_EOL;
    if(!$ok){$failed[]=$label;}
}
if($failed){
    fwrite(STDERR,'V0.2.94 global marketplace-ID duplicate contract failed: '.implode(', ',$failed).PHP_EOL);
    exit(1);
}
echo count($checks).' V0.2.94 global marketplace-ID duplicate checks passed.'.PHP_EOL;
