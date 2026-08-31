<?php
use App\Core\Util;

$postsTotal=(int)($summary['post_count']??0);
$goodTotal=(int)($summary['good_count']??0);
$badTotal=(int)($summary['bad_count']??0);
$unreviewedTotal=(int)($summary['unreviewed_count']??0);
?>

<div
    class="sales-portal"
    id="salesPortalDashboard"
    data-from="<?= Util::e($from) ?>"
    data-to="<?= Util::e($to) ?>"
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

        <h1 data-sales-i18n="dashboardTitle">
            My Sales Activity
        </h1>

        <p class="sales-portal-subtitle" data-sales-i18n="dashboardSubtitle">
            Review your verified Marketplace posts and Admin review status.
        </p>
    </div>

    <div class="sales-portal-head-actions">
        <form class="sales-range-filter" id="salesRangeForm" method="get"
            action="<?= Util::e($config['app']['base_path']) ?>/sales" novalidate>
            <label>
                <span data-sales-i18n="from">From</span>
                <input type="date" name="from" id="salesRangeFrom"
                    value="<?= Util::e($from) ?>" max="<?= Util::e($to) ?>">
            </label>

            <label>
                <span data-sales-i18n="to">To</span>
                <input type="date" name="to" id="salesRangeTo"
                    value="<?= Util::e($to) ?>" min="<?= Util::e($from) ?>">
            </label>

            <span
                class="sales-range-live-status"
                id="salesRangeStatus"
                aria-live="polite"
            ></span>
        </form>

        <a
            class="btn primary sales-submit-cta"
            href="<?= Util::e($config['app']['base_path']) ?>/sales/submit"
        >
            <span class="sales-submit-plus">+</span>
            <span data-sales-i18n="submitPost">Submit Post</span>
        </a>
    </div>
</div>

<section
    class="sales-activity-chart-panel"
    id="salesActivityChartPanel"
    data-daily-target="<?= (int)$dailyTarget ?>"
>
    <div class="sales-activity-chart-head">
        <div>
            <span class="eyebrow" data-sales-i18n="activityChart">
                Posting Activity
            </span>
            <h2 data-sales-i18n="dailyProgress">
                Daily Post Progress
            </h2>
            <p>
                <span data-sales-i18n="targetLine">Daily target</span>
                <strong id="salesChartTargetCopy">
                    <?= (int)$dailyTarget ?>
                </strong>
            </p>
        </div>

        <div
            class="sales-platform-filter"
            id="salesPlatformFilter"
            role="group"
            aria-label="Filter dashboard by platform"
        >
            <button
                type="button"
                class="sales-platform-filter-button active"
                data-sales-platform-filter="all"
                aria-pressed="true"
            >
                <span data-sales-i18n="allPlatforms">All</span>
            </button>
            <button
                type="button"
                class="sales-platform-filter-button"
                data-sales-platform-filter="facebook"
                aria-pressed="false"
            >
                Facebook
            </button>
            <button
                type="button"
                class="sales-platform-filter-button"
                data-sales-platform-filter="instagram"
                aria-pressed="false"
            >
                Instagram
            </button>
            <button
                type="button"
                class="sales-platform-filter-button"
                data-sales-platform-filter="offerup"
                aria-pressed="false"
            >
                OfferUp
            </button>
            <button
                type="button"
                class="sales-platform-filter-button"
                data-sales-platform-filter="craigslist"
                aria-pressed="false"
            >
                Craigslist
            </button>
        </div>
    </div>

    <div class="sales-chart-legend">
        <span><i class="good"></i><b data-sales-i18n="good">Good</b></span>
        <span><i class="bad"></i><b data-sales-i18n="issues">Issues</b></span>
        <span><i class="unreviewed"></i><b data-sales-i18n="unreviewed">Unreviewed</b></span>
        <span><i class="missing"></i><b data-sales-i18n="missing">Missing</b></span>
    </div>

    <div class="sales-chart-shell">
        <div class="sales-chart-y-axis" aria-hidden="true">
            <span class="top" id="salesChartMaxLabel">—</span>
            <span class="target" id="salesChartTargetLabel">
                <?= (int)$dailyTarget ?>
            </span>
            <span class="zero">0</span>
        </div>

        <div class="sales-chart-scroll" id="salesChartScroll">
            <div class="sales-chart-canvas" id="salesChartCanvas">
                <div
                    class="sales-chart-target-line"
                    id="salesChartTargetLine"
                >
                    <span>
                        <span data-sales-i18n="targetLine">Daily target</span>
                        <b id="salesChartTargetLineValue"><?= (int)$dailyTarget ?></b>
                    </span>
                </div>

                <div
                    class="sales-chart-bars"
                    id="salesChartBars"
                    aria-label="Daily post progress chart"
                ></div>
            </div>
        </div>
    </div>

    <div
        class="sales-chart-tooltip hidden"
        id="salesChartTooltip"
        role="status"
    ></div>
</section>

<script type="application/json" id="salesChartInitialData"><?= json_encode(
    [
        'from'=>$from,
        'to'=>$to,
        'daily_target'=>(int)$dailyTarget,
        'rows'=>$chartRows,
    ],
    JSON_UNESCAPED_SLASHES
    | JSON_UNESCAPED_UNICODE
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
) ?></script>

<div
    id="dailyPosts"
    class="daily-posts sales-daily-posts"
    data-from="<?= Util::e($from) ?>"
    data-to="<?= Util::e($to) ?>"
    data-offset="<?= (int)$loadedDays ?>"
    data-limit="<?= (int)$loadDays ?>"
>
    <?php foreach ($days as $day): ?>
        <?php require __DIR__ . '/_daily_post_section.php'; ?>
    <?php endforeach; ?>
</div>

<div
    id="dailyPostsEmpty"
    class="panel empty sales-empty-state<?= $days ? ' hidden' : '' ?>"
    data-sales-i18n="noPostsRange"
>
    No posts in this date range.
</div>

<div
    class="sales-post-detail-backdrop hidden"
    id="salesPostDetailModal"
    aria-hidden="true"
>
    <section
        class="sales-post-detail-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="salesPostDetailTitle"
    >
        <div class="sales-post-detail-head">
            <div>
                <span
                    class="sales-post-detail-platform"
                    id="salesPostDetailPlatform"
                >
                    Marketplace
                </span>
                <h2 id="salesPostDetailTitle">Post details</h2>
            </div>

            <button
                type="button"
                class="icon-close sales-post-detail-close"
                id="salesPostDetailClose"
                aria-label="Close post details"
                title="Close"
            >
                ×
            </button>
        </div>

        <div class="sales-post-detail-scroll">
            <button
                type="button"
                class="sales-post-detail-image-button hidden"
                id="salesPostDetailImageButton"
                aria-label="View larger image"
            >
                <img
                    id="salesPostDetailImage"
                    src=""
                    alt=""
                >
                <span class="sales-post-detail-image-zoom">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M10.5 4a6.5 6.5 0 1 1-4.6 11.1l-3.5 3.5 1.4 1.4 3.5-3.5A6.5 6.5 0 0 1 10.5 4Zm0 2a4.5 4.5 0 1 0 0 9 4.5 4.5 0 0 0 0-9Zm-.9 1.6h1.8v2h2v1.8h-2v2H9.6v-2h-2V9.6h2v-2Z"/>
                    </svg>
                </span>
            </button>

            <div
                class="sales-post-detail-no-image"
                id="salesPostDetailNoImage"
            >
                <span data-sales-i18n="noImage">No listing image</span>
            </div>

            <div class="sales-post-detail-content">
                <div class="sales-post-detail-status-row">
                    <span
                        class="sales-post-detail-status"
                        id="salesPostDetailStatus"
                    >
                        Unreviewed
                    </span>
                </div>

                <div class="sales-post-detail-date">
                    <span data-sales-i18n="published">Published</span>
                    <strong id="salesPostDetailPublished">—</strong>
                </div>

                <h3 id="salesPostDetailContentTitle">—</h3>

                <p id="salesPostDetailDescription">—</p>

                <dl class="sales-post-detail-facts">
                    <div>
                        <dt data-sales-i18n="platform">Platform</dt>
                        <dd id="salesPostDetailPlatformValue">—</dd>
                    </div>

                    <div>
                        <dt data-sales-i18n="postId">Post ID</dt>
                        <dd id="salesPostDetailExternalId">—</dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="sales-post-detail-footer">
            <button
                type="button"
                class="btn"
                id="salesPostDetailFooterClose"
            >
                <span data-sales-i18n="close">Close</span>
            </button>

            <a
                class="btn primary"
                id="salesPostDetailOriginal"
                target="_blank"
                rel="noopener"
                href="#"
            >
                <span data-sales-i18n="openOriginal">Open original</span>
            </a>
        </div>
    </section>
</div>

<div
    class="sales-image-lightbox hidden"
    id="salesImageLightbox"
    aria-hidden="true"
>
    <button
        type="button"
        class="sales-image-lightbox-close"
        id="salesImageLightboxClose"
        aria-label="Close image"
    >
        ×
    </button>
    <img
        id="salesImageLightboxImage"
        src=""
        alt=""
    >
</div>

<div class="daily-load-more-wrap">
    <button
        type="button"
        id="loadMoreDailyPosts"
        class="btn"
        <?= $loadedDays >= $totalDays ? 'hidden' : '' ?>
    >
        <span data-sales-i18n="loadEarlier">Load earlier days</span>
    </button>

    <div
        id="dailyLoadStatus"
        class="daily-load-status"
    ></div>
</div>
