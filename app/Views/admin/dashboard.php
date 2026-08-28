<?php
use App\Core\Csrf;
use App\Core\Util;

$base = $config['app']['base_path'];

$periodLabels = [
    'day' => 'Daily',
    'week' => 'Weekly',
    'month' => 'Monthly',
];

$maxPosts = 0;

foreach ($chartRows as $chartRow) {
    $maxPosts = max($maxPosts, (int)$chartRow['total_posts']);
}

$maxPosts = max(1, $maxPosts);

$selectedSalesName = 'All Sales';

if ($salesFilter > 0) {
    foreach ($sales as $salesUser) {
        if ((int)$salesUser['id'] === $salesFilter) {
            $selectedSalesName = (string)$salesUser['display_name'];
            break;
        }
    }
}
?>

<div class="page-head admin-page-head">
    <div>
        <div class="eyebrow">Administrator</div>
        <h1>Daily Work Progress</h1>
    </div>

    <form class="filters" method="get">
        <input
            type="hidden"
            name="chart_period"
            value="<?= Util::e($chartPeriod) ?>"
        >
        <?php if ($salesFilter > 0): ?>
            <input
                type="hidden"
                name="sales_id"
                value="<?= (int)$salesFilter ?>"
            >
        <?php endif; ?>
        <input
            type="date"
            name="date"
            value="<?= Util::e($date) ?>"
        >
        <button class="btn">View</button>
    </form>
</div>

<section class="panel admin-progress-panel">
    <div class="admin-progress-head">
        <div>
            <div class="eyebrow">Posting Performance</div>
            <h2><?= Util::e($periodLabels[$chartPeriod]) ?> Posting Volume</h2>
            <p>
                <?= Util::e($chartLabel) ?>
                · <?= Util::e($selectedSalesName) ?>
            </p>
        </div>

        <form class="admin-chart-filter" method="get">
            <input type="hidden" name="date" value="<?= Util::e($date) ?>">
            <input
                type="hidden"
                name="chart_period"
                value="<?= Util::e($chartPeriod) ?>"
            >

            <label>
                Sales
                <select name="sales_id" onchange="this.form.submit()">
                    <option value="0">All Sales</option>
                    <?php foreach ($sales as $salesUser): ?>
                        <option
                            value="<?= (int)$salesUser['id'] ?>"
                            <?= $salesFilter === (int)$salesUser['id']
                                ? 'selected'
                                : '' ?>
                        >
                            <?= Util::e($salesUser['display_name']) ?>
                            #<?= Util::e($salesUser['sales_id']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </form>
    </div>

    <div class="admin-period-switch" aria-label="Posting report period">
        <?php foreach ($periodLabels as $periodKey => $periodLabel): ?>
            <?php
            $periodHref = $base
                . '/admin?date=' . rawurlencode($date)
                . '&chart_period=' . rawurlencode($periodKey)
                . '&sales_id=' . (int)$salesFilter;
            ?>
            <a
                class="<?= $chartPeriod === $periodKey ? 'active' : '' ?>"
                href="<?= Util::e($periodHref) ?>"
            >
                <?= Util::e($periodLabel) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="admin-chart-metrics">
        <div>
            <strong><?= (int)$chartTotals['posts'] ?></strong>
            <span>Total Posts</span>
        </div>
        <div class="pass">
            <strong><?= (int)$chartTotals['good'] ?></strong>
            <span>Pass</span>
        </div>
        <div class="issue">
            <strong><?= (int)$chartTotals['bad'] ?></strong>
            <span>Issues</span>
        </div>
        <div>
            <strong><?= (int)$chartTotals['unreviewed'] ?></strong>
            <span>Unreviewed</span>
        </div>
    </div>

    <div class="admin-chart-legend" aria-label="Chart legend">
        <span><i class="pass"></i> Pass / Good</span>
        <span><i class="issue"></i> Issues / Bad</span>
        <span><i class="unreviewed"></i> Unreviewed</span>
    </div>

    <div class="admin-sales-chart-scroll">
        <?php if ($chartRows): ?>
            <div
                class="admin-sales-chart"
                style="--chart-columns:<?= max(1, count($chartRows)) ?>"
            >
                <?php foreach ($chartRows as $row): ?>
                    <?php
                    $total = (int)$row['total_posts'];
                    $good = (int)$row['good_posts'];
                    $bad = (int)$row['bad_posts'];
                    $unreviewed = max(0, $total - $good - $bad);

                    $trackHeight = $total > 0
                        ? max(7, ($total / $maxPosts) * 100)
                        : 0;

                    $goodHeight = $total > 0
                        ? ($good / $total) * 100
                        : 0;

                    $badHeight = $total > 0
                        ? ($bad / $total) * 100
                        : 0;

                    $badBottom = $goodHeight;

                    $barHref = $base
                        . '/admin?date=' . rawurlencode($date)
                        . '&chart_period=' . rawurlencode($chartPeriod)
                        . '&sales_id=' . (int)$row['sales_user_id']
                        . '#daily-posts';
                    ?>
                    <a
                        class="admin-sales-bar<?= $salesFilter === (int)$row['sales_user_id']
                            ? ' selected'
                            : '' ?>"
                        href="<?= Util::e($barHref) ?>"
                        title="<?= Util::e(
                            $row['display_name']
                            . ': ' . $total . ' posts, '
                            . $good . ' pass, '
                            . $bad . ' issues, '
                            . $unreviewed . ' unreviewed'
                        ) ?>"
                    >
                        <div class="admin-sales-bar-value">
                            <?= $total ?>
                        </div>

                        <div class="admin-sales-bar-shell">
                            <?php if ($total > 0): ?>
                                <div
                                    class="admin-sales-bar-track"
                                    style="height:<?= round($trackHeight, 2) ?>%"
                                >
                                    <div
                                        class="admin-sales-bar-pass"
                                        style="height:<?= round($goodHeight, 2) ?>%"
                                    ></div>
                                    <div
                                        class="admin-sales-bar-issue"
                                        style="
                                            height:<?= round($badHeight, 2) ?>%;
                                            bottom:<?= round($badBottom, 2) ?>%
                                        "
                                    ></div>
                                </div>
                            <?php else: ?>
                                <div class="admin-sales-bar-zero"></div>
                            <?php endif; ?>
                        </div>

                        <div class="admin-sales-bar-label">
                            <b><?= Util::e($row['display_name']) ?></b>
                            <small>#<?= Util::e($row['sales_id']) ?></small>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty">No Sales users are available.</div>
        <?php endif; ?>
    </div>
</section>

<section class="panel" id="daily-posts">
    <div class="panel-head">
        <div>
            <h2>
                Posts — <?= Util::e($date) ?>
                <?php if ($salesFilter > 0): ?>
                    · <?= Util::e($selectedSalesName) ?>
                <?php endif; ?>
            </h2>
            <p class="panel-subtitle">
                Based on the verified Facebook Marketplace published date.
            </p>
        </div>

        <a class="btn" href="<?= $base ?>/admin/reports">
            Weekly / Monthly Reports
        </a>
    </div>

    <div class="tablewrap">
        <table>
            <thead>
                <tr>
                    <th>Sales</th>
                    <th>Platform</th>
                    <th>Title</th>
                    <th>Published</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($posts as $post): ?>
                    <tr>
                        <td>
                            <?= Util::e($post['display_name']) ?>
                            <small>#<?= Util::e($post['sales_id']) ?></small>
                        </td>
                        <td><?= Util::e(ucfirst($post['platform'])) ?></td>
                        <td><?= Util::e($post['title']) ?></td>
                        <td><?= Util::e($post['published_at']) ?></td>
                        <td>
                            <?php if (in_array(
                                ($post['admin_review_status'] ?? null),
                                ['good','bad'],
                                true
                            )): ?>
                                <span class="status <?= Util::e($post['admin_review_status']) ?>">
                                    <?= Util::e(ucfirst($post['admin_review_status'])) ?>
                                </span>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?= $base ?>/admin/post?id=<?= (int)$post['id'] ?>">
                                Review
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$posts): ?>
                    <tr>
                        <td colspan="6">
                            No verified posts for this date/filter.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="panel">
    <h2>Daily Sales Reviews</h2>

    <div class="sales-grid">
        <?php foreach ($sales as $salesUser): ?>
            <a
                class="sales-card"
                href="<?= $base ?>/admin/daily?sales_id=<?= (int)$salesUser['id'] ?>&date=<?= Util::e($date) ?>"
            >
                <b><?= Util::e($salesUser['display_name']) ?></b>
                <span>#<?= Util::e($salesUser['sales_id']) ?></span>
                <em>Review day →</em>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($deletionRequests): ?>
    <div class="panel">
        <h2>Deletion Requests</h2>

        <?php foreach ($deletionRequests as $request): ?>
            <div class="request">
                <div>
                    <b>
                        <?= Util::e($request['display_name']) ?>
                        — <?= Util::e($request['title']) ?>
                    </b>
                    <span><?= Util::e($request['reason']) ?></span>
                </div>

                <form
                    method="post"
                    action="<?= $base ?>/admin/delete-request"
                >
                    <input
                        type="hidden"
                        name="_csrf"
                        value="<?= Util::e(Csrf::token()) ?>"
                    >
                    <input
                        type="hidden"
                        name="request_id"
                        value="<?= (int)$request['id'] ?>"
                    >
                    <button
                        name="action"
                        value="approve"
                        class="tiny okbtn"
                    >
                        Approve delete
                    </button>
                    <button
                        name="action"
                        value="reject"
                        class="tiny badbtn"
                    >
                        Reject
                    </button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
