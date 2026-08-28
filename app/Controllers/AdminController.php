<?php
namespace App\Controllers;

use App\Services\HtmlNoteSanitizer;
use App\Core\Controller;use App\Core\Auth;use App\Core\Csrf;use App\Core\Database;use App\Models\Post;use App\Models\User;use App\Services\UploadService;
class AdminController extends Controller{
    public function dashboard():void{
        $admin=Auth::requireRole('admin');
        $date=(string)($_GET['date']??date('Y-m-d'));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date=date('Y-m-d');
        }

        $salesFilter=(int)($_GET['sales_id']??0);
        $sales=User::allSales();
        $validSalesIds=array_map(
            static fn(array $row): int => (int)$row['id'],
            $sales
        );

        if ($salesFilter > 0
            && !in_array($salesFilter,$validSalesIds,true)) {
            $salesFilter=0;
        }

        $salesProgress=Post::adminDailySalesProgress($date);
        $dashboardState=Post::adminDashboardState($date);
        $posts=Post::adminQueue($date,$salesFilter);

        $selectedSalesName='All Sales';

        if ($salesFilter > 0) {
            foreach ($sales as $salesUser) {
                if ((int)$salesUser['id']===$salesFilter) {
                    $selectedSalesName=(string)$salesUser['display_name'];
                    break;
                }
            }
        }

        $s=Database::connection()->query(
            "SELECT d.*,p.title,u.display_name
             FROM cdsp_deletion_requests d
             JOIN cdsp_sales_posts p ON p.id=d.post_id
             JOIN cdsp_users u ON u.id=p.sales_user_id
             WHERE d.status='pending'
             ORDER BY d.created_at"
        );
        $deletionRequests=$s->fetchAll();

        $this->render(
            'admin/dashboard',
            compact(
                'admin',
                'date',
                'posts',
                'sales',
                'salesFilter',
                'selectedSalesName',
                'salesProgress',
                'dashboardState',
                'deletionRequests'
            )
        );
    }

    public function saveSalesTarget():void{
        $admin=Auth::requireRole('admin');
        Csrf::verify($_POST['_csrf']??null);

        $salesUserId=(int)($_POST['sales_user_id']??0);
        $target=(int)($_POST['target']??0);

        if ($salesUserId < 1) {
            $this->json([
                'ok'=>false,
                'message'=>'Sales user is required.',
            ],422);
        }

        if ($target < 1 || $target > 999) {
            $this->json([
                'ok'=>false,
                'field'=>'target',
                'message'=>'Target must be between 1 and 999.',
            ],422);
        }

        $salesUser=User::find($salesUserId);

        if (!$salesUser
            || ($salesUser['role']??'')!=='sales'
            || !(int)($salesUser['active']??0)) {
            $this->json([
                'ok'=>false,
                'message'=>'Sales user was not found.',
            ],404);
        }

        User::setDailyPostTarget($salesUserId,$target);

        $this->json([
            'ok'=>true,
            'target'=>$target,
            'message'=>'Daily target saved.',
        ]);
    }

    public function dashboardUpdates():void{
        Auth::requireRole('admin');

        $date=(string)($_GET['date']??date('Y-m-d'));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->json([
                'ok'=>false,
                'message'=>'Invalid dashboard date.',
            ],422);
        }

        if (session_status()===PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $state=Post::adminDashboardState($date);

        header(
            'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
        );

        $this->json([
            'ok'=>true,
            'date'=>$date,
            'post_count'=>$state['post_count'],
            'max_post_id'=>$state['max_post_id'],
        ]);
    }

    public function postReview():void{
        $admin=Auth::requireRole('admin');$post=Post::find((int)($_GET['id']??0));if(!$post){http_response_code(404);exit('Post not found');}
        $s=Database::connection()->prepare("SELECT * FROM cdsp_post_reviews WHERE post_id=? LIMIT 1");$s->execute([$post['id']]);$review=$s->fetch()?:null;
        $attachments=$review?$this->attachments('post_review',(int)$review['id']):[];$this->render('admin/post_review',compact('admin','post','review','attachments'));
    }
    public function savePostReview():void{
        $admin=Auth::requireRole('admin');Csrf::verify($_POST['_csrf']??null);$pid=(int)($_POST['post_id']??0);$decision=(string)($_POST['decision']??'');$note = HtmlNoteSanitizer::clean((string)($_POST['note'] ?? ''));
        if(!in_array($decision,['good','bad'],true)){$_SESSION['flash_error']='Choose Good or Bad.';$this->redirect('/admin/post?id='.$pid);}
        $pdo=Database::connection();$s=$pdo->prepare("INSERT INTO cdsp_post_reviews(post_id,admin_user_id,decision,rating,note,reviewed_at,created_at,updated_at) VALUES(?,?,?,NULL,?,NOW(),NOW(),NOW())
        ON DUPLICATE KEY UPDATE admin_user_id=VALUES(admin_user_id),decision=VALUES(decision),rating=NULL,note=VALUES(note),reviewed_at=NOW(),updated_at=NOW()");
        $s->execute([$pid,(int)$admin['id'],$decision,$note]);$s=$pdo->prepare("UPDATE cdsp_sales_posts SET admin_review_status=?,updated_at=NOW() WHERE id=?");$s->execute([$decision,$pid]);
        $s=$pdo->prepare("SELECT id FROM cdsp_post_reviews WHERE post_id=?");$s->execute([$pid]);$rid=(int)$s->fetchColumn();(new UploadService())->save('post_review',$rid,(int)$admin['id']);
        $_SESSION['flash_success']='Post review saved.';$this->redirect('/admin/post?id='.$pid);
    }
    public function dailyReview():void{
        $admin=Auth::requireRole('admin');$sid=(int)($_GET['sales_id']??0);$date=$_GET['date']??date('Y-m-d');$salesUser=User::find($sid);
        if(!$salesUser||$salesUser['role']!=='sales'){http_response_code(404);exit('Sales user not found');}
        $posts=Post::forSales($sid,$date,$date);$s=Database::connection()->prepare("SELECT * FROM cdsp_daily_sales_reviews WHERE sales_user_id=? AND work_date=? LIMIT 1");$s->execute([$sid,$date]);$review=$s->fetch()?:null;
        $attachments=$review?$this->attachments('daily_review',(int)$review['id']):[];$this->render('admin/daily_review',compact('admin','salesUser','date','posts','review','attachments'));
    }
    public function saveDailyReview():void{
        $admin=Auth::requireRole('admin');Csrf::verify($_POST['_csrf']??null);$sid=(int)$_POST['sales_user_id'];$date=(string)$_POST['work_date'];$note = HtmlNoteSanitizer::clean((string)($_POST['note'] ?? ''));
        $pdo=Database::connection();$s=$pdo->prepare("INSERT INTO cdsp_daily_sales_reviews(sales_user_id,work_date,admin_user_id,rating,note,reviewed_at,created_at,updated_at) VALUES(?,?,?,NULL,?,NOW(),NOW(),NOW())
        ON DUPLICATE KEY UPDATE admin_user_id=VALUES(admin_user_id),rating=NULL,note=VALUES(note),reviewed_at=NOW(),updated_at=NOW()");
        $s->execute([$sid,$date,(int)$admin['id'],$note]);$s=$pdo->prepare("SELECT id FROM cdsp_daily_sales_reviews WHERE sales_user_id=? AND work_date=?");$s->execute([$sid,$date]);$rid=(int)$s->fetchColumn();(new UploadService())->save('daily_review',$rid,(int)$admin['id']);
        $_SESSION['flash_success']='Daily review saved.';$this->redirect('/admin/daily?sales_id='.$sid.'&date='.urlencode($date));
    }
    public function reports():void{
        $admin=Auth::requireRole('admin');$period=$_GET['period']??'week';$sid=(int)($_GET['sales_id']??0);$sales=User::allSales();
        if($period==='month'){$start=$_GET['start']??date('Y-m-01');$end=date('Y-m-t',strtotime($start));}else{$period='week';$start=$_GET['start']??date('Y-m-d',strtotime('monday this week'));$end=date('Y-m-d',strtotime($start.' +6 days'));}
        $params=[$start.' 00:00:00',$end.' 23:59:59'];$filter='';if($sid>0){$filter=' AND p.sales_user_id=?';$params[]=$sid;}
        $sql="SELECT u.id sales_user_id,u.display_name,COUNT(p.id) total_posts,SUM(p.platform='facebook') facebook_posts,SUM(p.platform='offerup') offerup_posts,SUM(p.platform='craigslist') craigslist_posts,SUM(p.admin_review_status='good') good_posts,SUM(p.admin_review_status='bad') bad_posts
        FROM cdsp_users u LEFT JOIN cdsp_sales_posts p ON p.sales_user_id=u.id AND p.created_at BETWEEN ? AND ? AND p.deleted_at IS NULL WHERE u.role='sales' AND u.active=1 {$filter} GROUP BY u.id,u.display_name ORDER BY total_posts DESC,u.display_name";
        $s=Database::connection()->prepare($sql);$s->execute($params);$rows=$s->fetchAll();$salesUserId=$sid;$this->render('admin/reports',compact('admin','period','start','end','salesUserId','sales','rows'));
    }
    public function savePeriodReview():void{
        $admin=Auth::requireRole('admin');Csrf::verify($_POST['_csrf']??null);$sid=(int)$_POST['sales_user_id'];$type=(string)$_POST['period_type'];$start=(string)$_POST['period_start'];$end=(string)$_POST['period_end'];$note = HtmlNoteSanitizer::clean((string)($_POST['note'] ?? ''));
        $s=Database::connection()->prepare("INSERT INTO cdsp_period_sales_reviews(sales_user_id,period_type,period_start,period_end,admin_user_id,rating,note,reviewed_at,created_at,updated_at) VALUES(?,?,?,?,?,NULL,?,NOW(),NOW(),NOW())
        ON DUPLICATE KEY UPDATE period_end=VALUES(period_end),admin_user_id=VALUES(admin_user_id),rating=NULL,note=VALUES(note),reviewed_at=NOW(),updated_at=NOW()");
        $s->execute([$sid,$type,$start,$end,(int)$admin['id'],$note]);$s=Database::connection()->prepare("SELECT id FROM cdsp_period_sales_reviews WHERE sales_user_id=? AND period_type=? AND period_start=?");$s->execute([$sid,$type,$start]);$rid=(int)$s->fetchColumn();(new UploadService())->save('period_review',$rid,(int)$admin['id']);
        $_SESSION['flash_success']=ucfirst($type).' review saved.';$this->redirect('/admin/reports?period='.$type.'&start='.urlencode($start).'&sales_id='.$sid);
    }
    public function handleDeleteRequest():void{
        $admin=Auth::requireRole('admin');Csrf::verify($_POST['_csrf']??null);$id=(int)$_POST['request_id'];$action=(string)$_POST['action'];$pdo=Database::connection();$pdo->beginTransaction();
        try{$s=$pdo->prepare("SELECT * FROM cdsp_deletion_requests WHERE id=? AND status='pending' FOR UPDATE");$s->execute([$id]);$r=$s->fetch();if(!$r)throw new \RuntimeException('Request not found');
            $status=$action==='approve'?'approved':'rejected';if($status==='approved'){$s=$pdo->prepare("UPDATE cdsp_sales_posts SET deleted_at=NOW(),deleted_by=?,updated_at=NOW() WHERE id=?");$s->execute([(int)$admin['id'],$r['post_id']]);}
            $s=$pdo->prepare("UPDATE cdsp_deletion_requests SET status=?,reviewed_by=?,reviewed_at=NOW(),updated_at=NOW() WHERE id=?");$s->execute([$status,(int)$admin['id'],$id]);$pdo->commit();}
        catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$_SESSION['flash_error']=$e->getMessage();$this->redirect('/admin');}
        $_SESSION['flash_success']='Deletion request '.$status.'.';$this->redirect('/admin');
    }
    private function attachments(string $type,int $id):array{$s=Database::connection()->prepare("SELECT * FROM cdsp_review_attachments WHERE entity_type=? AND entity_id=? ORDER BY created_at");$s->execute([$type,$id]);return$s->fetchAll();}
}
