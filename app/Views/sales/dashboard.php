<?php
use App\Core\Util;

$metricDays = [];
foreach ($counts as $r) {
    $d = $r['work_date'];
    if (!isset($metricDays[$d])) {
        $metricDays[$d] = [
            'facebook' => 0,
            'offerup' => 0,
            'craigslist' => 0,
            'total' => 0,
        ];
    }

    $metricDays[$d][$r['platform']] = (int)$r['cnt'];
    $metricDays[$d]['total'] += (int)$r['cnt'];
}
?>

<div class="page-head">
    <div>
        <div class="eyebrow">Sales Dashboard</div>
        <h1><?= Util::e($user['display_name']) ?></h1>
        <p>Saved posts cannot be deleted by Sales. Request deletion from Admin if needed.</p>
    </div>

    <a class="btn primary" href="<?= $config['app']['base_path'] ?>/sales/submit">+ Submit Post</a>
</div>

<div class="panel filter-panel">
    <form class="filters">
        <label>
            From
            <input type="date" name="from" value="<?= Util::e($from) ?>">
        </label>

        <label>
            To
            <input type="date" name="to" value="<?= Util::e($to) ?>">
        </label>

        <button class="btn">Apply</button>
    </form>
</div>

<?php if ($metricDays): ?>
<div class="metrics compact-metrics">
    <?php foreach ($metricDays as $d => $c): ?>
        <div class="metric compact-metric">
            <small><?= Util::e($d) ?></small>
            <strong><?= $c['total'] ?></strong>

            <div class="metric-platforms">
                <span>FB <b><?= $c['facebook'] ?></b></span>
                <span>OfferUp <b><?= $c['offerup'] ?></b></span>
                <span>CL <b><?= $c['craigslist'] ?></b></span>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div
    id="dailyPosts"
    class="daily-posts"
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
    <div id="dailyPostsEmpty" class="panel empty">No posts in this date range.</div>
<?php endif; ?>

<div class="daily-load-more-wrap">
    <button
        type="button"
        id="loadMoreDailyPosts"
        class="btn"
        <?= $loadedDays >= $totalDays ? 'hidden' : '' ?>
    >Load earlier days</button>

    <div id="dailyLoadStatus" class="daily-load-status"></div>
</div>
