<?php
/** V0.2.125 runtime contract: strict Facebook date normalization + unavailable classification. */
require_once dirname(__DIR__).'/app/Services/FacebookListingMetadata.php';
require_once dirname(__DIR__).'/app/Services/FacebookListingUnavailableException.php';

use App\Services\FacebookListingMetadata;

$failed=[];
$check=function(bool $ok,string $label)use(&$failed){
    if(!$ok)$failed[]=$label;
};
$anchor=new DateTimeImmutable('2026-09-04T16:30:00Z');

$r=FacebookListingMetadata::normalizePublished('Listed 59 minutes ago in Fresno, TX',$anchor,'America/Los_Angeles');
$check($r['published_at']==='2026-09-04T15:31:00Z','relative minutes normalized to ISO UTC');
$check($r['published_date']==='2026-09-04','relative minutes normalized to strict local date');
$check($r['published_source']==='facebook_relative','relative source recorded');

$r=FacebookListingMetadata::normalizePublished('Listed yesterday in Fresno, TX',$anchor,'America/Los_Angeles');
$check($r['published_at']===null,'yesterday does not invent an exact clock time');
$check($r['published_date']==='2026-09-03','yesterday produces strict local date');

$r=FacebookListingMetadata::normalizePublished('Listed on Sep 3 in Fresno, TX',$anchor,'America/Los_Angeles');
$check($r['published_date']==='2026-09-03','Listed on date normalized');

$r=FacebookListingMetadata::normalizePublished('2026-09-04T15:31:00Z',$anchor,'America/Los_Angeles');
$check($r['published_at']==='2026-09-04T15:31:00Z','absolute ISO preserved as strict UTC');
$check($r['published_date']==='2026-09-04','absolute ISO gets local published date');

$r=FacebookListingMetadata::normalizePublished('1788535860',$anchor,'America/Los_Angeles');
$check((bool)preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',(string)$r['published_at']),'unix seconds become strict ISO UTC');
$check((bool)preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)$r['published_date']),'unix seconds become strict date');

$item=FacebookListingMetadata::normalizeItem([
    'external_post_id'=>'4445352499113724',
    'title'=>'Example',
    'description'=>'',
    'published_raw'=>null,
    'raw'=>['subtitle'=>'Listed 59 minutes ago in Fresno, TX'],
    'fetched_at'=>'2026-09-04T16:30:00Z',
]);
$check($item['published_at']==='2026-09-04T15:31:00Z','nested Facebook display text is extracted');
$check(FacebookListingMetadata::providerUsable($item)===true,'empty description/date before normalization does not make provider transport fail');
$check(FacebookListingMetadata::providerUsable(['external_post_id'=>'4445352499113724'])===false,'ID-only shell is not a usable listing');
$check(FacebookListingMetadata::providerUsable(['external_post_id'=>'4445352499113724','raw'=>['url'=>'https://www.facebook.com/marketplace/item/4445352499113724']])===false,'listing URL alone is not mistaken for image/listing evidence');

$check(FacebookListingMetadata::unavailableReason(['status'=>'unavailable'])!==null,'explicit unavailable status detected');
$check(FacebookListingMetadata::unavailableReason(['is_available'=>false])!==null,'explicit availability false detected');
$check(FacebookListingMetadata::unavailableReason(['seller'=>['available'=>false]])===null,'nested unrelated availability is not treated as listing unavailable');
$check(FacebookListingMetadata::unavailableReason(['message'=>'This listing is no longer available'])!==null,'explicit unavailable message detected');
$check(FacebookListingMetadata::unavailableReason(['message'=>'Please log in to continue'])===null,'login wall is not mislabeled unavailable');
$check(FacebookListingMetadata::unavailableReason(['message'=>'Rate limited'],429)===null,'429 is provider error, not unavailable');

if($failed){
    fwrite(STDERR,"FAILED\n - ".implode("\n - ",$failed)."\n");
    exit(1);
}
echo "OK Facebook strict date + unavailable v0.2.125\n";
