<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Csrf;
use App\Models\Post;
use App\Models\Inspection;

class SalesController extends Controller
{
    public function dashboard(): void
    {
        global $config;

        $u = Auth::requireRole('sales');

        $to = $_GET['to'] ?? date('Y-m-d');
        $from = $_GET['from'] ?? date('Y-m-01');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $from = date('Y-m-01');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $to = date('Y-m-d');
        }

        if($from>$to){
            $to=$from;
        }

        $counts = Post::dailyCounts((int)$u['id'], $from, $to);
        $summary = Post::salesRangeSummary(
            (int)$u['id'],
            $from,
            $to
        );

        $limit = max(1, (int)$config['app']['daily_posts_initial_days']);
        $dayRows = Post::dailyDatesForSales((int)$u['id'], $from, $to, $limit, 0);
        $days = [];

        foreach ($dayRows as $row) {
            $date = $row['published_date'];
            $days[] = [
                'date' => $date,
                'post_count' => (int)$row['post_count'],
                'good_count' => (int)$row['good_count'],
                'bad_count' => (int)$row['bad_count'],
                'posts' => Post::forSalesOnDate((int)$u['id'], $date),
            ];
        }

        $totalDays = Post::dailyDateCountForSales((int)$u['id'], $from, $to);

        $this->render('sales/dashboard', [
            'user' => $u,
            'from' => $from,
            'to' => $to,
            'counts' => $counts,
            'summary' => $summary,
            'days' => $days,
            'loadedDays' => count($days),
            'totalDays' => $totalDays,
            'loadDays' => max(1, (int)$config['app']['daily_posts_load_days']),
        ]);
    }

    public function dailyPostsAjax(): void
    {
        global $config;

        $u = Auth::requireRole('sales');

        $from = (string)($_GET['from'] ?? date('Y-m-01'));
        $to = (string)($_GET['to'] ?? date('Y-m-d'));
        $offset = max(0, (int)($_GET['offset'] ?? 0));
        $limit = max(1, min(10, (int)($_GET['limit'] ?? $config['app']['daily_posts_load_days'])));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $this->json(['ok' => false, 'message' => 'Invalid date range.'], 422);
        }

        if($from>$to){
            $to=$from;
        }

        $rows = Post::dailyDatesForSales((int)$u['id'], $from, $to, $limit, $offset);
        $days = [];

        foreach ($rows as $row) {
            $date = $row['published_date'];
            $days[] = [
                'date' => $date,
                'post_count' => (int)$row['post_count'],
                'good_count' => (int)$row['good_count'],
                'bad_count' => (int)$row['bad_count'],
                'posts' => Post::forSalesOnDate((int)$u['id'], $date),
            ];
        }

        ob_start();
        foreach ($days as $day) {
            $this->renderPartial('sales/_daily_post_section', ['day' => $day]);
        }
        $html = ob_get_clean();

        $nextOffset = $offset + count($days);
        $totalDays = Post::dailyDateCountForSales((int)$u['id'], $from, $to);

        $this->json([
            'ok' => true,
            'html' => $html,
            'loaded' => count($days),
            'next_offset' => $nextOffset,
            'has_more' => $nextOffset < $totalDays,
        ]);
    }

    public function submitForm(): void
    {
        $u=Auth::requireRole('sales');

        $this->render('sales/submit',[
            'user'=>$u,
        ]);
    }

    public function save(): void
    {
        $u = Auth::requireRole('sales');
        Csrf::verify($_POST['_csrf'] ?? null);

        $token = trim((string)($_POST['inspection_token'] ?? ''));
        $inspection = Inspection::verifiedForUser($token, (int)$u['id']);

        if (!$inspection) {
            $_SESSION['flash_error'] = 'Verification expired or invalid. Check the post again.';
            $this->redirect('/sales/submit');
        }

        Post::createFromInspection($inspection);
        $_SESSION['flash_success'] = 'Post saved.';
        $this->redirect('/sales');
    }

    public function requestDelete(): void
    {
        $u = Auth::requireRole('sales');
        Csrf::verify($_POST['_csrf'] ?? null);

        $postId = (int)($_POST['post_id'] ?? 0);
        $reason = trim((string)($_POST['reason'] ?? ''));

        Post::requestDeletion((int)$u['id'], $postId, $reason);

        $isAjax=strtolower(
            (string)($_SERVER['HTTP_X_REQUESTED_WITH']??'')
        )==='xmlhttprequest';

        if($isAjax){
            $this->json([
                'ok'=>true,
                'post_id'=>$postId,
                'message'=>'Deletion request sent to Admin.',
            ]);
        }

        $_SESSION['flash_success'] = 'Deletion request sent to Admin.';
        $this->redirect('/sales');
    }
}
