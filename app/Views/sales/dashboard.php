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

            <button class="btn sales-range-apply" id="salesRangeApply" type="button">
                <span data-sales-i18n="apply">Apply</span>
            </button>
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

<section class="sales-overview-grid">
    <article class="sales-overview-card">
        <span data-sales-i18n="posts">Posts</span>
        <strong data-sales-summary="posts"><?= $postsTotal ?></strong>
        <small data-sales-i18n="selectedRange">Selected range</small>
    </article>

    <article class="sales-overview-card good">
        <span data-sales-i18n="good">Good</span>
        <strong data-sales-summary="good"><?= $goodTotal ?></strong>
        <small data-sales-i18n="passedReview">Passed review</small>
    </article>

    <article class="sales-overview-card bad">
        <span data-sales-i18n="issues">Issues</span>
        <strong data-sales-summary="bad"><?= $badTotal ?></strong>
        <small data-sales-i18n="needsAttention">Needs attention</small>
    </article>

    <article class="sales-overview-card neutral">
        <span data-sales-i18n="unreviewed">Unreviewed</span>
        <strong data-sales-summary="unreviewed"><?= $unreviewedTotal ?></strong>
        <small data-sales-i18n="awaitingReview">Awaiting Admin review</small>
    </article>
</section>

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
