<?php
namespace App\Controllers;
use App\Core\Controller;use App\Core\Auth;use App\Core\Csrf;use App\Core\Database;use App\Models\Post;use App\Models\User;use App\Services\UploadService;
class AdminController extends Controller{
    public function dashboard():void{
        $admin=Auth::requireRole('admin');$date=$_GET['date']??date('Y-m-d');$posts=Post::adminQueue($date);$sales=User::allSales();
        $s=Database::connection()->query("SELECT d.*,p.title,u.display_name FROM deletion_requests d JOIN sales_posts p ON p.id=d.post_id JOIN users u ON u.id=p.sales_user_id WHERE d.status='pending' ORDER BY d.created_at");
        $deletionRequests=$s->fetchAll();$this->render('admin/dashboard',compact('admin','date','posts','sales','deletionRequests'));
    }
    public function postReview():void{
        $admin=Auth::requireRole('admin');$post=Post::find((int)($_GET['id']??0));if(!$post){http_response_code(404);exit('Post not found');}
        $s=Database::connection()->prepare("SELECT * FROM post_reviews WHERE post_id=? LIMIT 1");$s->execute([$post['id']]);$review=$s->fetch()?:null;
        $attachments=$review?$this->attachments('post_review',(int)$review['id']):[];$this->render('admin/post_review',compact('admin','post','review','attachments'));
    }
    public function savePostReview():void{
        $admin=Auth::requireRole('admin');Csrf::verify($_POST['_csrf']??null);$pid=(int)($_POST['post_id']??0);$decision=(string)($_POST['decision']??'');$rating=max(1,min(5,(int)($_POST['rating']??3)));$note=trim((string)($_POST['note']??''));
        if(!in_array($decision,['approved','rejected'],true)){$_SESSION['flash_error']='Choose Approve or Reject.';$this->redirect('/admin/post?id='.$pid);}
        $pdo=Database::connection();$s=$pdo->prepare("INSERT INTO post_reviews(post_id,admin_user_id,decision,rating,note,reviewed_at,created_at,updated_at) VALUES(?,?,?,?,?,NOW(),NOW(),NOW())
        ON DUPLICATE KEY UPDATE admin_user_id=VALUES(admin_user_id),decision=VALUES(decision),rating=VALUES(rating),note=VALUES(note),reviewed_at=NOW(),updated_at=NOW()");
        $s->execute([$pid,(int)$admin['id'],$decision,$rating,$note]);$s=$pdo->prepare("UPDATE sales_posts SET admin_review_status=?,updated_at=NOW() WHERE id=?");$s->execute([$decision,$pid]);
        $s=$pdo->prepare("SELECT id FROM post_reviews WHERE post_id=?");$s->execute([$pid]);$rid=(int)$s->fetchColumn();(new UploadService())->save('post_review',$rid,(int)$admin['id']);
        $_SESSION['flash_success']='Post review saved.';$this->redirect('/admin/post?id='.$pid);
    }
    public function dailyReview():void{
        $admin=Auth::requireRole('admin');$sid=(int)($_GET['sales_id']??0);$date=$_GET['date']??date('Y-m-d');$salesUser=User::find($sid);
        if(!$salesUser||$salesUser['role']!=='sales'){http_response_code(404);exit('Sales user not found');}
        $posts=Post::forSales($sid,$date,$date);$s=Database::connection()->prepare("SELECT * FROM daily_sales_reviews WHERE sales_user_id=? AND work_date=? LIMIT 1");$s->execute([$sid,$date]);$review=$s->fetch()?:null;
        $attachments=$review?$this->attachments('daily_review',(int)$review['id']):[];$this->render('admin/daily_review',compact('admin','salesUser','date','posts','review','attachments'));
    }
    public function saveDailyReview():void{
        $admin=Auth::requireRole('admin');Csrf::verify($_POST['_csrf']??null);$sid=(int)$_POST['sales_user_id'];$date=(string)$_POST['work_date'];$rating=max(1,min(5,(int)$_POST['rating']));$note=trim((string)($_POST['note']??''));
        $pdo=Database::connection();$s=$pdo->prepare("INSERT INTO daily_sales_reviews(sales_user_id,work_date,admin_user_id,rating,note,reviewed_at,created_at,updated_at) VALUES(?,?,?,?,?,NOW(),NOW(),NOW())
        ON DUPLICATE KEY UPDATE admin_user_id=VALUES(admin_user_id),rating=VALUES(rating),note=VALUES(note),reviewed_at=NOW(),updated_at=NOW()");
        $s->execute([$sid,$date,(int)$admin['id'],$rating,$note]);$s=$pdo->prepare("SELECT id FROM daily_sales_reviews WHERE sales_user_id=? AND work_date=?");$s->execute([$sid,$date]);$rid=(int)$s->fetchColumn();(new UploadService())->save('daily_review',$rid,(int)$admin['id']);
        $_SESSION['flash_success']='Daily review saved.';$this->redirect('/admin/daily?sales_id='.$sid.'&date='.urlencode($date));
    }
    public function reports():void{
        $admin=Auth::requireRole('admin');$period=$_GET['period']??'week';$sid=(int)($_GET['sales_id']??0);$sales=User::allSales();
        if($period==='month'){$start=$_GET['start']??date('Y-m-01');$end=date('Y-m-t',strtotime($start));}else{$period='week';$start=$_GET['start']??date('Y-m-d',strtotime('monday this week'));$end=date('Y-m-d',strtotime($start.' +6 days'));}
        $params=[$start.' 00:00:00',$end.' 23:59:59'];$filter='';if($sid>0){$filter=' AND p.sales_user_id=?';$params[]=$sid;}
        $sql="SELECT u.id sales_user_id,u.display_name,COUNT(p.id) total_posts,SUM(p.platform='facebook') facebook_posts,SUM(p.platform='offerup') offerup_posts,SUM(p.platform='craigslist') craigslist_posts,SUM(p.admin_review_status='approved') approved_posts,SUM(p.admin_review_status='rejected') rejected_posts,SUM(p.admin_review_status='pending') pending_posts
        FROM users u LEFT JOIN sales_posts p ON p.sales_user_id=u.id AND p.created_at BETWEEN ? AND ? AND p.deleted_at IS NULL WHERE u.role='sales' AND u.active=1 {$filter} GROUP BY u.id,u.display_name ORDER BY total_posts DESC,u.display_name";
        $s=Database::connection()->prepare($sql);$s->execute($params);$rows=$s->fetchAll();$salesUserId=$sid;$this->render('admin/reports',compact('admin','period','start','end','salesUserId','sales','rows'));
    }
    public function savePeriodReview():void{
        $admin=Auth::requireRole('admin');Csrf::verify($_POST['_csrf']??null);$sid=(int)$_POST['sales_user_id'];$type=(string)$_POST['period_type'];$start=(string)$_POST['period_start'];$end=(string)$_POST['period_end'];$rating=max(1,min(5,(int)$_POST['rating']));$note=trim((string)($_POST['note']??''));
        $s=Database::connection()->prepare("INSERT INTO period_sales_reviews(sales_user_id,period_type,period_start,period_end,admin_user_id,rating,note,reviewed_at,created_at,updated_at) VALUES(?,?,?,?,?,?,?,NOW(),NOW(),NOW())
        ON DUPLICATE KEY UPDATE period_end=VALUES(period_end),admin_user_id=VALUES(admin_user_id),rating=VALUES(rating),note=VALUES(note),reviewed_at=NOW(),updated_at=NOW()");
        $s->execute([$sid,$type,$start,$end,(int)$admin['id'],$rating,$note]);$s=Database::connection()->prepare("SELECT id FROM period_sales_reviews WHERE sales_user_id=? AND period_type=? AND period_start=?");$s->execute([$sid,$type,$start]);$rid=(int)$s->fetchColumn();(new UploadService())->save('period_review',$rid,(int)$admin['id']);
        $_SESSION['flash_success']=ucfirst($type).' review saved.';$this->redirect('/admin/reports?period='.$type.'&start='.urlencode($start).'&sales_id='.$sid);
    }
    public function handleDeleteRequest():void{
        $admin=Auth::requireRole('admin');Csrf::verify($_POST['_csrf']??null);$id=(int)$_POST['request_id'];$action=(string)$_POST['action'];$pdo=Database::connection();$pdo->beginTransaction();
        try{$s=$pdo->prepare("SELECT * FROM deletion_requests WHERE id=? AND status='pending' FOR UPDATE");$s->execute([$id]);$r=$s->fetch();if(!$r)throw new \RuntimeException('Request not found');
            $status=$action==='approve'?'approved':'rejected';if($status==='approved'){$s=$pdo->prepare("UPDATE sales_posts SET deleted_at=NOW(),deleted_by=?,updated_at=NOW() WHERE id=?");$s->execute([(int)$admin['id'],$r['post_id']]);}
            $s=$pdo->prepare("UPDATE deletion_requests SET status=?,reviewed_by=?,reviewed_at=NOW(),updated_at=NOW() WHERE id=?");$s->execute([$status,(int)$admin['id'],$id]);$pdo->commit();}
        catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$_SESSION['flash_error']=$e->getMessage();$this->redirect('/admin');}
        $_SESSION['flash_success']='Deletion request '.$status.'.';$this->redirect('/admin');
    }
    private function attachments(string $type,int $id):array{$s=Database::connection()->prepare("SELECT * FROM review_attachments WHERE entity_type=? AND entity_id=? ORDER BY created_at");$s->execute([$type,$id]);return$s->fetchAll();}
}
