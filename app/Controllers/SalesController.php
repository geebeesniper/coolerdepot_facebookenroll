<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Models\Post;
use App\Models\Inspection;
use App\Models\User;

class SalesController extends Controller
{
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
            'posts'=>$posts,
        ]);
    }

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
            'channel'=>$activeChannel,
            'period'=>$rangePeriod,
        ]);
    }

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



    public function submitForm(): void
    {
        $u=Auth::requireRole('sales');

        $this->render('sales/submit',[
            'user'=>$u,
        ]);
    }

    public function save(): void
    {
        global $config;
        $u=Auth::requireRole('sales');
        $isAjax=strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH']??''))==='xmlhttprequest';
        Csrf::verify($_POST['_csrf']??null);
        $token=trim((string)($_POST['inspection_token']??''));
        $inspection=Inspection::verified($token,(int)$u['id']);
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
            $inspection=Inspection::verified($token,(int)$u['id'],true);
            if(!$inspection){throw new \DomainException('Verification expired or was already saved. Check the post again.');}
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
                    $dup=Post::duplicate(
                        (int)$inspection['sales_user_id'],
                        (string)$inspection['platform'],
                        $inspection['canonical_url']??null,
                        $inspection['external_post_id']??null,
                        $inspection['title']??null,
                        $inspection['description']??null
                    );
                    if($dup){
                        $duplicateUrl=$dup['canonical_url']??null;
                        $duplicateTitle=$dup['title']??null;
                        $duplicateKind=$dup['kind']??null;
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
        $savedMessage='Post saved to '.$inspection['published_date'].'.';
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
