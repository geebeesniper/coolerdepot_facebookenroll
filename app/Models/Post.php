<?php
namespace App\Models;
use App\Core\Database;
use App\Core\Util;
class Post {
    public static function duplicate(int $uid,string $platform,?string $url,?string $eid,?string $title,?string $desc):?array{
        $pdo=Database::connection();
        $checks=[];
        if($url)$checks[]=[
            "SELECT id,title,canonical_url,platform FROM cdsp_sales_posts WHERE canonical_url_hash=? AND deleted_at IS NULL LIMIT 1",
            [Util::urlHash($url)],
            "This URL has already been submitted.",
            'url'
        ];
        if($eid)$checks[]=[
            "SELECT id,title,canonical_url,platform FROM cdsp_sales_posts WHERE platform=? AND external_post_id=? AND deleted_at IS NULL LIMIT 1",
            [$platform,$eid],
            "This platform post ID already exists.",
            'external_id'
        ];
        // Title blocking is intentionally literal. Different case, spacing or punctuation
        // is not treated as the same title. Only an exact byte-for-byte title on the
        // same platform blocks the save.
        if($title!==null && $title!=='')$checks[]=[
            "SELECT id,title,canonical_url,platform FROM cdsp_sales_posts WHERE platform=? AND BINARY title=BINARY ? AND deleted_at IS NULL LIMIT 1",
            [$platform,$title],
            "This exact title already exists on this platform.",
            'exact_title'
        ];
        if($desc && Util::normalizeText($desc)!=='')$checks[]=[
            "SELECT id,title,canonical_url,platform FROM cdsp_sales_posts WHERE sales_user_id=? AND platform=? AND description_hash=? AND deleted_at IS NULL LIMIT 1",
            [$uid,$platform,Util::hashText($desc)],
            "You already used exactly the same description on this platform.",
            'description'
        ];
        foreach($checks as [$sql,$args,$msg,$kind]){
            $s=$pdo->prepare($sql);$s->execute($args);
            if($r=$s->fetch()){
                $r['reason']=$msg;
                $r['kind']=$kind;
                return$r;
            }
        }
        return null;
    }
    public static function create(array $i):int{
        // Never trust a preflight result at save time. All callers must serialize
        // same-platform saves and own the surrounding transaction.
        if(!Database::connection()->inTransaction()){throw new \LogicException('Post creation requires a transaction.');}
        if($duplicate=self::duplicate((int)$i['sales_user_id'],$i['platform'],$i['canonical_url'],$i['external_post_id'],$i['title'],$i['description'])){
            throw new \DomainException($duplicate['reason']);
        }
        $meta=json_decode($i['raw_meta_json']??'{}',true)?:[];
        if(($meta['duplicate_report']['version']??0)!==1){throw new \DomainException('Check this post again to run the updated image comparison.');}
        $assets=$meta['duplicate_report']['assets']??[];
        $report=\App\Services\DuplicateIndex::compare($i['platform'],$i['title'],$assets);
        if($report['blocked']){throw new \DomainException($report['blocked']);}
        $s=Database::connection()->prepare("INSERT INTO cdsp_sales_posts
        (sales_user_id,platform,submitted_url,resolved_url,canonical_url,canonical_url_hash,external_post_id,title,normalized_title_hash,description,description_hash,published_at,published_date,fetched_at,fetched_image_url,verification_status,admin_review_status,created_at,updated_at)
        VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'verified',NULL,NOW(),NOW())");
        $s->execute([$i['sales_user_id'],$i['platform'],$i['submitted_url'],$i['resolved_url'],$i['canonical_url'],Util::urlHash($i['canonical_url']),$i['external_post_id']?:null,$i['title'],Util::hashText($i['title']),$i['description'],Util::hashText($i['description']),$i['published_at'],$i['published_date'],$i['fetched_at'],$assets[0]['url']??null]);
        $id=(int)Database::connection()->lastInsertId();
        \App\Services\DuplicateIndex::storePost($id,$assets);
        return $id;
    }
    public static function forSales(int $uid,string $from,string $to):array{
        $s=Database::connection()->prepare("SELECT p.*,r.decision review_decision FROM cdsp_sales_posts p LEFT JOIN cdsp_post_reviews r ON r.post_id=p.id WHERE p.sales_user_id=? AND p.published_date BETWEEN ? AND ? AND p.deleted_at IS NULL ORDER BY p.published_at DESC,p.id DESC");
        $s->execute([$uid,$from,$to]);return$s->fetchAll();
    }
    public static function find(int $id):?array{
        $s=Database::connection()->prepare("SELECT p.*,u.display_name,u.sales_id FROM cdsp_sales_posts p JOIN cdsp_users u ON u.id=p.sales_user_id WHERE p.id=? LIMIT 1");
        $s->execute([$id]);return$s->fetch()?:null;
    }
    public static function pendingDeletionRequests():array{
        $s=Database::connection()->query(
            "SELECT
                d.id,
                d.post_id,
                d.reason,
                d.created_at,
                p.title,
                p.canonical_url,
                p.platform,
                p.external_post_id,
                u.display_name,
                u.sales_id
             FROM cdsp_deletion_requests d
             JOIN cdsp_sales_posts p ON p.id=d.post_id
             JOIN cdsp_users u ON u.id=p.sales_user_id
             WHERE d.status='pending'
               AND p.deleted_at IS NULL
             ORDER BY d.created_at DESC,d.id DESC"
        );
        return $s->fetchAll();
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
                    COALESCE(
                        SUM(
                            COALESCE(
                                rh.decision,
                                p.admin_review_status
                            )='good'
                        ),
                        0
                    ) AS good_posts,
                    COALESCE(
                        SUM(
                            COALESCE(
                                rh.decision,
                                p.admin_review_status
                            )='bad'
                        ),
                        0
                    ) AS bad_posts
                FROM cdsp_users u
                LEFT JOIN cdsp_sales_posts p
                  ON p.sales_user_id=u.id
                 AND p.deleted_at IS NULL
                 AND p.published_date BETWEEN ? AND ?
                LEFT JOIN (
                    SELECT h.post_id,h.decision
                    FROM cdsp_post_review_history h
                    INNER JOIN (
                        SELECT post_id,MAX(id) AS max_id
                        FROM cdsp_post_review_history
                        GROUP BY post_id
                    ) latest
                      ON latest.max_id=h.id
                ) rh
                  ON rh.post_id=p.id
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

    public static function adminSalesProgress(
        string $from,
        string $to
    ): array {
        $s = Database::connection()->prepare(
            "SELECT
                u.id AS sales_user_id,
                u.sales_id,
                u.display_name,
                COALESCE(NULLIF(u.daily_post_target,0),10) AS daily_target,
                COUNT(p.id) AS post_count,
                COALESCE(
                    SUM(
                        COALESCE(
                            rh.decision,
                            p.admin_review_status
                        )='good'
                    ),
                    0
                ) AS good_count,
                COALESCE(
                    SUM(
                        COALESCE(
                            rh.decision,
                            p.admin_review_status
                        )='bad'
                    ),
                    0
                ) AS bad_count
             FROM cdsp_users u
             LEFT JOIN cdsp_sales_posts p
               ON p.sales_user_id=u.id
              AND p.deleted_at IS NULL
              AND p.published_date BETWEEN ? AND ?
             LEFT JOIN (
                SELECT h.post_id,h.decision
                FROM cdsp_post_review_history h
                INNER JOIN (
                    SELECT post_id,MAX(id) AS max_id
                    FROM cdsp_post_review_history
                    GROUP BY post_id
                ) latest
                  ON latest.max_id=h.id
             ) rh
               ON rh.post_id=p.id
             WHERE u.role='sales'
               AND u.active=1
             GROUP BY
                u.id,
                u.sales_id,
                u.display_name,
                u.daily_post_target
             ORDER BY u.display_name"
        );

        $s->execute([$from,$to]);

        return $s->fetchAll();
    }

    public static function adminDashboardStateRange(
        string $from,
        string $to
    ): array {
        $s = Database::connection()->prepare(
            "SELECT
                COUNT(*) AS post_count,
                COALESCE(MAX(id),0) AS max_post_id
             FROM cdsp_sales_posts
             WHERE deleted_at IS NULL
               AND published_date BETWEEN ? AND ?"
        );
        $s->execute([$from,$to]);
        $row = $s->fetch() ?: [];

        return [
            'post_count' => (int)($row['post_count'] ?? 0),
            'max_post_id' => (int)($row['max_post_id'] ?? 0),
        ];
    }

    public static function adminDailySalesProgress(string $date): array
    {
        return self::adminSalesProgress($date,$date);
    }

    public static function adminDashboardState(string $date): array
    {
        return self::adminDashboardStateRange($date,$date);
    }


    public static function adminSalesPostsForPeriod(
        int $salesUserId,
        string $from,
        string $to
    ): array {
        $s = Database::connection()->prepare(
            "SELECT
                p.*,
                u.display_name,
                u.sales_id,
                r.decision AS current_review_row_decision,
                rh.decision AS latest_history_decision,
                COALESCE(
                    rh.decision,
                    p.admin_review_status,
                    r.decision
                ) AS current_review_status
             FROM cdsp_sales_posts p
             JOIN cdsp_users u
               ON u.id=p.sales_user_id
             LEFT JOIN cdsp_post_reviews r
               ON r.post_id=p.id
             LEFT JOIN (
                SELECT h.post_id,h.decision,h.id AS history_id
                FROM cdsp_post_review_history h
                INNER JOIN (
                    SELECT post_id,MAX(id) AS max_id
                    FROM cdsp_post_review_history
                    GROUP BY post_id
                ) latest
                  ON latest.max_id=h.id
             ) rh
               ON rh.post_id=p.id
             WHERE p.sales_user_id=?
               AND p.deleted_at IS NULL
               AND p.published_date BETWEEN ? AND ?
             ORDER BY p.published_at ASC,p.id ASC"
        );

        $s->execute([$salesUserId,$from,$to]);

        return $s->fetchAll();
    }


    public static function updateFetchedContent(
        int $postId,
        string $title,
        string $description,
        ?string $imageUrl = null
    ): void {
        $s = Database::connection()->prepare(
            "UPDATE cdsp_sales_posts
             SET title=?,
                 normalized_title_hash=?,
                 description=?,
                 description_hash=?,
                 fetched_at=NOW(),
                 fetched_image_url=?,
                 verification_status='verified',
                 updated_at=NOW()
             WHERE id=?
               AND deleted_at IS NULL"
        );

        $s->execute([
            $title,
            Util::hashText($title),
            $description,
            Util::hashText($description),
            $imageUrl !== null && trim($imageUrl) !== ''
                ? trim($imageUrl)
                : null,
            $postId,
        ]);
    }

    public static function dailyCounts(int $uid,string $from,string $to):array{
        $s=Database::connection()->prepare("SELECT DATE(created_at) work_date,platform,COUNT(*) cnt FROM cdsp_sales_posts WHERE sales_user_id=? AND created_at>=? AND created_at<DATE_ADD(?,INTERVAL 1 DAY) AND deleted_at IS NULL GROUP BY DATE(created_at),platform ORDER BY work_date DESC");
        $s->execute([$uid,$from.' 00:00:00',$to.' 00:00:00']);return$s->fetchAll();
    }

public static function dailyDatesForSales(
    int $salesUserId,
    string $from,
    string $to,
    int $limit,
    int $offset = 0,
    ?string $platform = null
): array {
    $platformSql=$platform !== null
        ? " AND LOWER(p.platform)=? "
        : "";

    $stmt = Database::connection()->prepare(
        "SELECT
            p.published_date,
            COUNT(*) AS post_count,
            COALESCE(
                SUM(
                    COALESCE(
                        rh.decision,
                        p.admin_review_status
                    )='good'
                ),
                0
            ) AS good_count,
            COALESCE(
                SUM(
                    COALESCE(
                        rh.decision,
                        p.admin_review_status
                    )='bad'
                ),
                0
            ) AS bad_count
         FROM cdsp_sales_posts p
         LEFT JOIN (
            SELECT h.post_id,h.decision
            FROM cdsp_post_review_history h
            INNER JOIN (
                SELECT post_id,MAX(id) AS max_id
                FROM cdsp_post_review_history
                GROUP BY post_id
            ) latest
              ON latest.max_id=h.id
         ) rh
           ON rh.post_id=p.id
         WHERE p.sales_user_id = ?
           AND p.deleted_at IS NULL
           AND p.published_date BETWEEN ? AND ?"
         .$platformSql.
        " GROUP BY p.published_date
          ORDER BY p.published_date DESC
          LIMIT ? OFFSET ?"
    );

    $index=1;

    $stmt->bindValue(
        $index++,
        $salesUserId,
        \PDO::PARAM_INT
    );
    $stmt->bindValue($index++,$from);
    $stmt->bindValue($index++,$to);

    if($platform !== null){
        $stmt->bindValue(
            $index++,
            strtolower($platform)
        );
    }

    $stmt->bindValue(
        $index++,
        $limit,
        \PDO::PARAM_INT
    );
    $stmt->bindValue(
        $index,
        $offset,
        \PDO::PARAM_INT
    );

    $stmt->execute();

    return $stmt->fetchAll();
}

public static function forSalesOnDate(
    int $salesUserId,
    string $date,
    ?string $platform = null
): array {
    $platformSql=$platform !== null
        ? " AND LOWER(p.platform)=? "
        : "";

    $stmt = Database::connection()->prepare(
        "SELECT
            p.*,
            COALESCE(
                rh.decision,
                p.admin_review_status
            ) AS current_review_status,
            (
                SELECT d.status
                FROM cdsp_deletion_requests d
                WHERE d.post_id=p.id
                ORDER BY d.id DESC
                LIMIT 1
            ) AS deletion_request_status
         FROM cdsp_sales_posts p
         LEFT JOIN (
            SELECT h.post_id,h.decision
            FROM cdsp_post_review_history h
            INNER JOIN (
                SELECT post_id,MAX(id) AS max_id
                FROM cdsp_post_review_history
                GROUP BY post_id
            ) latest
              ON latest.max_id=h.id
         ) rh
           ON rh.post_id=p.id
         WHERE p.sales_user_id = ?
           AND p.deleted_at IS NULL
           AND p.published_date = ?"
         .$platformSql.
        " ORDER BY p.published_at DESC,p.id DESC"
    );

    $params=[
        $salesUserId,
        $date,
    ];

    if($platform !== null){
        $params[]=strtolower($platform);
    }

    $stmt->execute($params);

    return $stmt->fetchAll();
}


public static function forSalesPublishedRange(
    int $salesUserId,
    string $from,
    string $to,
    ?string $platform = null
): array {
    $platformSql=$platform !== null
        ? " AND LOWER(p.platform)=? "
        : "";

    $stmt=Database::connection()->prepare(
        "SELECT
            p.*,
            COALESCE(
                rh.decision,
                p.admin_review_status
            ) AS current_review_status
         FROM cdsp_sales_posts p
         LEFT JOIN (
            SELECT h.post_id,h.decision
            FROM cdsp_post_review_history h
            INNER JOIN (
                SELECT post_id,MAX(id) AS max_id
                FROM cdsp_post_review_history
                GROUP BY post_id
            ) latest
              ON latest.max_id=h.id
         ) rh
           ON rh.post_id=p.id
         WHERE p.sales_user_id=?
           AND p.deleted_at IS NULL
           AND p.published_date BETWEEN ? AND ?"
         .$platformSql.
        " ORDER BY
            p.published_date DESC,
            p.published_at DESC,
            p.id DESC"
    );

    $params=[
        $salesUserId,
        $from,
        $to,
    ];

    if($platform !== null){
        $params[]=strtolower($platform);
    }

    $stmt->execute($params);

    return $stmt->fetchAll();
}


public static function salesChartRows(
    int $salesUserId,
    string $from,
    string $to,
    ?string $platform = null
): array {
    $platformSql=$platform !== null
        ? " AND LOWER(p.platform)=? "
        : "";

    $stmt=Database::connection()->prepare(
        "SELECT
            p.published_date,
            p.platform,
            COUNT(p.id) AS post_count,
            COALESCE(
                SUM(
                    COALESCE(
                        rh.decision,
                        p.admin_review_status
                    )='good'
                ),
                0
            ) AS good_count,
            COALESCE(
                SUM(
                    COALESCE(
                        rh.decision,
                        p.admin_review_status
                    )='bad'
                ),
                0
            ) AS bad_count
         FROM cdsp_sales_posts p
         LEFT JOIN (
            SELECT h.post_id,h.decision
            FROM cdsp_post_review_history h
            INNER JOIN (
                SELECT post_id,MAX(id) AS max_id
                FROM cdsp_post_review_history
                GROUP BY post_id
            ) latest
              ON latest.max_id=h.id
         ) rh
           ON rh.post_id=p.id
         WHERE p.sales_user_id=?
           AND p.deleted_at IS NULL
           AND p.published_date BETWEEN ? AND ?"
         .$platformSql.
        " GROUP BY p.published_date,p.platform
          ORDER BY p.published_date ASC,p.platform ASC"
    );

    $params=[
        $salesUserId,
        $from,
        $to,
    ];

    if($platform !== null){
        $params[]=strtolower($platform);
    }

    $stmt->execute($params);

    $rows=[];

    foreach($stmt->fetchAll() as $row){
        $posts=(int)($row['post_count']??0);
        $good=(int)($row['good_count']??0);
        $bad=(int)($row['bad_count']??0);

        $rows[]=[
            'date'=>(string)$row['published_date'],
            'platform'=>(string)$row['platform'],
            'post_count'=>$posts,
            'good_count'=>$good,
            'bad_count'=>$bad,
            'unreviewed_count'=>max(
                0,
                $posts-$good-$bad
            ),
        ];
    }

    return $rows;
}

public static function salesRangeSummary(
    int $salesUserId,
    string $from,
    string $to,
    ?string $platform = null
): array {
    $platformSql=$platform !== null
        ? " AND LOWER(p.platform)=? "
        : "";

    $stmt=Database::connection()->prepare(
        "SELECT
            COUNT(p.id) AS post_count,
            COALESCE(
                SUM(
                    COALESCE(
                        rh.decision,
                        p.admin_review_status
                    )='good'
                ),
                0
            ) AS good_count,
            COALESCE(
                SUM(
                    COALESCE(
                        rh.decision,
                        p.admin_review_status
                    )='bad'
                ),
                0
            ) AS bad_count
         FROM cdsp_sales_posts p
         LEFT JOIN (
            SELECT h.post_id,h.decision
            FROM cdsp_post_review_history h
            INNER JOIN (
                SELECT post_id,MAX(id) AS max_id
                FROM cdsp_post_review_history
                GROUP BY post_id
            ) latest
              ON latest.max_id=h.id
         ) rh
           ON rh.post_id=p.id
         WHERE p.sales_user_id=?
           AND p.deleted_at IS NULL
           AND p.published_date BETWEEN ? AND ?"
         .$platformSql
    );

    $params=[
        $salesUserId,
        $from,
        $to,
    ];

    if($platform !== null){
        $params[]=strtolower($platform);
    }

    $stmt->execute($params);

    $row=$stmt->fetch() ?: [];

    $posts=(int)($row['post_count']??0);
    $good=(int)($row['good_count']??0);
    $bad=(int)($row['bad_count']??0);

    return [
        'post_count'=>$posts,
        'good_count'=>$good,
        'bad_count'=>$bad,
        'unreviewed_count'=>max(
            0,
            $posts-$good-$bad
        ),
    ];
}

public static function dailyDateCountForSales(
    int $salesUserId,
    string $from,
    string $to,
    ?string $platform = null
): int {
    $platformSql=$platform !== null
        ? " AND LOWER(platform)=? "
        : "";

    $stmt = Database::connection()->prepare(
        "SELECT COUNT(DISTINCT published_date)
         FROM cdsp_sales_posts
         WHERE sales_user_id = ?
           AND deleted_at IS NULL
           AND published_date BETWEEN ? AND ?"
         .$platformSql
    );

    $params=[
        $salesUserId,
        $from,
        $to,
    ];

    if($platform !== null){
        $params[]=strtolower($platform);
    }

    $stmt->execute($params);

    return (int)$stmt->fetchColumn();
}



public static function requestDeletion(int $salesUserId,int $postId,string $reason): void
{
    $pdo=Database::connection();
    $reason=trim($reason);
    if($postId<1){throw new \DomainException('Post was not found.');}
    if($reason===''){throw new \DomainException('Enter a reason for the deletion request.');}
    if(mb_strlen($reason)>1000){throw new \DomainException('Deletion reason must be 1000 characters or fewer.');}

    $check=$pdo->prepare("SELECT id FROM cdsp_sales_posts WHERE id=? AND sales_user_id=? AND deleted_at IS NULL LIMIT 1");
    $check->execute([$postId,$salesUserId]);
    if(!$check->fetchColumn()){throw new \DomainException('Post was not found.');}

    $pending=$pdo->prepare("SELECT id FROM cdsp_deletion_requests WHERE post_id=? AND requested_by=? AND status='pending' LIMIT 1");
    $pending->execute([$postId,$salesUserId]);
    if($pending->fetchColumn()){throw new \DomainException('A deletion request for this post is already pending.');}

    $q=$pdo->prepare("INSERT INTO cdsp_deletion_requests(post_id,requested_by,reason,status,created_at,updated_at) VALUES(?,?,?,'pending',NOW(),NOW())");
    $q->execute([$postId,$salesUserId,$reason]);
}

public static function hardDelete(int $postId): void
{
    if($postId<1){throw new \DomainException('Post was not found.');}
    $pdo=Database::connection();
    $ownsTransaction=!$pdo->inTransaction();
    if($ownsTransaction){$pdo->beginTransaction();}
    try{
        $exists=$pdo->prepare("SELECT id FROM cdsp_sales_posts WHERE id=? FOR UPDATE");
        $exists->execute([$postId]);
        if(!$exists->fetchColumn()){throw new \DomainException('Post was not found.');}

        $reviewIds=[];
        $q=$pdo->prepare("SELECT id FROM cdsp_post_reviews WHERE post_id=?");
        $q->execute([$postId]);
        $reviewIds=array_map('intval',$q->fetchAll(\PDO::FETCH_COLUMN));

        $commentIds=[];
        $q=$pdo->prepare("SELECT id FROM cdsp_post_review_comments WHERE post_id=?");
        $q->execute([$postId]);
        $commentIds=array_map('intval',$q->fetchAll(\PDO::FETCH_COLUMN));

        $pdo->prepare("DELETE FROM cdsp_review_attachments WHERE entity_type='post_note' AND entity_id=?")->execute([$postId]);
        if($reviewIds){
            $marks=implode(',',array_fill(0,count($reviewIds),'?'));
            $pdo->prepare("DELETE FROM cdsp_review_attachments WHERE entity_type='post_review' AND entity_id IN ($marks)")->execute($reviewIds);
        }
        if($commentIds){
            $marks=implode(',',array_fill(0,count($commentIds),'?'));
            $pdo->prepare("DELETE FROM cdsp_review_attachments WHERE entity_type='post_comment' AND entity_id IN ($marks)")->execute($commentIds);
        }

        $pdo->prepare("DELETE FROM cdsp_post_image_fingerprints WHERE post_id=?")->execute([$postId]);
        $pdo->prepare("DELETE FROM cdsp_deletion_requests WHERE post_id=?")->execute([$postId]);
        $pdo->prepare("DELETE FROM cdsp_post_review_comments WHERE post_id=?")->execute([$postId]);
        $pdo->prepare("DELETE FROM cdsp_post_review_history WHERE post_id=?")->execute([$postId]);
        $pdo->prepare("DELETE FROM cdsp_post_reviews WHERE post_id=?")->execute([$postId]);
        $delete=$pdo->prepare("DELETE FROM cdsp_sales_posts WHERE id=?");
        $delete->execute([$postId]);
        if($delete->rowCount()!==1){throw new \RuntimeException('Post could not be deleted.');}

        if($ownsTransaction){$pdo->commit();}
    }catch(\Throwable $e){
        if($ownsTransaction&&$pdo->inTransaction()){$pdo->rollBack();}
        throw $e;
    }
}

}
