<?php
/**
 * File / 文件：app/Services/VerificationQueueWorker.php
 * EN: Runs queued Marketplace verification independently from the browser request.
 * 中文：在浏览器请求之外处理 Marketplace 后台验证队列。
 */
namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\Post;
use App\Models\VerificationQueue;

final class VerificationQueueWorker
{
    /**
     * EN: Start one detached CLI worker when possible. Queue remains durable if process launch is unavailable.
     * 中文：尽可能启动独立 CLI Worker；即使无法启动，队列记录仍会保存在数据库中。
     */
    public static function kick(): bool
    {
        if(PHP_SAPI==='cli')return false;
        $script=dirname(__DIR__,2).'/scripts/process_verification_queue.php';
        if(!is_file($script))return false;
        $php='php';
        $candidates=[];
        if(defined('PHP_BINDIR'))$candidates[]=rtrim((string)PHP_BINDIR,'/').'/php';
        $candidates[]='/usr/local/bin/php';$candidates[]='/usr/bin/php';
        if(defined('PHP_BINARY'))$candidates[]=(string)PHP_BINARY;
        foreach(array_unique($candidates) as $candidate){if($candidate!==''&&is_executable($candidate)){$php=$candidate;break;}}
        $logDir=dirname(__DIR__,2).'/storage/logs';
        if(!is_dir($logDir))@mkdir($logDir,0775,true);
        $log=$logDir.'/verification-queue-worker.log';
        $cmd='nohup '.escapeshellarg($php).' '.escapeshellarg($script).' --max=20 >>'.escapeshellarg($log).' 2>&1 < /dev/null &';
        if(function_exists('exec')){
            @exec($cmd);
            return true;
        }
        if(function_exists('shell_exec')){
            @shell_exec($cmd);
            return true;
        }
        if(function_exists('proc_open')){
            $proc=@proc_open($cmd,[0=>['file','/dev/null','r'],1=>['file','/dev/null','a'],2=>['file','/dev/null','a']],$pipes);
            if(is_resource($proc)){@proc_close($proc);return true;}
        }
        Logger::warning('Verification queue worker could not be launched because exec functions are disabled.',[
            'event'=>'verification_queue_worker_launch_unavailable'
        ],'verification-queue');
        return false;
    }

    /** @return array{processed:int,passed:int,failed:int,duplicate:int,recovered:int} */
    public static function run(int $max=20): array
    {
        $max=max(1,min(100,$max));
        $pdo=Database::connection();
        $lockName='cdsp-verification-queue-worker';
        $s=$pdo->prepare('SELECT GET_LOCK(?,0)');$s->execute([$lockName]);
        if((int)$s->fetchColumn()!==1)return ['processed'=>0,'passed'=>0,'failed'=>0,'duplicate'=>0,'recovered'=>0];
        $stats=['processed'=>0,'passed'=>0,'failed'=>0,'duplicate'=>0,'recovered'=>0];
        try{
            $stats['recovered']=VerificationQueue::recoverStale(15);
            for($i=0;$i<$max;$i++){
                $token=bin2hex(random_bytes(32));
                $item=VerificationQueue::claimNext($token);
                if(!$item)break;
                $stats['processed']++;
                try{
                    $result=(new PostInspector())->inspect(
                        (int)$item['sales_user_id'],
                        (string)$item['platform'],
                        (string)$item['canonical_url']
                    );
                    $status=(string)($result['verification_status']??'failed');
                    $code=(string)($result['failure_code']??'');
                    $message=(string)($result['failure_message']??'');
                    if($status==='verified'){
                        $postId=self::saveVerifiedResult($result);
                        VerificationQueue::markPassed((int)$item['id'],$token,$postId,$result);
                        $stats['passed']++;
                    }elseif(in_array($code,['DUPLICATE','DUPLICATE_IMAGE'],true)){
                        $match=self::duplicateMatch($result);
                        VerificationQueue::markDuplicate(
                            (int)$item['id'],$token,$result,$code?:'DUPLICATE',
                            $message?:'A duplicate was found during final verification.',
                            $match['url']??null,$match['kind']??null
                        );
                        $stats['duplicate']++;
                    }elseif($status==='manual_pending'){
                        VerificationQueue::markFailed(
                            (int)$item['id'],$token,$result,
                            $code?:'MANUAL_VERIFICATION_REQUIRED',
                            $message?:'Automatic verification requires manual confirmation. Use Edit & Re-verify or Check Post Now.'
                        );
                        $stats['failed']++;
                    }else{
                        VerificationQueue::markFailed(
                            (int)$item['id'],$token,$result,$code?:'VERIFICATION_FAILED',
                            $message?:'Marketplace verification failed.'
                        );
                        $stats['failed']++;
                    }
                }catch(\DomainException $e){
                    $result=[
                        'sales_user_id'=>(int)$item['sales_user_id'],
                        'platform'=>(string)$item['platform'],
                        'submitted_url'=>(string)$item['submitted_url'],
                        'resolved_url'=>(string)$item['canonical_url'],
                        'canonical_url'=>(string)$item['canonical_url'],
                        'external_post_id'=>$item['external_post_id']??null,
                        'verification_status'=>'failed',
                        'failure_code'=>'SAVE_BLOCKED',
                        'failure_message'=>$e->getMessage(),
                        'raw_meta'=>[],
                    ];
                    $dup=Post::duplicate(
                        (int)$item['sales_user_id'],(string)$item['platform'],(string)$item['canonical_url'],
                        $item['external_post_id']??null,null,null
                    );
                    if($dup){
                        VerificationQueue::markDuplicate(
                            (int)$item['id'],$token,$result,'DUPLICATE',$e->getMessage(),
                            $dup['canonical_url']??null,$dup['kind']??null
                        );
                        $stats['duplicate']++;
                    }else{
                        VerificationQueue::markFailed((int)$item['id'],$token,$result,'SAVE_BLOCKED',$e->getMessage());
                        $stats['failed']++;
                    }
                }catch(\Throwable $e){
                    Logger::exception($e,'verification-queue',[
                        'event'=>'Verification queue item failed unexpectedly',
                        'queue_id'=>(int)$item['id'],
                        'sales_user_id'=>(int)$item['sales_user_id'],
                        'platform'=>(string)$item['platform'],
                    ],'error');
                    $result=[
                        'sales_user_id'=>(int)$item['sales_user_id'],
                        'platform'=>(string)$item['platform'],
                        'submitted_url'=>(string)$item['submitted_url'],
                        'canonical_url'=>(string)$item['canonical_url'],
                        'external_post_id'=>$item['external_post_id']??null,
                        'verification_status'=>'failed',
                        'failure_code'=>'QUEUE_WORKER_ERROR',
                        'failure_message'=>'Background verification encountered a server error. Retry this item.',
                        'raw_meta'=>[],
                    ];
                    VerificationQueue::markFailed((int)$item['id'],$token,$result,'QUEUE_WORKER_ERROR',$result['failure_message']);
                    $stats['failed']++;
                }
            }
        }finally{
            try{$r=$pdo->prepare('SELECT RELEASE_LOCK(?)');$r->execute([$lockName]);}catch(\Throwable $ignore){}
        }
        return$stats;
    }

    private static function saveVerifiedResult(array $result):int
    {
        global $config;
        $pdo=Database::connection();
        $lockName='cdsp-save-'.substr(hash('sha256',($config['db']['name']??'').':'.($result['platform']??'')),0,48);
        $lock=$pdo->prepare('SELECT GET_LOCK(?,10)');$lock->execute([$lockName]);
        if((int)$lock->fetchColumn()!==1)throw new \DomainException('Another post is being saved. Retry this item.');
        try{
            $pdo->beginTransaction();
            $save=$result;
            $save['raw_meta_json']=json_encode($result['raw_meta']??[],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
            $postId=Post::create($save);
            $pdo->commit();
            return$postId;
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
        finally{try{$r=$pdo->prepare('SELECT RELEASE_LOCK(?)');$r->execute([$lockName]);}catch(\Throwable $ignore){}}
    }

    private static function duplicateMatch(array $result):array
    {
        $meta=is_array($result['raw_meta']??null)?$result['raw_meta']:[];
        $match=$meta['duplicate_match']??null;
        if(is_array($match))return['url'=>$match['canonical_url']??null,'kind'=>$match['kind']??null];
        $matches=$meta['duplicate_report']['matches']??[];
        if(is_array($matches)&&isset($matches[0])&&is_array($matches[0]))return['url'=>$matches[0]['url']??null,'kind'=>$matches[0]['kind']??null];
        return[];
    }
}
