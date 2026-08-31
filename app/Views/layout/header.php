<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Util;
use App\Models\Setting;
use App\Models\Post;

$u = Auth::user();
$base = $config['app']['base_path'];
$ok = $_SESSION['flash_success'] ?? null;
$bad = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
try {
    $companyName = trim((string)Setting::get('company_name', 'CoolerDepot')) ?: 'CoolerDepot';
} catch (\Throwable $e) {
    $companyName = 'CoolerDepot';
}
$deletionRequests = [];
if ($u && ($u['role'] ?? '') === 'admin') {
    try {
        $deletionRequests = Post::pendingDeletionRequests();
    } catch (\Throwable $e) {
        $deletionRequests = [];
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= Util::e($companyName) ?> Sales Post Tracker</title>
    <link
        rel="stylesheet"
        href="<?= Util::e($base) ?>/public/assets/app.css?v=<?= rawurlencode($config['app']['version']) ?>"
    >
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
                    English
                </button>
                <button
                    type="button"
                    class="app-language-button"
                    data-app-lang="zh-CN"
                    aria-pressed="false"
                    title="简体中文"
                >
                    简体中文
                </button>
                <button
                    type="button"
                    class="app-language-button"
                    data-app-lang="zh-TW"
                    aria-pressed="false"
                    title="繁體中文"
                >
                    繁體中文
                </button>
                <button
                    type="button"
                    class="app-language-button"
                    data-app-lang="es"
                    aria-pressed="false"
                    title="Español"
                >
                    Español
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

            <?php if ($u['role'] === 'sales'): ?>
                <a
                    href="<?= Util::e($base) ?>/sales"
                    data-nav-i18n="dashboard"
                >
                    Dashboard
                </a>
                <a
                    href="<?= Util::e($base) ?>/sales/submit"
                    data-nav-i18n="submit"
                    data-open-sales-submit
                >
                    Submit
                </a>
            <?php else: ?>
                <a
                    href="<?= Util::e($base) ?>/admin"
                    data-nav-i18n="admin"
                >
                    Admin
                </a>
                <a
                    href="<?= Util::e($base) ?>/admin/reports"
                    data-nav-i18n="reports"
                >
                    Reports
                </a>
                <a
                    href="<?= Util::e($base) ?>/admin/settings"
                    data-nav-i18n="settings"
                >
                    Settings
                </a>
            <?php endif; ?>

            <form method="post" action="<?= Util::e($base) ?>/logout">
                <input
                    type="hidden"
                    name="_csrf"
                    value="<?= Util::e(Csrf::token()) ?>"
                >
                <button data-nav-i18n="signOut">Sign out</button>
            </form>
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
