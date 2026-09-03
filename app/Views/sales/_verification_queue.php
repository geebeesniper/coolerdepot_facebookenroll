<?php
/**
 * File / 文件：app/Views/sales/_verification_queue.php
 * EN: Shared Sales Verification Queue panel for Dashboard and Submit pages.
 * 中文：Dashboard 与 Submit 页面共用的 Sales Verification Queue 面板。
 */
?>
<section class="panel sales-verification-queue" data-verification-queue-panel>
    <div class="sales-verification-queue-head">
        <div>
            <span class="eyebrow" data-sales-i18n="verificationQueueEyebrow">Background Verification</span>
            <h2 data-sales-i18n="verificationQueueTitle">Verification Queue</h2>
            <p data-sales-i18n="verificationQueueHelp">Waiting and failed items are not counted as Posts. Passed items are saved automatically.</p>
        </div>
        <button type="button" class="btn compact" data-verification-queue-refresh>
            <span data-sales-i18n="refreshQueue">Refresh</span>
        </button>
    </div>

    <div class="sales-verification-queue-filters" role="group" aria-label="Verification queue filters">
        <button type="button" class="active" data-vq-filter="all"><span data-sales-i18n="queueAll">All</span> <b data-vq-count="all">0</b></button>
        <button type="button" data-vq-filter="waiting"><span data-sales-i18n="queueWaiting">Waiting</span> <b data-vq-count="waiting">0</b></button>
        <button type="button" data-vq-filter="verifying"><span data-sales-i18n="queueVerifying">Verifying</span> <b data-vq-count="verifying">0</b></button>
        <button type="button" data-vq-filter="passed"><span data-sales-i18n="queuePassed">Passed</span> <b data-vq-count="passed">0</b></button>
        <button type="button" data-vq-filter="failed"><span data-sales-i18n="queueFailed">Failed</span> <b data-vq-count="failed">0</b></button>
        <button type="button" data-vq-filter="duplicate"><span data-sales-i18n="queueDuplicate">Duplicate</span> <b data-vq-count="duplicate">0</b></button>
        <button type="button" data-vq-filter="invalid"><span data-sales-i18n="queueInvalid">Invalid</span> <b data-vq-count="invalid">0</b></button>
        <button type="button" data-vq-filter="needs_action"><span data-sales-i18n="queueNeedsAction">Needs Action</span> <b data-vq-count="needs_action">0</b></button>
    </div>

    <div class="sales-verification-queue-message hidden" data-vq-message aria-live="polite"></div>
    <div class="sales-verification-queue-list" data-vq-list></div>
    <div class="sales-verification-queue-empty" data-vq-empty>
        <strong data-sales-i18n="queueEmptyTitle">No verification items</strong>
        <span data-sales-i18n="queueEmptyHelp">Use Save &amp; Wait or Bulk Submit to add listings.</span>
    </div>
</section>
