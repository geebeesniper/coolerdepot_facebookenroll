<?php
/**
 * File / 文件：app/Services/WebsiteScanJob.php
 * EN: Persists company website product-scan progress so refreshes, collapsed UI,
 *     or duplicate browser tabs do not lose the crawl queue.
 * 中文：持久化公司网站产品扫描进度，使页面刷新、收起扫描面板或多个浏览器标签
 *      不会丢失扫描队列。
 */
namespace App\Services;

use App\Core\Database;

class WebsiteScanJob
{
    private const MAX_PAGES = 5000;
    private const STEP_SIZE = 1;
    private const STALE_AFTER_SECONDS = 55;
    /** Avoid repeating CREATE/SHOW/ALTER schema probes inside the same AJAX request. */
    private static bool $schemaReady = false;

    /** Ensure the persistent scan-job table exists. / 确保持久化扫描任务表存在。 */
    public static function ensureTable(): void
    {
        if(self::$schemaReady){return;}
        $pdo=Database::connection();
        $tableCount=(int)$pdo->query(
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema=DATABASE()
               AND table_name IN ('cdsp_website_scan_jobs','cdsp_website_scan_errors')"
        )->fetchColumn();
        if($tableCount===2){
            WebsiteActivityHistory::ensureTable();
            self::$schemaReady=true;
            return;
        }
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS cdsp_website_scan_jobs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                history_id BIGINT UNSIGNED NULL,
                source_host VARCHAR(191) NOT NULL,
                website_url TEXT NOT NULL,
                status ENUM('running','completed','paused','stopped','failed') NOT NULL DEFAULT 'running',
                queue_json MEDIUMTEXT NOT NULL,
                seen_json MEDIUMTEXT NOT NULL,
                checked INT UNSIGNED NOT NULL DEFAULT 0,
                products INT UNSIGNED NOT NULL DEFAULT 0,
                images_found INT UNSIGNED NOT NULL DEFAULT 0,
                indexed INT UNSIGNED NOT NULL DEFAULT 0,
                failed INT UNSIGNED NOT NULL DEFAULT 0,
                skipped_existing INT UNSIGNED NOT NULL DEFAULT 0,
                last_error TEXT NULL,
                started_by BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                finished_at DATETIME NULL,
                PRIMARY KEY (id),
                KEY idx_cdsp_website_scan_host (source_host,id),
                KEY idx_cdsp_website_scan_history (history_id,id),
                KEY idx_cdsp_website_scan_status (status,updated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        WebsiteActivityHistory::ensureTable();
        $column=$pdo->query("SHOW COLUMNS FROM cdsp_website_scan_jobs LIKE 'history_id'")->fetch();
        if(!$column){
            $pdo->exec("ALTER TABLE cdsp_website_scan_jobs ADD COLUMN history_id BIGINT UNSIGNED NULL AFTER id");
        }
        $skipColumn=$pdo->query("SHOW COLUMNS FROM cdsp_website_scan_jobs LIKE 'skipped_existing'")->fetch();
        if(!$skipColumn){
            $pdo->exec("ALTER TABLE cdsp_website_scan_jobs ADD COLUMN skipped_existing INT UNSIGNED NOT NULL DEFAULT 0 AFTER failed");
        }
        $statusColumn=$pdo->query("SHOW COLUMNS FROM cdsp_website_scan_jobs LIKE 'status'")->fetch();
        if($statusColumn && strpos((string)($statusColumn['Type']??''), "'paused'")===false){
            $pdo->exec("ALTER TABLE cdsp_website_scan_jobs MODIFY COLUMN status ENUM('running','completed','paused','stopped','failed') NOT NULL DEFAULT 'running'");
        }

        // v0.2.80: keep every run queue instead of overwriting one job per host.
        // Each History row owns its own resumable job; only RUNNING is globally exclusive.
        $indexes=$pdo->query('SHOW INDEX FROM cdsp_website_scan_jobs')->fetchAll()?:[];
        $indexNames=[];
        foreach($indexes as $index){$indexNames[(string)($index['Key_name']??'')]=(int)($index['Non_unique']??1);}
        if(isset($indexNames['uq_cdsp_website_scan_host'])){
            $pdo->exec('ALTER TABLE cdsp_website_scan_jobs DROP INDEX uq_cdsp_website_scan_host');
            unset($indexNames['uq_cdsp_website_scan_host']);
        }
        if(!isset($indexNames['idx_cdsp_website_scan_host'])){
            $pdo->exec('ALTER TABLE cdsp_website_scan_jobs ADD KEY idx_cdsp_website_scan_host (source_host,id)');
        }
        if(!isset($indexNames['idx_cdsp_website_scan_history'])){
            $pdo->exec('ALTER TABLE cdsp_website_scan_jobs ADD KEY idx_cdsp_website_scan_history (history_id,id)');
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS cdsp_website_scan_errors (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                source_host VARCHAR(191) NOT NULL,
                page_url TEXT NOT NULL,
                http_status SMALLINT UNSIGNED NULL,
                error_message TEXT NOT NULL,
                explanation TEXT NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_cdsp_website_scan_error_host (source_host,created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        self::$schemaReady=true;
    }

    /**
     * Start or resume one source scan. Only one website scan may actively run
     * at a time on this installation. This keeps crawler AJAX traffic and
     * remote HTTP work from multiplying into PHP/Apache 502/503 pressure.
     * 启动/恢复网站扫描。全站同一时间只允许一个网站处于 running，避免并行爬取
     * 放大 PHP/Apache 压力并触发 502/503。
     */
    public static function start(string $website, int $adminId): array
    {
        self::ensureTable();
        $pdo=Database::connection();
        $globalLock='cdsp-webscan-global-start';

        // Start must never sit on a 5-second advisory-lock wait. The user needs
        // an immediate History run or an immediate busy response.
        $lock=$pdo->prepare('SELECT GET_LOCK(?,0)');
        $lock->execute([$globalLock]);
        if((int)$lock->fetchColumn()!==1){throw new \DomainException('Website scanner is busy. Try again in a moment.');}

        try{
            // Clean abandoned jobs once, then do the running check directly.
            // Avoid runningHosts() here because that used to repeat schema work
            // and stale-job scans before a new History row could be returned.
            self::pauseStaleJobs();
            $runningRow=$pdo->query("SELECT source_host FROM cdsp_website_scan_jobs WHERE status='running' ORDER BY updated_at ASC,id ASC LIMIT 1")->fetch();
            if($runningRow){
                throw new \DomainException('Another website is currently scanning: '.(string)$runningRow['source_host'].'. Stop or pause it before starting a new scan.');
            }

            $source=WebsiteCatalog::ensureSource($website,$adminId);
            $host=(string)$source['host'];
            $websiteUrl=(string)$source['url'];
            $queue=WebsiteCatalog::productScanSeeds($websiteUrl);

            $clear=$pdo->prepare('DELETE FROM cdsp_website_scan_errors WHERE source_host=?');
            $clear->execute([$host]);

            // Persist the History run before any remote page request. Nothing in
            // this start path performs HTTP crawling. The browser receives the
            // run immediately and starts /scan-step separately.
            $historyId=WebsiteActivityHistory::begin($host,$websiteUrl,'product_scan',$adminId,'','Product scan started.');
            $q=$pdo->prepare(
                "INSERT INTO cdsp_website_scan_jobs
                 (history_id,source_host,website_url,status,queue_json,seen_json,checked,products,images_found,indexed,failed,skipped_existing,last_error,started_by,created_at,updated_at,finished_at)
                 VALUES(?,?,?,'running',?,'[]',0,0,0,0,0,0,NULL,?,NOW(),NOW(),NULL)"
            );
            $q->execute([$historyId,$host,$websiteUrl,self::json($queue),$adminId]);
            $jobId=(int)$pdo->lastInsertId();
            // Do not call statusByHistory() here. That path performs additional
            // stale/schema/status work and was the reason the browser could sit on
            // Starting… before the new History row appeared. Return the known fresh
            // state directly; the first scan-step owns all remote work.
            $now=date('Y-m-d H:i:s');
            return [
                'id'=>$jobId,
                'history_id'=>$historyId,
                'source_host'=>$host,
                'website_url'=>$websiteUrl,
                'status'=>'running',
                'checked'=>0,
                'products'=>0,
                'images_found'=>0,
                'indexed'=>0,
                'failed'=>0,
                'skipped_existing'=>0,
                'queue'=>count($queue),
                'seen'=>0,
                'next_url'=>(string)($queue[0]??''),
                'last_error'=>'',
                'created_at'=>$now,
                'updated_at'=>$now,
                'stale_seconds'=>0,
                'finished_at'=>'',
                'page_errors'=>[],
            ];
        }finally{
            try{$release=$pdo->prepare('SELECT RELEASE_LOCK(?)');$release->execute([$globalLock]);}catch(\Throwable $e){}
        }
    }

    /** Return one persisted job state. / 返回一个持久化扫描任务状态。 */
    public static function status(string $host): ?array
    {
        self::ensureTable();
        $host=strtolower(trim($host));
        if($host===''){return null;}
        self::pauseStaleJobs($host);
        $q=Database::connection()->prepare(
            "SELECT id,history_id,source_host,website_url,status,checked,products,images_found,indexed,failed,skipped_existing,
                    last_error,created_at,updated_at,finished_at,queue_json,seen_json,
                    GREATEST(0,TIMESTAMPDIFF(SECOND,updated_at,NOW())) AS stale_seconds
             FROM cdsp_website_scan_jobs WHERE source_host=?
             ORDER BY (status='running') DESC,id DESC LIMIT 1"
        );
        $q->execute([$host]);
        $row=$q->fetch();
        return $row?self::publicRow($row):null;
    }

    /** Exact persisted run selected from one Product Scan History row. */
    public static function statusByHistory(int $historyId): ?array
    {
        self::ensureTable();
        if($historyId<1){return null;}
        self::pauseStaleJobs();
        $q=Database::connection()->prepare(
            "SELECT id,history_id,source_host,website_url,status,checked,products,images_found,indexed,failed,skipped_existing,
                    last_error,created_at,updated_at,finished_at,queue_json,seen_json,
                    GREATEST(0,TIMESTAMPDIFF(SECOND,updated_at,NOW())) AS stale_seconds
             FROM cdsp_website_scan_jobs WHERE history_id=? LIMIT 1"
        );
        $q->execute([$historyId]);
        $row=$q->fetch();
        return $row?self::publicRow($row):null;
    }

    /** Return all hosts that are actively scanning. / 返回当前所有 running 网站。 */
    public static function runningHosts(): array
    {
        self::ensureTable();
        self::pauseStaleJobs();
        $rows=Database::connection()->query(
            "SELECT source_host FROM cdsp_website_scan_jobs WHERE status='running' ORDER BY updated_at ASC,id ASC"
        )->fetchAll();
        $hosts=[];
        foreach($rows?:[] as $row){
            $host=strtolower(trim((string)($row['source_host']??'')));
            if($host!==''&&!in_array($host,$hosts,true)){$hosts[]=$host;}
        }
        return $hosts;
    }

    /** History ids that can be continued. v0.2.81 can restart legacy paused runs too. */
    public static function resumableHistoryIds(): array
    {
        self::ensureTable();
        $rows=Database::connection()->query(
            "SELECT id AS history_id
             FROM cdsp_website_activity_history
             WHERE action='product_scan' AND status='paused'
             ORDER BY id DESC"
        )->fetchAll()?:[];
        $ids=[];
        foreach($rows as $row){$id=(int)($row['history_id']??0);if($id>0){$ids[$id]=true;}}
        return array_map('intval',array_keys($ids));
    }

    /** True when any website scan is currently active. / 任一网站正在扫描时返回 true。 */
    public static function hasRunning(): bool
    {
        return self::runningHosts()!==[];
    }

    /**
     * Convert abandoned "running" rows into resumable paused jobs.
     *
     * A browser tab can disappear, an AJAX request can be cut off, or PHP can
     * finish after the client has already disconnected. In those cases the DB
     * row used to remain "running" forever and blocked every scan/delete action.
     * We only auto-pause a stale row when the per-host MySQL advisory lock is
     * free, which means no scan step is currently executing for that host.
     */
    private static function pauseStaleJobs(?string $onlyHost=null): void
    {
        $pdo=Database::connection();
        $seconds=self::STALE_AFTER_SECONDS;
        $sql="SELECT * FROM cdsp_website_scan_jobs
              WHERE status='running'
                AND updated_at < DATE_SUB(NOW(), INTERVAL {$seconds} SECOND)";
        $params=[];
        if($onlyHost!==null){
            $onlyHost=strtolower(trim($onlyHost));
            if($onlyHost===''){return;}
            $sql.=' AND source_host=?';
            $params[]=$onlyHost;
        }
        $sql.=' ORDER BY updated_at ASC,id ASC';
        $q=$pdo->prepare($sql);
        $q->execute($params);
        $rows=$q->fetchAll()?:[];
        if(!$rows){return;}

        $lockCheck=$pdo->prepare('SELECT IS_FREE_LOCK(?)');
        $pause=$pdo->prepare(
            "UPDATE cdsp_website_scan_jobs
             SET status='paused',last_error=?,updated_at=NOW(),finished_at=NOW()
             WHERE id=? AND status='running' AND updated_at=?"
        );
        $message='Scan paused automatically because no progress was recorded for '.self::STALE_AFTER_SECONDS.' seconds. Use the Play control in Scan History to continue from the saved queue.';

        foreach($rows as $row){
            $host=strtolower(trim((string)($row['source_host']??'')));
            if($host===''){continue;}
            $lockName='cdsp-webscan-'.substr(hash('sha256',$host),0,40);
            $lockCheck->execute([$lockName]);
            // 1 = no scan step owns the lock. 0 = a real step is still active.
            if((int)$lockCheck->fetchColumn()!==1){continue;}

            $pause->execute([$message,(int)$row['id'],(string)$row['updated_at']]);
            if($pause->rowCount()!==1){continue;}

            $row['status']='paused';
            $row['last_error']=$message;
            $row['finished_at']=date('Y-m-d H:i:s');
            $row['stale_seconds']=0;
            self::syncHistory(self::publicRow($row),true);
        }
    }

    /** Delete persisted scan state for one removed website source. / 删除已移除网站的持久扫描状态。 */
    public static function remove(string $host): void
    {
        self::ensureTable();
        $host=strtolower(trim($host));
        $pdo=Database::connection();
        $q=$pdo->prepare('DELETE FROM cdsp_website_scan_jobs WHERE source_host=?');
        $q->execute([$host]);
        $e=$pdo->prepare('DELETE FROM cdsp_website_scan_errors WHERE source_host=?');
        $e->execute([$host]);
        WebsiteActivityHistory::removeHost($host);
    }

    /**
     * Pause a running job after the current short scan step finishes. Queue, seen URLs,
     * counters and history remain persisted. The Play control in Scan History resumes
     * this same run from the saved queue.
     * 暂停正在运行的任务；保留 queue / seen / counters / history，之后可从 History 的播放按钮继续。
     */
    public static function pause(string $host,int $historyId=0): ?array
    {
        return self::changeRunningState($host,$historyId,'paused','Scan paused by Admin. Use ▶ on this History row to continue.');
    }

    /** Backward-compatible alias for older callers that used stop() as pause(). */
    public static function stop(string $host,int $historyId=0): ?array
    {
        return self::pause($host,$historyId);
    }

    /**
     * Stop the current run permanently. Its history remains visible, but its saved
     * queue is not exposed as resumable; a later Scan Website starts a new history run.
     */
    public static function terminate(string $host,int $historyId=0): ?array
    {
        return self::changeRunningState($host,$historyId,'stopped','Scan stopped by Admin.');
    }

    /**
     * Continue one paused History run.
     * v0.2.80+ runs resume their exact persisted queue. Older History rows were
     * created before per-run queues existed, so v0.2.81 reconstructs a conservative
     * seed queue for that same History id instead of leaving every old Play icon dead.
     */
    public static function resume(string $host,int $historyId): ?array
    {
        self::ensureTable();
        $host=strtolower(trim($host));
        if($host===''||$historyId<1){return null;}
        $pdo=Database::connection();
        $globalLock='cdsp-webscan-global-start';
        $lock=$pdo->prepare('SELECT GET_LOCK(?,5)');
        $lock->execute([$globalLock]);
        if((int)$lock->fetchColumn()!==1){throw new \DomainException('Website scanner is busy. Try again in a moment.');}
        try{
            $running=self::runningHosts();
            if($running){throw new \DomainException('Another scan is already running: '.$running[0].'. Pause or stop it first.');}

            $job=$pdo->prepare(
                "SELECT id,status FROM cdsp_website_scan_jobs WHERE source_host=? AND history_id=? ORDER BY id DESC LIMIT 1"
            );
            $job->execute([$host,$historyId]);
            $existing=$job->fetch();

            if($existing){
                if((string)$existing['status']!=='paused'){
                    throw new \DomainException('That History run is not paused.');
                }
                $q=$pdo->prepare(
                    "UPDATE cdsp_website_scan_jobs SET status='running',last_error=NULL,updated_at=NOW(),finished_at=NULL
                     WHERE id=? AND status='paused'"
                );
                $q->execute([(int)$existing['id']]);
                if($q->rowCount()!==1){throw new \DomainException('That paused History run could not be continued.');}
            }else{
                $history=WebsiteActivityHistory::find($historyId);
                if(!$history
                    || strtolower(trim((string)($history['source_host']??'')))!==$host
                    || (string)($history['action']??'')!=='product_scan'
                    || (string)($history['status']??'')!=='paused'
                ){
                    throw new \DomainException('That paused History run is no longer available.');
                }

                $website=trim((string)($history['website_url']??''));
                if($website===''){throw new \DomainException('That History run has no website URL.');}
                $queue=WebsiteCatalog::productScanSeeds($website);
                $message=(string)($history['message']??'');
                $imagesFound=self::historyMetric($message,'First images found');
                $indexed=self::historyMetric($message,'Exact fingerprints');
                $skipped=self::historyMetric($message,'Existing URLs skipped');

                $q=$pdo->prepare(
                    "INSERT INTO cdsp_website_scan_jobs
                     (history_id,source_host,website_url,status,queue_json,seen_json,checked,products,images_found,indexed,failed,skipped_existing,last_error,started_by,created_at,updated_at,finished_at)
                     VALUES(?,?,?,'running',?,'[]',?,?,?,?,?,?,NULL,NULL,NOW(),NOW(),NULL)"
                );
                $q->execute([
                    $historyId,$host,$website,self::json($queue),
                    max(0,(int)($history['processed']??0)),
                    max(0,(int)($history['saved']??0)),
                    $imagesFound,$indexed,
                    max(0,(int)($history['failed']??0)),
                    $skipped,
                ]);
            }

            $state=self::statusByHistory($historyId);
            if($state){
                $message=$existing
                    ?'Scan resumed from saved queue.'
                    :'Legacy paused scan restarted from website entry points; previously saved products remain in the library and are skipped when rediscovered.';
                WebsiteActivityHistory::addScanItem($historyId,(int)$state['id'],(string)$state['website_url'],'running','run','','',false,false,$message);
                self::syncHistory($state,false);
            }
            return $state;
        }finally{
            try{$release=$pdo->prepare('SELECT RELEASE_LOCK(?)');$release->execute([$globalLock]);}catch(\Throwable $e){}
        }
    }

    private static function historyMetric(string $message,string $label): int
    {
        if($message===''||$label===''){return 0;}
        return preg_match('/'.preg_quote($label,'/').'\s+(\d+)/i',$message,$m)?max(0,(int)$m[1]):0;
    }

    private static function changeRunningState(string $host,int $historyId,string $targetStatus,string $message): ?array
    {
        self::ensureTable();
        $host=strtolower(trim($host));
        if($host===''){return null;}
        $pdo=Database::connection();
        $lockName='cdsp-webscan-'.substr(hash('sha256',$host),0,40);
        $lock=$pdo->prepare('SELECT GET_LOCK(?,35)');
        $lock->execute([$lockName]);
        if((int)$lock->fetchColumn()!==1){throw new \DomainException('The current page is still finishing. Try again in a moment.');}
        try{
            if($historyId>0){
                $find=$pdo->prepare("SELECT id,history_id FROM cdsp_website_scan_jobs WHERE source_host=? AND history_id=? AND status='running' LIMIT 1");
                $find->execute([$host,$historyId]);
            }else{
                $find=$pdo->prepare("SELECT id,history_id FROM cdsp_website_scan_jobs WHERE source_host=? AND status='running' ORDER BY id DESC LIMIT 1");
                $find->execute([$host]);
            }
            $target=$find->fetch();
            if(!$target){return $historyId>0?self::statusByHistory($historyId):self::status($host);}
            $q=$pdo->prepare("UPDATE cdsp_website_scan_jobs SET status=?,last_error=?,updated_at=NOW(),finished_at=NOW() WHERE id=? AND status='running'");
            $q->execute([$targetStatus,$message,(int)$target['id']]);
            $state=self::statusByHistory((int)$target['history_id']);
            if($state){
                WebsiteActivityHistory::addScanItem((int)$state['history_id'],(int)$state['id'],(string)$state['website_url'],$targetStatus,'run','','',false,false,$message);
                self::syncHistory($state,true);
            }
            return $state;
        }finally{
            try{$release=$pdo->prepare('SELECT RELEASE_LOCK(?)');$release->execute([$lockName]);}catch(\Throwable $e){}
        }
    }

    /** Return true only while a source has an active running scan. */
    public static function isRunning(string $host): bool
    {
        $state=self::status($host);
        return $state && (string)($state['status']??'')==='running';
    }

    /**
     * Process one short persisted crawl step. A MySQL advisory lock prevents two
     * tabs from consuming the same source queue at once; it is held only for one
     * short AJAX batch, not for the whole website scan.
     * 处理一个短批次；MySQL advisory lock 只保护该批次，避免两个标签页同时消费同一队列。
     */
    public static function step(string $host,int $historyId=0): array
    {
        self::ensureTable();
        $host=strtolower(trim($host));
        $pdo=Database::connection();
        $lockName='cdsp-webscan-'.substr(hash('sha256',$host),0,40);
        $lock=$pdo->prepare('SELECT GET_LOCK(?,0)');
        $lock->execute([$lockName]);
        if((int)$lock->fetchColumn()!==1){
            $state=$historyId>0?(self::statusByHistory($historyId)??['source_host'=>$host,'status'=>'running']):(self::status($host)??['source_host'=>$host,'status'=>'running']);
            $state['busy']=true;
            return $state;
        }

        try{
            if($historyId>0){
                $raw=$pdo->prepare('SELECT * FROM cdsp_website_scan_jobs WHERE source_host=? AND history_id=? LIMIT 1');
                $raw->execute([$host,$historyId]);
            }else{
                $raw=$pdo->prepare("SELECT * FROM cdsp_website_scan_jobs WHERE source_host=? AND status='running' ORDER BY id DESC LIMIT 1");
                $raw->execute([$host]);
            }
            $row=$raw->fetch();
            if(!$row){throw new \DomainException('Website scan job was not found.');}
            if((string)$row['status']!=='running'){return self::publicRow($row);}

            $queue=self::decodeList((string)$row['queue_json']);
            $seen=self::decodeList((string)$row['seen_json']);
            $seenMap=array_fill_keys($seen,true);
            $batch=[];
            while($queue && count($batch)<self::STEP_SIZE && count($seen)<self::MAX_PAGES){
                $url=(string)array_shift($queue);
                if($url===''||isset($seenMap[$url])){continue;}
                $seenMap[$url]=true;$seen[]=$url;$batch[]=$url;
            }

            if(!$batch){
                $message=count($seen)>=self::MAX_PAGES?'Stopped at the 5,000-page safety limit.':'Scan complete.';
                $done=$pdo->prepare(
                    "UPDATE cdsp_website_scan_jobs SET status='completed',queue_json=?,seen_json=?,last_error=?,updated_at=NOW(),finished_at=NOW()
                     WHERE id=? AND status='running'"
                );
                $done->execute([self::json($queue),self::json($seen),$message,(int)$row['id']]);
                $state=self::statusByHistory((int)$row['history_id']) ?? [];
                if($state){WebsiteActivityHistory::addScanItem((int)$row['history_id'],(int)$row['id'],(string)$row['website_url'],'completed','run','','',false,false,$message);}
                self::syncHistory($state,true);
                return $state;
            }

            try{
                $scanBatch=[];
                $skippedExisting=(int)($row['skipped_existing']??0);
                foreach($batch as $candidateUrl){
                    // Only skip a previously indexed URL when it is clearly a
                    // product-detail page. Listing/category/navigation pages
                    // must be re-fetched on repeat scans so newly added product
                    // links can still be discovered.
                    if(
                        WebsiteCatalog::isProductDetailUrl((string)$candidateUrl)
                        && WebsiteCatalog::referenceUrlExists($host,(string)$candidateUrl)
                    ){
                        $skippedExisting++;
                        WebsiteActivityHistory::addScanItem((int)$row['history_id'],(int)$row['id'],(string)$candidateUrl,'skipped','product','','',false,false,'Existing product URL skipped.');
                        continue;
                    }
                    $scanBatch[]=$candidateUrl;
                }
                $result=$scanBatch
                    ?WebsiteCatalog::scanProductBatch((string)$row['website_url'],$scanBatch)
                    :['checked'=>0,'products'=>0,'images_found'=>0,'indexed'=>0,'failed'=>0,'discovered'=>[],'results'=>[]];
                self::recordErrors($host,(array)($result['results']??[]));
                foreach((array)($result['results']??[]) as $pageResult){
                    if(!is_array($pageResult)){continue;}
                    $pageUrl=(string)($pageResult['url']??'');
                    $kind=(string)($pageResult['kind']??'page');
                    $ok=!empty($pageResult['ok']);
                    $status=$ok?($kind==='product'?'saved':'checked'):'failed';
                    $title=(string)($pageResult['title']??'');
                    $imageUrl=(string)($pageResult['image_url']??'');
                    $imageFound=$imageUrl!=='';
                    $fingerprinted=!empty($pageResult['image_indexed']);
                    $message='';
                    if(!$ok){$message=(string)($pageResult['message']??'Scan failed.');}
                    elseif($kind==='product'){$message='Product saved'.($imageFound?' · image found':' · no image').($fingerprinted?' · fingerprinted':'');}
                    elseif($kind==='sitemap'){$message='Sitemap checked · '.(int)($pageResult['found']??0).' links discovered';}
                    elseif($kind==='navigation'){$message='Navigation/category page checked';}
                    WebsiteActivityHistory::addScanItem((int)$row['history_id'],(int)$row['id'],$pageUrl,$status,$kind,$title,$imageUrl,$imageFound,$fingerprinted,$message);
                }
                $queueMap=array_fill_keys($queue,true);
                foreach((array)($result['discovered']??[]) as $url){
                    if(!is_string($url)||$url===''||isset($seenMap[$url])||isset($queueMap[$url])){continue;}
                    if(count($seen)+count($queue)>=self::MAX_PAGES){break;}
                    $queue[]=$url;
                    $queueMap[$url]=true;
                }
                // Keep the persisted queue globally prioritized. Product-detail
                // URLs discovered from a category page should be consumed before
                // hundreds of additional navigation/category pages, otherwise
                // the UI can look stuck at the same product count for a long time.
                usort(
                    $queue,
                    static fn(string $a,string $b): int =>
                        WebsiteCatalog::crawlPriority($b) <=> WebsiteCatalog::crawlPriority($a)
                );
                $checked=(int)$row['checked']+(int)($result['checked']??0);
                $products=(int)$row['products']+(int)($result['products']??0);
                $imagesFound=(int)$row['images_found']+(int)($result['images_found']??0);
                $indexed=(int)$row['indexed']+(int)($result['indexed']??0);
                $failed=(int)$row['failed']+(int)($result['failed']??0);
                $status=(!$queue||count($seen)>=self::MAX_PAGES)?'completed':'running';
                $message=$status==='completed'
                    ?(count($seen)>=self::MAX_PAGES?'Stopped at the 5,000-page safety limit.':'Scan complete.')
                    :null;
                $u=$pdo->prepare(
                    "UPDATE cdsp_website_scan_jobs
                     SET status=?,queue_json=?,seen_json=?,checked=?,products=?,images_found=?,indexed=?,failed=?,skipped_existing=?,last_error=?,updated_at=NOW(),finished_at=?
                     WHERE id=? AND status='running'"
                );
                $u->execute([
                    $status,self::json($queue),self::json($seen),$checked,$products,$imagesFound,$indexed,$failed,$skippedExisting,$message,
                    $status==='completed'?date('Y-m-d H:i:s'):null,(int)$row['id']
                ]);
            }catch(\Throwable $e){
                \App\Core\Logger::exception($e,'website-catalog',['event'=>'Persistent website scan step failed','source_host'=>$host],'warning');
                $u=$pdo->prepare(
                    "UPDATE cdsp_website_scan_jobs SET failed=failed+1,last_error=?,queue_json=?,seen_json=?,updated_at=NOW() WHERE id=? AND status='running'"
                );
                $u->execute([$e->getMessage(),self::json($queue),self::json($seen),(int)$row['id']]);
            }
            $state=self::statusByHistory((int)$row['history_id']) ?? [];
            self::syncHistory($state,(string)($state['status']??'')!=='running');
            return $state;
        }finally{
            try{$release=$pdo->prepare('SELECT RELEASE_LOCK(?)');$release->execute([$lockName]);}catch(\Throwable $e){}
        }
    }

    private static function publicRow(array $row): array
    {
        $queue=self::decodeList((string)($row['queue_json']??'[]'));
        $seen=self::decodeList((string)($row['seen_json']??'[]'));
        $updatedAt=(string)($row['updated_at']??'');
        $staleSeconds=max(0,(int)($row['stale_seconds']??0));
        return [
            'id'=>(int)($row['id']??0),
            'history_id'=>(int)($row['history_id']??0),
            'source_host'=>(string)($row['source_host']??''),
            'website_url'=>(string)($row['website_url']??''),
            'status'=>(string)($row['status']??''),
            'checked'=>(int)($row['checked']??0),
            'products'=>(int)($row['products']??0),
            'images_found'=>(int)($row['images_found']??0),
            'indexed'=>(int)($row['indexed']??0),
            'failed'=>(int)($row['failed']??0),
            'skipped_existing'=>(int)($row['skipped_existing']??0),
            'queue'=>count($queue),
            'seen'=>count($seen),
            'next_url'=>(string)($queue[0]??''),
            'last_error'=>(string)($row['last_error']??''),
            'created_at'=>(string)($row['created_at']??''),
            'updated_at'=>$updatedAt,
            'stale_seconds'=>$staleSeconds,
            'finished_at'=>(string)($row['finished_at']??''),
            'page_errors'=>self::errors((string)($row['source_host']??''),50),
        ];
    }

    /** Return recent per-page failures with a human-readable explanation and clickable URL. */
    public static function errors(string $host,int $limit=50): array
    {
        self::ensureTable();
        $host=strtolower(trim($host));$limit=max(1,min(100,$limit));
        if($host===''){return [];}
        $q=Database::connection()->prepare(
            "SELECT id,page_url,http_status,error_message,explanation,created_at
             FROM cdsp_website_scan_errors WHERE source_host=? ORDER BY id DESC LIMIT {$limit}"
        );
        $q->execute([$host]);
        return $q->fetchAll()?:[];
    }

    /** Persist page-level crawler failures without turning one bad URL into a whole-job failure. */
    private static function recordErrors(string $host,array $results): void
    {
        $pdo=Database::connection();
        $q=$pdo->prepare(
            'INSERT INTO cdsp_website_scan_errors (source_host,page_url,http_status,error_message,explanation,created_at) VALUES(?,?,?,?,?,NOW())'
        );
        foreach($results as $result){
            if(!is_array($result)||!empty($result['ok'])){continue;}
            $url=trim((string)($result['url']??''));$message=trim((string)($result['message']??'Unknown scan error.'));
            if($url===''){continue;}
            $status=null;
            if(isset($result['http_status'])&&is_numeric($result['http_status'])){$status=(int)$result['http_status'];}
            elseif(preg_match('/\bHTTP\s+(\d{3})\b/i',$message,$m)){$status=(int)$m[1];}
            $explanation=self::explainError($status,$message);
            $q->execute([$host,$url,$status,$message,$explanation]);
        }
    }

    private static function explainError(?int $status,string $message): string
    {
        return match($status){
            401=>'The page requires authentication, so the scanner cannot read it anonymously.',
            403=>'The website refused access to the scanner for this page.',
            404=>'The page was not found or has been removed.',
            408=>'The page request timed out before the remote website answered.',
            429=>'The remote website rate-limited the scanner. Continue later or reduce scan speed.',
            500=>'The remote website returned an internal server error for this page.',
            502=>'A gateway in front of the remote website returned a bad gateway response.',
            503=>'The remote website was temporarily unavailable or overloaded for this page. This page can be retried later.',
            504=>'A gateway timed out while waiting for the remote website.',
            default=>($message!==''?'Scanner error: '.$message:'The page could not be scanned.'),
        };
    }

    /** Keep the durable scan-history row synchronized with the current persisted job. */
    private static function syncHistory(array $state,bool $finished): void
    {
        $historyId=(int)($state['history_id']??0);
        if($historyId<1){return;}
        WebsiteActivityHistory::update(
            $historyId,
            (string)($state['status']??'running'),
            (int)($state['checked']??0),
            (int)($state['products']??0),
            (int)($state['failed']??0),
            self::historyMessage($state),
            $finished
        );
    }

    private static function historyMessage(array $state): string
    {
        $parts=[];
        $images=(int)($state['images_found']??0);
        $indexed=(int)($state['indexed']??0);
        $queue=(int)($state['queue']??0);
        $skipped=(int)($state['skipped_existing']??0);
        $parts[]='First images found '.$images;
        $parts[]='Exact fingerprints '.$indexed;
        if($skipped>0){$parts[]='Existing URLs skipped '.$skipped;}
        if($queue>0){$parts[]='Queue '.$queue;}
        $last=trim((string)($state['last_error']??''));
        if($last!==''&&!in_array($last,['Scan complete.','Stopped at the 5,000-page safety limit.'],true)){$parts[]=$last;}
        return implode(' · ',$parts);
    }

    private static function decodeList(string $json): array
    {
        $v=json_decode($json,true);
        if(!is_array($v)){return [];}
        $out=[];
        foreach($v as $item){if(is_string($item)&&$item!==''){$out[]=$item;}}
        return array_values(array_unique($out));
    }

    private static function json(array $value): string
    {
        return json_encode(array_values($value),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:'[]';
    }
}
