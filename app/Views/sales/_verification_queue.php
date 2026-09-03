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

<div class="sales-post-detail-backdrop sales-vq-detail-backdrop hidden" data-vq-detail-modal aria-hidden="true">
    <section class="sales-post-detail-modal sales-vq-detail-modal" role="dialog" aria-modal="true" aria-label="Verification Queue item details">
        <div class="sales-post-detail-head">
            <div>
                <span class="sales-post-detail-platform" data-vq-detail-platform>Marketplace</span>
                <h2 data-vq-detail-heading>Verification details</h2>
            </div>
            <button type="button" class="icon-close sales-post-detail-close" data-vq-detail-close aria-label="Close verification details" title="Close">×</button>
        </div>
        <div class="sales-post-detail-scroll">
            <div class="sales-post-detail-content">
                <div class="sales-post-detail-status-row">
                    <span class="sales-post-detail-status" data-vq-detail-status>Waiting</span>
                </div>
                <div class="sales-post-detail-date">
                    <span>Queued</span>
                    <strong data-vq-detail-date>—</strong>
                </div>
                <h3 data-vq-detail-title>Queued listing</h3>
                <p data-vq-detail-message>Background verification is waiting to start.</p>
                <dl class="sales-post-detail-facts">
                    <div><dt>Platform</dt><dd data-vq-detail-platform-value>—</dd></div>
                    <div><dt>Post ID</dt><dd data-vq-detail-post-id>—</dd></div>
                    <div class="sales-vq-detail-url-fact"><dt>Original URL</dt><dd><a data-vq-detail-url target="_blank" rel="noopener noreferrer" href="#">—</a></dd></div>
                    <div><dt>Status</dt><dd data-vq-detail-status-value>—</dd></div>
                </dl>
            </div>
        </div>
        <div class="sales-post-detail-footer">
            <button type="button" class="btn" data-vq-detail-close>Close</button>
            <a class="btn primary" data-vq-detail-open target="_blank" rel="noopener noreferrer" href="#">Open original</a>
        </div>
    </section>
</div>
