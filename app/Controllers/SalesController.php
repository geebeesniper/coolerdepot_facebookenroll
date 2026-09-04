<?php
/**
 * File / 文件：app/Controllers/SalesController.php
 * EN: Defines the SalesController HTTP controller and request/response actions.
 * 中文：定义 SalesController HTTP Controller 及其请求/响应操作。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Models\Post;
use App\Models\Inspection;
use App\Models\VerificationQueue;
use App\Models\User;

/**
 * EN: HTTP controller for sales requests, responses, and server-side authorization.
 * 中文：负责 sales 请求、响应及服务器端权限控制的 HTTP Controller。
 */
class SalesController extends Controller
{
    /**
     * EN: Handle the dashboard HTTP action for sales controller and return the appropriate response.
     * 中文：处理 sales controller 的“dashboard”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function dashboard(): void
    {
        $u=Auth::requireRole('sales');
        $today=date('Y-m-d');
        $to=(string)($_GET['to']??$today);
        $from=(string)($_GET['from']??$to);

        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)){$from=$today;}
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)){$to=$today;}
        if($to>$today){$to=$today;}
        if($from>$today){$from=$today;}
        if($from>$to){$from=$to;}

        $activeChannel=strtolower(trim((string)($_GET['channel']??'all')));
        $allowedChannels=['all','facebook','instagram','offerup','craigslist'];
        if(!in_array($activeChannel,$allowedChannels,true)){$activeChannel='all';}
        $platformFilter=$activeChannel==='all'?null:$activeChannel;

        $hasExplicitRange=(isset($_GET['from'])||isset($_GET['to']));
        $requestedPeriod=strtolower(trim((string)($_GET['period']??'')));
        if(!in_array($requestedPeriod,['single','day','week','month','custom'],true)){
            $requestedPeriod=$hasExplicitRange?'custom':'single';
        }
        $rangePeriod=$requestedPeriod;
        if(in_array($rangePeriod,['single','day','week','month'],true)){
            [$from,$to]=$this->salesPresetRange($rangePeriod,$to,$today);
        }

        $summary=Post::salesRangeSummary((int)$u['id'],$from,$to,$platformFilter);
        $chartRows=Post::salesChartRows((int)$u['id'],$from,$to,$platformFilter);
        $posts=Post::forSalesPublishedRange((int)$u['id'],$from,$to,$platformFilter);
        $salesUser=User::find((int)$u['id']);
        $dailyTarget=max(1,(int)($salesUser['daily_post_target']??10));
        $dailyTargets=User::dailyPostTargetsForRange(
            (int)$u['id'],
            $from,
            $to,
            $dailyTarget
        );
        if($dailyTargets){
            $dailyTarget=(int)($dailyTargets[$to]??$dailyTarget);
        }

        $this->render('sales/dashboard',[
            'user'=>$u,
            'from'=>$from,
            'to'=>$to,
            'today'=>$today,
            'rangePeriod'=>$rangePeriod,
            'activeChannel'=>$activeChannel,
            'summary'=>$summary,
            'chartRows'=>$chartRows,
            'dailyTarget'=>$dailyTarget,
            'dailyTargets'=>$dailyTargets,
            'posts'=>$posts,
        ]);
    }

    /**
     * EN: Handle the daily posts ajax HTTP action for sales controller and return the appropriate response.
     * 中文：处理 sales controller 的“daily posts ajax”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function dailyPostsAjax(): void
    {
        $u=Auth::requireRole('sales');
        $today=date('Y-m-d');
        $to=(string)($_GET['to']??$today);
        $from=(string)($_GET['from']??$to);

        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)){
            $this->json(['ok'=>false,'message'=>'Invalid date range.'],422);
        }
        if($to>$today){$to=$today;}
        if($from>$today){$from=$today;}
        if($from>$to){$from=$to;}

        $hasExplicitRange=(isset($_GET['from'])||isset($_GET['to']));
        $defaultPeriod=$hasExplicitRange?'custom':'single';
        $rangePeriod=strtolower(trim((string)($_GET['period']??$defaultPeriod)));
        if(!in_array($rangePeriod,['single','day','week','month','custom'],true)){$rangePeriod=$defaultPeriod;}
        if(in_array($rangePeriod,['single','day','week','month'],true)){
            [$from,$to]=$this->salesPresetRange($rangePeriod,$to,$today);
        }

        $activeChannel=strtolower(trim((string)($_GET['channel']??'all')));
        $allowedChannels=['all','facebook','instagram','offerup','craigslist'];
        if(!in_array($activeChannel,$allowedChannels,true)){$activeChannel='all';}
        $platformFilter=$activeChannel==='all'?null:$activeChannel;

        $summary=Post::salesRangeSummary((int)$u['id'],$from,$to,$platformFilter);
        $chartRows=Post::salesChartRows((int)$u['id'],$from,$to,$platformFilter);
        $posts=Post::forSalesPublishedRange((int)$u['id'],$from,$to,$platformFilter);
        $salesUser=User::find((int)$u['id']);
        $dailyTarget=max(1,(int)($salesUser['daily_post_target']??10));
        $dailyTargets=User::dailyPostTargetsForRange(
            (int)$u['id'],
            $from,
            $to,
            $dailyTarget
        );
        if($dailyTargets){
            $dailyTarget=(int)($dailyTargets[$to]??$dailyTarget);
        }

        ob_start();
        $this->renderPartial('sales/_post_range_section',[
            'posts'=>$posts,
            'summary'=>$summary,
            'from'=>$from,
            'to'=>$to,
        ]);
        $html=ob_get_clean();

        $start=new \DateTimeImmutable($from.' 12:00:00');
        $end=new \DateTimeImmutable($to.' 12:00:00');
        $totalDays=(int)$start->diff($end)->days+1;

        $this->json([
            'ok'=>true,
            'html'=>$html,
            'loaded'=>count($posts),
            'next_offset'=>0,
            'has_more'=>false,
            'total_days'=>$totalDays,
            'total_posts'=>count($posts),
            'from'=>$from,
            'to'=>$to,
            'summary'=>$summary,
            'chart_rows'=>$chartRows,
            'daily_target'=>$dailyTarget,
            'daily_targets'=>$dailyTargets,
            'channel'=>$activeChannel,
            'period'=>$rangePeriod,
        ]);
    }


    /**
     * EN: Search the authenticated Sales user's saved Marketplace posts across all dates.
     * 中文：跨全部日期搜索当前登录 Sales 自己保存的 Marketplace Posts。
     *
     * @return void
     */
    public function postSearch():void
    {
        $u=Auth::requireRole('sales');
        $query=trim((string)($_GET['q']??''));

        if(strlen($query)>500){
            $query=substr($query,0,500);
        }

        if(strlen($query)<2){
            $this->json([
                'ok'=>true,
                'query'=>$query,
                'matches'=>[],
                'count'=>0,
            ]);
        }

        if(session_status()===PHP_SESSION_ACTIVE){
            session_write_close();
        }

        $rows=Post::salesSearchOriginalPosts((int)$u['id'],$query,40);
        $matches=[];

        foreach($rows as $row){
            $originalUrl=trim((string)($row['canonical_url']??''));
            if($originalUrl===''){
                $originalUrl=trim((string)($row['resolved_url']??''));
            }
            if($originalUrl===''){
                $originalUrl=trim((string)($row['submitted_url']??''));
            }

            $status=strtolower(trim((string)($row['current_review_status']??'')));
            if(!in_array($status,['good','bad'],true)){
                $status='unreviewed';
            }

            $matches[]=[
                'post_id'=>(int)$row['id'],
                'platform'=>(string)($row['platform']??''),
                'title'=>(string)($row['title']??''),
                'description'=>(string)($row['description']??''),
                'original_url'=>$originalUrl,
                'external_post_id'=>(string)($row['external_post_id']??''),
                'published_at'=>(string)($row['published_at']??''),
                'published_date'=>(string)($row['published_date']??''),
                'published_display'=>($publishedTs=strtotime((string)($row['published_at']??$row['published_date']??'')))
                    ?date('M j, Y · g:i A',$publishedTs)
                    :(string)($row['published_date']??''),
                'thumbnail_url'=>!empty($row['fetched_image_url'])
                    ?(string)$row['fetched_image_url']
                    :null,
                'status'=>$status,
                'platform_account_id'=>(string)($row['platform_account_id']??''),
                'platform_account_name'=>(string)($row['platform_account_name']??''),
                'platform_account_url'=>(string)($row['platform_account_url']??''),
                'deletion_request_status'=>(string)($row['deletion_request_status']??''),
            ];
        }

        $this->json([
            'ok'=>true,
            'query'=>$query,
            'matches'=>$matches,
            'count'=>count($matches),
        ]);
    }

/**
 * EN: Perform the sales preset range operation.
 * 中文：执行“sales preset range”操作。
 *
 * @param string $period Period value used by this operation. / 本操作使用的“period”参数值。
 * @param string $to To value used by this operation. / 本操作使用的“to”参数值。
 * @param string $today Today value used by this operation. / 本操作使用的“today”参数值。
 *
 * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
 */
private function salesPresetRange(
    string $period,
    string $to,
    string $today
): array {
    if(
        !preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $to
        )
    ){
        $to=$today;
    }

    if($to>$today){
        $to=$today;
    }

    $anchor=new \DateTimeImmutable(
        $to.' 12:00:00'
    );

    if($period==='single'){
        $from=$anchor;
    }elseif($period==='day'){
        $from=$anchor->modify('-2 days');
    }elseif($period==='week'){
        $from=$anchor->modify('-6 days');
    }elseif($period==='month'){
        $anchorDay=(int)$anchor->format('j');

        $previousMonthStart=$anchor
            ->modify('first day of previous month');

        $previousMonthLast=$anchor
            ->modify('last day of previous month');

        $previousDay=min(
            $anchorDay,
            (int)$previousMonthLast->format('j')
        );

        $from=$previousMonthStart
            ->setDate(
                (int)$previousMonthStart->format('Y'),
                (int)$previousMonthStart->format('m'),
                $previousDay
            )
            ->modify('+1 day');
    }else{
        return [$to,$to];
    }

    return [
        $from->format('Y-m-d'),
        $anchor->format('Y-m-d'),
    ];
}



    /**
     * EN: Handle the submit form HTTP action for sales controller and return the appropriate response.
     * 中文：处理 sales controller 的“submit form”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function submitForm(): void
    {
        Auth::requireRole('sales');

        // V0.2.127: the single-item Submit Post UI was retired. Bulk Submit accepts
        // one URL or many URLs and is now the only Sales submission entry point.
        $this->redirect('/sales/bulk-submit');
    }

    /**
     * EN: Render the dedicated Bulk Submit Post workflow.
     * 中文：渲染独立的 Bulk Submit Post 平级流程。
     *
     * @return void
     */
    public function bulkSubmitForm(): void
    {
        $u=Auth::requireRole('sales');

        $this->render('sales/bulk_submit',[
            'user'=>$u,
        ]);
    }

    /**
     * EN: Handle the save HTTP action for sales controller and return the appropriate response.
     * 中文：处理 sales controller 的“save”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     *
     * @throws \DomainException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    public function save(): void
    {
        global $config;
        $u=Auth::requireRole('sales');
        $isAjax=strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH']??''))==='xmlhttprequest';
        Csrf::verify($_POST['_csrf']??null);
        $token=trim((string)($_POST['inspection_token']??''));
        $inspection=Inspection::savable($token,(int)$u['id']);
        if(!$inspection){
            if($isAjax){$this->json(['ok'=>false,'message'=>'Verification expired or invalid. Check the post again.'],422);}
            $_SESSION['flash_error']='Verification expired or invalid. Check the post again.';
            $this->redirect('/sales/submit');
        }
        $pdo=Database::connection();
        $lockName='cdsp-save-'.substr(hash('sha256',($config['db']['name']??'').':'.$inspection['platform']),0,48);
        $locked=false;$error=null;$duplicateUrl=null;$duplicateTitle=null;$duplicateKind=null;
        try{
            $lock=$pdo->prepare('SELECT GET_LOCK(?,10)');$lock->execute([$lockName]);
            $locked=(int)$lock->fetchColumn()===1;
            if(!$locked){throw new \DomainException('Another post is being saved. Please try again.');}
            $pdo->beginTransaction();
            $inspection=Inspection::savable($token,(int)$u['id'],true);
            if(!$inspection){throw new \DomainException('Verification expired or was already saved. Check the post again.');}
            $queueDup=VerificationQueue::reservationDuplicate(
                (int)$inspection['sales_user_id'],
                (string)$inspection['platform'],
                (string)($inspection['canonical_url']??''),
                $inspection['external_post_id']??null
            );
            if($queueDup){throw new \DomainException((string)$queueDup['reason']);}
            $postId=Post::create($inspection);
            Inspection::consume((int)$inspection['id']);
            $pdo->commit();
        }catch(\Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            \App\Core\Logger::exception(
                $e,
                'sales-save',
                ['event' => 'Sales post save transaction failed'],
                $e instanceof \DomainException ? 'warning' : 'error'
            );
            if($e instanceof \DomainException){
                $error=$e->getMessage();
                if(isset($inspection) && is_array($inspection)){
                    // V0.2.77: save-time error reconstruction must use the same
                    // marketplace account scope as Verify and Post::create(). Omitting
                    // the provider account here could incorrectly report a different
                    // account's title as the duplicate after a save-time exception.
                    $rawMeta=json_decode((string)($inspection['raw_meta_json']??'{}'),true)?:[];
                    $platformAccount=is_array($rawMeta['platform_account']??null)
                        ? $rawMeta['platform_account']
                        : null;
                    $dup=Post::duplicate(
                        (int)$inspection['sales_user_id'],
                        (string)$inspection['platform'],
                        $inspection['canonical_url']??null,
                        $inspection['external_post_id']??null,
                        $inspection['title']??null,
                        $inspection['description']??null,
                        $platformAccount
                    );
                    if($dup){
                        $duplicateUrl=$dup['canonical_url']??null;
                        $duplicateTitle=$dup['title']??null;
                        $duplicateKind=$dup['kind']??null;
                    } else {
                        $queueDup=VerificationQueue::reservationDuplicate(
                            (int)$inspection['sales_user_id'],
                            (string)$inspection['platform'],
                            (string)($inspection['canonical_url']??''),
                            $inspection['external_post_id']??null
                        );
                        if($queueDup){
                            $duplicateUrl=$queueDup['canonical_url']??null;
                            $duplicateKind=$queueDup['kind']??null;
                        }
                    }
                    if(!$duplicateUrl){
                        // EN: A website/exact-image duplicate can appear between Verify and Save.
                        // Re-run the exact comparison so the AJAX error can still return the
                        // concrete duplicate URL instead of only a generic message.
                        // 中文：Verify 与 Save 之间也可能新增官网/完全相同图片重复项。
                        // 重新执行精确查重，以便 AJAX 错误仍返回实际重复 URL，而不是只有通用提示。
                        $assets=$rawMeta['duplicate_report']['assets']??[];
                        if(is_array($assets)){
                            $report=\App\Services\DuplicateIndex::compare(
                                (int)$inspection['sales_user_id'],
                                (string)$inspection['platform'],
                                (string)($inspection['title']??''),
                                $assets,
                                $platformAccount
                            );
                            $match=$report['matches'][0]??null;
                            if(is_array($match)){
                                $duplicateUrl=$match['url']??null;
                                $duplicateTitle=$match['title']??null;
                                $duplicateKind=$match['kind']??null;
                            }
                        }
                    }
                }
            }
            elseif($e instanceof \PDOException&&(int)($e->errorInfo[1]??0)===1062){$error='This post ID or URL has already been saved.';}
            elseif($e instanceof \PDOException
                && (int)($e->errorInfo[1]??0)===1048
                && stripos($e->getMessage(),'admin_review_status')!==false){
                $error='Database review-status migration is required. Ask an administrator to run the v0.1.72 migration.';
            }
            else{$error='Post could not be saved. Please check it again and retry.';}
        }finally{
            if($locked){$release=$pdo->prepare('SELECT RELEASE_LOCK(?)');$release->execute([$lockName]);}
        }
        if($error!==null){
            if($isAjax){$this->json([
                'ok'=>false,
                'message'=>$error,
                'duplicate_url'=>$duplicateUrl,
                'duplicate_title'=>$duplicateTitle,
                'duplicate_kind'=>$duplicateKind,
            ],422);}
            $_SESSION['flash_error']=$error;$this->redirect('/sales/submit');
        }
        $dashboardPath='/sales?period=single&to='.rawurlencode($inspection['published_date']);
        $savedMessage=(string)($inspection['verification_status']??'')==='manual_pending'
            ? 'Post saved for Admin verification on '.$inspection['published_date'].'.'
            : 'Post saved to '.$inspection['published_date'].'.';
        if($isAjax){
            $this->json([
                'ok'=>true,
                'post_id'=>(int)$postId,
                'message'=>$savedMessage,
                'published_date'=>$inspection['published_date'],
                'dashboard_url'=>rtrim($config['app']['base_path'],'/').$dashboardPath,
            ]);
        }
        $_SESSION['flash_success']=$savedMessage;
        $this->redirect($dashboardPath);
    }

    /**
     * EN: Handle the request delete HTTP action for sales controller and return the appropriate response.
     * 中文：处理 sales controller 的“request delete”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function requestDelete(): void
    {
        $u = Auth::requireRole('sales');
        Csrf::verify($_POST['_csrf'] ?? null);

        $postId = (int)($_POST['post_id'] ?? 0);
        $reason = trim((string)($_POST['reason'] ?? ''));

        $isAjax=strtolower(
            (string)($_SERVER['HTTP_X_REQUESTED_WITH']??'')
        )==='xmlhttprequest';

        try{
            Post::requestDeletion((int)$u['id'], $postId, $reason);
        }catch(\DomainException $e){
            if($isAjax){$this->json(['ok'=>false,'message'=>$e->getMessage()],422);}
            $_SESSION['flash_error']=$e->getMessage();
            $this->redirect('/sales');
        }

        if($isAjax){
            $this->json([
                'ok'=>true,
                'post_id'=>$postId,
                'message'=>'Deletion request sent to Admin.',
            ]);
        }

        $_SESSION['flash_success'] = 'Deletion request sent to Admin.';
        $this->redirect('/sales');
    }
}
