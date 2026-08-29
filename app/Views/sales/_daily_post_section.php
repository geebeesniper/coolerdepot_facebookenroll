<?php
use App\Core\Csrf;
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

        <div class="daily-post-summary">
            <span class="post-summary total">
                <span data-sales-i18n="posts">Posts</span>
                <?= $postCount ?>
            </span>
            <span class="post-summary good">
                <span data-sales-i18n="good">Good</span>
                <?= $goodCount ?>
            </span>
            <span class="post-summary bad">
                <span data-sales-i18n="issues">Issues</span>
                <?= $badCount ?>
            </span>
            <span class="post-summary neutral">
                <span data-sales-i18n="unreviewed">Unreviewed</span>
                <?= $unreviewedCount ?>
            </span>
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
                    <a
                        class="sales-open-original"
                        target="_blank"
                        rel="noopener"
                        href="<?= Util::e((string)$p['canonical_url']) ?>"
                    >
                        <span data-sales-i18n="openOriginal">Open original</span>
                    </a>

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

                <div class="sales-delete-zone">
                    <button
                        type="button"
                        class="sales-delete-toggle"
                        data-delete-toggle
                    >
                        <span data-sales-i18n="requestDeletion">
                            Request deletion
                        </span>
                    </button>

                    <form
                        class="sales-delete-form hidden"
                        data-delete-form
                        method="post"
                        action="<?= Util::e($config['app']['base_path']) ?>/sales/delete-request"
                    >
                        <input
                            type="hidden"
                            name="_csrf"
                            value="<?= Util::e(Csrf::token()) ?>"
                        >
                        <input
                            type="hidden"
                            name="post_id"
                            value="<?= (int)$p['id'] ?>"
                        >

                        <label>
                            <span data-sales-i18n="reason">Reason</span>
                            <input
                                name="reason"
                                required
                                placeholder="Reason"
                                data-sales-placeholder="reason"
                            >
                        </label>

                        <div class="sales-delete-actions">
                            <button
                                type="button"
                                class="tiny"
                                data-delete-cancel
                            >
                                <span data-sales-i18n="cancel">Cancel</span>
                            </button>

                            <button class="tiny badbtn" type="submit">
                                <span data-sales-i18n="sendRequest">Send request</span>
                            </button>
                        </div>

                        <div
                            class="sales-delete-message"
                            data-delete-message
                            aria-live="polite"
                        ></div>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
