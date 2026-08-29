<?php
use App\Core\Csrf;
use App\Core\Util;

$base = $config['app']['base_path'];
$csrf = Csrf::token();
$today = date('Y-m-d');

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
    data-post-review-url="<?= Util::e($base) ?>/admin/dashboard/post-review"
    data-sales-review-save-url="<?= Util::e($base) ?>/admin/dashboard/sales-review/save"
    data-review-save-url="<?= Util::e($base) ?>/admin/post/review"
    data-get-content-url="<?= Util::e($base) ?>/admin/dashboard/get-content"
    data-editor-image-url="<?= Util::e($base) ?>/admin/dashboard/editor-image"
    data-comment-add-url="<?= Util::e($base) ?>/admin/dashboard/comment/add"
    data-comment-update-url="<?= Util::e($base) ?>/admin/dashboard/comment/update"
    data-comment-delete-url="<?= Util::e($base) ?>/admin/dashboard/comment/delete"
    data-attachment-delete-url="<?= Util::e($base) ?>/admin/dashboard/attachment/delete"
    data-today="<?= Util::e($today) ?>"
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

    <form
        class="filters dashboard-date-controls"
        method="get"
        id="dashboardDateForm"
    >
        <input
            type="hidden"
            name="period"
            value="<?= Util::e($period) ?>"
            id="dashboardPeriodFormValue"
        >

        <div class="dashboard-date-control-row">
            <input
                type="date"
                name="date"
                id="dashboardDateInput"
                value="<?= Util::e($date) ?>"
                aria-label="Dashboard date"
            >

            <button
                type="button"
                class="btn dashboard-date-view"
                id="dashboardDateView"
            >
                View
            </button>

            <button
                type="button"
                class="dashboard-back-today<?= $date === $today ? ' hidden' : '' ?>"
                id="dashboardBackToday"
            >
                Back to today
            </button>
        </div>
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

    <input
        type="hidden"
        id="adminDashboardCsrf"
        value="<?= Util::e($csrf) ?>"
    >

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

                <?php
                $progressDenominator = max(
                    1,
                    (int)$row['period_target'],
                    (int)$row['post_count']
                );
                $goodProgress = round(
                    ((int)$row['good_count'] / $progressDenominator) * 100,
                    3
                );
                $badProgress = round(
                    ((int)$row['bad_count'] / $progressDenominator) * 100,
                    3
                );
                $unreviewedProgress = round(
                    ((int)$row['unreviewed_count'] / $progressDenominator) * 100,
                    3
                );
                ?>

                <div
                    class="sales-progress-track"
                    role="progressbar"
                    aria-label="<?= Util::e($row['display_name']) ?> posting progress"
                    aria-valuemin="0"
                    aria-valuemax="<?= (int)$row['period_target'] ?>"
                    aria-valuenow="<?= (int)$row['post_count'] ?>"
                >
                    <span
                        class="sales-progress-segment good"
                        data-progress-good
                        style="width:<?= $goodProgress ?>%"
                    ></span>
                    <span
                        class="sales-progress-segment bad"
                        data-progress-bad
                        style="width:<?= $badProgress ?>%"
                    ></span>
                    <span
                        class="sales-progress-segment unreviewed"
                        data-progress-unreviewed
                        style="width:<?= $unreviewedProgress ?>%"
                    ></span>
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
                    <div
                        class="sales-card-admin-actions"
                        data-card-control
                    >
                        <a
                            class="sales-daily-review<?= $period === 'day' ? '' : ' hidden' ?>"
                            data-daily-review
                            href="<?= Util::e($row['daily_review_url']) ?>"
                        >
                            Daily Review
                        </a>

                        <div class="sales-target-editor">
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
                </div>

                <div
                    class="sales-target-message"
                    data-target-message
                    aria-live="polite"
                ></div>

                <div
                    class="sales-card-view-footer"
                    aria-hidden="true"
                >
                    <span class="sales-card-view-label">
                        View posts
                    </span>
                    <span
                        class="sales-card-chevron"
                        aria-hidden="true"
                    ></span>
                </div>
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
                <div class="eyebrow">Post List</div>
                <h3 id="salesExpandedTitle">Posts</h3>
                <p id="salesExpandedSubtitle"></p>
            </div>

            <button
                type="button"
                class="sales-expanded-close icon-close"
                id="salesExpandedClose"
                aria-label="Close Sales post grid"
                title="Close"
            >
                ×
            </button>
        </div>

<section
    class="sales-period-review hidden"
    id="salesExpandedReview"
>
    <div class="sales-period-review-top">
        <div>
            <span
                class="sales-period-review-label"
                id="salesExpandedReviewLabel"
            >
                Daily Review
            </span>

            <strong
                class="sales-period-review-state"
                id="salesExpandedReviewState"
            >
                No review yet
            </strong>
        </div>

        <button
            type="button"
            class="sales-period-review-edit"
            id="salesExpandedReviewEdit"
        >
            Add Review
        </button>
    </div>

    <div
        class="sales-period-review-note empty"
        id="salesExpandedReviewNote"
    >
        Add a management review for this Sales period.
    </div>

    <div
        class="sales-period-review-meta"
        id="salesExpandedReviewMeta"
    ></div>
</section>

        <div
            class="sales-expanded-loading hidden"
            id="salesExpandedLoading"
        >
            <span></span>
            <span></span>
            <span></span>
        </div>

        <div
            class="sales-expanded-grid"
            id="salesExpandedList"
        ></div>
    </section>
</section>


<div
    class="sales-period-review-backdrop hidden"
    id="salesPeriodReviewModal"
    aria-hidden="true"
>
    <section
        class="sales-period-review-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="salesPeriodReviewModalTitle"
    >
        <div class="sales-period-review-modal-head">
            <div>
                <div
                    class="eyebrow"
                    id="salesPeriodReviewModalEyebrow"
                >
                    Daily Review
                </div>
                <h2 id="salesPeriodReviewModalTitle">
                    Sales Review
                </h2>
                <p id="salesPeriodReviewModalSubtitle"></p>
            </div>

            <button
                type="button"
                class="icon-close"
                id="salesPeriodReviewClose"
                aria-label="Close Sales review editor"
            >
                ×
            </button>
        </div>

        <form
            class="sales-period-review-form"
            id="salesPeriodReviewForm"
            novalidate
        >
            <input
                type="hidden"
                name="_csrf"
                value="<?= Util::e($csrf) ?>"
            >
            <input
                type="hidden"
                name="sales_user_id"
                id="salesPeriodReviewSalesId"
                value=""
            >
            <input
                type="hidden"
                name="date"
                id="salesPeriodReviewDate"
                value=""
            >
            <input
                type="hidden"
                name="period"
                id="salesPeriodReviewPeriod"
                value="day"
            >

            <div class="sales-period-review-editor-body">
                <?php
                $fieldName = 'note';
                $fieldLabel = 'Management Review';
                $fieldId = 'sales-period-review-note';
                $noteValue = '';
                $enableImageUpload = false;
                require __DIR__ . '/_html_note_editor.php';
                ?>

                <div
                    class="sales-period-review-message"
                    id="salesPeriodReviewMessage"
                    aria-live="polite"
                ></div>
            </div>

            <div class="sales-period-review-modal-footer">
                <button
                    type="button"
                    class="btn"
                    id="salesPeriodReviewCancel"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="btn primary"
                    id="salesPeriodReviewSave"
                >
                    Save Review
                </button>
            </div>
        </form>
    </section>
</div>


<div
    class="review-modal-backdrop hidden"
    id="dashboardReviewModal"
    aria-hidden="true"
>
    <section
        class="review-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="dashboardReviewModalTitle"
    >
        <div class="review-modal-head">
            <div>
                <div class="eyebrow">Post Review</div>
                <h2 id="dashboardReviewModalTitle">Review Post</h2>
                <p id="dashboardReviewModalSubtitle"></p>
            </div>

            <div class="review-modal-head-actions">
                <button
                    type="button"
                    class="review-get-content"
                    id="dashboardGetContent"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 4a8 8 0 0 1 7.4 4.9l1.7-1.7V13h-5.8l2.3-2.3A6 6 0 1 0 18 14h2a8 8 0 1 1-8-10Z"/>
                    </svg>
                    <span>Get Content</span>
                </button>

                <a
                    class="review-modal-original hidden"
                    id="dashboardReviewOriginal"
                    target="_blank"
                    rel="noopener"
                    href="#"
                >
                    Open original
                </a>

                <button
                    type="button"
                    class="icon-close"
                    id="dashboardReviewClose"
                    aria-label="Close review"
                    title="Close"
                >
                    ×
                </button>
            </div>
        </div>

        <form
            id="dashboardReviewForm"
            class="review-modal-form"
            enctype="multipart/form-data"
            novalidate
        >
            <input
                type="hidden"
                name="_csrf"
                value="<?= Util::e($csrf) ?>"
            >
            <input
                type="hidden"
                name="post_id"
                id="dashboardReviewPostId"
                value=""
            >

            <div class="review-modal-scroll">

            <div class="review-modal-meta">
                <div>
                    <span>Published</span>
                    <strong id="dashboardReviewPublished">—</strong>
                </div>
                <div>
                    <span>Platform</span>
                    <strong id="dashboardReviewPlatform">—</strong>
                </div>
                <div>
                    <span>Item ID</span>
                    <strong id="dashboardReviewItemId">—</strong>
                </div>
            </div>

            <section
                class="review-content-preview"
                id="dashboardContentPreview"
            >
                <div class="review-content-head">
                    <div>
                        <span class="review-content-kicker">Listing Content</span>
                        <strong id="dashboardContentProvider">Saved post</strong>
                    </div>
                    <span
                        class="review-content-fetched"
                        id="dashboardContentFetched"
                    ></span>
                </div>

                <div class="review-content-body">
                    <div
                        class="review-content-date hidden"
                        id="dashboardContentDate"
                    ></div>

                    <h3 id="dashboardContentTitle">No content loaded</h3>
                    <p id="dashboardContentDescription"></p>

                    <div
                        class="review-content-facts"
                        id="dashboardContentFacts"
                    ></div>

                    <div
                        class="review-content-photos hidden"
                        id="dashboardContentPhotos"
                    ></div>
                </div>
            </section>

            <fieldset class="review-decision review-decision-modern" aria-required="true">
                <legend>
                    Decision
                    <span class="review-required">Required</span>
                    <span
                        class="review-decision-saved hidden"
                        id="dashboardDecisionSaved"
                    ></span>
                </legend>

                <label class="review-decision-option good">
                    <input
                        type="radio"
                        name="decision"
                        value="good"
                        aria-required="true"
                        aria-describedby="dashboardDecisionError"
                    >
                    <span class="review-decision-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path d="M8.2 10.1 11 4.3c.3-.7 1-1.2 1.8-1.2 1.2 0 2.1 1 2 2.2l-.4 3.2h4.3c1.4 0 2.4 1.3 2 2.6l-2.1 7.2c-.3.9-1.1 1.5-2 1.5H8.2V10.1ZM3 10h3.2v10H3V10Z"/>
                        </svg>
                    </span>
                    <span class="review-decision-copy">
                        <strong>Good</strong>
                        <small>Pass review</small>
                    </span>
                    <span class="review-decision-check" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="m9.1 16.6-4.2-4.2 1.4-1.4 2.8 2.8 8.6-8.6 1.4 1.4-10 10Z"/></svg>
                    </span>
                </label>

                <label class="review-decision-option bad">
                    <input
                        type="radio"
                        name="decision"
                        value="bad"
                        aria-required="true"
                        aria-describedby="dashboardDecisionError"
                    >
                    <span class="review-decision-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path d="m8.2 13.9 2.8 5.8c.3.7 1 1.2 1.8 1.2 1.2 0 2.1-1 2-2.2l-.4-3.2h4.3c1.4 0 2.4-1.3 2-2.6l-2.1-7.2c-.3-.9-1.1-1.5-2-1.5H8.2v9.7ZM3 4h3.2v10H3V4Z"/>
                        </svg>
                    </span>
                    <span class="review-decision-copy">
                        <strong>Bad</strong>
                        <small>Needs attention</small>
                    </span>
                    <span class="review-decision-check" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="m9.1 16.6-4.2-4.2 1.4-1.4 2.8 2.8 8.6-8.6 1.4 1.4-10 10Z"/></svg>
                    </span>
                </label>

                <div
                    class="review-decision-error hidden"
                    id="dashboardDecisionError"
                    data-decision-error
                    role="alert"
                >
                    Select Good or Bad before saving.
                </div>
            </fieldset>

            <section class="review-comment-thread">
                <div class="review-comment-thread-head">
                    <div>
                        <span class="review-comment-kicker">History</span>
                        <strong id="dashboardCommentCount">0 activities</strong>
                    </div>

                    <button
                        type="button"
                        class="history-deleted-switch hidden"
                        id="dashboardHistoryDeletedSwitch"
                        role="switch"
                        aria-checked="false"
                    >
                        <span class="history-deleted-switch-track">
                            <span></span>
                        </span>
                        <span
                            class="history-deleted-switch-label"
                            id="dashboardHistoryDeletedLabel"
                        >
                            See full comments
                        </span>
                    </button>
                </div>

                <div
                    class="review-comment-list"
                    id="dashboardCommentList"
                ></div>

                <div
                    class="review-comment-empty"
                    id="dashboardCommentEmpty"
                >
                    No review activity yet.
                </div>
            </section>

            <?php
            $fieldName = 'comment_body';
            $fieldLabel = 'Add Note';
            $fieldId = 'dashboard-review-note';
            $noteValue = '';
            $enableImageUpload = true;
            require __DIR__ . '/_html_note_editor.php';
            ?>

            <div class="review-comment-media">
                <label for="dashboardCommentImages">
                    Images <span>saved with this note</span>
                </label>
                <input
                    id="dashboardCommentImages"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    multiple
                >
                <div
                    class="review-comment-file-selection"
                    id="dashboardCommentFileSelection"
                ></div>
            </div>

            <div class="review-comment-composer-actions">
                <button
                    type="button"
                    class="btn hidden"
                    id="dashboardCommentCancelEdit"
                >
                    Cancel Edit
                </button>

                <button
                    type="button"
                    class="btn primary"
                    id="dashboardCommentSave"
                >
                    Add Note
                </button>
            </div>

            <div
                class="review-comment-message"
                id="dashboardCommentMessage"
                aria-live="polite"
            ></div>

            <section
                class="review-legacy-attachments hidden"
                id="dashboardReviewAttachments"
            >
                <div class="review-legacy-attachments-title">Other review images</div>
                <div class="review-legacy-attachments-list" data-review-attachment-list></div>
            </section>

<div
                class="review-modal-message"
                id="dashboardReviewMessage"
                aria-live="polite"
            ></div>

            </div><!-- /.review-modal-scroll -->

            <div class="review-modal-footer">
                <div
                    class="review-save-state hidden"
                    id="dashboardReviewSaveState"
                    role="status"
                    aria-live="polite"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="m9.1 16.6-4.2-4.2 1.4-1.4 2.8 2.8 8.6-8.6 1.4 1.4-10 10Z"/>
                    </svg>
                    <span>Review saved</span>
                </div>

                <div class="review-modal-footer-actions">
                    <button
                        type="button"
                        class="btn"
                        id="dashboardReviewCancel"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="btn primary"
                        id="dashboardReviewSave"
                    >
                        Save Review
                    </button>
                </div>
            </div>
        </form>

        <div
            class="review-modal-loading hidden"
            id="dashboardReviewLoading"
        >
            Loading review…
        </div>
    </section>
</div>


<div
    class="comment-delete-popover hidden"
    id="commentDeletePopover"
    role="dialog"
    aria-hidden="true"
    aria-label="Delete note confirmation"
>
    <div class="comment-delete-popover-copy">
        <strong>Mark this note as deleted?</strong>
        <span>The record stays in History and will be marked as deleted.</span>
    </div>

    <div class="comment-delete-popover-actions">
        <button type="button" class="tiny badbtn" id="commentDeleteConfirm">Delete</button>
        <button type="button" class="tiny" id="commentDeleteCancel">Cancel</button>
    </div>

    <span class="comment-delete-popover-arrow" aria-hidden="true"></span>
</div>

<div class="listing-image-lightbox hidden" id="listingImageLightbox" aria-hidden="true">
    <div class="listing-image-dialog" role="dialog" aria-modal="true">
        <button type="button" class="icon-close listing-image-close" id="listingImageClose" aria-label="Close image">×</button>
        <img id="listingImageLarge" src="" alt="Marketplace listing">
    </div>
</div>

<?php if ($deletionRequests): ?>
    <section class="panel">
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
    </section>
<?php endif; ?>
