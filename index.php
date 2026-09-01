<?php
$config = require __DIR__ . '/config/bootstrap.php';

use App\Core\Router;
use App\Controllers\AuthController;
use App\Controllers\AuthHandoffController;
use App\Controllers\SalesController;
use App\Controllers\AdminController;
use App\Controllers\AdminSettingsController;
use App\Controllers\ApiController;
use App\Controllers\AttachmentController;

$router = new Router($config['app']['base_path']);

// Authentication and signed parent-portal handoff.
$router->get('/', [AuthController::class, 'home']);
$router->get('/login', [AuthController::class, 'login']);
$router->post('/login', [AuthController::class, 'authenticate']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/auth/handoff', [AuthHandoffController::class, 'handoff']);
$router->post('/auth/handoff', [AuthHandoffController::class, 'handoff']);

// Sales dashboard and post verification workflow.
$router->get('/sales', [SalesController::class, 'dashboard']);
$router->get('/sales/submit', [SalesController::class, 'submitForm']);
$router->get('/sales/daily-posts', [SalesController::class, 'dailyPostsAjax']);
$router->post('/sales/save', [SalesController::class, 'save']);
$router->post('/sales/delete-request', [SalesController::class, 'requestDelete']);
$router->post('/api/inspect/preflight', [ApiController::class, 'inspectPreflight']);
$router->post('/api/inspect', [ApiController::class, 'inspect']);
$router->post('/api/client-log', [ApiController::class, 'clientLog']);

// Admin dashboard, review and reporting workflow.
$router->get('/admin', [AdminController::class, 'dashboard']);
$router->get('/admin/dashboard/updates', [AdminController::class, 'dashboardUpdates']);
$router->get('/admin/dashboard/progress', [AdminController::class, 'dashboardProgress']);
$router->get('/admin/dashboard/sales-posts', [AdminController::class, 'dashboardSalesPosts']);
$router->get('/admin/dashboard/post-review', [AdminController::class, 'dashboardPostReview']);
$router->post('/admin/dashboard/sales-review/save', [AdminController::class, 'dashboardSaveSalesReview']);
$router->post('/admin/dashboard/sales-review/history/delete', [AdminController::class, 'dashboardDeleteSalesReviewHistory']);
$router->post('/admin/dashboard/get-content', [AdminController::class, 'dashboardGetContent']);
$router->post('/admin/dashboard/editor-image', [AdminController::class, 'dashboardEditorImage']);
$router->post('/admin/dashboard/comment/add', [AdminController::class, 'dashboardAddComment']);
$router->post('/admin/dashboard/comment/update', [AdminController::class, 'dashboardUpdateComment']);
$router->post('/admin/dashboard/comment/delete', [AdminController::class, 'dashboardDeleteComment']);
$router->post('/admin/dashboard/attachment/delete', [AdminController::class, 'dashboardDeleteAttachment']);
$router->post('/admin/sales-target', [AdminController::class, 'saveSalesTarget']);
$router->get('/admin/post', [AdminController::class, 'postReview']);
$router->post('/admin/post/review', [AdminController::class, 'savePostReview']);
$router->get('/admin/daily', [AdminController::class, 'dailyReview']);
$router->post('/admin/daily/review', [AdminController::class, 'saveDailyReview']);
$router->get('/admin/reports', [AdminController::class, 'reports']);
$router->get('/admin/reports/download', [AdminController::class, 'reportsDownload']);
$router->post('/admin/period/review', [AdminController::class, 'savePeriodReview']);
$router->post('/admin/delete-request', [AdminController::class, 'handleDeleteRequest']);
$router->post('/admin/post/delete', [AdminController::class, 'deletePost']);

// Admin settings, provider registry and website duplicate-reference library.
$router->get('/admin/settings', [AdminSettingsController::class, 'index']);
$router->post('/admin/settings/brand', [AdminSettingsController::class, 'saveBrand']);
$router->post('/admin/settings/website', [AdminSettingsController::class, 'saveWebsiteSource']);
$router->post('/admin/settings/save', [AdminSettingsController::class, 'save']);
$router->post('/admin/settings/test', [AdminSettingsController::class, 'test']);
$router->post('/admin/providers/test', [AdminSettingsController::class, 'testProvider']);
$router->get('/admin/providers/jobs', [AdminSettingsController::class, 'providerJobs']);
$router->post('/admin/providers/add', [AdminSettingsController::class, 'addProvider']);
$router->post('/admin/providers/reorder', [AdminSettingsController::class, 'reorderProviders']);
$router->post('/admin/providers/toggle', [AdminSettingsController::class, 'toggleProvider']);
$router->post('/admin/providers/delete', [AdminSettingsController::class, 'deleteProvider']);
$router->post('/admin/website/scan', [AdminSettingsController::class, 'scanWebsite']);
$router->post('/admin/website/reference/add', [AdminSettingsController::class, 'addWebsiteReference']);
$router->get('/admin/website/references', [AdminSettingsController::class, 'websiteReferences']);
$router->post('/admin/website/reference/delete', [AdminSettingsController::class, 'deleteWebsiteReference']);
$router->get('/admin/website-catalog/sample.csv', [AdminSettingsController::class, 'websiteCatalogSample']);
$router->post('/admin/duplicate-catalog/import', [AdminSettingsController::class, 'importWebsiteCatalog']);

// Authenticated attachment delivery.
$router->get('/attachment', [AttachmentController::class, 'show']);

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
