<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Util;
use App\Models\Setting;

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

            <?php if ($u['role'] === 'admin' && isset($deletionRequests)): ?>
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
                        <?php if ($infoRequestCount > 0): ?>
                            <span class="admin-info-badge"><?= (int)$infoRequestCount ?></span>
                        <?php endif; ?>
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
                            <span><?= (int)$infoRequestCount ?> pending</span>
                        </div>

                        <div class="admin-info-list">
                            <?php if ($deletionRequests): ?>
                                <?php foreach ($deletionRequests as $request): ?>
                                    <article class="admin-info-item">
                                        <div class="admin-info-item-copy">
                                            <div class="admin-info-item-meta">
                                                <span>Delete request</span>
                                                <b><?= Util::e((string)$request['display_name']) ?></b>
                                            </div>
                                            <a
                                                class="admin-info-post-link"
                                                href="<?= Util::e((string)($request['canonical_url'] ?? '#')) ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                title="Open original post"
                                            ><?= Util::e((string)$request['title']) ?></a>
                                            <p><?= Util::e((string)$request['reason']) ?></p>
                                        </div>

                                        <form
                                            class="admin-info-actions"
                                            method="post"
                                            action="<?= Util::e($base) ?>/admin/delete-request"
                                        >
                                            <input type="hidden" name="_csrf" value="<?= Util::e(Csrf::token()) ?>">
                                            <input type="hidden" name="request_id" value="<?= (int)$request['id'] ?>">
                                            <button name="action" value="approve" class="tiny okbtn">Approve</button>
                                            <button name="action" value="reject" class="tiny badbtn">Reject</button>
                                        </form>
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

<main class="container">
    <?php if ($ok): ?>
        <div class="notice ok"><?= Util::e($ok) ?></div>
    <?php endif; ?>

    <?php if ($bad): ?>
        <div class="notice bad"><?= Util::e($bad) ?></div>
    <?php endif; ?>
