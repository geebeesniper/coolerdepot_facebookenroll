<?php
use App\Core\Util;

$date=(string)$day['date'];
$postCount=(int)$day['post_count'];
$goodCount=(int)$day['good_count'];
$badCount=(int)$day['bad_count'];
$unreviewedCount=max(
    0,
    $postCount-$goodCount-$badCount
);

$salesPlatformIcon = static function(string $platform): string
{
    $platform=strtolower($platform);

    if($platform==='facebook'){
        return '<span class="platform-logo platform-logo-facebook"'
            .' title="Facebook" aria-label="Facebook">'
            .'<svg viewBox="0 0 24 24" aria-hidden="true">'
            .'<path d="M13.8 21v-8h2.7l.4-3.1h-3.1v-2c0-.9.3-1.5 1.6-1.5H17V3.6c-.3 0-1.3-.1-2.5-.1-2.5 0-4.2 1.5-4.2 4.3v2.1H7.5V13h2.8v8h3.5Z"/>'
            .'</svg></span>';
    }

    if($platform==='offerup'){
        return '<span class="platform-logo platform-logo-offerup"'
            .' title="OfferUp" aria-label="OfferUp">'
            .'<svg viewBox="0 0 24 24" aria-hidden="true">'
            .'<circle cx="8" cy="12" r="5.2"/>'
            .'<circle cx="16" cy="12" r="5.2"/>'
            .'<path d="M7.8 8.7v6.6M16.2 8.7v6.6"/>'
            .'</svg></span>';
    }

    if($platform==='craigslist'){
        return '<span class="platform-logo platform-logo-craigslist"'
            .' title="Craigslist" aria-label="Craigslist">'
            .'<svg viewBox="0 0 24 24" aria-hidden="true">'
            .'<circle cx="12" cy="12" r="8"/>'
            .'<path d="M12 4v16M12 12l-5.2 4M12 12l5.2 4"/>'
            .'</svg></span>';
    }

    return '<span class="platform-logo platform-logo-generic">'
        .'<svg viewBox="0 0 24 24" aria-hidden="true">'
        .'<path d="M4 5h16v14H4V5Zm2 2v10h12V7H6Z"/>'
        .'</svg></span>';
};
?>
<section
    class="daily-post-section sales-day-section"
    data-post-date="<?= Util::e($date) ?>"
>
    <div class="daily-post-head sales-day-head">
        <div>
            <div class="daily-post-date">
                <?= Util::e($date) ?>
            </div>
            <h2 data-sales-i18n="dailyPosts">Daily Posts</h2>
        </div>

        <div
            class="daily-post-summary sales-day-filter"
            role="group"
            aria-label="Filter posts by review status"
        >
            <button
                type="button"
                class="post-summary total active"
                data-sales-day-filter="all"
                aria-pressed="true"
            >
                <span data-sales-i18n="allPosts">All</span>
                <strong><?= $postCount ?></strong>
            </button>

            <button
                type="button"
                class="post-summary good"
                data-sales-day-filter="good"
                aria-pressed="false"
            >
                <span data-sales-i18n="good">Good</span>
                <strong><?= $goodCount ?></strong>
            </button>

            <button
                type="button"
                class="post-summary bad"
                data-sales-day-filter="bad"
                aria-pressed="false"
            >
                <span data-sales-i18n="issues">Issues</span>
                <strong><?= $badCount ?></strong>
            </button>

            <button
                type="button"
                class="post-summary neutral"
                data-sales-day-filter="unreviewed"
                aria-pressed="false"
            >
                <span data-sales-i18n="unreviewed">Unreviewed</span>
                <strong><?= $unreviewedCount ?></strong>
            </button>
        </div>
    </div>

    <div class="sales-post-card-grid">
        <?php foreach ($day['posts'] as $index => $p): ?>
            <?php
            $status=(string)($p['current_review_status']??'');
            $statusClass=in_array($status,['good','bad'],true)
                ? ' review-'.$status
                : '';
            ?>
            <article
                class="sales-self-post-card<?= Util::e($statusClass) ?>"
                data-sales-post-id="<?= (int)$p['id'] ?>"
                data-sales-post-platform="<?= Util::e((string)$p['platform']) ?>"
                data-sales-post-title="<?= Util::e((string)$p['title']) ?>"
                data-sales-post-description="<?= Util::e((string)($p['description'] ?? '')) ?>"
                data-sales-post-published="<?= Util::e((string)$p['published_at']) ?>"
                data-sales-post-url="<?= Util::e((string)$p['canonical_url']) ?>"
                data-sales-post-image="<?= Util::e((string)($p['fetched_image_url'] ?? '')) ?>"
                data-sales-post-status="<?= Util::e($status ?: 'unreviewed') ?>"
                data-sales-post-external-id="<?= Util::e((string)($p['external_post_id'] ?? '')) ?>"
                role="button"
                tabindex="0"
                aria-label="View post details: <?= Util::e((string)$p['title']) ?>"
            >
                <div class="sales-self-post-media">
                    <?php if (!empty($p['fetched_image_url'])): ?>
                        <img
                            src="<?= Util::e((string)$p['fetched_image_url']) ?>"
                            loading="lazy"
                            alt=""
                        >
                    <?php else: ?>
                        <div class="sales-self-post-placeholder">
                            <?= $salesPlatformIcon((string)$p['platform']) ?>
                            <span><?= Util::e(ucfirst((string)$p['platform'])) ?></span>
                        </div>
                    <?php endif; ?>

                    <span class="sales-self-post-sequence">
                        <?= (int)$index + 1 ?>
                    </span>

                    <span class="sales-self-post-platform">
                        <?= $salesPlatformIcon((string)$p['platform']) ?>
                    </span>
                </div>

                <div class="sales-self-post-body">
                    <div class="sales-self-post-date">
                        <span data-sales-i18n="published">Published</span>
                        · <?= Util::e((string)$p['published_at']) ?>
                    </div>

                    <h3><?= Util::e((string)$p['title']) ?></h3>

                    <p>
                        <?= Util::e(
                            trim((string)($p['description']??'')) !== ''
                                ? (string)$p['description']
                                : 'No description available.'
                        ) ?>
                    </p>
                </div>

                <div class="sales-self-post-footer">
                    <button
                        type="button"
                        class="sales-view-details"
                        data-view-sales-post
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 5c5.5 0 9.5 5.2 9.5 7s-4 7-9.5 7S2.5 13.8 2.5 12 6.5 5 12 5Zm0 2C8.3 7 5.3 10.2 4.6 12c.7 1.8 3.7 5 7.4 5s6.7-3.2 7.4-5C18.7 10.2 15.7 7 12 7Zm0 2.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Z"/>
                        </svg>
                        <span data-sales-i18n="viewDetails">View details</span>
                    </button>

                    <span
                        class="sales-self-post-status<?= $status ? ' '.$status : '' ?>"
                    >
                        <?php if ($status === 'good'): ?>
                            <span data-sales-i18n="good">Good</span>
                        <?php elseif ($status === 'bad'): ?>
                            <span data-sales-i18n="issues">Issues</span>
                        <?php else: ?>
                            <span data-sales-i18n="unreviewed">Unreviewed</span>
                        <?php endif; ?>
                    </span>
                </div>

            </article>
        <?php endforeach; ?>
    </div>
</section>
