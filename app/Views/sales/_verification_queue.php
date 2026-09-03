<?php
/**
 * File / 文件：app/Views/sales/_verification_queue.php
 * EN: Shared collapsible Sales Verification Queue panel for Dashboard and Submit pages.
 * 中文：Dashboard 与 Submit 页面共用的可收起 Sales Verification Queue 面板。
 */
?>
<section class="panel sales-verification-queue is-collapsed" data-verification-queue-panel data-vq-current-filter="all">
    <div class="sales-verification-queue-head">
        <div class="sales-verification-queue-title-block">
            <span class="eyebrow" data-sales-i18n="verificationQueueEyebrow">Background Verification</span>
            <h2 data-sales-i18n="verificationQueueTitle">Verification Queue</h2>
            <p data-sales-i18n="verificationQueueHelp">Waiting and failed items are not counted as Posts. Passed items are saved automatically.</p>
        </div>

        <div class="sales-verification-queue-head-actions">
            <div class="sales-verification-queue-summary" aria-live="polite">
                <span><span data-sales-i18n="queueAll">All</span> <b data-vq-count="all">0</b></span>
                <span><span data-sales-i18n="queueNeedsAction">Needs Action</span> <b data-vq-count="needs_action">0</b></span>
            </div>
            <button type="button" class="btn compact" data-verification-queue-refresh>
                <span data-sales-i18n="refreshQueue">Refresh</span>
            </button>
            <button
                type="button"
                class="sales-verification-queue-collapse"
                data-vq-collapse-toggle
                aria-expanded="false"
                aria-label="Open Verification Queue"
                title="Open Verification Queue"
            >
                <svg viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="m6.5 7.75 3.5 3.5 3.5-3.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
        </div>
    </div>

    <div class="sales-verification-queue-body" data-vq-collapse-body>
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
    </div>
</section>
