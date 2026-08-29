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
        <strong><?= $postsTotal ?></strong>
        <small data-sales-i18n="selectedRange">Selected range</small>
    </article>

    <article class="sales-overview-card good">
        <span data-sales-i18n="good">Good</span>
        <strong><?= $goodTotal ?></strong>
        <small data-sales-i18n="passedReview">Passed review</small>
    </article>

    <article class="sales-overview-card bad">
        <span data-sales-i18n="issues">Issues</span>
        <strong><?= $badTotal ?></strong>
        <small data-sales-i18n="needsAttention">Needs attention</small>
    </article>

    <article class="sales-overview-card neutral">
        <span data-sales-i18n="unreviewed">Unreviewed</span>
        <strong><?= $unreviewedTotal ?></strong>
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

<?php if (!$days): ?>
    <div
        id="dailyPostsEmpty"
        class="panel empty sales-empty-state"
        data-sales-i18n="noPostsRange"
    >
        No posts in this date range.
    </div>
<?php endif; ?>

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
