<?php
/**
 * File / 文件：app/Controllers/AuthHandoffController.php
 * EN: Defines the AuthHandoffController HTTP controller and request/response actions.
 * 中文：定义 AuthHandoffController HTTP Controller 及其请求/响应操作。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Logger;
use App\Services\ExternalAuthService;

/**
 * EN: HTTP controller for auth handoff requests, responses, and server-side authorization.
 * 中文：负责 auth handoff 请求、响应及服务器端权限控制的 HTTP Controller。
 */
class AuthHandoffController extends Controller
{
    /**
     * EN: Accept a signed browser SSO handoff and establish the corresponding local authenticated session.
     * 中文：接收浏览器签名式 SSO 交接，并建立对应的本地认证 Session。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function handoff(): void
    {
        try {
            $user = (new ExternalAuthService())->accept(array_merge($_GET, $_POST));
            Auth::login($user, 'coolerdepot_handoff');
            $this->redirect($user['role'] === 'admin' ? '/admin' : '/sales');
        } catch (\Throwable $e) {
            Logger::exception(
                $e,
                'auth',
                ['event' => 'Signed authentication handoff failed'],
                'warning'
            );
            Logger::httpStatus(401, ['event' => 'auth_handoff_rejected']);
            http_response_code(401);
            $this->render('auth/handoff_error', ['message' => $e->getMessage()]);
        }
    }
}
