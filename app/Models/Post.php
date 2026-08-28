<?php
namespace App\Models;
use App\Core\Database;
use App\Core\Util;
class Post {
    public static function duplicate(int $uid,string $platform,?string $url,?string $eid,?string $title,?string $desc):?array{
        $pdo=Database::connection();
        $checks=[];
        if($url)$checks[]=["SELECT id,title FROM sales_posts WHERE canonical_url_hash=? AND deleted_at IS NULL LIMIT 1",[Util::urlHash($url)],"This URL has already been submitted."];
        if($eid)$checks[]=["SELECT id,title FROM sales_posts WHERE platform=? AND external_post_id=? AND deleted_at IS NULL LIMIT 1",[$platform,$eid],"This platform post ID already exists."];
        if($title)$checks[]=["SELECT id,title FROM sales_posts WHERE sales_user_id=? AND platform=? AND normalized_title_hash=? AND deleted_at IS NULL LIMIT 1",[$uid,$platform,Util::hashText($title)],"You already used exactly the same title on this platform."];
        if($desc && Util::normalizeText($desc)!=='')$checks[]=["SELECT id,title FROM sales_posts WHERE sales_user_id=? AND platform=? AND description_hash=? AND deleted_at IS NULL LIMIT 1",[$uid,$platform,Util::hashText($desc)],"You already used exactly the same description on this platform."];
        foreach($checks as [$sql,$args,$msg]){$s=$pdo->prepare($sql);$s->execute($args);if($r=$s->fetch()){$r['reason']=$msg;return$r;}}
        return null;
    }
    public static function create(array $i):int{
        $s=Database::connection()->prepare("INSERT INTO sales_posts
        (sales_user_id,platform,submitted_url,resolved_url,canonical_url,canonical_url_hash,external_post_id,title,normalized_title_hash,description,description_hash,published_at,published_date,fetched_at,verification_status,admin_review_status,created_at,updated_at)
        VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,'verified','pending',NOW(),NOW())");
        $s->execute([$i['sales_user_id'],$i['platform'],$i['submitted_url'],$i['resolved_url'],$i['canonical_url'],Util::urlHash($i['canonical_url']),$i['external_post_id']?:null,$i['title'],Util::hashText($i['title']),$i['description'],Util::hashText($i['description']),$i['published_at'],$i['published_date'],$i['fetched_at']]);
        return(int)Database::connection()->lastInsertId();
    }
    public static function forSales(int $uid,string $from,string $to):array{
        $s=Database::connection()->prepare("SELECT p.*,r.decision review_decision,r.rating review_rating FROM sales_posts p LEFT JOIN post_reviews r ON r.post_id=p.id WHERE p.sales_user_id=? AND p.created_at>=? AND p.created_at<DATE_ADD(?,INTERVAL 1 DAY) AND p.deleted_at IS NULL ORDER BY p.created_at DESC");
        $s->execute([$uid,$from.' 00:00:00',$to.' 00:00:00']);return$s->fetchAll();
    }
    public static function find(int $id):?array{
        $s=Database::connection()->prepare("SELECT p.*,u.display_name,u.sales_id FROM sales_posts p JOIN users u ON u.id=p.sales_user_id WHERE p.id=? LIMIT 1");
        $s->execute([$id]);return$s->fetch()?:null;
    }
    public static function adminQueue(string $date):array{
        $s=Database::connection()->prepare("SELECT p.*,u.display_name,u.sales_id,r.decision,r.rating FROM sales_posts p JOIN users u ON u.id=p.sales_user_id LEFT JOIN post_reviews r ON r.post_id=p.id WHERE DATE(p.created_at)=? AND p.deleted_at IS NULL ORDER BY u.display_name,p.created_at DESC");
        $s->execute([$date]);return$s->fetchAll();
    }
    public static function dailyCounts(int $uid,string $from,string $to):array{
        $s=Database::connection()->prepare("SELECT DATE(created_at) work_date,platform,COUNT(*) cnt FROM sales_posts WHERE sales_user_id=? AND created_at>=? AND created_at<DATE_ADD(?,INTERVAL 1 DAY) AND deleted_at IS NULL GROUP BY DATE(created_at),platform ORDER BY work_date DESC");
        $s->execute([$uid,$from.' 00:00:00',$to.' 00:00:00']);return$s->fetchAll();
    }
}
