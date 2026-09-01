<?php
/**
 * File / 文件：tests/duplicate_comparison.php
 * EN: Automated regression/contract test for duplicate comparison.
 * 中文：用于 duplicate comparison 的自动回归/契约测试。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
// Run with PHP CLI + PDO SQLite + mbstring; GD adds perceptual-image tests.
// Uses an isolated in-memory database; never reads production DB credentials.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
if(!in_array('sqlite',PDO::getAvailableDrivers(),true)){
    echo "SKIP duplicate comparison regression: PDO SQLite driver unavailable.\n";
    exit(0);
}
spl_autoload_register(function($class){if(str_starts_with($class,'App\\')){require dirname(__DIR__).'/app/'.str_replace('\\','/',substr($class,4)).'.php';}});
use App\Core\Database;
use App\Core\Util;
use App\Models\Post;
use App\Services\DuplicateIndex;
use App\Services\ImageFingerprint;
use App\Services\PostInspector;
$config=['app'=>['timezone'=>'America/Los_Angeles','version'=>'test'],'logging'=>['path'=>sys_get_temp_dir().'/cdsp-test-logs','level'=>'critical','retention_days'=>1,'max_bytes'=>1048576]];
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->sqliteCreateFunction('NOW',fn()=>date('Y-m-d H:i:s'));
$property=new ReflectionProperty(Database::class,'pdo');$property->setValue(null,$pdo);
$pdo->exec('CREATE TABLE cdsp_sales_posts(id INTEGER PRIMARY KEY AUTOINCREMENT,sales_user_id INTEGER,platform TEXT,submitted_url TEXT,resolved_url TEXT,canonical_url TEXT,canonical_url_hash TEXT UNIQUE,external_post_id TEXT,title TEXT,normalized_title_hash TEXT,description TEXT,description_hash TEXT,published_at TEXT,published_date TEXT,fetched_at TEXT,fetched_image_url TEXT,verification_status TEXT,admin_review_status TEXT,created_at TEXT,updated_at TEXT,deleted_at TEXT,UNIQUE(platform,external_post_id))');
$pdo->exec('CREATE TABLE cdsp_post_image_fingerprints(id INTEGER PRIMARY KEY,post_id INTEGER,image_url TEXT,sha256 TEXT,dhash TEXT)');
$pdo->exec('CREATE TABLE cdsp_website_references(id INTEGER PRIMARY KEY,page_url TEXT,title TEXT,title_hash TEXT,image_url TEXT,sha256 TEXT,dhash TEXT)');
$pdo->exec('CREATE TABLE cdsp_post_reviews(post_id INTEGER,decision TEXT)');
$count=0;
/**
 * EN: Check or validate the check helper used by this automated regression test.
 * 中文：检查或验证 当前自动回归测试使用的“check”辅助操作。
 *
 * @param mixed $condition Condition value used by this operation. / 本操作使用的“condition”参数值。
 * @param string $label Label value used by this operation. / 本操作使用的“label”参数值。
 *
 * @return void No value is returned. / 无返回值。
 *
 * @throws \RuntimeException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
 */
function check($condition,string $label):void{global $count;if(!$condition){throw new RuntimeException('FAIL: '.$label);}echo 'PASS '.$label."\n";$count++;}
/**
 * EN: Perform the blocked helper used by this automated regression test.
 * 中文：执行 当前自动回归测试使用的“blocked”辅助操作。
 *
 * @param callable $fn Fn value used by this operation. / 本操作使用的“fn”参数值。
 * @param string $message Human-readable message associated with the result or log entry. / 与结果或日志记录关联的可读消息。
 *
 * @return void No value is returned. / 无返回值。
 *
 * @throws \RuntimeException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
 */
function blocked(callable $fn,string $message):void{try{$fn();}catch(DomainException $e){check(str_contains($e->getMessage(),$message),'save-time '.$message);return;}throw new RuntimeException('Expected block: '.$message);}
$insert=$pdo->prepare('INSERT INTO cdsp_sales_posts(sales_user_id,platform,canonical_url,canonical_url_hash,external_post_id,title,normalized_title_hash,description_hash,published_at,published_date,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
$insert->execute([11,'facebook','https://www.facebook.com/marketplace/item/101',Util::urlHash('https://www.facebook.com/marketplace/item/101'),'101','NSF 2HP exhaust fan',Util::hashText('NSF 2HP exhaust fan'),Util::hashText('description one'),'2026-08-27 19:01:00','2026-08-27','2026-08-31 12:00:00']);
check(Post::duplicate(11,'facebook',null,'101',null,null)!==null,'own same-platform ID blocks');
check(Post::duplicate(22,'facebook',null,'101',null,null)!==null,'other salesperson same-platform ID blocks');
check(Post::duplicate(22,'offerup',null,'101',null,null)===null,'ID namespace stays platform-specific');
check(Post::duplicate(22,'facebook',null,null,' NSF  2HP EXHAUST FAN! ',null)!==null,'normalized title checked across salespeople');
check(Post::duplicate(22,'offerup',null,null,'NSF 2HP exhaust fan',null)===null,'platform title scope preserved');
$pdo->exec("UPDATE cdsp_sales_posts SET deleted_at='2026-08-31' WHERE id=1");
check(Post::duplicate(22,'facebook',null,'101',null,null)!==null,'deleted original ID still blocks');
check(Post::duplicate(22,'facebook','https://www.facebook.com/marketplace/item/101',null,null,null)!==null,'deleted canonical URL still blocks');
$pdo->exec('UPDATE cdsp_sales_posts SET deleted_at=NULL WHERE id=1');
check(count(Post::forSales(11,'2026-08-27','2026-08-27'))===1&&count(Post::forSales(11,'2026-08-31','2026-08-31'))===0,'archive uses publication date, not entry date');
$inspector=new PostInspector();$validate=new ReflectionMethod($inspector,'validateAndFinish');
$inspect=fn($raw,$title='Different title',$id='202')=>$validate->invoke($inspector,22,'facebook','https://www.facebook.com/marketplace/item/'.$id,'https://www.facebook.com/marketplace/item/'.$id,'https://www.facebook.com/marketplace/item/'.$id,$id,$title,'unique description '.$id,$raw,[]);
$past=$inspect('2026-08-27T19:01:00-07:00');
check($past['verification_status']==='verified'&&$past['published_date']==='2026-08-27','historical post accepted with original date');
check($inspect('2026-08-28T02:01:00Z')['published_at']==='2026-08-27 19:01:00','UTC date boundary uses company timezone');
check($inspect('2026-02-30')['failure_code']==='DATE_NOT_VERIFIABLE','invalid calendar date rejected');
check($inspect('')['failure_code']==='DATE_NOT_VERIFIABLE','missing date rejected');
check($inspect('tomorrow')['failure_code']==='FUTURE_DATE','future date rejected');
check(str_contains(implode(' ',$past['raw_meta']['duplicate_report']['warnings']),'not configured'),'missing website is explicit, never a clean result');
$past['raw_meta_json']=json_encode($past['raw_meta']);
$pdo->beginTransaction();$savedId=Post::create($past);$pdo->commit();
$saved=$pdo->query('SELECT * FROM cdsp_sales_posts WHERE id='.(int)$savedId)->fetch();
check($saved['published_at']==='2026-08-27 19:01:00'&&$saved['published_date']==='2026-08-27','save preserves verified historical timestamp');
$pdo->beginTransaction();blocked(fn()=>Post::create($past),'already been submitted');$pdo->rollBack();
$race=$inspect('2026-08-26','Race title','303');check($race['verification_status']==='verified','preflight succeeds before competing title is inserted');$race['canonical_url']='https://www.facebook.com/marketplace/item/303';$race['external_post_id']='303';
$race['raw_meta_json']=json_encode($past['raw_meta']);
$insert->execute([33,'facebook','https://www.facebook.com/marketplace/item/404',Util::urlHash('https://www.facebook.com/marketplace/item/404'),'404','Race title',Util::hashText('Race title'),Util::hashText('four'),'2026-08-26 19:00:00','2026-08-26','2026-08-31']);
$pdo->beginTransaction();blocked(fn()=>Post::create($race),'title is already used');$pdo->rollBack();
$asset=['url'=>'https://images.example.com/product.png','sha256'=>str_repeat('a',64),'dhash'=>'1234567890abcdef'];
$q=$pdo->prepare('INSERT INTO cdsp_post_image_fingerprints(id,post_id,image_url,sha256,dhash) VALUES(1,1,?,?,?)');$q->execute([$asset['url'],$asset['sha256'],$asset['dhash']]);
check(DuplicateIndex::compare('facebook','Unique title',[$asset])['blocked']!==null,'identical image blocks across owners on same platform');
check(DuplicateIndex::compare('offerup','Unique title',[$asset])['blocked']===null,'images are scoped to same platform');
$imageRace=$inspect('2026-08-25','Image race','505');$imageRace['raw_meta']['duplicate_report']['assets']=[$asset];$imageRace['raw_meta_json']=json_encode($imageRace['raw_meta']);
$pdo->beginTransaction();blocked(fn()=>Post::create($imageRace),'image already exists');$pdo->rollBack();
$similar=$asset;$similar['sha256']=str_repeat('b',64);$similar['dhash']='1234567890abcdee';
$r=DuplicateIndex::compare('facebook','Unique title',[$similar]);
check($r['blocked']===null&&in_array('similar_platform_image',array_column($r['matches'],'kind')),'perceptual similarity warns without claiming exact identity');
$q=$pdo->prepare('INSERT INTO cdsp_website_references(id,page_url,title,title_hash,image_url,sha256,dhash) VALUES(1,?,?,?,?,?,?)');
$q->execute(['https://company.example.com/fan','Company fan',Util::hashText('Company fan'),$asset['url'],$asset['sha256'],$asset['dhash']]);
$r=DuplicateIndex::compare('offerup','Company FAN!',[$asset]);$kinds=array_column($r['matches'],'kind');
check($r['blocked']===null&&in_array('website_title',$kinds)&&in_array('website_image',$kinds),'website title and image matches include sources for review');
$pdo->exec('UPDATE cdsp_website_references SET sha256=NULL,dhash=NULL WHERE id=1');
check(str_contains(implode(' ',DuplicateIndex::compare('offerup','No match',[])['warnings']),'website image comparison is incomplete'),'unindexed website images cannot look like a clean result');
$urls=ImageFingerprint::urls(['provider_record'=>['listingPhotos'=>[['url'=>$asset['url']]],'seller'=>['profile_url'=>'https://example.com/person','image'=>'https://example.com/avatar.png']]]);
check($urls===[$asset['url']],'extract listing photos without treating seller profile links as images');
$metaMethod=new ReflectionMethod($inspector,'meta');
if(class_exists('DOMDocument')){
 $meta=$metaMethod->invoke($inspector,'<html><head><meta property="og:image" content="https://images.example.com/1.png"><script type="application/ld+json">{"@graph":[{"image":{"url":"https://images.example.com/2.png"}}]}</script></head></html>');
 check(count(ImageFingerprint::urls($meta))===2,'HTML and nested JSON-LD photos extracted');
}
check(ImageFingerprint::distance('0000000000000000','ffffffffffffffff')===64,'perceptual distance covers all 64 bits');
foreach(['file:///etc/passwd','https://127.0.0.1/image.png','https://user:pass@example.com/image.png'] as $url){
 try{ImageFingerprint::fromUrl($url);throw new LogicException('Unsafe URL accepted');}catch(RuntimeException $e){check(true,'unsafe image destination blocked before fetch');}
}
if(function_exists('imagecreatetruecolor')){
 $im=imagecreatetruecolor(40,40);
 for($x=0;$x<40;$x++){imageline($im,$x,0,$x,39,imagecolorallocate($im,255-$x*6,$x*6,90));}
 ob_start();imagepng($im);$png=ob_get_clean();ob_start();imagejpeg($im,null,85);$jpg=ob_get_clean();imagedestroy($im);
 $a=ImageFingerprint::fromBytes($png);$b=ImageFingerprint::fromBytes($jpg);
 check($a['sha256']!==$b['sha256']&&ImageFingerprint::distance($a['dhash'],$b['dhash'])<=5,'re-encoded image keeps perceptual similarity while byte hash changes');
}else{echo "SKIP GD perceptual decoding (extension unavailable)\n";}
try{ImageFingerprint::fromBytes('<svg></svg>');throw new LogicException('SVG accepted');}catch(RuntimeException $e){check(true,'unsupported image format rejected');}
echo "$count regression checks passed. SQLite verifies application queries; MySQL advisory locks/migration require integration verification.\n";
