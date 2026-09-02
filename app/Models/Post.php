<?php
/**
 * File / 文件：app/Models/Post.php
 * EN: Defines the Post database model and its persistence/query helpers.
 * 中文：定义 Post 数据库模型及其持久化与查询辅助逻辑。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Models;
use App\Core\Database;
use App\Core\Util;
/**
 * EN: Database model for post records, queries, and persistence operations.
 * 中文：负责 post 记录、查询及持久化操作的数据库 Model。
 */
class Post {
    /**
     * EN: Calculate or compare the duplicate data for post in the application database.
     * 中文：计算或比较 post 的“duplicate”数据，并访问应用数据库。
     *
     * @param int $uid External user identifier supplied by the parent authentication system. / 父级认证系统提供的外部用户 ID。
     * @param string $platform Platform value used by this operation. / 本操作使用的“platform”参数值。
     * @param ?string $url URL to validate, resolve, fetch, or process. / 需要验证、解析、抓取或处理的 URL。
     * @param ?string $eid Identifier of the e record or entity. / e 记录或实体的标识 ID。
     * @param ?string $title Title value used by this operation. / 本操作使用的“title”参数值。
     * @param ?string $desc Desc value used by this operation. / 本操作使用的“desc”参数值。
     *
     * @return ?array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
    public static function duplicate(int $uid,string $platform,?string $url,?string $eid,?string $title,?string $desc,?array $platformAccount=null):?array{
        $pdo=Database::connection();
        $platform=strtolower(trim($platform));
        $hasStableAccount=\App\Services\MarketplaceAccount::hasStableIdentity($platformAccount);

        // V0.2.71 restores hard listing-identity checks before title/image
        // comparison. V0.2.54 intentionally allows a different Sales user to
        // save the same listing, so every marketplace identity check remains
        // scoped to this Sales user + this platform. Description is not a key.
        if($eid!==null && trim($eid)!==''){
            $s=$pdo->prepare(
                "SELECT id,title,canonical_url,platform FROM cdsp_sales_posts
                 WHERE platform=? AND external_post_id=? AND sales_user_id=?
                   AND deleted_at IS NULL LIMIT 1"
            );
            $s->execute([$platform,trim($eid),$uid]);
            if($r=$s->fetch()){
                $r['reason']='This '.$platform.' Post ID has already been submitted by you.';
                $r['kind']='external_id';
                return $r;
            }
        }

        if($url!==null && trim($url)!==''){
            $url=trim($url);
            $s=$pdo->prepare(
                "SELECT id,title,canonical_url,platform FROM cdsp_sales_posts
                 WHERE sales_user_id=? AND platform=? AND canonical_url_hash=?
                   AND deleted_at IS NULL LIMIT 1"
            );
            $s->execute([$uid,$platform,Util::urlHash($url)]);
            if($r=$s->fetch()){
                $r['reason']='This '.$platform.' URL has already been submitted by you.';
                $r['kind']='url';
                return $r;
            }
        }

        if($title!==null && $title!==''){
            if($hasStableAccount){
                // V0.2.77: every marketplace title check with a stable provider/API
                // account goes through the same account-equivalence helper. The helper
                // matches stable ID OR normalized profile URL, so changing provider
                // response shape cannot silently broaden the check back to Sales scope.
                $r=\App\Services\DuplicateIndex::findMarketplaceAccountTitle(
                    $platform,
                    $title,
                    $platformAccount
                );
                if($r){
                    $label=\App\Services\MarketplaceAccount::label($platformAccount);
                    $r['reason']='This '.$platform.' account'.($label!==''?' ('.$label.')':'').' already used this exact title.';
                    $r['kind']='same_account_title';
                    return $r;
                }
            } else {
                // Provider did not return a stable external account identity. Fall back
                // to the pre-account rule: this Sales user + this platform.
                $s=$pdo->prepare(
                    "SELECT id,title,canonical_url,platform FROM cdsp_sales_posts
                     WHERE sales_user_id=? AND platform=? AND BINARY title=BINARY ?
                       AND deleted_at IS NULL LIMIT 1"
                );
                $s->execute([$uid,$platform,$title]);
                if($r=$s->fetch()){
                    $r['reason']='You already used this exact title on '.$platform.'.';
                    $r['kind']='exact_title';
                    return $r;
                }
            }
        }
        return null;
    }
    /**
     * EN: Create or store the create data for post in the application database.
     * 中文：创建或保存 post 的“create”数据，并访问应用数据库。
     *
     * @param array $i I value used by this operation. / 本操作使用的“i”参数值。
     *
     * @return int Numeric result produced by this operation. / 本操作生成的数字结果。
     *
     * @throws \LogicException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     * @throws \DomainException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    public static function create(array $i):int{
        // Never trust a preflight result at save time. All callers must serialize
        // same-platform saves and own the surrounding transaction.
        if(!Database::connection()->inTransaction()){throw new \LogicException('Post creation requires a transaction.');}
        $meta=json_decode($i['raw_meta_json']??'{}',true)?:[];
        $platformAccount=is_array($meta['platform_account']??null)
            ? $meta['platform_account']
            : null;
        if($duplicate=self::duplicate((int)$i['sales_user_id'],$i['platform'],$i['canonical_url'],$i['external_post_id'],$i['title'],$i['description'],$platformAccount)){
            throw new \DomainException($duplicate['reason']);
        }
        if(($meta['duplicate_report']['version']??0)!==1){throw new \DomainException('Check this post again to run the updated image comparison.');}
        $assets=$meta['duplicate_report']['assets']??[];
        // EN: Keep the first extracted listing image even when the remote CDN
        // blocks server-side fingerprint download. Display/persistence must not
        // depend on the duplicate-fingerprint request succeeding.
        // 中文：即使远端 CDN 阻止服务器下载图片指纹，也保留解析到的第一张帖子图片；
        // 图片显示/保存不能依赖 Duplicate Fingerprint 下载成功。
        $listingImageUrls=\App\Services\ImageFingerprint::urls($meta);
        $fetchedImageUrl=$listingImageUrls[0]??($assets[0]['url']??null);
        $report=\App\Services\DuplicateIndex::compare((int)$i['sales_user_id'],$i['platform'],$i['title'],$assets,$platformAccount);
        if($report['blocked']){throw new \DomainException($report['blocked']);}
        $verificationStatus=(string)($i['verification_status']??'verified');
        if(!in_array($verificationStatus,['verified','manual_pending'],true)){
            throw new \DomainException('This inspection is not ready to be saved.');
        }
        $accountId=trim((string)($platformAccount['id']??''));
        $accountName=trim((string)($platformAccount['name']??''));
        $accountUrl=trim((string)($platformAccount['url']??''));
        $accountHash=strtolower(trim((string)($platformAccount['key_hash']??'')));
        if(!preg_match('/^[a-f0-9]{64}$/',$accountHash)){$accountHash='';}

        $s=Database::connection()->prepare("INSERT INTO cdsp_sales_posts
        (sales_user_id,platform,submitted_url,resolved_url,canonical_url,canonical_url_hash,external_post_id,platform_account_id,platform_account_name,platform_account_url,platform_account_key_hash,title,normalized_title_hash,description,description_hash,published_at,published_date,fetched_at,fetched_image_url,verification_status,admin_review_status,created_at,updated_at)
        VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NULL,NOW(),NOW())");
        $s->execute([
            $i['sales_user_id'],$i['platform'],$i['submitted_url'],$i['resolved_url'],$i['canonical_url'],Util::urlHash($i['canonical_url']),$i['external_post_id']?:null,
            $accountId!==''?$accountId:null,$accountName!==''?$accountName:null,$accountUrl!==''?$accountUrl:null,$accountHash!==''?$accountHash:null,
            $i['title'],Util::hashText($i['title']),$i['description'],Util::hashText($i['description']),$i['published_at'],$i['published_date'],$i['fetched_at'],$fetchedImageUrl,$verificationStatus
        ]);
        $id=(int)Database::connection()->lastInsertId();
        \App\Services\DuplicateIndex::storePost($id,$assets);
        return $id;
    }
    /**
     * EN: Retrieve the for sales data for post in the application database.
     * 中文：读取 post 的“for sales”数据，并访问应用数据库。
     *
     * @param int $uid External user identifier supplied by the parent authentication system. / 父级认证系统提供的外部用户 ID。
     * @param string $from From value used by this operation. / 本操作使用的“from”参数值。
     * @param string $to To value used by this operation. / 本操作使用的“to”参数值。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
    public static function forSales(int $uid,string $from,string $to):array{
        $s=Database::connection()->prepare("SELECT p.*,r.decision review_decision FROM cdsp_sales_posts p LEFT JOIN cdsp_post_reviews r ON r.post_id=p.id WHERE p.sales_user_id=? AND p.published_date BETWEEN ? AND ? AND p.deleted_at IS NULL ORDER BY p.published_at DESC,p.id DESC");
        $s->execute([$uid,$from,$to]);return$s->fetchAll();
    }
    /**
     * EN: Retrieve the find data for post in the application database.
     * 中文：读取 post 的“find”数据，并访问应用数据库。
     *
     * @param int $id Identifier of the record record or entity. / record 记录或实体的标识 ID。
     *
     * @return ?array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
    public static function find(int $id):?array{
        $s=Database::connection()->prepare("SELECT p.*,u.display_name,u.sales_id FROM cdsp_sales_posts p JOIN cdsp_users u ON u.id=p.sales_user_id WHERE p.id=? LIMIT 1");
        $s->execute([$id]);return$s->fetch()?:null;
    }
    /**
     * EN: Retrieve the pending deletion requests data for post in the application database.
     * 中文：读取 post 的“pending deletion requests”数据，并访问应用数据库。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
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
    /**
     * EN: Perform the admin queue data for post in the application database.
     * 中文：执行 post 的“admin queue”数据，并访问应用数据库。
     *
     * @param string $date Date value used to scope the operation. / 用于限定本操作范围的日期值。
     * @param int $salesUserId Application or external user identifier. / 应用或外部用户 ID。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
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

    /**
     * EN: Perform the admin progress stats data for post in the application database.
     * 中文：执行 post 的“admin progress stats”数据，并访问应用数据库。
     *
     * @param string $from From value used by this operation. / 本操作使用的“from”参数值。
     * @param string $to To value used by this operation. / 本操作使用的“to”参数值。
     * @param int $salesUserId Application or external user identifier. / 应用或外部用户 ID。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
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

    /**
     * EN: Perform the admin sales progress data for post in the application database.
     * 中文：执行 post 的“admin sales progress”数据，并访问应用数据库。
     *
     * @param string $from From value used by this operation. / 本操作使用的“from”参数值。
     * @param string $to To value used by this operation. / 本操作使用的“to”参数值。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
    public static function adminSalesProgress(
        string $from,
        string $to
    ): array {
        $s = Database::connection()->prepare(
            "SELECT
                u.id AS sales_user_id,
                u.sales_id,
                u.display_name,
                u.location_id,
                COALESCE(l.name,'') AS location_name,
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
             LEFT JOIN cdsp_locations l
               ON l.id=u.location_id
              AND l.active=1
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
                u.location_id,
                l.name,
                u.daily_post_target
             ORDER BY u.display_name"
        );

        $s->execute([$from,$to]);

        return $s->fetchAll();
    }

    /**
     * EN: Perform the admin dashboard state range data for post in the application database.
     * 中文：执行 post 的“admin dashboard state range”数据，并访问应用数据库。
     *
     * @param string $from From value used by this operation. / 本操作使用的“from”参数值。
     * @param string $to To value used by this operation. / 本操作使用的“to”参数值。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
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

    /**
     * EN: Perform the admin daily sales progress data for post.
     * 中文：执行 post 的“admin daily sales progress”数据。
     *
     * @param string $date Date value used to scope the operation. / 用于限定本操作范围的日期值。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
    public static function adminDailySalesProgress(string $date): array
    {
        return self::adminSalesProgress($date,$date);
    }

    /**
     * EN: Perform the admin dashboard state data for post.
     * 中文：执行 post 的“admin dashboard state”数据。
     *
     * @param string $date Date value used to scope the operation. / 用于限定本操作范围的日期值。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
    public static function adminDashboardState(string $date): array
    {
        return self::adminDashboardStateRange($date,$date);
    }


    /**
     * EN: Perform the admin sales posts for period data for post in the application database.
     * 中文：执行 post 的“admin sales posts for period”数据，并访问应用数据库。
     *
     * @param int $salesUserId Application or external user identifier. / 应用或外部用户 ID。
     * @param string $from From value used by this operation. / 本操作使用的“from”参数值。
     * @param string $to To value used by this operation. / 本操作使用的“to”参数值。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
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


    /**
     * EN: Update the update fetched content data for post in the application database.
     * 中文：更新 post 的“update fetched content”数据，并访问应用数据库。
     *
     * @param int $postId Sales post identifier. / 销售 Post ID。
     * @param string $title Title value used by this operation. / 本操作使用的“title”参数值。
     * @param string $description Description value used by this operation. / 本操作使用的“description”参数值。
     * @param ?string $imageUrl Image url value used by this operation. / 本操作使用的“image url”参数值。
     *
     * @return void No value is returned. / 无返回值。
     */
    public static function updateFetchedContent(
        int $postId,
        string $title,
        string $description,
        ?string $imageUrl = null,
        ?array $platformAccount = null
    ): void {
        $accountId=trim((string)($platformAccount['id']??''));
        $accountName=trim((string)($platformAccount['name']??''));
        $accountUrl=trim((string)($platformAccount['url']??''));
        $accountHash=strtolower(trim((string)($platformAccount['key_hash']??'')));
        if(!preg_match('/^[a-f0-9]{64}$/',$accountHash)){$accountHash='';}

        $s = Database::connection()->prepare(
            "UPDATE cdsp_sales_posts
             SET title=?,
                 normalized_title_hash=?,
                 description=?,
                 description_hash=?,
                 fetched_at=NOW(),
                 fetched_image_url=?,
                 platform_account_id=COALESCE(?,platform_account_id),
                 platform_account_name=COALESCE(?,platform_account_name),
                 platform_account_url=COALESCE(?,platform_account_url),
                 platform_account_key_hash=COALESCE(?,platform_account_key_hash),
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
            $accountId!==''?$accountId:null,
            $accountName!==''?$accountName:null,
            $accountUrl!==''?$accountUrl:null,
            $accountHash!==''?$accountHash:null,
            $postId,
        ]);
    }

    /**
     * EN: Perform the daily counts data for post in the application database.
     * 中文：执行 post 的“daily counts”数据，并访问应用数据库。
     *
     * @param int $uid External user identifier supplied by the parent authentication system. / 父级认证系统提供的外部用户 ID。
     * @param string $from From value used by this operation. / 本操作使用的“from”参数值。
     * @param string $to To value used by this operation. / 本操作使用的“to”参数值。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
    public static function dailyCounts(int $uid,string $from,string $to):array{
        $s=Database::connection()->prepare("SELECT DATE(created_at) work_date,platform,COUNT(*) cnt FROM cdsp_sales_posts WHERE sales_user_id=? AND created_at>=? AND created_at<DATE_ADD(?,INTERVAL 1 DAY) AND deleted_at IS NULL GROUP BY DATE(created_at),platform ORDER BY work_date DESC");
        $s->execute([$uid,$from.' 00:00:00',$to.' 00:00:00']);return$s->fetchAll();
    }

/**
 * EN: Perform the daily dates for sales data for post in the application database.
 * 中文：执行 post 的“daily dates for sales”数据，并访问应用数据库。
 *
 * @param int $salesUserId Application or external user identifier. / 应用或外部用户 ID。
 * @param string $from From value used by this operation. / 本操作使用的“from”参数值。
 * @param string $to To value used by this operation. / 本操作使用的“to”参数值。
 * @param int $limit Maximum number of records or items to process. / 允许处理的最大记录或数据项数量。
 * @param int $offset Offset used when reading or paginating data. / 读取或分页数据时使用的偏移量。
 * @param ?string $platform Platform value used by this operation. / 本操作使用的“platform”参数值。
 *
 * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
 */
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

/**
 * EN: Retrieve the for sales on date data for post in the application database.
 * 中文：读取 post 的“for sales on date”数据，并访问应用数据库。
 *
 * @param int $salesUserId Application or external user identifier. / 应用或外部用户 ID。
 * @param string $date Date value used to scope the operation. / 用于限定本操作范围的日期值。
 * @param ?string $platform Platform value used by this operation. / 本操作使用的“platform”参数值。
 *
 * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
 */
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


/**
 * EN: Retrieve the for sales published range data for post in the application database.
 * 中文：读取 post 的“for sales published range”数据，并访问应用数据库。
 *
 * @param int $salesUserId Application or external user identifier. / 应用或外部用户 ID。
 * @param string $from From value used by this operation. / 本操作使用的“from”参数值。
 * @param string $to To value used by this operation. / 本操作使用的“to”参数值。
 * @param ?string $platform Platform value used by this operation. / 本操作使用的“platform”参数值。
 *
 * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
 */
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


/**
 * EN: Perform the sales chart rows data for post in the application database.
 * 中文：执行 post 的“sales chart rows”数据，并访问应用数据库。
 *
 * @param int $salesUserId Application or external user identifier. / 应用或外部用户 ID。
 * @param string $from From value used by this operation. / 本操作使用的“from”参数值。
 * @param string $to To value used by this operation. / 本操作使用的“to”参数值。
 * @param ?string $platform Platform value used by this operation. / 本操作使用的“platform”参数值。
 *
 * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
 */
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

/**
 * EN: Perform the sales range summary data for post in the application database.
 * 中文：执行 post 的“sales range summary”数据，并访问应用数据库。
 *
 * @param int $salesUserId Application or external user identifier. / 应用或外部用户 ID。
 * @param string $from From value used by this operation. / 本操作使用的“from”参数值。
 * @param string $to To value used by this operation. / 本操作使用的“to”参数值。
 * @param ?string $platform Platform value used by this operation. / 本操作使用的“platform”参数值。
 *
 * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
 */
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

/**
 * EN: Perform the daily date count for sales data for post in the application database.
 * 中文：执行 post 的“daily date count for sales”数据，并访问应用数据库。
 *
 * @param int $salesUserId Application or external user identifier. / 应用或外部用户 ID。
 * @param string $from From value used by this operation. / 本操作使用的“from”参数值。
 * @param string $to To value used by this operation. / 本操作使用的“to”参数值。
 * @param ?string $platform Platform value used by this operation. / 本操作使用的“platform”参数值。
 *
 * @return int Numeric result produced by this operation. / 本操作生成的数字结果。
 */
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



/**
 * EN: Send or process the request deletion data for post in the application database.
 * 中文：发送或处理 post 的“request deletion”数据，并访问应用数据库。
 *
 * @param int $salesUserId Application or external user identifier. / 应用或外部用户 ID。
 * @param int $postId Sales post identifier. / 销售 Post ID。
 * @param string $reason Reason value used by this operation. / 本操作使用的“reason”参数值。
 *
 * @return void No value is returned. / 无返回值。
 *
 * @throws \DomainException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
 */
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

/**
 * EN: Delete or clean the hard delete data for post in the application database.
 * 中文：删除或清理 post 的“hard delete”数据，并访问应用数据库。
 *
 * @param int $postId Sales post identifier. / 销售 Post ID。
 *
 * @return void No value is returned. / 无返回值。
 *
 * @throws \DomainException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
 * @throws \RuntimeException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
 * @throws \Throwable When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
 */
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
