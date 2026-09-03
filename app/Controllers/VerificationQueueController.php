<?php
/**
 * File / 文件：app/Controllers/VerificationQueueController.php
 * EN: Sales endpoints for Save & Wait, Bulk Submit, queue filters, retry/edit/delete and history.
 * 中文：Sales 的 Save & Wait、Bulk Submit、队列筛选、重试/修改/删除及历史接口。
 */
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Logger;
use App\Models\VerificationQueue;
use App\Services\VerificationQueueWorker;

final class VerificationQueueController extends Controller
{
    public function index():void
    {
        $u=Auth::requireRole('sales');
        $filter=strtolower(trim((string)($_GET['status']??'all')));
        if(!in_array($filter,['all','waiting','verifying','passed','error','errors','failed','duplicate','invalid','needs_action'],true))$filter='all';
        try{
            // V0.2.102: counters and rows must come from the same DB snapshot.
            // Previously the worker was kicked after counts but before listForSales(),
            // so a newly queued row could move status between the two SELECTs and the
            // browser could render a non-zero counter with an empty list.
            $snapshot=VerificationQueue::snapshotForSales((int)$u['id'],$filter,100);
            $counts=$snapshot['counts'];
            $items=$snapshot['items'];
            if((int)$counts['waiting']>0&&(int)$counts['verifying']===0){
                VerificationQueueWorker::kick();
                // V0.2.104: a Waiting item must never depend only on a detached CLI launch.
                // The AJAX response returns first; one item is then processed by PHP-FPM.
                VerificationQueueWorker::deferOne();
            }
            $this->json([
                'ok'=>true,
                'filter'=>$filter,
                'counts'=>$counts,
                'items'=>$items,
            ]);
        }catch(\Throwable $e){
            Logger::exception($e,'verification-queue',['event'=>'Queue list failed'],'error');
            $this->json(['ok'=>false,'message'=>'Verification Queue could not be loaded.'],500);
        }
    }

    public function enqueue():void
    {
        $u=Auth::requireRole('sales');Csrf::verify($_POST['_csrf']??null);
        $url=trim((string)($_POST['url']??''));
        if($url==='')$this->json(['ok'=>false,'message'=>'Enter a post URL.'],422);
        try{
            $preflight=VerificationQueue::preflightUrl((int)$u['id'],$url);
            if(!$preflight['ok']){
                $row=VerificationQueue::recordPreflightIssue((int)$u['id'],$url,$preflight,(int)$u['id']);
                $this->json([
                    'ok'=>true,'accepted'=>false,'item'=>$row,'worker_started'=>false,
                    'message'=>(string)$preflight['message'].' Saved under Errors; edit or delete it there.',
                    'counts'=>VerificationQueue::countsForSales((int)$u['id']),
                ]);
            }
            $row=VerificationQueue::enqueueUrl((int)$u['id'],$url,(int)$u['id']);
            $launched=VerificationQueueWorker::kick();
            VerificationQueueWorker::deferOne();
            $this->json([
                'ok'=>true,'accepted'=>true,'item'=>$row,'worker_started'=>$launched,
                'message'=>'Saved to Verification Queue. You can submit the next post now.',
                'counts'=>VerificationQueue::countsForSales((int)$u['id']),
            ]);
        }catch(\DomainException $e){
            $this->json(['ok'=>false,'message'=>$e->getMessage()],409);
        }catch(\Throwable $e){
            Logger::exception($e,'verification-queue',['event'=>'Queue enqueue failed','sales_user_id'=>(int)$u['id']],'error');
            $this->json(['ok'=>false,'message'=>'Post could not be added to the Verification Queue.'],500);
        }
    }

    public function bulkEnqueue():void
    {
        $u=Auth::requireRole('sales');Csrf::verify($_POST['_csrf']??null);
        $raw=(string)($_POST['urls']??'');
        $urls=array_values(array_filter(array_map('trim',preg_split('/\r\n|\r|\n/',$raw)?:[]),static fn($v)=>$v!==''));
        if(!$urls)$this->json(['ok'=>false,'message'=>'Paste at least one listing URL.'],422);
        if(count($urls)>100)$this->json(['ok'=>false,'message'=>'Bulk Submit accepts up to 100 URLs at a time.'],422);
        $seen=[];$results=[];$queued=0;$duplicate=0;$invalid=0;
        foreach($urls as $line=>$url){
            if(isset($seen[$url])){
                $results[]=['line'=>$line+1,'url'=>$url,'status'=>'duplicate','message'=>'Duplicate line in this bulk batch.'];$duplicate++;continue;
            }
            $seen[$url]=true;
            try{
                $preflight=VerificationQueue::preflightUrl((int)$u['id'],$url);
                if(!$preflight['ok']){
                    $kind=(string)($preflight['duplicate_kind']??'');
                    $isDup=$kind!==''||!empty($preflight['duplicate_url'])||($preflight['failure_code']??'')==='DUPLICATE_PREFLIGHT';
                    $issue=VerificationQueue::recordPreflightIssue((int)$u['id'],$url,$preflight,(int)$u['id']);
                    $results[]=['line'=>$line+1,'url'=>$url,'status'=>$isDup?'duplicate':'invalid','id'=>(int)($issue['id']??0),'message'=>$preflight['message']??'Could not queue URL.','duplicate_url'=>$preflight['duplicate_url']??null];
                    if($isDup)$duplicate++;else$invalid++;
                    continue;
                }
                $item=VerificationQueue::enqueueUrl((int)$u['id'],$url,(int)$u['id']);
                $results[]=['line'=>$line+1,'url'=>$url,'status'=>'queued','id'=>(int)($item['id']??0),'message'=>'Queued'];$queued++;
            }catch(\DomainException $e){
                $results[]=['line'=>$line+1,'url'=>$url,'status'=>'duplicate','message'=>$e->getMessage()];$duplicate++;
            }catch(\Throwable $e){
                Logger::exception($e,'verification-queue',['event'=>'Bulk queue row failed','line'=>$line+1,'sales_user_id'=>(int)$u['id']],'error');
                $results[]=['line'=>$line+1,'url'=>$url,'status'=>'invalid','message'=>'Could not queue this URL.'];$invalid++;
            }
        }
        if($queued>0){VerificationQueueWorker::kick();VerificationQueueWorker::deferOne();}
        $this->json([
            'ok'=>true,'submitted'=>count($urls),'queued'=>$queued,'duplicate'=>$duplicate,'invalid'=>$invalid,
            'results'=>$results,'counts'=>VerificationQueue::countsForSales((int)$u['id']),
            'message'=>$queued.' queued · '.$duplicate.' duplicate · '.$invalid.' invalid',
        ]);
    }

    public function retry():void
    {
        $u=Auth::requireRole('sales');Csrf::verify($_POST['_csrf']??null);$id=(int)($_POST['id']??0);
        try{
            $row=VerificationQueue::retry((int)$u['id'],$id,(int)$u['id']);VerificationQueueWorker::kick();VerificationQueueWorker::deferOne();
            $this->json(['ok'=>true,'item'=>$row,'message'=>'Queued for re-verification.']);
        }catch(\DomainException $e){$this->json(['ok'=>false,'message'=>$e->getMessage()],409);}
    }

    public function update():void
    {
        $u=Auth::requireRole('sales');Csrf::verify($_POST['_csrf']??null);$id=(int)($_POST['id']??0);$url=trim((string)($_POST['url']??''));
        try{
            $preflight=VerificationQueue::preflightUrl((int)$u['id'],$url,$id);
            if(!$preflight['ok'])$this->json(array_merge($preflight,['ok'=>false]),409);
            $row=VerificationQueue::editAndRequeue((int)$u['id'],$id,$url,(int)$u['id']);VerificationQueueWorker::kick();VerificationQueueWorker::deferOne();
            $this->json(['ok'=>true,'item'=>$row,'message'=>'URL updated and queued for re-verification.']);
        }catch(\DomainException $e){$this->json(['ok'=>false,'message'=>$e->getMessage()],409);}
    }

    public function delete():void
    {
        $u=Auth::requireRole('sales');Csrf::verify($_POST['_csrf']??null);$id=(int)($_POST['id']??0);
        try{VerificationQueue::deleteOwned((int)$u['id'],$id);$this->json(['ok'=>true,'message'=>'Queue item deleted.']);}
        catch(\DomainException $e){$this->json(['ok'=>false,'message'=>$e->getMessage()],409);}
    }

    public function history():void
    {
        $u=Auth::requireRole('sales');$id=(int)($_GET['id']??0);
        $row=VerificationQueue::findOwned((int)$u['id'],$id);
        if(!$row)$this->json(['ok'=>false,'message'=>'Queue item not found.'],404);
        $this->json(['ok'=>true,'item_id'=>$id,'history'=>VerificationQueue::historyForSales((int)$u['id'],$id)]);
    }
}
