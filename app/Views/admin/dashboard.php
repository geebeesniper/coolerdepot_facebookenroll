<?php
use App\Core\Csrf;
use App\Core\Util;

$base = $config['app']['base_path'];
$csrf = Csrf::token();

$periodNames = [
    'day' => 'Daily',
    'week' => 'Weekly',
    'month' => 'Monthly',
];
?>

<div
    id="adminDashboardLive"
    data-updates-url="<?= Util::e($base) ?>/admin/dashboard/updates"
    data-progress-url="<?= Util::e($base) ?>/admin/dashboard/progress"
    data-sales-posts-url="<?= Util::e($base) ?>/admin/dashboard/sales-posts"
    data-date="<?= Util::e($date) ?>"
    data-period="<?= Util::e($period) ?>"
    data-period-days="<?= (int)$periodInfo['days'] ?>"
    data-post-count="<?= (int)$dashboardState['post_count'] ?>"
    data-max-post-id="<?= (int)$dashboardState['max_post_id'] ?>"
></div>

<div class="dashboard-refresh-notice hidden" id="dashboardRefreshNotice">
    <div>
        <span class="dashboard-refresh-dot"></span>
        <strong id="dashboardRefreshTitle">New posts are available</strong>
        <small id="dashboardRefreshText">
            Sales activity changed since this view was loaded.
        </small>
    </div>
    <button type="button" class="btn" id="dashboardRefreshButton">
        Refresh
    </button>
</div>

<div class="page-head admin-page-head">
    <div>
        <div class="eyebrow">Administrator</div>
        <h1>Sales Work Progress</h1>
        <p class="dashboard-date-copy" id="dashboardPeriodLabel">
            <?= Util::e($periodInfo['label']) ?>
        </p>
    </div>

    <form class="filters" method="get">
        <input
            type="hidden"
            name="period"
            value="<?= Util::e($period) ?>"
            id="dashboardPeriodFormValue"
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

<section class="admin-sales-progress-section">
    <div class="admin-progress-toolbar">
        <div
            class="dashboard-period-switch"
            id="dashboardPeriodSwitch"
            aria-label="Sales progress period"
        >
            <?php foreach ($periodNames as $periodKey => $periodName): ?>
                <button
                    type="button"
                    class="dashboard-period-button<?= $period === $periodKey ? ' active' : '' ?>"
                    data-period="<?= Util::e($periodKey) ?>"
                    aria-pressed="<?= $period === $periodKey ? 'true' : 'false' ?>"
                >
                    <?= Util::e($periodName) ?>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="admin-section-summary">
            <strong id="dashboardSalesCount">
                <?= count($salesProgress) ?>
            </strong>
            Sales
            <span>·</span>
            <strong id="dashboardPostCount">
                <?= (int)$dashboardState['post_count'] ?>
            </strong>
            Posts
        </div>
    </div>

    <div class="admin-section-head compact">
        <div>
            <h2 id="dashboardProgressTitle">
                <?= Util::e($periodNames[$period]) ?> Posting Progress
            </h2>
            <p id="dashboardProgressSubtitle">
                Daily target × <?= (int)$periodInfo['days'] ?>
                = <?= Util::e($periodInfo['short_label']) ?>.
            </p>
        </div>
    </div>

    <input type="hidden" id="adminDashboardCsrf" value="<?= Util::e($csrf) ?>">

    <div
        class="sales-progress-grid"
        id="salesProgressGrid"
        data-target-url="<?= Util::e($base) ?>/admin/sales-target"
    >
        <?php foreach ($salesProgress as $index => $row): ?>
            <article
                class="sales-progress-card sales-progress-color-<?= ($index % 8) + 1 ?><?= !empty($row['target_met']) ? ' target-met' : '' ?>"
                data-sales-id="<?= (int)$row['sales_user_id'] ?>"
                data-sales-name="<?= Util::e($row['display_name']) ?>"
                data-post-count="<?= (int)$row['post_count'] ?>"
                data-daily-target="<?= (int)$row['daily_target'] ?>"
                data-card-toggle
                role="button"
                tabindex="0"
                aria-expanded="false"
                aria-controls="salesExpandedPosts"
                style="--card-index:<?= (int)$index ?>"
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

                    <span
                        class="sales-target-badge<?= empty($row['target_met']) ? ' hidden' : '' ?>"
                        data-target-badge
                    >
                        Target met
                    </span>
                </div>

                <div class="sales-progress-number">
                    <strong data-progress-count>
                        <?= (int)$row['post_count'] ?>
                    </strong>
                    <span>
                        / <b data-progress-target>
                            <?= (int)$row['period_target'] ?>
                        </b> posts
                    </span>
                </div>

                <div class="sales-period-target-copy">
                    <span>
                        <b data-daily-target-label>
                            <?= (int)$row['daily_target'] ?>
                        </b>/day
                    </span>
                    <span>
                        <b data-period-days>
                            <?= (int)$periodInfo['days'] ?>
                        </b>
                        day<?= (int)$periodInfo['days'] === 1 ? '' : 's' ?>
                    </span>
                </div>

                <div
                    class="sales-progress-track"
                    role="progressbar"
                    aria-label="<?= Util::e($row['display_name']) ?> posting progress"
                    aria-valuemin="0"
                    aria-valuemax="<?= (int)$row['period_target'] ?>"
                    aria-valuenow="<?= (int)$row['post_count'] ?>"
                >
                    <div
                        class="sales-progress-fill"
                        data-progress-fill
                        style="width:<?= (int)$row['percent'] ?>%"
                    ></div>
                </div>

                <div class="sales-progress-meta">
                    <span>
                        <b data-good-count><?= (int)$row['good_count'] ?></b>
                        Good
                    </span>
                    <span>
                        <b data-bad-count><?= (int)$row['bad_count'] ?></b>
                        Issues
                    </span>
                    <span>
                        <b data-unreviewed-count><?= (int)$row['unreviewed_count'] ?></b>
                        Unreviewed
                    </span>
                </div>

                <div class="sales-progress-actions">
                    <div class="sales-card-expand-hint">
                        <span>Tap to view posts</span>
                        <span class="sales-card-chevron" aria-hidden="true">⌄</span>
                    </div>

                    <div class="sales-target-editor" data-card-control>
                        <label>
                            Daily Target
                            <input
                                type="number"
                                min="1"
                                max="999"
                                value="<?= (int)$row['daily_target'] ?>"
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

    <section
        class="sales-expanded-posts hidden"
        id="salesExpandedPosts"
        aria-live="polite"
    >
        <div class="sales-expanded-head">
            <div>
                <div class="eyebrow" id="salesExpandedEyebrow">
                    Sales Posts
                </div>
                <h3 id="salesExpandedTitle">Posts</h3>
                <p id="salesExpandedSubtitle"></p>
            </div>
            <button
                type="button"
                class="tiny sales-expanded-close"
                id="salesExpandedClose"
                aria-label="Close Sales post list"
            >
                Close
            </button>
        </div>

        <div class="sales-expanded-loading hidden" id="salesExpandedLoading">
            <span></span>
            <span></span>
            <span></span>
        </div>

        <div
            class="sales-expanded-list"
            id="salesExpandedList"
        ></div>
    </section>
</section>

<section class="panel daily-posts-panel" id="daily-posts">
    <div class="panel-head">
        <div>
            <h2>
                Posts — <?= Util::e($date) ?>
                <?php if ($salesFilter > 0): ?>
                    · <?= Util::e($selectedSalesName) ?>
                <?php endif; ?>
            </h2>
            <p class="panel-subtitle">
                This table stays on the selected calendar day.
            </p>
        </div>

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
