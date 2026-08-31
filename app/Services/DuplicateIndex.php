<?php
namespace App\Services;

use App\Core\Database;
use App\Core\Util;

class DuplicateIndex
{
    public static function ready(): bool
    {
        try{
            Database::connection()->query('SELECT id FROM cdsp_post_image_fingerprints LIMIT 0');
            Database::connection()->query('SELECT id FROM cdsp_website_references LIMIT 0');return true;
        }catch(\PDOException $e){return false;}
    }

    public static function inspect(string $platform,string $title,array $meta): array
    {
        if(!self::ready()){throw new \RuntimeException('Run the v0.1.70 duplicate-comparison migration before submitting posts.');}
        $assets=[];$warnings=[];$urls=ImageFingerprint::urls($meta);$started=microtime(true);
        foreach(array_slice($urls,0,8) as $url){
            if(microtime(true)-$started>20){$warnings[]='Image comparison time limit reached; some photos were not checked.';break;}
            try{$assets[]=ImageFingerprint::fromUrl($url);}catch(\Throwable $e){
                error_log('[CDSP image comparison] '.$e->getMessage());
                $warnings[]='A listing image could not be checked. Image comparison is incomplete.';
            }
        }
        if(!$urls){$warnings[]='No listing image was returned; image comparison could not be completed.';}
        if(count($urls)>8){$warnings[]='Only the first 8 listing images were checked.';}
        if($assets&&!function_exists('imagecreatefromstring')){$warnings[]='PHP GD is unavailable; only identical image files were compared.';}
        $report=self::compare($platform,$title,$assets);
        $report['warnings']=array_values(array_unique(array_merge($warnings,$report['warnings'])));
        $report['assets']=$assets;
        return $report;
    }

    public static function compare(string $platform,string $title,array $assets): array
    {
        $pdo=Database::connection();$warnings=[];$matches=[];$blocked=null;
        foreach($assets as $asset){
            $q=$pdo->prepare('SELECT p.id,p.sales_user_id,p.title,p.canonical_url FROM cdsp_post_image_fingerprints f JOIN cdsp_sales_posts p ON p.id=f.post_id WHERE LOWER(p.platform)=? AND p.deleted_at IS NULL AND f.sha256=? LIMIT 1');
            $q->execute([strtolower($platform),$asset['sha256']]);
            if($row=$q->fetch()){
                $blocked='This image already exists on '.$platform.' in an existing saved post ('.$row['title'].').';
                $matches[]=['kind'=>'same_platform_image','post_id'=>(int)$row['id'],'url'=>$row['canonical_url']];break;
            }
        }
        // Perceptual hashes are similarity evidence only, never proof of identity.
        $hashes=array_column($assets,'dhash');$hashes=array_filter($hashes);
        if($hashes){
            $q=$pdo->prepare('SELECT f.dhash,p.id,p.canonical_url FROM cdsp_post_image_fingerprints f JOIN cdsp_sales_posts p ON p.id=f.post_id WHERE LOWER(p.platform)=? AND p.deleted_at IS NULL AND f.dhash IS NOT NULL');
            $q->execute([strtolower($platform)]);
            while($row=$q->fetch()){
                foreach($hashes as $hash){
                    if(ImageFingerprint::distance($hash,$row['dhash'])<=5){
                        $warnings[]='Possible similar image in an existing '.$platform.' post. Review the image before saving.';
                        $matches[]=['kind'=>'similar_platform_image','post_id'=>(int)$row['id'],'url'=>$row['canonical_url']];
                        break 2;
                    }
                }
            }
        }
        $q=$pdo->prepare("SELECT COUNT(*) FROM cdsp_sales_posts p WHERE LOWER(p.platform)=? AND p.deleted_at IS NULL AND NOT EXISTS (SELECT 1 FROM cdsp_post_image_fingerprints f WHERE f.post_id=p.id)");
        $q->execute([strtolower($platform)]);$unindexed=(int)$q->fetchColumn();
        if($unindexed){$warnings[]=$unindexed.' existing '.$platform.' posts have no image fingerprint; historical image comparison is incomplete.';}
        $website=(int)$pdo->query('SELECT COUNT(*) FROM cdsp_website_references')->fetchColumn();
        if(!$website){$warnings[]='Website comparison is not configured: import the company website product CSV in Settings.';}
        else{
            $q=$pdo->prepare('SELECT page_url,title FROM cdsp_website_references WHERE title_hash=? LIMIT 1');
            $q->execute([Util::hashText($title)]);
            if($row=$q->fetch()){
                $warnings[]='The title also appears on the company website: '.$row['title'].'.';
                $matches[]=['kind'=>'website_title','url'=>$row['page_url']];
            }
            foreach($assets as $asset){
                $q=$pdo->prepare('SELECT page_url,title FROM cdsp_website_references WHERE sha256=? LIMIT 1');$q->execute([$asset['sha256']]);
                if($row=$q->fetch()){
                    $warnings[]='An identical image appears on the company website: '.$row['title'].'.';
                    $matches[]=['kind'=>'website_image','url'=>$row['page_url']];break;
                }
            }
            if($hashes){
                $q=$pdo->query('SELECT page_url,title,dhash FROM cdsp_website_references WHERE dhash IS NOT NULL');
                while($row=$q->fetch()){
                    foreach($hashes as $hash){
                        if(ImageFingerprint::distance($hash,$row['dhash'])<=5){
                            $warnings[]='A similar image appears on the company website: '.$row['title'].'.';
                            $matches[]=['kind'=>'similar_website_image','url'=>$row['page_url']];break 2;
                        }
                    }
                }
            }
            $pending=(int)$pdo->query("SELECT COUNT(*) FROM cdsp_website_references WHERE sha256 IS NULL")->fetchColumn();
            if($pending){$warnings[]=$pending.' website images still need indexing; website image comparison is incomplete.';}
        }
        return ['blocked'=>$blocked,'warnings'=>$warnings,'matches'=>$matches,'website_count'=>$website,'unindexed_posts'=>$unindexed];
    }

    public static function storePost(int $postId,array $assets): void
    {
        if(!$assets){return;}
        $q=Database::connection()->prepare('INSERT INTO cdsp_post_image_fingerprints(post_id,image_url,image_url_hash,sha256,dhash,checked_at) VALUES(?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE sha256=VALUES(sha256),dhash=VALUES(dhash),checked_at=NOW()');
        foreach($assets as $a){$q->execute([$postId,$a['url'],hash('sha256',$a['url']),$a['sha256'],$a['dhash']??null]);}
    }

    public static function websiteStats(): array
    {
        if(!self::ready()){return ['ready'=>false,'total'=>0,'pending'=>0];}
        $row=Database::connection()->query("SELECT COUNT(*) total,COALESCE(SUM(image_url IS NOT NULL AND image_url<>'' AND sha256 IS NULL),0) pending,MAX(imported_at) imported_at FROM cdsp_website_references")->fetch();
        return ['ready'=>true]+$row;
    }
}
