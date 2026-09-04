<?php
/**
 * File / 文件：index.php
 * EN: Application PHP entry/helper file for index.
 * 中文：用于 index 的应用 PHP 入口/辅助文件。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
$config = require __DIR__ . '/config/bootstrap.php';

use App\Core\Router;
use App\Controllers\AuthController;
use App\Controllers\AuthHandoffController;
use App\Controllers\SalesController;
use App\Controllers\AdminController;
use App\Controllers\AdminSettingsController;
use App\Controllers\ApiController;
use App\Controllers\VerificationQueueController;
use App\Controllers\AttachmentController;
use App\Controllers\ExternalApiController;
use App\Controllers\GraphqlController;
use App\Controllers\HelpController;
use App\Controllers\AdminMaintenanceController;

$router = new Router($config['app']['base_path']);

// Authentication and signed parent-portal handoff.
$router->get('/', [AuthController::class, 'home']);
$router->get('/login', [AuthController::class, 'login']);
$router->get('/auth/recheck', [AuthController::class, 'recheck']);
$router->post('/login', [AuthController::class, 'authenticate']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/auth/handoff', [AuthHandoffController::class, 'handoff']);
$router->post('/auth/handoff', [AuthHandoffController::class, 'handoff']);

// External integration APIs. REST and GraphQL share the same signed identity exchange,
// Bearer-token store, and server-side Admin/Sales RBAC.
$router->get('/api/v1/health', [ExternalApiController::class, 'health']);
$router->post('/api/v1/auth/exchange', [ExternalApiController::class, 'exchange']);
$router->get('/api/v1/auth/me', [ExternalApiController::class, 'me']);
$router->post('/api/v1/auth/logout', [ExternalApiController::class, 'logout']);
$router->get('/api/v1/admin/users', [ExternalApiController::class, 'adminUsers']);
$router->get('/api/v1/sales/profile', [ExternalApiController::class, 'salesProfile']);
$router->options('/api/v1/health', [ExternalApiController::class, 'cors']);
$router->options('/api/v1/auth/exchange', [ExternalApiController::class, 'cors']);
$router->options('/api/v1/auth/me', [ExternalApiController::class, 'cors']);
$router->options('/api/v1/auth/logout', [ExternalApiController::class, 'cors']);
$router->options('/api/v1/admin/users', [ExternalApiController::class, 'cors']);
$router->options('/api/v1/sales/profile', [ExternalApiController::class, 'cors']);
$router->post('/graphql', [GraphqlController::class, 'handle']);
$router->options('/graphql', [GraphqlController::class, 'cors']);

// Sales dashboard and post verification workflow.
$router->get('/sales', [SalesController::class, 'dashboard']);
$router->get('/sales/submit', [SalesController::class, 'submitForm']);
$router->get('/sales/bulk-submit', [SalesController::class, 'bulkSubmitForm']);
$router->get('/sales/daily-posts', [SalesController::class, 'dailyPostsAjax']);
$router->get('/sales/post-search', [SalesController::class, 'postSearch']);
$router->post('/sales/save', [SalesController::class, 'save']);
$router->post('/sales/delete-request', [SalesController::class, 'requestDelete']);
$router->post('/api/inspect/preflight', [ApiController::class, 'inspectPreflight']);
$router->get('/api/inspect/status', [ApiController::class, 'inspectStatus']);
$router->post('/api/inspect', [ApiController::class, 'inspect']);
$router->post('/api/client-log', [ApiController::class, 'clientLog']);
$router->get('/api/verification-queue', [VerificationQueueController::class, 'index']);
$router->get('/api/verification-queue/history', [VerificationQueueController::class, 'history']);
$router->post('/api/verification-queue/enqueue', [VerificationQueueController::class, 'enqueue']);
$router->post('/api/verification-queue/bulk', [VerificationQueueController::class, 'bulkEnqueue']);
$router->post('/api/verification-queue/retry', [VerificationQueueController::class, 'retry']);
$router->post('/api/verification-queue/update', [VerificationQueueController::class, 'update']);
$router->post('/api/verification-queue/delete', [VerificationQueueController::class, 'delete']);

// Admin dashboard, review and reporting workflow.
$router->get('/admin', [AdminController::class, 'dashboard']);
$router->get('/admin/dashboard/updates', [AdminController::class, 'dashboardUpdates']);
$router->get('/admin/dashboard/progress', [AdminController::class, 'dashboardProgress']);
$router->get('/admin/dashboard/sales-posts', [AdminController::class, 'dashboardSalesPosts']);
$router->get('/admin/dashboard/post-search', [AdminController::class, 'dashboardPostSearch']);
$router->get('/admin/dashboard/post-review', [AdminController::class, 'dashboardPostReview']);
$router->post('/admin/dashboard/sales-review/save', [AdminController::class, 'dashboardSaveSalesReview']);
$router->post('/admin/dashboard/sales-review/history/delete', [AdminController::class, 'dashboardDeleteSalesReviewHistory']);
$router->get('/admin/dashboard/daily-status', [AdminController::class, 'dashboardDailyStatus']);
$router->post('/admin/dashboard/daily-complete', [AdminController::class, 'dashboardMarkDailyComplete']);
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
$router->post('/admin/settings/location/add', [AdminSettingsController::class, 'addLocation']);
$router->post('/admin/settings/location/update', [AdminSettingsController::class, 'updateLocation']);
$router->post('/admin/settings/location/delete', [AdminSettingsController::class, 'deleteLocation']);
$router->post('/admin/settings/website', [AdminSettingsController::class, 'saveWebsiteSource']);
$router->get('/admin/website/source', [AdminSettingsController::class, 'websiteSourceDetail']);
$router->post('/admin/settings/save', [AdminSettingsController::class, 'save']);
$router->post('/admin/settings/test', [AdminSettingsController::class, 'test']);
$router->post('/admin/providers/test', [AdminSettingsController::class, 'testProvider']);
$router->get('/admin/providers/jobs', [AdminSettingsController::class, 'providerJobs']);
$router->post('/admin/providers/add', [AdminSettingsController::class, 'addProvider']);
$router->post('/admin/providers/reorder', [AdminSettingsController::class, 'reorderProviders']);
$router->post('/admin/providers/toggle', [AdminSettingsController::class, 'toggleProvider']);
$router->post('/admin/providers/delete', [AdminSettingsController::class, 'deleteProvider']);
$router->post('/admin/settings/verification-recovery', [AdminSettingsController::class, 'saveInspectionRecovery']);
$router->post('/admin/inspection-lock/unlock', [AdminSettingsController::class, 'unlockInspection']);
$router->post('/admin/website/scan', [AdminSettingsController::class, 'scanWebsite']);
$router->post('/admin/website/source/remove', [AdminSettingsController::class, 'removeWebsiteSource']);
$router->post('/admin/website/products/scan-batch', [AdminSettingsController::class, 'scanWebsiteProductsBatch']);
$router->post('/admin/website/products/scan-start', [AdminSettingsController::class, 'startWebsiteProductScan']);
$router->post('/admin/website/products/scan-step', [AdminSettingsController::class, 'stepWebsiteProductScan']);
$router->get('/admin/website/products/scan-status', [AdminSettingsController::class, 'websiteProductScanStatus']);
$router->post('/admin/website/products/scan-stop', [AdminSettingsController::class, 'stopWebsiteProductScan']);
$router->post('/admin/website/products/scan-resume', [AdminSettingsController::class, 'resumeWebsiteProductScan']);
$router->post('/admin/website/reference/add', [AdminSettingsController::class, 'addWebsiteReference']);
$router->get('/admin/website/references', [AdminSettingsController::class, 'websiteReferences']);
$router->post('/admin/website/reference/delete', [AdminSettingsController::class, 'deleteWebsiteReference']);
$router->get('/admin/website-catalog/sample.csv', [AdminSettingsController::class, 'websiteCatalogSample']);
$router->post('/admin/duplicate-catalog/import', [AdminSettingsController::class, 'importWebsiteCatalog']);

// Browser-based Admin database maintenance for servers where SSH is unavailable.
// 无 SSH 环境下通过浏览器执行 Admin 数据库维护。
$router->get('/admin/maintenance', [AdminMaintenanceController::class, 'index']);
$router->post('/admin/maintenance/repairs', [AdminMaintenanceController::class, 'runRepairs']);
$router->post('/admin/maintenance/query', [AdminMaintenanceController::class, 'runQuery']);

// Authenticated role-specific user manual. / 已登录用户按角色查看站内使用说明。
$router->get('/help', [HelpController::class, 'show']);

// Authenticated attachment delivery.
$router->get('/attachment', [AttachmentController::class, 'show']);

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
