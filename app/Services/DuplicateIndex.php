<?php
/**
 * File / 文件：app/Services/DuplicateIndex.php
 * EN: Defines the DuplicateIndex service used by application business, security, or provider integration flows.
 * 中文：定义 DuplicateIndex 服务，用于应用业务、安全或 Provider 集成流程。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Services;

use App\Core\Database;
use App\Core\Util;

/**
 * EN: Application service that encapsulates duplicate index business, security, or integration behavior.
 * 中文：封装 duplicate index 业务、安全或外部集成行为的应用服务。
 */
class DuplicateIndex
{
    /**
     * EN: Retrieve the ready operation implemented by duplicate index.
     * 中文：读取 duplicate index 实现的“ready”操作。
     *
     * @return bool True when the requested condition is satisfied; otherwise false. / 请求条件满足时返回 true，否则返回 false。
     */
    public static function ready(): bool
    {
        try{
            Database::connection()->query('SELECT id FROM cdsp_post_image_fingerprints LIMIT 0');
            Database::connection()->query('SELECT id FROM cdsp_website_references LIMIT 0');return true;
        }catch(\PDOException $e){
            // Readiness is used as a feature gate, so without this record a
            // database/schema failure would be misreported only as "migration required".
            \App\Core\Logger::exception(
                $e,
                'duplicate-index',
                ['event' => 'Duplicate comparison readiness check failed'],
                'error'
            );
            return false;
        }
    }

    /**
     * EN: Execute the inspect operation implemented by duplicate index.
     * 中文：执行 duplicate index 实现的“inspect”操作。
     *
     * @param string $platform Platform value used by this operation. / 本操作使用的“platform”参数值。
     * @param string $title Title value used by this operation. / 本操作使用的“title”参数值。
     * @param array $meta Meta value used by this operation. / 本操作使用的“meta”参数值。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     *
     * @throws \RuntimeException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    public static function inspect(int $salesUserId,string $platform,string $title,array $meta,?array $platformAccount=null): array
    {
        if(!self::ready()){throw new \RuntimeException('Run the v0.1.70 duplicate-comparison migration before submitting posts.');}
        // EN: Verification intentionally uses only the first listing image.
        // 中文：帖子验证明确只使用第一张 Listing 图片，其余图片不参与查重。
        $assets=[];$warnings=[];$urls=array_slice(ImageFingerprint::urls($meta),0,1);$started=microtime(true);
        foreach($urls as $url){
            if(microtime(true)-$started>20){$warnings[]='Image comparison time limit reached; some photos were not checked.';break;}
            try{$assets[]=ImageFingerprint::fromUrl($url);}catch(\Throwable $e){
                \App\Core\Logger::exception($e, 'duplicate-index', ['event' => 'Image comparison failed'], 'warning');
                $warnings[]='A listing image could not be checked. Image comparison is incomplete.';
            }
        }
        if(!$urls){$warnings[]='No listing image was returned; image comparison could not be completed.';}
        $report=self::compare($salesUserId,$platform,$title,$assets,$platformAccount);
        $report['warnings']=array_values(array_unique(array_merge($warnings,$report['warnings'])));
        $report['assets']=$assets;
        return $report;
    }

    /**
     * EN: Calculate or compare the compare operation implemented by duplicate index.
     * 中文：计算或比较 duplicate index 实现的“compare”操作。
     *
     * @param string $platform Platform value used by this operation. / 本操作使用的“platform”参数值。
     * @param string $title Title value used by this operation. / 本操作使用的“title”参数值。
     * @param array $assets Assets value used by this operation. / 本操作使用的“assets”参数值。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
    public static function compare(int $salesUserId,string $platform,string $title,array $assets,?array $platformAccount=null): array
    {
        $pdo=Database::connection();$warnings=[];$matches=[];$blocked=null;
        $platformScope=strtolower(trim($platform));
        $hasStableAccount=MarketplaceAccount::hasStableIdentity($platformAccount);
        $accountLabel=MarketplaceAccount::label($platformAccount);

        // V0.2.72 adds a second marketplace duplicate scope when the provider
        // API actually identifies the external posting account. This scope is
        // platform + external account (across internal Sales users), because two
        // Sales users posting through the same marketplace account are still
        // reusing content on that same public account.
        if($hasStableAccount && trim($title)!==''){
            $row=self::findMarketplaceAccountTitle($platformScope,$title,$platformAccount);
            if($row){
                $blocked='Duplicate — this '.$platform.' account'.($accountLabel!==''?' ('.$accountLabel.')':'').' already used this exact title.';
                $matches[]=[
                    'kind'=>'same_account_title',
                    'post_id'=>(int)$row['id'],
                    'url'=>$row['canonical_url'],
                    'title'=>$row['title'],
                    'platform'=>$platformScope,
                    'platform_account_name'=>$row['platform_account_name']??null,
                    'platform_account_id'=>$row['platform_account_id']??null,
                ];
            }
        }

        foreach($assets as $asset){
            if(!$blocked && $hasStableAccount){
                // V0.2.77: a stable provider/API account owns marketplace image
                // duplicate scope. Account equality is centralized and matches stable
                // account ID OR normalized profile URL. Different public accounts
                // never fall through to the legacy Sales/platform image rule.
                $row=self::findMarketplaceAccountImage($platformScope,(string)$asset['sha256'],$platformAccount);
                if($row){
                    $blocked='Duplicate — this '.$platform.' account'.($accountLabel!==''?' ('.$accountLabel.')':'').' already used this exact image.';
                    $matches[]=[
                        'kind'=>'same_account_image',
                        'post_id'=>(int)$row['id'],
                        'url'=>$row['canonical_url'],
                        'title'=>$row['title'],
                        'platform'=>$platformScope,
                        'platform_account_name'=>$row['platform_account_name']??null,
                        'platform_account_id'=>$row['platform_account_id']??null,
                    ];
                }
            } elseif(!$blocked) {
                // No stable account was returned by the API. Preserve the legacy
                // fallback scope: current Sales user + current marketplace platform.
                $row=self::findOwnPlatformExactImage($pdo,$salesUserId,$platformScope,(string)$asset['sha256']);
                if($row){
                    $blocked='Image duplicate — you already used this exact image on '.$platform.' ('.$row['title'].').';
                    $matches[]=['kind'=>'same_platform_image','post_id'=>(int)$row['id'],'url'=>$row['canonical_url'],'title'=>$row['title'],'platform'=>$platformScope];
                }
            }
            if($blocked){break;}
        }
        // EN: Similar/perceptual-image matches are intentionally ignored.
        // Only an identical SHA-256 image file is considered a duplicate.
        // 中文：不再使用感知哈希/相似图片阻止或警告；只有 SHA-256 完全一致才算图片重复。
        $unindexed=0;
        if($hasStableAccount){
            $unindexed=self::countUnindexedForAccount($pdo,$platformScope,$platformAccount);
            if($unindexed){
                $warnings[]=$unindexed.' existing '.$platform.' posts from this account have no image fingerprint; account image comparison is incomplete.';
            }
        } else {
            $q=$pdo->prepare("SELECT COUNT(*) FROM cdsp_sales_posts p WHERE p.sales_user_id=? AND LOWER(p.platform)=? AND p.deleted_at IS NULL AND NOT EXISTS (SELECT 1 FROM cdsp_post_image_fingerprints f WHERE f.post_id=p.id)");
            $q->execute([$salesUserId,$platformScope]);
            $unindexed=(int)$q->fetchColumn();
            if($unindexed){$warnings[]=$unindexed.' existing '.$platform.' posts have no image fingerprint; historical image comparison is incomplete.';}
        }
        $website=(int)$pdo->query('SELECT COUNT(*) FROM cdsp_website_references')->fetchColumn();
        if(!$website){$warnings[]='Website comparison is not configured: import the company website product CSV in Settings.';}
        else{
            // EN: Company website title comparison is literal. An exact title is a hard duplicate.
            // 中文：公司官网标题使用完全一致比较；标题完全相同则直接判重复。
            if(!$blocked){
                $q=$pdo->prepare('SELECT page_url,title FROM cdsp_website_references WHERE BINARY title=BINARY ? LIMIT 1');
                $q->execute([$title]);
                if($row=$q->fetch()){
                    $blocked='This exact title already exists on the company website ('.$row['title'].').';
                    $matches[]=['kind'=>'website_exact_title','url'=>$row['page_url'],'title'=>$row['title'],'platform'=>'website'];
                }
            }
            // EN: Website image comparison is exact SHA-256 only. Similar dHash images are ignored.
            // 中文：官网图片只比较 SHA-256 完全一致；相似 dHash 图片不提示、不阻止。
            if(!$blocked){
                foreach($assets as $asset){
                    $q=$pdo->prepare('SELECT page_url,title FROM cdsp_website_references WHERE sha256=? LIMIT 1');
                    $q->execute([$asset['sha256']]);
                    if($row=$q->fetch()){
                        $blocked='This exact image already exists on the company website ('.$row['title'].').';
                        $matches[]=['kind'=>'website_exact_image','url'=>$row['page_url'],'title'=>$row['title'],'platform'=>'website'];
                        break;
                    }
                }
            }
            $pending=(int)$pdo->query("SELECT COUNT(*) FROM cdsp_website_references WHERE sha256 IS NULL")->fetchColumn();
            if($pending){$warnings[]=$pending.' website images still need indexing; website image comparison is incomplete.';}
        }
        return ['blocked'=>$blocked,'warnings'=>$warnings,'matches'=>$matches,'website_count'=>$website,'unindexed_posts'=>$unindexed];
    }


    /**
     * Find an exact marketplace title only when the saved Post belongs to the
     * same stable external platform account. Account equality is centralized in
     * MarketplaceAccount and matches stable ID OR normalized profile URL.
     */
    public static function findMarketplaceAccountTitle(
        string $platform,
        string $title,
        ?array $platformAccount
    ): ?array {
        $platform=strtolower(trim($platform));
        $title=trim($title);
        if($platform==='' || $title==='' || !MarketplaceAccount::hasStableIdentity($platformAccount)){
            return null;
        }
        $q=Database::connection()->prepare(
            'SELECT id,title,canonical_url,platform_account_id,platform_account_name,platform_account_url,platform_account_key_hash
             FROM cdsp_sales_posts
             WHERE LOWER(platform)=?
               AND BINARY title=BINARY ?
               AND deleted_at IS NULL
             ORDER BY id DESC'
        );
        $q->execute([$platform,$title]);
        while($row=$q->fetch()){
            if(MarketplaceAccount::sameStoredAccount($platformAccount,$row)){
                return $row;
            }
        }
        return null;
    }

    /** Find an exact first image only on the same stable external account. */
    public static function findMarketplaceAccountImage(
        string $platform,
        string $sha256,
        ?array $platformAccount
    ): ?array {
        $platform=strtolower(trim($platform));
        $sha256=strtolower(trim($sha256));
        if($platform==='' || !preg_match('/^[a-f0-9]{64}$/',$sha256)
            || !MarketplaceAccount::hasStableIdentity($platformAccount)){
            return null;
        }
        $q=Database::connection()->prepare(
            'SELECT p.id,p.title,p.canonical_url,p.platform_account_id,p.platform_account_name,
                    p.platform_account_url,p.platform_account_key_hash
             FROM cdsp_post_image_fingerprints f
             JOIN cdsp_sales_posts p ON p.id=f.post_id
             WHERE LOWER(p.platform)=?
               AND p.deleted_at IS NULL
               AND f.sha256=?
             ORDER BY p.id DESC'
        );
        $q->execute([$platform,$sha256]);
        while($row=$q->fetch()){
            if(MarketplaceAccount::sameStoredAccount($platformAccount,$row)){
                return $row;
            }
        }
        return null;
    }

    /** Count unindexed posts only inside the same stable external account scope. */
    private static function countUnindexedForAccount(
        \PDO $pdo,
        string $platform,
        ?array $platformAccount
    ): int {
        if(!MarketplaceAccount::hasStableIdentity($platformAccount)){
            return 0;
        }
        $q=$pdo->prepare(
            'SELECT p.platform_account_id,p.platform_account_url,p.platform_account_key_hash
             FROM cdsp_sales_posts p
             WHERE LOWER(p.platform)=?
               AND p.deleted_at IS NULL
               AND NOT EXISTS (
                   SELECT 1 FROM cdsp_post_image_fingerprints f WHERE f.post_id=p.id
               )'
        );
        $q->execute([strtolower(trim($platform))]);
        $count=0;
        while($row=$q->fetch()){
            if(MarketplaceAccount::sameStoredAccount($platformAccount,$row)){
                $count++;
            }
        }
        return $count;
    }

    /**
     * Find an exact first-image fingerprint only inside one Sales user's own
     * marketplace-platform history. This helper deliberately contains both
     * scope keys in the SQL so future refactors cannot silently broaden image
     * duplicate checks across Sales users or marketplace platforms.
     */
    private static function findOwnPlatformExactImage(\PDO $pdo,int $salesUserId,string $platform,string $sha256): ?array
    {
        if($salesUserId<=0 || $platform==='' || $sha256===''){return null;}
        $q=$pdo->prepare(
            'SELECT p.id,p.sales_user_id,p.title,p.canonical_url
             FROM cdsp_post_image_fingerprints f
             JOIN cdsp_sales_posts p ON p.id=f.post_id
             WHERE p.sales_user_id=?
               AND LOWER(p.platform)=?
               AND p.deleted_at IS NULL
               AND f.sha256=?
             LIMIT 1'
        );
        $q->execute([$salesUserId,$platform,$sha256]);
        return $q->fetch()?:null;
    }

    /**
     * EN: Create or store the store post operation implemented by duplicate index.
     * 中文：创建或保存 duplicate index 实现的“store post”操作。
     *
     * @param int $postId Sales post identifier. / 销售 Post ID。
     * @param array $assets Assets value used by this operation. / 本操作使用的“assets”参数值。
     *
     * @return void No value is returned. / 无返回值。
     */
    public static function storePost(int $postId,array $assets): void
    {
        if(!$assets){return;}
        $q=Database::connection()->prepare('INSERT INTO cdsp_post_image_fingerprints(post_id,image_url,image_url_hash,sha256,dhash,checked_at) VALUES(?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE sha256=VALUES(sha256),dhash=VALUES(dhash),checked_at=NOW()');
        foreach($assets as $a){$q->execute([$postId,$a['url'],hash('sha256',$a['url']),$a['sha256'],$a['dhash']??null]);}
    }

    /**
     * Replace the saved Post image index after Admin Refresh Content. Old image
     * fingerprints must never survive after the Post's first image changes, or
     * future duplicate checks can report a false match against stale content.
     */
    public static function replacePostFingerprints(int $postId,array $assets): void
    {
        $pdo=Database::connection();
        $delete=$pdo->prepare('DELETE FROM cdsp_post_image_fingerprints WHERE post_id=?');
        $delete->execute([$postId]);
        self::storePost($postId,$assets);
    }

    /**
     * EN: Perform the website stats operation implemented by duplicate index.
     * 中文：执行 duplicate index 实现的“website stats”操作。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
    public static function websiteStats(): array
    {
        if(!self::ready()){return ['ready'=>false,'total'=>0,'pending'=>0];}
        $row=Database::connection()->query("SELECT COUNT(*) total,COALESCE(SUM(image_url IS NOT NULL AND image_url<>'' AND sha256 IS NULL),0) pending,MAX(imported_at) imported_at FROM cdsp_website_references")->fetch();
        return ['ready'=>true]+$row;
    }
}
