<?php
/**
 * File / 文件：app/Controllers/HelpController.php
 * EN: Serves the bundled role-specific Sales/Admin user manuals to authenticated users.
 * 中文：向已登录用户提供随程序打包的 Sales/Admin 角色使用说明。
 * Maintenance / 维护：Keep role isolation explicit; a user should only receive the guide for the current authenticated role.
 * 维护要求：角色隔离必须清晰；用户只能读取其当前登录角色对应的说明。
 */
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\ErrorPage;
use App\Core\Logger;

/**
 * EN: HTTP controller for authenticated role-specific in-application help.
 * 中文：负责已登录用户角色专属站内帮助页面的 HTTP Controller。
 */
class HelpController extends Controller
{
    /**
     * EN: Serve the Sales or Admin guide selected from the current authenticated role.
     * 中文：根据当前已登录角色输出 Sales 或 Admin 对应的使用说明。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function show(): void
    {
        $user = Auth::requireLogin();
        $role = (string)($user['role'] ?? '');

        if (!in_array($role, ['sales', 'admin'], true)) {
            Logger::httpStatus(403, ['event' => 'help_role_denied', 'role' => $role]);
            ErrorPage::render(403, 'Your account does not have access to this help area.');
        }

        $file = dirname(__DIR__, 2) . '/docs/user-guides/' . $role . '.html';
        if (!is_file($file)) {
            Logger::httpStatus(404, ['event' => 'help_guide_missing', 'role' => $role]);
            ErrorPage::render(404, 'The user guide is not available.');
        }

        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: private, no-store, max-age=0');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        readfile($file);
        exit;
    }
}
