<?php
/**
 * File / 文件：app/Controllers/HelpController.php
 * EN: Renders the bundled role-specific Help as a normal application page.
 * 中文：将角色专属 Help 作为系统标准页面渲染，而不是独立 HTML 页面。
 */
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\ErrorPage;
use App\Core\Logger;

class HelpController extends Controller
{
    public function show(): void
    {
        $user = Auth::requireLogin();
        $role = (string)($user['role'] ?? '');

        if (!in_array($role, ['sales', 'admin'], true)) {
            Logger::httpStatus(403, ['event' => 'help_role_denied', 'role' => $role]);
            ErrorPage::render(403, 'Your account does not have access to this help area.');
        }

        $partial = dirname(__DIR__) . '/Views/help/' . $role . '.php';
        if (!is_file($partial)) {
            Logger::httpStatus(404, ['event' => 'help_view_missing', 'role' => $role]);
            ErrorPage::render(404, 'The user guide is not available.');
        }

        header('Cache-Control: private, no-store, max-age=0');
        header('Pragma: no-cache');
        $this->render('help', ['helpRole' => $role]);
    }
}
