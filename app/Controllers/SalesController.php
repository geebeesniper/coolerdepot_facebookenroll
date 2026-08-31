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

        $hasExplicitRange=(
            isset($_GET['from'])
            ||isset($_GET['to'])
        );

        $requestedPeriod=strtolower(
            trim(
                (string)($_GET['period']??'')
            )
        );

        if(
            !in_array(
                $requestedPeriod,
                ['day','week','month','custom'],
                true
            )
        ){
            $requestedPeriod=$hasExplicitRange
                ?'custom'
                :'month';
        }

        $rangePeriod=$requestedPeriod;

        if(
            in_array(
                $rangePeriod,
                ['day','week','month'],
                true
            )
        ){
            [$from,$to]=$this->salesPresetRange(
                $rangePeriod,
                $to,
                $today
            );
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

        $initialLimit=$this->salesCalendarInitialLimit(
            $rangePeriod,
            (int)$config['app']['daily_posts_initial_days']
        );

        $calendarPage=$this->salesCalendarDaySections(
            (int)$u['id'],
            $from,
            $to,
            $platformFilter,
            $initialLimit,
            0
        );

        $days=$calendarPage['days'];
        $totalDays=$calendarPage['total_days'];

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

        $rangePeriod=strtolower(
            trim(
                (string)($_GET['period']??'custom')
            )
        );

        if(
            !in_array(
                $rangePeriod,
                ['day','week','month','custom'],
                true
            )
        ){
            $rangePeriod='custom';
        }

        if(
            in_array(
                $rangePeriod,
                ['day','week','month'],
                true
            )
        ){
            [$from,$to]=$this->salesPresetRange(
                $rangePeriod,
                $to,
                $today
            );
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

        if($offset===0){
            $limit=$this->salesCalendarInitialLimit(
                $rangePeriod,
                $limit
            );
        }

        $calendarPage=$this->salesCalendarDaySections(
            (int)$u['id'],
            $from,
            $to,
            $platformFilter,
            $limit,
            $offset
        );

        $days=$calendarPage['days'];
        $nextOffset=$calendarPage['next_offset'];
        $totalDays=$calendarPage['total_days'];

        ob_start();
        foreach ($days as $day) {
            $this->renderPartial(
                'sales/_daily_post_section',
                ['day' => $day]
            );
        }
        $html = ob_get_clean();
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

    if($period==='day'){
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


private function salesCalendarInitialLimit(
    string $period,
    int $configuredLimit
): int {
    if($period==='day'){
        return 3;
    }

    if($period==='week'){
        return 7;
    }

    /*
     * Monthly / Custom should still surface a useful block immediately:
     * enough calendar days to include nearby posts AND the empty dates
     * around them. Older dates continue through Load Earlier.
     */
    return max(
        10,
        $configuredLimit
    );
}

private function salesCalendarDaySections(
    int $salesUserId,
    string $from,
    string $to,
    ?string $platformFilter,
    int $limit,
    int $offset
): array {
    $start=new \DateTimeImmutable(
        $from.' 12:00:00'
    );

    $end=new \DateTimeImmutable(
        $to.' 12:00:00'
    );

    $totalDays=
        (int)$start
            ->diff($end)
            ->days
        +1;

    $offset=max(
        0,
        min(
            $offset,
            $totalDays
        )
    );

    $limit=max(
        1,
        $limit
    );

    $cursor=$end->modify(
        '-'.$offset.' days'
    );

    $dates=[];

    while(
        $cursor >= $start
        &&count($dates)<$limit
    ){
        $dates[]=$cursor->format(
            'Y-m-d'
        );

        $cursor=$cursor->modify(
            '-1 day'
        );
    }

    if(!$dates){
        return [
            'days'=>[],
            'total_days'=>$totalDays,
            'next_offset'=>$offset,
        ];
    }

    $pageFrom=end($dates);
    $pageTo=$dates[0];

    $posts=Post::forSalesPublishedRange(
        $salesUserId,
        $pageFrom,
        $pageTo,
        $platformFilter
    );

    $postsByDate=[];

    foreach($posts as $post){
        $date=(string)$post['published_date'];

        if(!isset($postsByDate[$date])){
            $postsByDate[$date]=[];
        }

        $postsByDate[$date][]=$post;
    }

    $days=[];

    foreach($dates as $date){
        $datePosts=
            $postsByDate[$date]
            ??[];

        $good=0;
        $bad=0;

        foreach($datePosts as $post){
            $status=(string)(
                $post['current_review_status']
                ??''
            );

            if($status==='good'){
                $good++;
            }elseif($status==='bad'){
                $bad++;
            }
        }

        $days[]=[
            'date'=>$date,
            'post_count'=>count($datePosts),
            'good_count'=>$good,
            'bad_count'=>$bad,
            'posts'=>$datePosts,
        ];
    }

    $nextOffset=
        $offset
        +count($dates);

    return [
        'days'=>$days,
        'total_days'=>$totalDays,
        'next_offset'=>$nextOffset,
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
