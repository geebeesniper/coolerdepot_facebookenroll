<?php
use App\Core\Csrf;
use App\Core\Util;

$days = [];
foreach ($counts as $r) {
    $d = $r['work_date'];
    if (!isset($days[$d])) {
        $days[$d] = [
            'facebook' => 0,
            'offerup' => 0,
            'craigslist' => 0,
            'total' => 0,
        ];
    }
    $days[$d][$r['platform']] = (int)$r['cnt'];
    $days[$d]['total'] += (int)$r['cnt'];
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

<?php if ($days): ?>
<div class="metrics compact-metrics">
    <?php foreach ($days as $d => $c): ?>
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

<div class="panel posts-panel">
    <div class="panel-head">
        <h2>Posts <span class="post-count"><?= count($posts) ?></span></h2>
        <div class="view-switch">
            <button type="button" class="tiny" data-view="list">List</button>
            <button type="button" class="tiny active" data-view="grid">Grid</button>
        </div>
    </div>

    <div id="postCollection" class="post-grid">
        <?php foreach ($posts as $p): ?>
            <article class="post">
                <div class="post-top">
                    <span class="platform <?= Util::e($p['platform']) ?>">
                        <?= Util::e(ucfirst($p['platform'])) ?>
                    </span>
                    <span class="status <?= Util::e($p['admin_review_status']) ?>">
                        <?= Util::e(ucfirst($p['admin_review_status'])) ?>
                    </span>
                </div>

                <div class="post-main">
                    <b class="post-title"><?= Util::e($p['title']) ?></b>
                    <a
                        class="post-url"
                        target="_blank"
                        rel="noopener"
                        href="<?= Util::e($p['canonical_url']) ?>"
                        title="<?= Util::e($p['canonical_url']) ?>"
                    ><?= Util::e($p['canonical_url']) ?></a>

                    <div class="post-dates">
                        <small><span>Published</span><?= Util::e($p['published_at']) ?></small>
                        <small><span>Saved</span><?= Util::e($p['created_at']) ?></small>
                    </div>
                </div>

                <details class="delete-request">
                    <summary>Request deletion</summary>
                    <form method="post" action="<?= $config['app']['base_path'] ?>/sales/delete-request">
                        <input type="hidden" name="_csrf" value="<?= Util::e(Csrf::token()) ?>">
                        <input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>">
                        <input name="reason" required placeholder="Reason">
                        <button class="tiny badbtn">Send</button>
                    </form>
                </details>
            </article>
        <?php endforeach; ?>

        <?php if (!$posts): ?>
            <div class="empty">No posts in this date range.</div>
        <?php endif; ?>
    </div>
</div>
