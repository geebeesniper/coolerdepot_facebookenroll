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
        $csrf=(string)($_POST['_csrf']??'');
        if(!$csrf||empty($_SESSION['_csrf'])||!hash_equals((string)$_SESSION['_csrf'],$csrf)){
            if($isAjax){$this->json(['ok'=>false,'message'=>'Security token expired. Refresh and try again.'],419);}
            http_response_code(419);exit('CSRF validation failed');
        }
        $token=trim((string)($_POST['inspection_token']??''));
        $inspection=Inspection::verified($token,(int)$u['id']);
        if(!$inspection){
            if($isAjax){$this->json(['ok'=>false,'message'=>'Verification expired or invalid. Check the post again.'],422);}
            $_SESSION['flash_error']='Verification expired or invalid. Check the post again.';
            $this->redirect('/sales/submit');
        }
        $pdo=Database::connection();
        $pdo->beginTransaction();

        try{
            $postId=Post::create($inspection);
            Inspection::consume((int)$inspection['id']);
            $pdo->commit();
        }catch(\Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            if($isAjax){$this->json(['ok'=>false,'message'=>$e->getMessage()],422);}
            throw $e;
        }
        if($isAjax){
            $this->json([
                'ok'=>true,
                'post_id'=>(int)$postId,
                'message'=>'Post saved.',
                'dashboard_url'=>rtrim($config['app']['base_path'],'/').'/sales',
            ]);
        }
        $_SESSION['flash_success']='Post saved.';
        $this->redirect('/sales');
    }

    public function requestDelete(): void
    {
        $u = Auth::requireRole('sales');
        Csrf::verify($_POST['_csrf'] ?? null);

        $postId = (int)($_POST['post_id'] ?? 0);
        $reason = trim((string)($_POST['reason'] ?? ''));

        Post::requestDeletion((int)$u['id'], $postId, $reason);

        $isAjax=strtolower(
            (string)($_SERVER['HTTP_X_REQUESTED_WITH']??'')
        )==='xmlhttprequest';

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
