<?php
/**
 * File / 文件：app/Views/sales/_bulk_submit_form.php
 * EN: Shared Bulk Submit Post form used by the dashboard popup and dedicated fallback page.
 * 中文：Dashboard 弹窗与独立备用页面共用的 Bulk Submit Post 表单。
 */
use App\Core\Csrf;
use App\Core\Util;
?>
<section class="sales-bulk-submit sales-bulk-submit-page">
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
