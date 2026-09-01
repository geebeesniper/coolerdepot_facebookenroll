<?php
/**
 * File / 文件：scripts/index_duplicate_images.php
 * EN: Operations/deployment/diagnostics script owned by this project.
 * 中文：该文件是本项目自有的运维、部署或诊断脚本。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$config=require dirname(__DIR__).'/config/bootstrap.php';
use App\Core\Database;
use App\Services\DuplicateIndex;
use App\Services\ImageFingerprint;
if(!DuplicateIndex::ready()){fwrite(STDERR,"Run scripts/migrate_v0_1_70.php first.\n");exit(1);}
$options=getopt('',['limit:','after:','website','all']);
$limit=max(1,min(1000,(int)($options['limit']??200)));
$after=max(0,(int)($options['after']??0));$all=isset($options['all']);
$pdo=Database::connection();$ok=0;$failed=0;$last=$after;
if(isset($options['website'])){
    $rows=$pdo->query('SELECT id,image_url FROM cdsp_website_references WHERE id>'.$after." AND image_url IS NOT NULL AND image_url<>''".($all?'':' AND sha256 IS NULL').' ORDER BY id LIMIT '.$limit)->fetchAll();
    $update=$pdo->prepare('UPDATE cdsp_website_references SET sha256=?,dhash=?,checked_at=NOW() WHERE id=? AND image_url=?');
    foreach($rows as $row){
        $last=(int)$row['id'];
        try{$a=ImageFingerprint::fromUrl($row['image_url']);$update->execute([$a['sha256'],$a['dhash'],$row['id'],$row['image_url']]);$ok++;}
        catch(Throwable $e){$failed++;fwrite(STDERR,'Website #'.$last.': '.$e->getMessage()."\n");}
    }
}else{
    $rows=$pdo->query('SELECT p.id,p.platform,p.canonical_url,p.external_post_id,p.fetched_image_url FROM cdsp_sales_posts p WHERE p.id>'.$after.' AND p.deleted_at IS NULL'.($all?'':' AND NOT EXISTS(SELECT 1 FROM cdsp_post_image_fingerprints f WHERE f.post_id=p.id)').' ORDER BY p.id LIMIT '.$limit)->fetchAll();
    $inspection=$pdo->prepare("SELECT raw_meta_json FROM cdsp_post_inspections WHERE platform=? AND verification_status='verified' AND (canonical_url=? OR external_post_id=?) ORDER BY id DESC LIMIT 1");
    foreach($rows as $row){
        $last=(int)$row['id'];$assets=[];
        $inspection->execute([$row['platform'],$row['canonical_url'],$row['external_post_id']?:null]);
        $meta=json_decode($inspection->fetchColumn()?:'{}',true)?:[];
        unset($meta['duplicate_report']); // Never index old report links as listing photos.
        $meta['fetched_image_url']=$row['fetched_image_url'];
        $urls=ImageFingerprint::urls($meta);
        foreach(array_slice($urls,0,8) as $url){
            try{$assets[]=ImageFingerprint::fromUrl($url);}catch(Throwable $e){$failed++;fwrite(STDERR,'Post #'.$last.': '.$e->getMessage()."\n");}
        }
        if($assets){DuplicateIndex::storePost($last,$assets);$ok++;}
        elseif(!$urls){$failed++;fwrite(STDERR,'Post #'.$last.": no stored source image; fetch listing content in Admin first.\n");}
        if(count($urls)>8){$failed++;fwrite(STDERR,'Post #'.$last.": only the first 8 photos were indexed.\n");}
    }
}
echo "Indexed records: $ok; incomplete/failed checks: $failed; last ID: $last.\n";
if(count($rows)===$limit){echo 'Continue this batch with --after='.$last.(isset($options['website'])?' --website':'').($all?' --all':'')."\n";}
echo "Use --all to refresh already indexed images. Missing/unavailable images remain an explicit comparison warning.\n";
exit($failed?2:0);
