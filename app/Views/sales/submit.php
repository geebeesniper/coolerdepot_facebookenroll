<?php
use App\Core\Csrf;
use App\Core\Util;
?>

<div
    id="salesSubmitPortal"
    class="sales-submit-portal"
></div>

<div class="page-head sales-portal-head">
    <div>
        <div
            class="eyebrow"
            data-sales-i18n="greeting"
            data-sales-name="<?= Util::e((string)$user['display_name']) ?>"
        >
            Hi, <?= Util::e((string)$user['display_name']) ?>
        </div>

        <h1 data-sales-i18n="submitTitle">
            Submit Marketplace Post
        </h1>

        <p class="sales-portal-subtitle" data-sales-i18n="submitSubtitle">
            Verify the listing first. Only verified posts can be saved.
        </p>
    </div>

    <a
        class="btn"
        href="<?= Util::e($config['app']['base_path']) ?>/sales"
    >
        <span data-sales-i18n="backDashboard">Back to Dashboard</span>
    </a>
</div>

<?php require __DIR__ . '/_submit_form.php'; ?>
