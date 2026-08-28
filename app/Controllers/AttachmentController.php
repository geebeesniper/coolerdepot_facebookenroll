<?php
namespace App\Controllers;
use App\Core\Controller;use App\Core\Auth;use App\Core\Database;
class AttachmentController extends Controller{
    public function show():void{
        Auth::requireLogin();$id=(int)($_GET['id']??0);$s=Database::connection()->prepare("SELECT * FROM review_attachments WHERE id=? LIMIT 1");$s->execute([$id]);$a=$s->fetch();
        if(!$a){http_response_code(404);exit('Not found');}$base=realpath(dirname(__DIR__,2).'/storage/uploads');$path=realpath(dirname(__DIR__,2).'/storage/uploads/'.$a['stored_path']);
        if(!$base||!$path||strpos($path,$base)!==0||!is_file($path)){http_response_code(404);exit('Not found');}
        header('Content-Type: '.$a['mime_type']);header('Content-Length: '.filesize($path));readfile($path);exit;
    }
}
