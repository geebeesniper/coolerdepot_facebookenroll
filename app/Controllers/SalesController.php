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
        global $config;

        $u = Auth::requireRole('sales');

        $today=date('Y-m-d');
        $to = $_GET['to'] ?? $today;
        $from = $_GET['from'] ?? date('Y-m-01');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $from = date('Y-m-01');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $to = $today;
        }

        if($to>$today){
            $to=$today;
        }

        if($from>$today){
            $from=$today;
        }

        if($from>$to){
            $from=$to;
        }

        $activeChannel=strtolower(
            trim((string)($_GET['channel']??'all'))
        );

        $allowedChannels=[
            'all',
            'facebook',
            'instagram',
            'offerup',
            'craigslist',
        ];

        if(!in_array(
            $activeChannel,
            $allowedChannels,
            true
        )){
            $activeChannel='all';
        }

        $platformFilter=$activeChannel==='all'
            ?null
            :$activeChannel;

        $rangePeriod=(string)($_GET['period']??'');

        if(!in_array($rangePeriod,['day','week','month','custom'],true)){
            if($from===$to){
                $rangePeriod='day';
            }elseif(
                $from===date('Y-m-01',strtotime($to))
                &&$to===min(
                    $today,
                    date('Y-m-t',strtotime($to))
                )
            ){
                $rangePeriod='month';
            }else{
                $rangePeriod='custom';
            }
        }

        $counts = Post::dailyCounts((int)$u['id'], $from, $to);
        $summary = Post::salesRangeSummary(
            (int)$u['id'],
            $from,
            $to,
            $platformFilter
        );
        $chartRows=Post::salesChartRows(
            (int)$u['id'],
            $from,
            $to,
            $platformFilter
        );
        $salesUser=User::find((int)$u['id']);
        $dailyTarget=max(
            1,
            (int)($salesUser['daily_post_target']??10)
        );

        $limit = max(1, (int)$config['app']['daily_posts_initial_days']);
        $dayRows = Post::dailyDatesForSales(
            (int)$u['id'],
            $from,
            $to,
            $limit,
            0,
            $platformFilter
        );
        $days = [];

        foreach ($dayRows as $row) {
            $date = $row['published_date'];
            $days[] = [
                'date' => $date,
                'post_count' => (int)$row['post_count'],
                'good_count' => (int)$row['good_count'],
                'bad_count' => (int)$row['bad_count'],
                'posts' => Post::forSalesOnDate(
                    (int)$u['id'],
                    $date,
                    $platformFilter
                ),
            ];
        }

        $totalDays = Post::dailyDateCountForSales(
            (int)$u['id'],
            $from,
            $to,
            $platformFilter
        );

        $this->render('sales/dashboard', [
            'user' => $u,
            'from' => $from,
            'to' => $to,
            'today'=>$today,
            'rangePeriod'=>$rangePeriod,
            'activeChannel'=>$activeChannel,
            'counts' => $counts,
            'summary' => $summary,
            'chartRows'=>$chartRows,
            'dailyTarget'=>$dailyTarget,
            'days' => $days,
            'loadedDays' => count($days),
            'totalDays' => $totalDays,
            'loadDays' => max(1, (int)$config['app']['daily_posts_load_days']),
        ]);
    }

    public function dailyPostsAjax(): void
    {
        global $config;

        $u = Auth::requireRole('sales');

        $today=date('Y-m-d');
        $from = (string)($_GET['from'] ?? date('Y-m-01'));
        $to = (string)($_GET['to'] ?? $today);
        $offset = max(0, (int)($_GET['offset'] ?? 0));
        $limit = max(1, min(10, (int)($_GET['limit'] ?? $config['app']['daily_posts_load_days'])));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $this->json(['ok' => false, 'message' => 'Invalid date range.'], 422);
        }

        if($to>$today){
            $to=$today;
        }

        if($from>$today){
            $from=$today;
        }

        if($from>$to){
            $from=$to;
        }

        $activeChannel=strtolower(
            trim((string)($_GET['channel']??'all'))
        );

        $allowedChannels=[
            'all',
            'facebook',
            'instagram',
            'offerup',
            'craigslist',
        ];

        if(!in_array(
            $activeChannel,
            $allowedChannels,
            true
        )){
            $activeChannel='all';
        }

        $platformFilter=$activeChannel==='all'
            ?null
            :$activeChannel;

        $rows = Post::dailyDatesForSales(
            (int)$u['id'],
            $from,
            $to,
            $limit,
            $offset,
            $platformFilter
        );
        $days = [];

        foreach ($rows as $row) {
            $date = $row['published_date'];
            $days[] = [
                'date' => $date,
                'post_count' => (int)$row['post_count'],
                'good_count' => (int)$row['good_count'],
                'bad_count' => (int)$row['bad_count'],
                'posts' => Post::forSalesOnDate(
                    (int)$u['id'],
                    $date,
                    $platformFilter
                ),
            ];
        }

        ob_start();
        foreach ($days as $day) {
            $this->renderPartial('sales/_daily_post_section', ['day' => $day]);
        }
        $html = ob_get_clean();

        $nextOffset = $offset + count($days);
        $totalDays = Post::dailyDateCountForSales(
            (int)$u['id'],
            $from,
            $to,
            $platformFilter
        );
        $summary=Post::salesRangeSummary(
            (int)$u['id'],
            $from,
            $to,
            $platformFilter
        );
        $chartRows=Post::salesChartRows(
            (int)$u['id'],
            $from,
            $to,
            $platformFilter
        );
        $salesUser=User::find((int)$u['id']);
        $dailyTarget=max(
            1,
            (int)($salesUser['daily_post_target']??10)
        );

        $this->json([
            'ok' => true,
            'html' => $html,
            'loaded' => count($days),
            'next_offset' => $nextOffset,
            'has_more' => $nextOffset < $totalDays,
            'total_days'=>$totalDays,
            'from'=>$from,
            'to'=>$to,
            'summary'=>$summary,
            'chart_rows'=>$chartRows,
            'daily_target'=>$dailyTarget,
            'channel'=>$activeChannel,
        ]);
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
