<?php
namespace App\Controllers;
use App\Core\Controller;use App\Core\Auth;use App\Core\Csrf;use App\Core\Database;use App\Models\Post;use App\Models\Inspection;
class SalesController extends Controller{
    public function dashboard():void{
        $user=Auth::requireRole('sales');$from=$_GET['from']??date('Y-m-01');$to=$_GET['to']??date('Y-m-d');
        $posts=Post::forSales((int)$user['id'],$from,$to);$counts=Post::dailyCounts((int)$user['id'],$from,$to);$this->render('sales/dashboard',compact('user','posts','counts','from','to'));
    }
    public function submitForm():void{$user=Auth::requireRole('sales');$this->render('sales/submit',compact('user'));}
    public function save():void{
        $user=Auth::requireRole('sales');Csrf::verify($_POST['_csrf']??null);$i=Inspection::verified(trim((string)($_POST['inspection_token']??'')),(int)$user['id']);
        if(!$i){$_SESSION['flash_error']='Inspection expired or is not verified. Check the post again.';$this->redirect('/sales/submit');}
        if($d=Post::duplicate((int)$user['id'],$i['platform'],$i['canonical_url'],$i['external_post_id'],$i['title'],$i['description'])){$_SESSION['flash_error']=$d['reason'];$this->redirect('/sales/submit');}
        $pdo=Database::connection();
        try{$pdo->beginTransaction();Post::create($i);Inspection::consume((int)$i['id']);$pdo->commit();}
        catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$_SESSION['flash_error']='Save failed; the post may now be a duplicate.';$this->redirect('/sales/submit');}
        $_SESSION['flash_success']='Post saved.';$this->redirect('/sales');
    }
    public function requestDelete():void{
        $u=Auth::requireRole('sales');Csrf::verify($_POST['_csrf']??null);$pid=(int)($_POST['post_id']??0);$reason=trim((string)($_POST['reason']??''));
        $s=Database::connection()->prepare("INSERT INTO cdsp_deletion_requests(post_id,requested_by,reason,status,created_at,updated_at)
        SELECT id,?,?,'pending',NOW(),NOW() FROM cdsp_sales_posts WHERE id=? AND sales_user_id=? AND deleted_at IS NULL");
        $s->execute([(int)$u['id'],$reason,$pid,(int)$u['id']]);$_SESSION['flash_success']='Deletion request sent to Admin.';$this->redirect('/sales');
    }
}
