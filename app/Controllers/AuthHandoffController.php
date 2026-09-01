<?php
/**
 * File / 文件：app/Controllers/AuthHandoffController.php
 * EN: HTTP controller for request validation, orchestration, and responses.
 * 中文：该文件负责 HTTP 请求校验、业务编排与响应。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Logger;
use App\Services\ExternalAuthService;

class AuthHandoffController extends Controller
{
    /**
     * EN: Implements the application operation `handoff` (handoff).
     * 中文：实现应用操作 `handoff`（handoff）。
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
