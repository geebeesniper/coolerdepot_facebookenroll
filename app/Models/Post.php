<?php
namespace App\Models;
use App\Core\Database;
use App\Core\Util;
class Post {
    public static function duplicate(int $uid,string $platform,?string $url,?string $eid,?string $title,?string $desc):?array{
        $pdo=Database::connection();
        $checks=[];
        if($url)$checks[]=["SELECT id,title FROM cdsp_sales_posts WHERE canonical_url_hash=? AND deleted_at IS NULL LIMIT 1",[Util::urlHash($url)],"This URL has already been submitted."];
        if($eid)$checks[]=["SELECT id,title FROM cdsp_sales_posts WHERE platform=? AND external_post_id=? AND deleted_at IS NULL LIMIT 1",[$platform,$eid],"This platform post ID already exists."];
        if($title)$checks[]=["SELECT id,title FROM cdsp_sales_posts WHERE sales_user_id=? AND platform=? AND normalized_title_hash=? AND deleted_at IS NULL LIMIT 1",[$uid,$platform,Util::hashText($title)],"You already used exactly the same title on this platform."];
        if($desc && Util::normalizeText($desc)!=='')$checks[]=["SELECT id,title FROM cdsp_sales_posts WHERE sales_user_id=? AND platform=? AND description_hash=? AND deleted_at IS NULL LIMIT 1",[$uid,$platform,Util::hashText($desc)],"You already used exactly the same description on this platform."];
        foreach($checks as [$sql,$args,$msg]){$s=$pdo->prepare($sql);$s->execute($args);if($r=$s->fetch()){$r['reason']=$msg;return$r;}}
        return null;
    }
    public static function create(array $i):int{
        $s=Database::connection()->prepare("INSERT INTO cdsp_sales_posts
        (sales_user_id,platform,submitted_url,resolved_url,canonical_url,canonical_url_hash,external_post_id,title,normalized_title_hash,description,description_hash,published_at,published_date,fetched_at,verification_status,admin_review_status,created_at,updated_at)
        VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,'verified',NULL,NOW(),NOW())");
        $s->execute([$i['sales_user_id'],$i['platform'],$i['submitted_url'],$i['resolved_url'],$i['canonical_url'],Util::urlHash($i['canonical_url']),$i['external_post_id']?:null,$i['title'],Util::hashText($i['title']),$i['description'],Util::hashText($i['description']),$i['published_at'],$i['published_date'],$i['fetched_at']]);
        return(int)Database::connection()->lastInsertId();
    }
    public static function forSales(int $uid,string $from,string $to):array{
        $s=Database::connection()->prepare("SELECT p.*,r.decision review_decision FROM cdsp_sales_posts p LEFT JOIN cdsp_post_reviews r ON r.post_id=p.id WHERE p.sales_user_id=? AND p.created_at>=? AND p.created_at<DATE_ADD(?,INTERVAL 1 DAY) AND p.deleted_at IS NULL ORDER BY p.created_at DESC");
        $s->execute([$uid,$from.' 00:00:00',$to.' 00:00:00']);return$s->fetchAll();
    }
    public static function find(int $id):?array{
        $s=Database::connection()->prepare("SELECT p.*,u.display_name,u.sales_id FROM cdsp_sales_posts p JOIN cdsp_users u ON u.id=p.sales_user_id WHERE p.id=? LIMIT 1");
        $s->execute([$id]);return$s->fetch()?:null;
    }
    public static function adminQueue(string $date, int $salesUserId = 0):array{
        $sql = "SELECT p.*,u.display_name,u.sales_id,r.decision
                FROM cdsp_sales_posts p
                JOIN cdsp_users u ON u.id=p.sales_user_id
                LEFT JOIN cdsp_post_reviews r ON r.post_id=p.id
                WHERE p.published_date=?
                  AND p.deleted_at IS NULL";
        $params = [$date];

        if ($salesUserId > 0) {
            $sql .= " AND p.sales_user_id=?";
            $params[] = $salesUserId;
        }

        $sql .= " ORDER BY u.display_name,p.published_at DESC,p.id DESC";

        $s=Database::connection()->prepare($sql);
        $s->execute($params);
        return $s->fetchAll();
    }

    public static function adminProgressStats(
        string $from,
        string $to,
        int $salesUserId = 0
    ): array {
        $sql = "SELECT
                    u.id AS sales_user_id,
                    u.sales_id,
                    u.display_name,
                    COUNT(p.id) AS total_posts,
                    COALESCE(SUM(p.admin_review_status='good'),0) AS good_posts,
                    COALESCE(SUM(p.admin_review_status='bad'),0) AS bad_posts
                FROM cdsp_users u
                LEFT JOIN cdsp_sales_posts p
                  ON p.sales_user_id=u.id
                 AND p.deleted_at IS NULL
                 AND p.published_date BETWEEN ? AND ?
                WHERE u.role='sales'
                  AND u.active=1";
        $params = [$from, $to];

        if ($salesUserId > 0) {
            $sql .= " AND u.id=?";
            $params[] = $salesUserId;
        }

        $sql .= " GROUP BY u.id,u.sales_id,u.display_name
                  ORDER BY total_posts DESC,u.display_name";

        $s = Database::connection()->prepare($sql);
        $s->execute($params);

        return $s->fetchAll();
    }

    public static function adminDailySalesProgress(string $date): array
    {
        $s = Database::connection()->prepare(
            "SELECT
                u.id AS sales_user_id,
                u.sales_id,
                u.display_name,
                COALESCE(NULLIF(u.daily_post_target,0),10) AS target_posts,
                COUNT(p.id) AS post_count,
                COALESCE(SUM(p.admin_review_status='good'),0) AS good_count,
                COALESCE(SUM(p.admin_review_status='bad'),0) AS bad_count
             FROM cdsp_users u
             LEFT JOIN cdsp_sales_posts p
               ON p.sales_user_id=u.id
              AND p.deleted_at IS NULL
              AND p.published_date=?
             WHERE u.role='sales'
               AND u.active=1
             GROUP BY
                u.id,
                u.sales_id,
                u.display_name,
                u.daily_post_target
             ORDER BY u.display_name"
        );

        $s->execute([$date]);

        return $s->fetchAll();
    }

    public static function adminDashboardState(string $date): array
    {
        $s = Database::connection()->prepare(
            "SELECT
                COUNT(*) AS post_count,
                COALESCE(MAX(id),0) AS max_post_id
             FROM cdsp_sales_posts
             WHERE deleted_at IS NULL
               AND published_date=?"
        );
        $s->execute([$date]);
        $row = $s->fetch() ?: [];

        return [
            'post_count' => (int)($row['post_count'] ?? 0),
            'max_post_id' => (int)($row['max_post_id'] ?? 0),
        ];
    }

    public static function dailyCounts(int $uid,string $from,string $to):array{
        $s=Database::connection()->prepare("SELECT DATE(created_at) work_date,platform,COUNT(*) cnt FROM cdsp_sales_posts WHERE sales_user_id=? AND created_at>=? AND created_at<DATE_ADD(?,INTERVAL 1 DAY) AND deleted_at IS NULL GROUP BY DATE(created_at),platform ORDER BY work_date DESC");
        $s->execute([$uid,$from.' 00:00:00',$to.' 00:00:00']);return$s->fetchAll();
    }

    public static function dailyDatesForSales(int $salesUserId, string $from, string $to, int $limit, int $offset = 0): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT published_date, COUNT(*) AS post_count,
                    SUM(admin_review_status='good') AS good_count,
                    SUM(admin_review_status='bad') AS bad_count
             FROM cdsp_sales_posts
             WHERE sales_user_id = ?
               AND deleted_at IS NULL
               AND published_date BETWEEN ? AND ?
             GROUP BY published_date
             ORDER BY published_date DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->bindValue(1, $salesUserId, \PDO::PARAM_INT);
        $stmt->bindValue(2, $from);
        $stmt->bindValue(3, $to);
        $stmt->bindValue(4, $limit, \PDO::PARAM_INT);
        $stmt->bindValue(5, $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function forSalesOnDate(int $salesUserId, string $date): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT *
             FROM cdsp_sales_posts
             WHERE sales_user_id = ?
               AND deleted_at IS NULL
               AND published_date = ?
             ORDER BY published_at DESC, id DESC"
        );
        $stmt->execute([$salesUserId, $date]);

        return $stmt->fetchAll();
    }

    public static function dailyDateCountForSales(int $salesUserId, string $from, string $to): int
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(DISTINCT published_date)
             FROM cdsp_sales_posts
             WHERE sales_user_id = ?
               AND deleted_at IS NULL
               AND published_date BETWEEN ? AND ?"
        );
        $stmt->execute([$salesUserId, $from, $to]);

        return (int)$stmt->fetchColumn();
    }

}
