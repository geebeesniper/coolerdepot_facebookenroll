<?php
namespace App\Controllers;use App\Core\Controller;use App\Core\Auth;use App\Services\ExternalAuthService;
class AuthHandoffController extends Controller{public function handoff():void{try{$user=(new ExternalAuthService())->accept(array_merge($_GET,$_POST));Auth::login($user,'coolerdepot_handoff');$this->redirect($user['role']==='admin'?'/admin':'/sales');}catch(\Throwable$e){http_response_code(401);$this->render('auth/handoff_error',['message'=>$e->getMessage()]);}}}
