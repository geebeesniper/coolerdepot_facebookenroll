<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Util;

$u = Auth::user();
$base = $config['app']['base_path'];
$ok = $_SESSION['flash_success'] ?? null;
$bad = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= Util::e($config['app']['name']) ?></title>
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
        CoolerDepot <span>Sales Posts</span>
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
