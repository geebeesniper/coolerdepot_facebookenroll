<?php
/**
 * File / 文件：app/Controllers/AdminMaintenanceController.php
 * EN: Admin-only browser controller for database compatibility repairs and guarded SQL execution.
 * 中文：仅供 Admin 使用的浏览器数据库兼容修复及受保护 SQL 执行 Controller。
 * Maintenance / 维护：Never expose these actions without Auth::requireRole('admin') and CSRF verification.
 * 维护要求：这些操作必须始终通过 Auth::requireRole('admin') 与 CSRF 验证保护。
 */
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Logger;
use App\Services\DatabaseMaintenance;
use Throwable;

/**
 * EN: Handles the Admin Maintenance page and its database operations.
 * 中文：处理 Admin Maintenance 页面及其数据库操作。
 */
final class AdminMaintenanceController extends Controller
{
    /**
     * EN: Render database checks, repair status, and the guarded SQL console.
     * 中文：渲染数据库检查、修复状态及受保护 SQL 控制台。
     */
    public function index(): void
    {
        Auth::requireRole('admin');
        $queryResult = $_SESSION['maintenance_query_result'] ?? null;
        $repairResults = $_SESSION['maintenance_repair_results'] ?? null;
        unset($_SESSION['maintenance_query_result'], $_SESSION['maintenance_repair_results']);

        try {
            $status = DatabaseMaintenance::status();
        } catch (Throwable $e) {
            // EN: The maintenance page is itself the recovery surface. Never let
            // a diagnostic exception hide behind the generic global 500 page.
            // 中文：Maintenance 页面本身就是恢复入口，因此诊断异常不能再被
            // 全局 500 页面遮住，必须在本页显示可读错误。
            Logger::exception($e, 'maintenance', ['event' => 'Maintenance page status failed'], 'error');
            $status = [
                'provider_registry' => ['error' => $e->getMessage()],
                'inspection_manual_pending' => ['error' => $e->getMessage()],
                'post_manual_pending' => ['error' => $e->getMessage()],
            ];
        }

        $this->render('admin/maintenance', compact('status', 'queryResult', 'repairResults'));
    }

    /**
     * EN: Run predefined compatibility repairs through the application's existing PDO connection.
     * 中文：通过应用现有 PDO 连接运行预定义数据库兼容修复。
     */
    public function runRepairs(): void
    {
        $admin = Auth::requireRole('admin');
        Csrf::verify($_POST['_csrf'] ?? null);

        try {
            $results = DatabaseMaintenance::runRecommendedRepairs((int)$admin['id']);
            $_SESSION['maintenance_repair_results'] = $results;
            $failed = array_filter($results, static fn(array $row): bool => ($row['status'] ?? '') === 'failed');
            if ($failed) {
                $_SESSION['flash_error'] = 'Database repairs completed with one or more errors. Review the results below.';
            } else {
                $_SESSION['flash_success'] = 'Database compatibility checks and recommended repairs completed.';
            }
        } catch (Throwable $e) {
            Logger::exception($e, 'maintenance', ['event' => 'Recommended database repairs failed'], 'error');
            $_SESSION['flash_error'] = 'Database repair failed: ' . $e->getMessage();
        }

        $this->redirect('/admin/maintenance');
    }

    /**
     * EN: Execute one read or confirmed write SQL statement from the Admin browser page.
     * 中文：从 Admin 浏览器页面执行一条只读 SQL 或经确认的写入 SQL。
     */
    public function runQuery(): void
    {
        $admin = Auth::requireRole('admin');
        Csrf::verify($_POST['_csrf'] ?? null);

        $sql = trim((string)($_POST['sql'] ?? ''));
        $mode = (string)($_POST['mode'] ?? 'read');

        if ($mode === 'write' && trim((string)($_POST['write_confirmation'] ?? '')) !== 'RUN WRITE SQL') {
            $_SESSION['flash_error'] = 'Write SQL was not executed. Type RUN WRITE SQL exactly to confirm.';
            $_SESSION['maintenance_query_result'] = [
                'error' => 'Write confirmation was not accepted.',
                'submitted_sql' => $sql,
                'mode' => $mode,
            ];
            $this->redirect('/admin/maintenance');
        }

        try {
            $result = DatabaseMaintenance::executeSql($sql, $mode, (int)$admin['id']);
            $result['submitted_sql'] = $sql;
            $_SESSION['maintenance_query_result'] = $result;
            $_SESSION['flash_success'] = $mode === 'write'
                ? 'SQL write statement completed.'
                : 'SQL query completed.';
        } catch (Throwable $e) {
            $_SESSION['maintenance_query_result'] = [
                'error' => $e->getMessage(),
                'submitted_sql' => $sql,
                'mode' => $mode,
            ];
            $_SESSION['flash_error'] = 'SQL was not executed: ' . $e->getMessage();
        }

        $this->redirect('/admin/maintenance');
    }
}
