<?php
/**
 * File / 文件：app/Views/sales/bulk_submit.php
 * EN: Dedicated Bulk Submit Post page. Bulk submission is a sibling workflow of Submit Post.
 * 中文：独立 Bulk Submit Post 页面；Bulk Submit 与 Submit Post 为平级流程。
 */
use App\Core\Csrf;
use App\Core\Util;
?>

<div class="page-head sales-portal-head">
    <div>
        <div class="eyebrow" data-sales-i18n="bulkSubmit">Bulk Submit</div>
        <h1 data-sales-i18n="bulkSubmitPost">Bulk Submit Post</h1>
        <p class="sales-portal-subtitle" data-sales-i18n="bulkSubmitHelp">Paste one Marketplace listing URL per line. Each valid, non-duplicate listing is saved to the background Verification Queue.</p>
    </div>
    <a class="btn" href="<?= Util::e($config['app']['base_path']) ?>/sales">
        <span data-sales-i18n="backDashboard">Back to Dashboard</span>
    </a>
</div>

<section class="panel sales-bulk-submit sales-bulk-submit-page">
    <input type="hidden" name="_csrf" id="salesBulkCsrf" value="<?= Util::e(Csrf::token()) ?>">
    <label for="salesBulkUrls" data-sales-i18n="bulkUrlsLabel">One listing URL per line</label>
    <textarea id="salesBulkUrls" rows="12" placeholder="https://www.facebook.com/marketplace/item/...&#10;https://offerup.com/item/detail/...&#10;https://craigslist.org/..."></textarea>
    <div class="sales-bulk-submit-actions">
        <button type="button" class="btn primary" id="bulkQueueButton">
            <span data-sales-i18n="bulkSubmitPost">Bulk Submit Post</span>
        </button>
    </div>
    <div class="sales-bulk-result hidden" id="salesBulkResult" aria-live="polite"></div>
</section>

<?php require __DIR__ . '/_verification_queue.php'; ?>
