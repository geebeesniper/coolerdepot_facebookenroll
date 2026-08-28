<?php
use App\Core\Csrf;
use App\Core\Util;

$base = $config['app']['base_path'];
$csrf = Csrf::token();
?>

<div
    id="adminDashboardLive"
    data-updates-url="<?= Util::e($base) ?>/admin/dashboard/updates"
    data-date="<?= Util::e($date) ?>"
    data-post-count="<?= (int)$dashboardState['post_count'] ?>"
    data-max-post-id="<?= (int)$dashboardState['max_post_id'] ?>"
></div>

<div class="dashboard-refresh-notice hidden" id="dashboardRefreshNotice">
    <div>
        <span class="dashboard-refresh-dot"></span>
        <strong id="dashboardRefreshTitle">New posts are available</strong>
        <small id="dashboardRefreshText">
            Sales activity changed since this page was loaded.
        </small>
    </div>
    <button type="button" class="btn" id="dashboardRefreshButton">
        Refresh
    </button>
</div>

<div class="page-head admin-page-head">
    <div>
        <div class="eyebrow">Administrator</div>
        <h1>Daily Work Progress</h1>
        <p class="dashboard-date-copy">
            <?= Util::e(date('F j, Y', strtotime($date.' 12:00:00'))) ?>
        </p>
    </div>

    <form class="filters" method="get">
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

<section class="admin-sales-progress-section">
    <div class="admin-section-head">
        <div>
            <h2>Sales Posting Progress</h2>
            <p>
                Today's verified Marketplace posts against each Sales target.
            </p>
        </div>
        <div class="admin-section-summary">
            <?= count($salesProgress) ?> Sales
            · <?= (int)$dashboardState['post_count'] ?> Posts
        </div>
    </div>

    <input type="hidden" id="adminDashboardCsrf" value="<?= Util::e($csrf) ?>">

    <div
        class="sales-progress-grid"
        id="salesProgressGrid"
        data-target-url="<?= Util::e($base) ?>/admin/sales-target"
    >
        <?php foreach ($salesProgress as $row): ?>
            <?php
            $count = (int)$row['post_count'];
            $target = max(1, (int)$row['target_posts']);
            $percent = min(100, round(($count / $target) * 100));
            $met = $count >= $target;
            $unreviewed = max(
                0,
                $count - (int)$row['good_count'] - (int)$row['bad_count']
            );
            $cardHref = $base
                . '/admin?date=' . rawurlencode($date)
                . '&sales_id=' . (int)$row['sales_user_id']
                . '#daily-posts';
            ?>
            <article
                class="sales-progress-card<?= $met ? ' target-met' : '' ?><?= $salesFilter === (int)$row['sales_user_id'] ? ' selected' : '' ?>"
                data-sales-id="<?= (int)$row['sales_user_id'] ?>"
                data-post-count="<?= $count ?>"
            >
                <div class="sales-progress-card-head">
                    <div class="sales-progress-avatar" aria-hidden="true">
                        <?= Util::e(
                            strtoupper(
                                substr(
                                    trim((string)$row['display_name']),
                                    0,
                                    1
                                )
                            )
                        ) ?>
                    </div>
                    <div class="sales-progress-person">
                        <strong><?= Util::e($row['display_name']) ?></strong>
                        <span>#<?= Util::e($row['sales_id']) ?></span>
                    </div>
                    <?php if ($met): ?>
                        <span class="sales-target-badge">Target met</span>
                    <?php endif; ?>
                </div>

                <div class="sales-progress-number">
                    <strong data-progress-count><?= $count ?></strong>
                    <span>
                        / <b data-progress-target><?= $target ?></b> posts
                    </span>
                </div>

                <div
                    class="sales-progress-track"
                    role="progressbar"
                    aria-label="<?= Util::e($row['display_name']) ?> daily posting progress"
                    aria-valuemin="0"
                    aria-valuemax="<?= $target ?>"
                    aria-valuenow="<?= $count ?>"
                >
                    <div
                        class="sales-progress-fill"
                        data-progress-fill
                        style="width:<?= $percent ?>%"
                    ></div>
                </div>

                <div class="sales-progress-meta">
                    <span>
                        <?= (int)$row['good_count'] ?> Good
                    </span>
                    <span>
                        <?= (int)$row['bad_count'] ?> Issues
                    </span>
                    <span>
                        <?= $unreviewed ?> Unreviewed
                    </span>
                </div>

                <div class="sales-progress-actions">
                    <a
                        class="sales-progress-view"
                        href="<?= Util::e($cardHref) ?>"
                    >
                        View Posts
                    </a>

                    <div class="sales-target-editor">
                        <label>
                            Target
                            <input
                                type="number"
                                min="1"
                                max="999"
                                value="<?= $target ?>"
                                data-target-input
                                aria-label="Daily target for <?= Util::e($row['display_name']) ?>"
                            >
                        </label>
                        <button
                            type="button"
                            class="tiny sales-target-save"
                            data-target-save
                        >
                            Save
                        </button>
                    </div>
                </div>

                <div
                    class="sales-target-message"
                    data-target-message
                    aria-live="polite"
                ></div>
            </article>
        <?php endforeach; ?>

        <?php if (!$salesProgress): ?>
            <div class="panel empty">
                No active Sales users.
            </div>
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
                Verified Facebook Marketplace publication date.
            </p>
        </div>

        <?php if ($salesFilter > 0): ?>
            <a
                class="btn"
                href="<?= Util::e(
                    $base . '/admin?date=' . rawurlencode($date)
                    . '#daily-posts'
                ) ?>"
            >
                All Sales
            </a>
        <?php endif; ?>
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
                                <span
                                    class="status <?= Util::e(
                                        $post['admin_review_status']
                                    ) ?>"
                                >
                                    <?= Util::e(
                                        ucfirst($post['admin_review_status'])
                                    ) ?>
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
                        value="<?= Util::e($csrf) ?>"
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
