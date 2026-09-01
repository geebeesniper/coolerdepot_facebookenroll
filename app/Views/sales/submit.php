<?php
/**
 * File / 文件：app/Views/sales/submit.php
 * EN: Renders the sales/submit application view template.
 * 中文：渲染应用视图模板 sales/submit。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
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
