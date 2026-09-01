<?php
use App\Core\Util;

$postCount=(int)($summary['post_count']??0);
$goodCount=(int)($summary['good_count']??0);
$badCount=(int)($summary['bad_count']??0);
$unreviewedCount=max(0,$postCount-$goodCount-$badCount);

$formatRangeDate=static function(string $value): string {
    $time=strtotime($value);
    return $time ? date('M j, Y',$time) : $value;
};

$formatPostDate=static function(string $value): string {
    $time=strtotime($value);
    return $time ? date('M j, Y · g:i A',$time) : $value;
};

$salesPlatformIcon=static function(string $platform): string {
    $platform=strtolower($platform);
    if($platform==='facebook'){
        return '<span class="platform-logo platform-logo-facebook" title="Facebook" aria-label="Facebook">'
            .'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13.8 21v-8h2.7l.4-3.1h-3.1v-2c0-.9.3-1.5 1.6-1.5H17V3.6c-.3 0-1.3-.1-2.5-.1-2.5 0-4.2 1.5-4.2 4.3v2.1H7.5V13h2.8v8h3.5Z"/></svg></span>';
    }
    if($platform==='instagram'){
        return '<span class="platform-logo platform-logo-instagram" title="Instagram" aria-label="Instagram">'
            .'<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4.5" y="4.5" width="15" height="15" rx="4"/><circle cx="12" cy="12" r="3.4"/><circle cx="17.2" cy="6.8" r="1"/></svg></span>';
    }
    if($platform==='offerup'){
        return '<span class="platform-logo platform-logo-offerup" title="OfferUp" aria-label="OfferUp">'
            .'<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="8" cy="12" r="5.2"/><circle cx="16" cy="12" r="5.2"/><path d="M7.8 8.7v6.6M16.2 8.7v6.6"/></svg></span>';
    }
    if($platform==='craigslist'){
        return '<span class="platform-logo platform-logo-craigslist" title="Craigslist" aria-label="Craigslist">'
            .'<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M12 4v16M12 12l-5.2 4M12 12l5.2 4"/></svg></span>';
    }
    return '<span class="platform-logo platform-logo-generic"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4V5Zm2 2v10h12V7H6Z"/></svg></span>';
};
?>
<section class="sales-range-post-section" id="salesRangePostSection" data-active-post-filter="all">
    <div class="sales-range-post-head">
        <div>
            <h2><span data-sales-i18n="posts">Posts</span></h2>
            <p class="sales-range-post-dates"><?= Util::e($formatRangeDate($from)) ?> — <?= Util::e($formatRangeDate($to)) ?></p>
        </div>
        <div class="daily-post-summary sales-day-filter sales-range-post-filter" role="group" aria-label="Filter posts by review status">
            <button type="button" class="post-summary total active" data-sales-post-filter="all" aria-pressed="true" title="All: <?= $postCount ?>"><span data-sales-i18n="allPosts">All</span><strong><?= $postCount ?></strong></button>
            <button type="button" class="post-summary good" data-sales-post-filter="good" aria-pressed="false" title="Good: <?= $goodCount ?>"><span data-sales-i18n="good">Good</span><strong><?= $goodCount ?></strong></button>
            <button type="button" class="post-summary bad" data-sales-post-filter="bad" aria-pressed="false" title="Bad: <?= $badCount ?>"><span data-sales-i18n="issues">Bad</span><strong><?= $badCount ?></strong></button>
            <button type="button" class="post-summary neutral" data-sales-post-filter="unreviewed" aria-pressed="false" title="Unreviewed: <?= $unreviewedCount ?>"><span data-sales-i18n="unreviewed">Unreviewed</span><strong><?= $unreviewedCount ?></strong></button>
        </div>
    </div>
    <div class="sales-post-card-grid sales-range-post-grid" id="salesRangePostGrid">
        <?php if (!$posts): ?>
            <div class="sales-empty-message sales-range-post-empty" role="status" data-sales-range-empty>
                <span class="sales-empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 4h16v16H4V4Zm2 2v12h12V6H6Zm2 2h8v2H8V8Zm0 4h5v2H8v-2Z"/></svg></span>
                <strong data-sales-i18n="empty">Empty</strong>
                <span data-sales-i18n="noPostsRange">No posts in this date range.</span>
            </div>
        <?php endif; ?>
        <?php foreach ($posts as $index => $p): ?>
            <?php
            $status=strtolower(trim((string)($p['current_review_status']??'')));
            if(!in_array($status,['good','bad'],true)){$status='';}
            $statusClass=in_array($status,['good','bad'],true) ? ' review-'.$status : '';
            $publishedRaw=(string)($p['published_at']??$p['published_date']??'');
            ?>
            <article class="sales-self-post-card<?= Util::e($statusClass) ?>"
                data-sales-post-id="<?= (int)$p['id'] ?>"
                data-sales-post-platform="<?= Util::e((string)$p['platform']) ?>"
                data-sales-post-title="<?= Util::e((string)$p['title']) ?>"
                data-sales-post-description="<?= Util::e((string)($p['description']??'')) ?>"
                data-sales-post-published="<?= Util::e($publishedRaw) ?>"
                data-sales-post-date="<?= Util::e((string)($p['published_date']??'')) ?>"
                data-sales-post-url="<?= Util::e((string)$p['canonical_url']) ?>"
                data-sales-post-image="<?= Util::e((string)($p['fetched_image_url']??'')) ?>"
                data-sales-post-status="<?= Util::e($status ?: 'unreviewed') ?>"
                data-sales-post-external-id="<?= Util::e((string)($p['external_post_id']??'')) ?>"
                data-sales-post-delete-status="<?= Util::e((string)($p['deletion_request_status']??'')) ?>"
                role="button" tabindex="0" aria-label="View post details: <?= Util::e((string)$p['title']) ?>">
                <div class="sales-self-post-media">
                    <?php if (!empty($p['fetched_image_url'])): ?>
                        <img src="<?= Util::e((string)$p['fetched_image_url']) ?>" loading="lazy" alt="">
                    <?php else: ?>
                        <div class="sales-self-post-placeholder"><?= $salesPlatformIcon((string)$p['platform']) ?><span><?= Util::e(ucfirst((string)$p['platform'])) ?></span></div>
                    <?php endif; ?>
                    <span class="sales-self-post-platform"><?= $salesPlatformIcon((string)$p['platform']) ?></span>
                </div>
                <div class="sales-self-post-body">
                    <div class="sales-self-post-date"><span data-sales-i18n="postDate">Post date</span> · <?= Util::e($formatPostDate($publishedRaw)) ?></div>
                    <h3><?= Util::e((string)$p['title']) ?></h3>
                    <p><?= Util::e(trim((string)($p['description']??''))!=='' ? (string)$p['description'] : 'No description available.') ?></p>
                </div>
                <div class="sales-self-post-footer">
                    <button type="button" class="sales-view-details" data-view-sales-post><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5c5.5 0 9.5 5.2 9.5 7s-4 7-9.5 7S2.5 13.8 2.5 12 6.5 5 12 5Zm0 2C8.3 7 5.3 10.2 4.6 12c.7 1.8 3.7 5 7.4 5s6.7-3.2 7.4-5C18.7 10.2 15.7 7 12 7Zm0 2.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Z"/></svg><span data-sales-i18n="viewDetails">View details</span></button>
                    <span class="sales-self-post-status<?= $status ? ' '.$status : '' ?>"><?php if ($status==='good'): ?><span data-sales-i18n="good">Good</span><?php elseif ($status==='bad'): ?><span data-sales-i18n="issues">Bad</span><?php else: ?><span data-sales-i18n="unreviewed">Unreviewed</span><?php endif; ?></span>
                </div>
            </article>
        <?php endforeach; ?>
        <div class="sales-empty-message sales-range-filter-empty hidden" data-sales-post-filter-empty role="status">
            <span class="sales-empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 4h16v16H4V4Zm2 2v12h12V6H6Zm2 2h8v2H8V8Zm0 4h5v2H8v-2Z"/></svg></span>
            <strong data-sales-i18n="empty">Empty</strong>
            <span data-sales-post-filter-empty-copy>No posts match this filter.</span>
        </div>
    </div>
</section>
