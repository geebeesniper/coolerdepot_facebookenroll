<?php
/**
 * File / 文件：app/Models/VerificationQueue.php
 * EN: Persists Sales Save & Wait / Bulk verification queue records and history.
 * 中文：保存 Sales 的 Save & Wait / Bulk 后台验证队列及历史记录。
 */
namespace App\Models;

use App\Core\Database;
use App\Core\Util;
use App\Services\PlatformUrl;

final class VerificationQueue
{
    private const ACTIVE = ['waiting','verifying'];
    private const NEEDS_ACTION = ['failed','duplicate','invalid'];
    private static ?bool $tableReady=null;


    private static function tableReady():bool
    {
        if(self::$tableReady!==null)return self::$tableReady;
        try{
            $s=Database::connection()->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='cdsp_post_verification_queue' LIMIT 1");
            $s->execute();
            return self::$tableReady=(bool)$s->fetchColumn();
        }catch(\Throwable $e){return self::$tableReady=false;}
    }

    public static function listForSales(int $salesUserId, string $filter='all', int $limit=100): array
    {
        $limit=max(1,min(200,$limit));
        $where='sales_user_id=?';
        $params=[$salesUserId];
        $filter=strtolower(trim($filter));
        if(in_array($filter,['waiting','verifying','passed','failed','duplicate','invalid'],true)){
            $where.=' AND status=?';$params[]=$filter;
        }elseif($filter==='needs_action'){
            $where.=" AND status IN ('failed','duplicate','invalid')";
        }else{
            // V0.2.106: the default Queue view is operational, not an archive.
            // Passed rows have already been promoted to formal Posts; keep them
            // available only under the dedicated Passed filter/history view.
            $where.=" AND status<>'passed'";
        }
        $stmt=Database::connection()->prepare(
            "SELECT id,sales_user_id,platform,submitted_url,canonical_url,external_post_id,status,attempt_count,
                    result_title,result_published_at,result_published_date,result_image_url,result_platform_account_name,
                    failure_code,failure_message,duplicate_url,duplicate_kind,post_id,created_at,queued_at,started_at,finished_at,updated_at
             FROM cdsp_post_verification_queue
             WHERE {$where}
             ORDER BY FIELD(status,'verifying','waiting','failed','invalid','duplicate','passed'), updated_at DESC, id DESC
             LIMIT {$limit}"
        );
        $stmt->execute($params);
        return $stmt->fetchAll()?:[];
    }

    public static function countsForSales(int $salesUserId): array
    {
        $stmt=Database::connection()->prepare(
            "SELECT status,COUNT(*) c FROM cdsp_post_verification_queue WHERE sales_user_id=? GROUP BY status"
        );
        $stmt->execute([$salesUserId]);
        $counts=['all'=>0,'waiting'=>0,'verifying'=>0,'passed'=>0,'failed'=>0,'duplicate'=>0,'invalid'=>0,'needs_action'=>0];
        foreach($stmt->fetchAll()?:[] as $row){
            $status=(string)$row['status'];$count=(int)$row['c'];
            if(isset($counts[$status])){$counts[$status]=$count;}
            // V0.2.106: All represents the active/actionable Verification Queue.
            // Passed records are already counted Posts and live under Passed history.
            if($status!=='passed')$counts['all']+=$count;
        }
        $counts['needs_action']=$counts['failed']+$counts['duplicate']+$counts['invalid'];
        return $counts;
    }

    /**
     * EN: Read queue counters and visible rows from one database snapshot so the UI
     * cannot receive "Waiting 1" together with an empty list while a worker changes
     * the row between two separate SELECT statements.
     * 中文：在同一个数据库快照中读取计数和列表，避免 Worker 恰好在两次查询之间
     * 修改状态，导致界面出现“Waiting 1”但列表为空。
     *
     * @return array{counts:array,items:array}
     */
    public static function snapshotForSales(int $salesUserId,string $filter='all',int $limit=100):array
    {
        $pdo=Database::connection();
        $ownsTransaction=!$pdo->inTransaction();
        try{
            if($ownsTransaction)$pdo->beginTransaction();
            $counts=self::countsForSales($salesUserId);
            $items=self::listForSales($salesUserId,$filter,$limit);
            if($ownsTransaction&&$pdo->inTransaction())$pdo->commit();
            return ['counts'=>$counts,'items'=>$items];
        }catch(\Throwable $e){
            if($ownsTransaction&&$pdo->inTransaction())$pdo->rollBack();
            throw $e;
        }
    }

    public static function historyForSales(int $salesUserId,int $queueId,int $limit=30):array
    {
        $limit=max(1,min(100,$limit));
        $stmt=Database::connection()->prepare(
            "SELECT h.id,h.event_type,h.from_status,h.to_status,h.message,h.created_at
             FROM cdsp_post_verification_queue_history h
             INNER JOIN cdsp_post_verification_queue q ON q.id=h.queue_id
             WHERE q.id=? AND q.sales_user_id=?
             ORDER BY h.id DESC LIMIT {$limit}"
        );
        $stmt->execute([$queueId,$salesUserId]);
        return $stmt->fetchAll()?:[];
    }

    /**
     * EN: Return a queued reservation that conflicts with a hard listing identity.
     * Platform Post ID is global; canonical URL fallback is scoped to the same Sales + platform.
     */
    public static function reservationDuplicate(
        int $salesUserId,string $platform,string $canonicalUrl,?string $externalId,?int $excludeId=null
    ):?array{
        $pdo=Database::connection();$platform=strtolower(trim($platform));
        if(!self::tableReady())return null;
        $exclude=$excludeId!==null?' AND id<>?':'';
        if($externalId!==null&&trim($externalId)!==''){
            $sql="SELECT id,sales_user_id,platform,canonical_url,external_post_id,status
                  FROM cdsp_post_verification_queue
                  WHERE platform=? AND external_post_id=? AND status IN ('waiting','verifying'){$exclude}
                  LIMIT 1";
            $params=[$platform,trim($externalId)];if($excludeId!==null)$params[]=$excludeId;
            $s=$pdo->prepare($sql);$s->execute($params);
            if($r=$s->fetch()){
                $r['reason']='This '.$platform.' Post ID is already waiting for verification.';
                $r['kind']='queue_external_id';return$r;
            }
        }
        if(trim($canonicalUrl)!==''){
            $sql="SELECT id,sales_user_id,platform,canonical_url,external_post_id,status
                  FROM cdsp_post_verification_queue
                  WHERE sales_user_id=? AND platform=? AND canonical_url_hash=? AND status IN ('waiting','verifying'){$exclude}
                  LIMIT 1";
            $params=[$salesUserId,$platform,Util::urlHash($canonicalUrl)];if($excludeId!==null)$params[]=$excludeId;
            $s=$pdo->prepare($sql);$s->execute($params);
            if($r=$s->fetch()){
                $r['reason']='This '.$platform.' URL is already in your verification queue.';
                $r['kind']='queue_url';return$r;
            }
        }
        return null;
    }

    /**
     * EN: Validate a submitted URL and return hard duplicate/reservation information without remote provider work.
     * 中文：只做 URL/平台及硬查重，不启动远程 Provider。
     */
    public static function preflightUrl(int $salesUserId,string $url,?int $excludeId=null):array
    {
        $platform=PlatformUrl::platformFor($url);
        if(!$platform)return ['ok'=>false,'failure_code'=>'INVALID_URL','message'=>'Unsupported URL. Use Facebook Marketplace, OfferUp, or Craigslist.'];
        $canonical=PlatformUrl::normalize($url,$platform);
        if(!$canonical)return ['ok'=>false,'failure_code'=>'INVALID_URL','message'=>'The post URL is malformed. Paste one complete listing URL.','platform'=>$platform];
        $externalId=PlatformUrl::externalId($platform,$canonical);
        $dup=Post::duplicate($salesUserId,$platform,$canonical,$externalId,null,null);
        if(!$dup)$dup=self::reservationDuplicate($salesUserId,$platform,$canonical,$externalId,$excludeId);
        return [
            'ok'=>$dup===null,
            'platform'=>$platform,
            'canonical_url'=>$canonical,
            'external_post_id'=>$externalId,
            'duplicate_url'=>$dup['canonical_url']??null,
            'duplicate_kind'=>$dup['kind']??null,
            'failure_code'=>$dup?'DUPLICATE_PREFLIGHT':null,
            'message'=>$dup['reason']??'Ready to add to the verification queue.',
        ];
    }

    /**
     * EN: Persist a preflight duplicate/invalid result so Sales can edit or delete it from Needs Action.
     * 中文：把预检阶段的重复/无效记录保存到 Needs Action，Sales 可修改或删除。
     */
    public static function recordPreflightIssue(int $salesUserId,string $url,array $preflight,?int $actorUserId=null):array
    {
        $platform=isset($preflight['platform'])&&in_array((string)$preflight['platform'],['facebook','offerup','craigslist'],true)
            ?(string)$preflight['platform']:null;
        $canonical=trim((string)($preflight['canonical_url']??''));
        $externalId=trim((string)($preflight['external_post_id']??''));
        $duplicate=trim((string)($preflight['duplicate_kind']??''))!==''||trim((string)($preflight['duplicate_url']??''))!==''||($preflight['failure_code']??'')==='DUPLICATE_PREFLIGHT';
        $status=$duplicate?'duplicate':'invalid';
        $code=$duplicate?'DUPLICATE_PREFLIGHT':'INVALID_URL';
        $message=trim((string)($preflight['message']??''))?:($duplicate?'Duplicate listing.':'Invalid listing URL.');
        $pdo=Database::connection();$pdo->beginTransaction();
        try{
            $s=$pdo->prepare(
                "INSERT INTO cdsp_post_verification_queue
                 (sales_user_id,platform,submitted_url,canonical_url,canonical_url_hash,external_post_id,status,attempt_count,
                  failure_code,failure_message,duplicate_url,duplicate_kind,created_at,queued_at,finished_at,updated_at)
                 VALUES(?,?,?,?,?,?,?,0,?,?,?,?,NOW(),NOW(),NOW(),NOW())"
            );
            $s->execute([
                $salesUserId,$platform,$url,$canonical!==''?$canonical:null,$canonical!==''?Util::urlHash($canonical):null,$externalId!==''?$externalId:null,$status,
                $code,$message,trim((string)($preflight['duplicate_url']??''))?:null,trim((string)($preflight['duplicate_kind']??''))?:null
            ]);
            $id=(int)$pdo->lastInsertId();
            self::history($id,$actorUserId,'preflight_'.$status,null,$status,$message.' This submission was not counted as a post.');
            $pdo->commit();
            return self::findOwned($salesUserId,$id)?:['id'=>$id,'status'=>$status];
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }

    /** @throws \DomainException */
    public static function enqueueUrl(int $salesUserId,string $url,?int $actorUserId=null):array
    {
        $platform=PlatformUrl::platformFor($url);
        if(!$platform)throw new \DomainException('Unsupported URL. Use Facebook Marketplace, OfferUp, or Craigslist.');
        $canonical=PlatformUrl::normalize($url,$platform);
        if(!$canonical)throw new \DomainException('The post URL is malformed. Paste one complete listing URL.');
        $externalId=PlatformUrl::externalId($platform,$canonical);
        return self::enqueueNormalized($salesUserId,$platform,$url,$canonical,$externalId,$actorUserId);
    }

    /** @throws \DomainException */
    private static function enqueueNormalized(
        int $salesUserId,string $platform,string $submittedUrl,string $canonicalUrl,?string $externalId,?int $actorUserId=null,?int $replaceId=null
    ):array{
        $pdo=Database::connection();
        $lockName='cdsp-vq-'.substr(hash('sha256',$platform.':'.($externalId?:Util::urlHash($canonicalUrl))),0,48);
        $lock=$pdo->prepare('SELECT GET_LOCK(?,10)');$lock->execute([$lockName]);
        if((int)$lock->fetchColumn()!==1)throw new \DomainException('Verification queue is busy. Please try again.');
        try{
            if($dup=Post::duplicate($salesUserId,$platform,$canonicalUrl,$externalId,null,null)){
                throw new \DomainException($dup['reason']);
            }
            if($dup=self::reservationDuplicate($salesUserId,$platform,$canonicalUrl,$externalId,$replaceId)){
                throw new \DomainException($dup['reason']);
            }
            $pdo->beginTransaction();
            if($replaceId!==null){
                $get=$pdo->prepare('SELECT * FROM cdsp_post_verification_queue WHERE id=? AND sales_user_id=? FOR UPDATE');
                $get->execute([$replaceId,$salesUserId]);$old=$get->fetch();
                if(!$old||!in_array((string)$old['status'],self::NEEDS_ACTION,true)){
                    throw new \DomainException('Only failed, duplicate, or invalid queue items can be edited and re-verified.');
                }
                $from=(string)$old['status'];
                $s=$pdo->prepare(
                    "UPDATE cdsp_post_verification_queue
                     SET platform=?,submitted_url=?,canonical_url=?,canonical_url_hash=?,external_post_id=?,status='waiting',
                         worker_token=NULL,failure_code=NULL,failure_message=NULL,duplicate_url=NULL,duplicate_kind=NULL,
                         result_title=NULL,result_description=NULL,result_published_at=NULL,result_published_date=NULL,
                         result_image_url=NULL,result_platform_account_name=NULL,result_json=NULL,post_id=NULL,
                         queued_at=NOW(),started_at=NULL,finished_at=NULL,updated_at=NOW()
                     WHERE id=? AND sales_user_id=?"
                );
                $s->execute([$platform,$submittedUrl,$canonicalUrl,Util::urlHash($canonicalUrl),$externalId?:null,$replaceId,$salesUserId]);
                self::history($replaceId,$actorUserId,'edited_requeued',$from,'waiting','URL edited and queued for re-verification.');
                $id=$replaceId;
            }else{
                $s=$pdo->prepare(
                    "INSERT INTO cdsp_post_verification_queue
                     (sales_user_id,platform,submitted_url,canonical_url,canonical_url_hash,external_post_id,status,attempt_count,created_at,queued_at,updated_at)
                     VALUES(?,?,?,?,?,?,'waiting',0,NOW(),NOW(),NOW())"
                );
                $s->execute([$salesUserId,$platform,$submittedUrl,$canonicalUrl,Util::urlHash($canonicalUrl),$externalId?:null]);
                $id=(int)$pdo->lastInsertId();
                self::history($id,$actorUserId,'queued',null,'waiting','Saved to the background verification queue.');
            }
            $pdo->commit();
            return self::findOwned($salesUserId,$id)?:['id'=>$id,'status'=>'waiting'];
        }catch(\Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();throw$e;
        }finally{
            try{$r=$pdo->prepare('SELECT RELEASE_LOCK(?)');$r->execute([$lockName]);}catch(\Throwable $ignore){}
        }
    }

    /** @throws \DomainException */
    public static function editAndRequeue(int $salesUserId,int $id,string $url,int $actorUserId):array
    {
        $platform=PlatformUrl::platformFor($url);
        if(!$platform)throw new \DomainException('Unsupported URL. Use Facebook Marketplace, OfferUp, or Craigslist.');
        $canonical=PlatformUrl::normalize($url,$platform);
        if(!$canonical)throw new \DomainException('The post URL is malformed. Paste one complete listing URL.');
        $externalId=PlatformUrl::externalId($platform,$canonical);
        return self::enqueueNormalized($salesUserId,$platform,$url,$canonical,$externalId,$actorUserId,$id);
    }

    /** @throws \DomainException */
    public static function retry(int $salesUserId,int $id,int $actorUserId):array
    {
        $row=self::findOwned($salesUserId,$id);
        if(!$row||!in_array((string)$row['status'],self::NEEDS_ACTION,true))throw new \DomainException('Only failed or duplicate queue items can be retried.');
        return self::editAndRequeue($salesUserId,$id,(string)$row['submitted_url'],$actorUserId);
    }

    /** @throws \DomainException */
    public static function deleteOwned(int $salesUserId,int $id):void
    {
        $pdo=Database::connection();$pdo->beginTransaction();
        try{
            $s=$pdo->prepare('SELECT status FROM cdsp_post_verification_queue WHERE id=? AND sales_user_id=? FOR UPDATE');$s->execute([$id,$salesUserId]);
            $status=(string)($s->fetchColumn()?:'');
            // V0.2.109: Passed queue rows are acknowledgements only. The formal Post
            // already exists, so Sales may clear the queue row without deleting the Post.
            if($status===''||!in_array($status,['waiting','passed','failed','duplicate','invalid'],true))throw new \DomainException('Only waiting, passed, failed, duplicate, or invalid queue items can be deleted.');
            $d=$pdo->prepare('DELETE FROM cdsp_post_verification_queue WHERE id=? AND sales_user_id=?');$d->execute([$id,$salesUserId]);
            $pdo->commit();
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }

    public static function findOwned(int $salesUserId,int $id):?array
    {
        $s=Database::connection()->prepare('SELECT * FROM cdsp_post_verification_queue WHERE id=? AND sales_user_id=? LIMIT 1');
        $s->execute([$id,$salesUserId]);return$s->fetch()?:null;
    }

    public static function hasWaiting():bool
    {
        return (bool)Database::connection()->query("SELECT 1 FROM cdsp_post_verification_queue WHERE status='waiting' LIMIT 1")->fetchColumn();
    }

    public static function claimNext(string $workerToken):?array
    {
        $pdo=Database::connection();$pdo->beginTransaction();
        try{
            $row=$pdo->query("SELECT * FROM cdsp_post_verification_queue WHERE status='waiting' ORDER BY queued_at ASC,id ASC LIMIT 1 FOR UPDATE")->fetch();
            if(!$row){$pdo->commit();return null;}
            $s=$pdo->prepare("UPDATE cdsp_post_verification_queue SET status='verifying',worker_token=?,attempt_count=attempt_count+1,started_at=NOW(),finished_at=NULL,updated_at=NOW() WHERE id=? AND status='waiting'");
            $s->execute([$workerToken,(int)$row['id']]);
            if($s->rowCount()!==1){$pdo->rollBack();return null;}
            self::history((int)$row['id'],null,'worker_started','waiting','verifying','Background verification started.');
            $pdo->commit();
            $row['status']='verifying';$row['worker_token']=$workerToken;$row['attempt_count']=(int)$row['attempt_count']+1;
            return$row;
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }

    public static function markPassed(int $id,string $workerToken,int $postId,array $result):void
    {
        self::finish($id,$workerToken,'passed',$result,null,null,null,null,$postId,'Verification passed. Post counted and saved.');
    }
    public static function markFailed(int $id,string $workerToken,array $result,string $code,string $message):void
    {
        self::finish($id,$workerToken,'failed',$result,$code,$message,null,null,null,'Verification failed. This submission was not counted as a post.');
    }
    public static function markDuplicate(int $id,string $workerToken,array $result,string $code,string $message,?string $url,?string $kind):void
    {
        self::finish($id,$workerToken,'duplicate',$result,$code,$message,$url,$kind,null,'Duplicate found. This submission was not counted as a post.');
    }

    private static function finish(int $id,string $workerToken,string $status,array $result,?string $code,?string $message,?string $dupUrl,?string $dupKind,?int $postId,string $historyMessage):void
    {
        $meta=is_array($result['raw_meta']??null)?$result['raw_meta']:[];
        $images=\App\Services\ImageFingerprint::urls($meta);
        $account=is_array($meta['platform_account']??null)?$meta['platform_account']:[];
        $pdo=Database::connection();$pdo->beginTransaction();
        try{
            $s=$pdo->prepare(
                "UPDATE cdsp_post_verification_queue SET status=?,result_title=?,result_description=?,result_published_at=?,result_published_date=?,
                    result_image_url=?,result_platform_account_name=?,failure_code=?,failure_message=?,duplicate_url=?,duplicate_kind=?,result_json=?,post_id=?,
                    worker_token=NULL,finished_at=NOW(),updated_at=NOW()
                 WHERE id=? AND status='verifying' AND worker_token=?"
            );
            $s->execute([$status,$result['title']??null,$result['description']??null,$result['published_at']??null,$result['published_date']??null,
                $images[0]??null,trim((string)($account['name']??''))?:null,$code,$message,$dupUrl,$dupKind,
                json_encode($result,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE),$postId,$id,$workerToken]);
            if($s->rowCount()===1)self::history($id,null,'worker_finished','verifying',$status,$historyMessage.($message?' '.$message:''));
            $pdo->commit();
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }

    public static function recoverStale(int $minutes=15):int
    {
        $minutes=max(5,min(120,$minutes));
        $pdo=Database::connection();
        $ids=$pdo->query("SELECT id FROM cdsp_post_verification_queue WHERE status='verifying' AND started_at<DATE_SUB(NOW(),INTERVAL {$minutes} MINUTE)")->fetchAll();
        $s=$pdo->prepare("UPDATE cdsp_post_verification_queue SET status='waiting',worker_token=NULL,queued_at=NOW(),updated_at=NOW(),failure_code='STALE_WORKER_RECOVERED',failure_message='A stale worker was recovered and this item was queued again.' WHERE status='verifying' AND started_at<DATE_SUB(NOW(),INTERVAL {$minutes} MINUTE)");
        $s->execute();
        foreach($ids?:[] as $row)self::history((int)$row['id'],null,'stale_recovered','verifying','waiting','Stale verification worker recovered; item re-queued.');
        return$s->rowCount();
    }

    private static function history(int $queueId,?int $actorUserId,string $event,?string $from,?string $to,?string $message):void
    {
        $s=Database::connection()->prepare("INSERT INTO cdsp_post_verification_queue_history(queue_id,actor_user_id,event_type,from_status,to_status,message,created_at) VALUES(?,?,?,?,?,?,NOW())");
        $s->execute([$queueId,$actorUserId,$event,$from,$to,$message]);
    }
}
