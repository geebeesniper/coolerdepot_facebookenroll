<?php
/**
 * File / 文件：app/Views/layout/header.php
 * EN: Renders the layout/header application view template.
 * 中文：渲染应用视图模板 layout/header。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Util;
use App\Core\Logger;
use App\Models\Setting;
use App\Models\Post;

$u = Auth::user();
$base = $config['app']['base_path'];

// EN: Keep the primary menu synchronized with the actual routed page. Query
// strings never affect the active state; route families such as Reports and
// Settings keep their parent menu item active on child pages.
// 中文：主菜单依据当前实际 URL 路由显示选中状态；Query String 不影响高亮，
// Reports / Settings 等子页面继续高亮所属的父级菜单。
$requestPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/');
$basePath = rtrim((string)$base, '/');
$relativePath = $requestPath;
if ($basePath !== '' && $basePath !== '/' && str_starts_with($requestPath, $basePath)) {
    $relativePath = substr($requestPath, strlen($basePath)) ?: '/';
}
$relativePath = '/' . ltrim($relativePath, '/');

$navActive = [
    'dashboard' => false,
    'submit' => false,
    'bulk_submit' => false,
    'reports' => false,
    'settings' => false,
    'help' => false,
];
if ($u) {
    if (str_starts_with($relativePath, '/help')) {
        $navActive['help'] = true;
    } elseif (($u['role'] ?? '') === 'sales') {
        if (str_starts_with($relativePath, '/sales/bulk-submit')) {
            $navActive['bulk_submit'] = true;
        } elseif (str_starts_with($relativePath, '/sales/submit')) {
            $navActive['submit'] = true;
        } elseif ($relativePath === '/sales' || str_starts_with($relativePath, '/sales/')) {
            $navActive['dashboard'] = true;
        }
    } elseif (($u['role'] ?? '') === 'admin') {
        if (str_starts_with($relativePath, '/admin/reports')) {
            $navActive['reports'] = true;
        } elseif (
            str_starts_with($relativePath, '/admin/settings')
            || str_starts_with($relativePath, '/admin/providers')
            || str_starts_with($relativePath, '/admin/inspection-lock')
            || str_starts_with($relativePath, '/admin/website')
            || str_starts_with($relativePath, '/admin/duplicate-catalog')
            || str_starts_with($relativePath, '/admin/maintenance')
        ) {
            $navActive['settings'] = true;
        } elseif ($relativePath === '/admin' || str_starts_with($relativePath, '/admin/')) {
            $navActive['dashboard'] = true;
        }
    }
}

$ok = $_SESSION['flash_success'] ?? null;
$bad = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
try {
    $companyName = trim((string)Setting::get('company_name', 'CoolerDepot')) ?: 'CoolerDepot';
} catch (\Throwable $e) {
    Logger::exception($e, 'view', ['event' => 'Company name lookup failed'], 'warning');
    $companyName = 'CoolerDepot';
}
$deletionRequests = [];
if ($u && ($u['role'] ?? '') === 'admin') {
    try {
        $deletionRequests = Post::pendingDeletionRequests();
    } catch (\Throwable $e) {
        Logger::exception($e, 'view', ['event' => 'Pending deletion request lookup failed'], 'warning');
        $deletionRequests = [];
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="cdsp-csrf" content="<?= Util::e(Csrf::token()) ?>">
    <meta name="cdsp-request-id" content="<?= Util::e(Logger::requestId()) ?>">
    <meta name="cdsp-client-log-url" content="<?= Util::e($base) ?>/api/client-log">
    <title><?= Util::e($companyName) ?> Sales Post Tracker</title>
    <link
        rel="stylesheet"
        href="<?= Util::e($base) ?>/public/assets/app.css?v=<?= rawurlencode($config['app']['version']) ?>"
    >
    <!-- EN: Canonical breakpoint layer; responsive ownership stays out of the historical app.css chain.
         中文：统一断点层；响应式规则独立于历史 app.css 覆盖链，避免窄屏多套规则互相冲突。 -->
    <link
        rel="stylesheet"
        href="<?= Util::e($base) ?>/public/assets/responsive.css?v=<?= rawurlencode($config['app']['version']) ?>"
    >
    <script src="<?= Util::e($base) ?>/public/assets/diagnostics.js?v=<?= rawurlencode($config['app']['version']) ?>"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body>
<header
    class="topbar"
    data-user-role="<?= $u ? Util::e((string)$u['role']) : '' ?>"
>
    <a class="brand" href="<?= Util::e($base) ?>/">
        <?= Util::e($companyName) ?> <span>Sales Posts</span>
    </a>

    <?php if ($u): ?>
        <div class="nav">
            <b class="nav-user">
                <?= Util::e($u['display_name'] ?: $u['username']) ?>
            </b>

            <!-- EN: Full language names are shown on desktop; compact EN/简/繁/ES labels are shown on narrow screens.
                 中文：桌面显示完整语言名；窄屏显示 EN/简/繁/ES 固定缩写。 -->
            <div
                class="app-language-switch"
                id="appLanguageSwitch"
                aria-label="Language"
            >
                <button
                    type="button"
                    class="app-language-button active"
                    data-app-lang="en"
                    aria-pressed="true"
                    title="English"
                >
                    <span class="language-label-full">English</span>
                    <span class="language-label-short" aria-hidden="true">EN</span>
                </button>
                <button
                    type="button"
                    class="app-language-button"
                    data-app-lang="zh-CN"
                    aria-pressed="false"
                    title="简体中文"
                >
                    <span class="language-label-full">简体中文</span>
                    <span class="language-label-short" aria-hidden="true">简</span>
                </button>
                <button
                    type="button"
                    class="app-language-button"
                    data-app-lang="zh-TW"
                    aria-pressed="false"
                    title="繁體中文"
                >
                    <span class="language-label-full">繁體中文</span>
                    <span class="language-label-short" aria-hidden="true">繁</span>
                </button>
                <button
                    type="button"
                    class="app-language-button"
                    data-app-lang="es"
                    aria-pressed="false"
                    title="Español"
                >
                    <span class="language-label-full">Español</span>
                    <span class="language-label-short" aria-hidden="true">ES</span>
                </button>
            </div>

            <?php if ($u['role'] === 'admin'): ?>
                <?php $infoRequestCount = count($deletionRequests); ?>
                <div class="admin-info-menu" id="adminInfoMenu">
                    <button
                        type="button"
                        class="admin-info-toggle"
                        id="adminInfoToggle"
                        aria-expanded="false"
                        aria-controls="adminInfoPanel"
                        title="Information Center"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 22a2.6 2.6 0 0 0 2.45-1.75h-4.9A2.6 2.6 0 0 0 12 22Zm7-5.1-1.55-1.75V10a5.46 5.46 0 0 0-4.2-5.3V4a1.25 1.25 0 0 0-2.5 0v.7A5.46 5.46 0 0 0 6.55 10v5.15L5 16.9V18h14v-1.1Z"/>
                        </svg>
                        <span class="admin-info-badge<?= $infoRequestCount > 0 ? '' : ' hidden' ?>"><?= (int)$infoRequestCount ?></span>
                    </button>

                    <section
                        class="admin-info-panel hidden"
                        id="adminInfoPanel"
                        aria-label="Information Center"
                    >
                        <div class="admin-info-head">
                            <div>
                                <span class="eyebrow">Information Center</span>
                                <strong>Notifications</strong>
                            </div>
                            <span id="adminInfoPendingCount"><?= (int)$infoRequestCount ?> pending</span>
                        </div>

                        <div class="admin-info-list" id="adminInfoList">
                            <?php if ($deletionRequests): ?>
                                <?php foreach ($deletionRequests as $request): ?>
                                    <article
                                        class="admin-info-item"
                                        data-info-request-id="<?= (int)$request['id'] ?>"
                                        data-info-post-id="<?= (int)$request['post_id'] ?>"
                                        data-info-sales="<?= Util::e((string)$request['display_name']) ?>"
                                        data-info-reason="<?= Util::e((string)$request['reason']) ?>"
                                    >
                                        <button
                                            type="button"
                                            class="admin-info-post-open"
                                            data-info-open-post
                                            data-request-id="<?= (int)$request['id'] ?>"
                                            data-post-id="<?= (int)$request['post_id'] ?>"
                                        >
                                            <span class="admin-info-item-meta">
                                                <span>Delete request</span>
                                                <b><?= Util::e((string)$request['display_name']) ?></b>
                                            </span>
                                            <strong class="admin-info-summary-title"><?= Util::e((string)$request['title']) ?></strong>
                                            <span class="admin-info-summary-reason"><?= Util::e((string)$request['reason']) ?></span>
                                            <span class="admin-info-open-label">Open post →</span>
                                        </button>
                                    </article>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="admin-info-empty">No new notifications.</div>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>
            <?php endif; ?>

            <!-- EN: Narrow screens use one hamburger button; route links live in the expandable panel below.
                 中文：窄屏只显示一个汉堡菜单按钮；路由链接放在下方可展开菜单中。 -->
            <button
                type="button"
                class="mobile-nav-toggle"
                id="mobileNavToggle"
                aria-expanded="false"
                aria-controls="appPrimaryNav"
                aria-label="Open navigation menu"
                title="Menu"
            >
                <span class="mobile-nav-toggle-bars" aria-hidden="true">
                    <i></i><i></i><i></i>
                </span>
            </button>

            <div class="app-nav-menu" id="appPrimaryNav">
                <b class="mobile-nav-user">
                    <?= Util::e($u['display_name'] ?: $u['username']) ?>
                </b>

            <?php if ($u['role'] === 'sales'): ?>
                <a
                    class="app-nav-link<?= $navActive['dashboard'] ? ' active' : '' ?>"
                    href="<?= Util::e($base) ?>/sales"
                    <?= $navActive['dashboard'] ? 'aria-current="page"' : '' ?>
                    data-nav-i18n="dashboard"
                >
                    Dashboard
                </a>
                <a
                    class="app-nav-link<?= $navActive['submit'] ? ' active' : '' ?>"
                    href="<?= Util::e($base) ?>/sales/submit"
                    <?= $navActive['submit'] ? 'aria-current="page"' : '' ?>
                    data-nav-i18n="submit"
                    data-open-sales-submit
                >
                    Submit
                </a>
                <a
                    class="app-nav-link<?= $navActive['bulk_submit'] ? ' active' : '' ?>"
                    href="<?= Util::e($base) ?>/sales/bulk-submit"
                    <?= $navActive['bulk_submit'] ? 'aria-current="page"' : '' ?>
                    data-nav-i18n="bulkSubmit"
                    data-open-sales-bulk-submit
                >
                    Bulk Submit
                </a>
            <?php else: ?>
                <a
                    class="app-nav-link<?= $navActive['dashboard'] ? ' active' : '' ?>"
                    href="<?= Util::e($base) ?>/admin"
                    <?= $navActive['dashboard'] ? 'aria-current="page"' : '' ?>
                    data-nav-i18n="dashboard"
                >
                    Dashboard
                </a>
                <a
                    class="app-nav-link<?= $navActive['reports'] ? ' active' : '' ?>"
                    href="<?= Util::e($base) ?>/admin/reports"
                    <?= $navActive['reports'] ? 'aria-current="page"' : '' ?>
                    data-nav-i18n="reports"
                >
                    Reports
                </a>
                <a
                    class="app-nav-link<?= $navActive['settings'] ? ' active' : '' ?>"
                    href="<?= Util::e($base) ?>/admin/settings"
                    <?= $navActive['settings'] ? 'aria-current="page"' : '' ?>
                    data-nav-i18n="settings"
                >
                    Settings
                </a>
            <?php endif; ?>

                <!-- EN: Help is a normal routed application page and uses the same header/navigation/footer.
                     中文：Help 是系统内的正常路由页面，与其他页面共用 Header / Navigation / Footer。 -->
                <a
                    class="app-nav-link<?= $navActive['help'] ? ' active' : '' ?>"
                    href="<?= Util::e($base) ?>/help"
                    <?= $navActive['help'] ? 'aria-current="page"' : '' ?>
                    data-nav-i18n="help"
                >
                    Help
                </a>

            <form class="app-nav-signout" method="post" action="<?= Util::e($base) ?>/logout">
                <input
                    type="hidden"
                    name="_csrf"
                    value="<?= Util::e(Csrf::token()) ?>"
                >
                <button data-nav-i18n="signOut">Sign out</button>
            </form>
            </div>
        </div>
    <?php endif; ?>
</header>

<?php if ($u && ($u['role'] ?? '') === 'admin'): ?>
<div
    class="admin-delete-request-modal hidden"
    id="adminDeleteRequestPostModal"
    aria-hidden="true"
    data-post-url="<?= Util::e($base) ?>/admin/dashboard/post-review"
    data-action-url="<?= Util::e($base) ?>/admin/delete-request"
    data-csrf="<?= Util::e(Csrf::token()) ?>"
>
    <div class="admin-delete-request-backdrop" data-delete-request-modal-close></div>
    <section class="admin-delete-request-card" role="dialog" aria-modal="true" aria-labelledby="adminDeleteRequestTitle">
        <header class="admin-delete-request-head">
            <div>
                <span class="eyebrow">Delete Request</span>
                <h2 id="adminDeleteRequestTitle">Post details</h2>
                <p id="adminDeleteRequestSubtitle"></p>
            </div>
            <button type="button" class="admin-delete-request-close" data-delete-request-modal-close aria-label="Close">×</button>
        </header>

        <div class="admin-delete-request-loading" id="adminDeleteRequestLoading">Loading post…</div>

        <div class="admin-delete-request-body hidden" id="adminDeleteRequestBody">
            <div class="admin-delete-request-meta">
                <div><span>Sales</span><strong id="adminDeleteRequestSales">—</strong></div>
                <div><span>Platform</span><strong id="adminDeleteRequestPlatform">—</strong></div>
                <div><span>Published</span><strong id="adminDeleteRequestPublished">—</strong></div>
                <div><span>Post ID</span><strong id="adminDeleteRequestPostId">—</strong></div>
                <div class="hidden" id="adminDeleteRequestAccountFact"><span>Account</span><strong id="adminDeleteRequestAccount">—</strong></div>
            </div>

            <section class="admin-delete-request-content">
                <h3 id="adminDeleteRequestPostTitle">—</h3>
                <div class="admin-delete-request-description" id="adminDeleteRequestDescription"></div>
                <div class="admin-delete-request-photos" id="adminDeleteRequestPhotos"></div>
                <a class="btn admin-delete-request-original hidden" id="adminDeleteRequestOriginal" href="#" target="_blank" rel="noopener noreferrer">Open original</a>
            </section>

            <section class="admin-delete-request-reason-card">
                <span>Sales reason for deletion</span>
                <p id="adminDeleteRequestReason">—</p>
            </section>

            <div class="admin-delete-request-status" id="adminDeleteRequestStatus" aria-live="polite"></div>
        </div>

        <footer class="admin-delete-request-footer hidden" id="adminDeleteRequestFooter">
            <button type="button" class="btn" data-delete-request-modal-close>Close</button>
            <button type="button" class="btn danger-soft" id="adminDeleteRequestReject">Reject</button>
            <button type="button" class="btn danger-confirm" id="adminDeleteRequestApprove">Approve &amp; Delete</button>
        </footer>
    </section>
</div>
<?php endif; ?>

<main class="container">
    <?php if ($ok): ?>
        <div class="notice ok"><?= Util::e($ok) ?></div>
    <?php endif; ?>

    <?php if ($bad): ?>
        <div class="notice bad"><?= Util::e($bad) ?></div>
    <?php endif; ?>
