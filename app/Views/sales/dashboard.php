<?php
/**
 * File / 文件：app/Views/sales/dashboard.php
 * EN: Renders the sales/dashboard application view template.
 * 中文：渲染应用视图模板 sales/dashboard。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
use App\Core\Util;

$postsTotal=(int)($summary['post_count']??0);
$goodTotal=(int)($summary['good_count']??0);
$badTotal=(int)($summary['bad_count']??0);
$unreviewedTotal=(int)($summary['unreviewed_count']??0);

$chartByDate=[];

foreach (($chartRows ?? []) as $row) {
    $dateKey=(string)($row['date'] ?? '');

    if ($dateKey === '') {
        continue;
    }

    if (!isset($chartByDate[$dateKey])) {
        $chartByDate[$dateKey]=[
            'post_count'=>0,
            'good_count'=>0,
            'bad_count'=>0,
            'unreviewed_count'=>0,
        ];
    }

    $chartByDate[$dateKey]['post_count']+=(int)($row['post_count'] ?? 0);
    $chartByDate[$dateKey]['good_count']+=(int)($row['good_count'] ?? 0);
    $chartByDate[$dateKey]['bad_count']+=(int)($row['bad_count'] ?? 0);
    $chartByDate[$dateKey]['unreviewed_count']+=(int)(
        $row['unreviewed_count'] ?? 0
    );
}

$chartDates=[];
$chartStart=new DateTimeImmutable($from.' 12:00:00');
$chartEnd=new DateTimeImmutable($to.' 12:00:00');

for (
    $cursor=$chartStart;
    $cursor <= $chartEnd;
    $cursor=$cursor->modify('+1 day')
) {
    $chartDates[]=$cursor->format('Y-m-d');
}

$chartTarget=max(1,(int)$dailyTarget);
$chartCap=max(1,$chartTarget*1.2);
$chartTargetPercent=min(100,($chartTarget/$chartCap)*100);
$chartInitialWidth=max(100,count($chartDates)*30);

/*
 * Canonical chart fallback geometry.
 * The dedicated sales-dashboard.js uses the exact same numbers.
 */
$chartHeight=280;
$chartXAxisHeight=32;
$chartPlotHeight=$chartHeight-$chartXAxisHeight;

$roughStep=$chartCap/6;

if($roughStep<=1){
    $chartTickStep=1;
}elseif($roughStep<=2){
    $chartTickStep=2;
}elseif($roughStep<=3){
    $chartTickStep=3;
}elseif($roughStep<=5){
    $chartTickStep=5;
}else{
    $magnitude=10 ** floor(log10($roughStep));
    $normalized=$roughStep/$magnitude;

    if($normalized<=1){
        $nice=1;
    }elseif($normalized<=2){
        $nice=2;
    }elseif($normalized<=5){
        $nice=5;
    }else{
        $nice=10;
    }

    $chartTickStep=$nice*$magnitude;
}

$chartTicks=[];

for(
    $tick=0;
    $tick<=$chartCap+0.0001;
    $tick+=$chartTickStep
){
    $chartTicks[]=round($tick,4);
}

if(
    !$chartTicks
    ||abs(
        (float)end($chartTicks)
        -(float)$chartCap
    )>0.0001
){
    $chartTicks[]=$chartCap;
}

$chartTargetTop=
    $chartPlotHeight
    *(1-($chartTarget/$chartCap));
?>

<div
    class="sales-portal"
    id="salesPortalDashboard"
    data-from="<?= Util::e($from) ?>"
    data-to="<?= Util::e($to) ?>"
    data-today="<?= Util::e($today) ?>"
    data-range-period="<?= Util::e($rangePeriod ?? 'custom') ?>"
    data-channel="<?= Util::e($activeChannel ?? 'all') ?>"
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
<div
    class="sales-period-switch sales-head-period-switch"
    id="salesPeriodSwitch"
    role="group"
    aria-label="Sales activity period"
>
    <button
        type="button"
        class="sales-period-button<?= ($rangePeriod ?? '') === 'single' ? ' active' : '' ?>"
        data-sales-period="single"
        aria-pressed="<?= ($rangePeriod ?? '') === 'single' ? 'true' : 'false' ?>"
    >
        <span data-sales-i18n="oneDay">1 Day</span>
    </button>

    <button
        type="button"
        class="sales-period-button<?= ($rangePeriod ?? '') === 'day' ? ' active' : '' ?>"
        data-sales-period="day"
        aria-pressed="<?= ($rangePeriod ?? '') === 'day' ? 'true' : 'false' ?>"
    >
        <span data-sales-i18n="threeDays">3 Days</span>
    </button>

    <button
        type="button"
        class="sales-period-button<?= ($rangePeriod ?? '') === 'week' ? ' active' : '' ?>"
        data-sales-period="week"
        aria-pressed="<?= ($rangePeriod ?? '') === 'week' ? 'true' : 'false' ?>"
    >
        <span data-sales-i18n="weekly">Weekly</span>
    </button>

    <button
        type="button"
        class="sales-period-button<?= ($rangePeriod ?? '') === 'month' ? ' active' : '' ?>"
        data-sales-period="month"
        aria-pressed="<?= ($rangePeriod ?? '') === 'month' ? 'true' : 'false' ?>"
    >
        <span data-sales-i18n="monthly">Monthly</span>
    </button>

    <button
        type="button"
        class="sales-period-button sales-period-custom<?= ($rangePeriod ?? '') === 'custom' ? ' active' : '' ?>"
        data-sales-period="custom"
        aria-pressed="<?= ($rangePeriod ?? '') === 'custom' ? 'true' : 'false' ?>"
        title="Use the From and To dates"
    >
        <span data-sales-i18n="customRange">Custom</span>
    </button>
</div>

        <form
            class="filters dashboard-date-controls admin-range-controls sales-range-filter"
            id="salesRangeForm"
            method="get"
            action="<?= Util::e($config['app']['base_path']) ?>/sales"
            novalidate
        >
            <div class="dashboard-date-control-row sales-date-control-row activity-range-date-row">
                <div class="admin-range-field activity-range-field">
                    <div class="activity-range-label-row">
                        <label for="salesRangeFrom" data-sales-i18n="from">From</label>
                    </div>
                    <input
                        type="date"
                        name="from"
                        id="salesRangeFrom"
                        value="<?= Util::e($from) ?>"
                        max="<?= Util::e(min($to,$today)) ?>"
                    >
                </div>

                <div class="sales-to-field-stack activity-range-field activity-range-to-field">
                    <div class="activity-range-label-row">
                        <label for="salesRangeTo" data-sales-i18n="to">To</label>
                        <button
                            type="button"
                            class="dashboard-back-today sales-back-today<?= (
                                $to===$today
                            ) ? ' hidden' : '' ?>"
                            id="salesBackToday"
                        >
                            <span data-sales-i18n="backToday">Back to today</span>
                        </button>
                    </div>
                    <input
                        type="date"
                        name="to"
                        id="salesRangeTo"
                        value="<?= Util::e($to) ?>"
                        min="<?= Util::e($from) ?>"
                        max="<?= Util::e($today) ?>"
                    >
                </div>
            </div>

            <span
                class="sales-range-live-status"
                id="salesRangeStatus"
                aria-live="polite"
            ></span>
        </form>

        <button
            class="btn primary sales-submit-cta sales-bulk-submit-cta"
            type="button"
            data-open-sales-bulk-submit
            data-bulk-fallback-url="<?= Util::e($config['app']['base_path']) ?>/sales/bulk-submit"
        >
            <span class="sales-submit-plus">+</span>
            <span data-sales-i18n="bulkSubmitPost">Bulk Submit Post</span>
        </button>

        <button
            class="btn primary sales-submit-cta"
            type="button"
            data-open-sales-submit
        >
            <span class="sales-submit-plus">+</span>
            <span data-sales-i18n="submitPost">Submit Post</span>
        </button>
    </div>
</div>

<?php require __DIR__ . '/_verification_queue.php'; ?>

<section
    class="sales-activity-chart-panel"
    id="salesActivityChartPanel"
    data-daily-target="<?= (int)$dailyTarget ?>"
>
    <div class="sales-activity-chart-head">
        <div>
            <span class="eyebrow" data-sales-i18n="activityChart">
                Posting Activity
            </span>
            <h2
                id="salesChartPeriodTitle"
                data-sales-i18n="dailyProgress"
            >
                Daily Post Progress
            </h2>
            <p>
                <span data-sales-i18n="targetLine">Daily target</span>
                <strong id="salesChartTargetCopy">
                    <?= (int)$dailyTarget ?>
                </strong>
            </p>
        </div>

        <div class="sales-chart-toolbar">
<div class="sales-channel-control">
    <div class="sales-channel-title">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M7 3a3 3 0 1 1-1 5.83v3.34A3.001 3.001 0 1 1 4 12.17V8.83A3 3 0 0 1 7 3Zm10 0a3 3 0 1 1-1 5.83v1.34A3 3 0 0 1 13 13h-2a1 1 0 0 0-1 1v1.17a3 3 0 1 1-2 0V14a3 3 0 0 1 3-3h2a1 1 0 0 0 1-1V8.83A3 3 0 0 1 17 3ZM7 5a1 1 0 1 0 0 2 1 1 0 0 0 0-2Zm10 0a1 1 0 1 0 0 2 1 1 0 0 0 0-2ZM5 15a1 1 0 1 0 0 2 1 1 0 0 0 0-2Zm4 2a1 1 0 1 0 0 2 1 1 0 0 0 0-2Z"/>
        </svg>
        <strong data-sales-i18n="channels">Channels</strong>
    </div>

<div
    class="sales-platform-filter"
    id="salesPlatformFilter"
    role="group"
    aria-label="Filter dashboard by platform"
>
    <button
        type="button"
        class="sales-platform-filter-button<?= ($activeChannel ?? 'all') === 'all' ? ' active' : '' ?>"
        data-sales-platform-filter="all"
        aria-pressed="<?= ($activeChannel ?? 'all') === 'all' ? 'true' : 'false' ?>"
        title="All channels"
    >
        <svg class="sales-platform-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3h8v8H3V3Zm10 0h8v8h-8V3ZM3 13h8v8H3v-8Zm10 0h8v8h-8v-8ZM5 5v4h4V5H5Zm10 0v4h4V5h-4ZM5 15v4h4v-4H5Zm10 0v4h4v-4h-4Z"/></svg>
        <span data-sales-i18n="allPlatforms">All</span>
    </button>
    <button
        type="button"
        class="sales-platform-filter-button<?= ($activeChannel ?? 'all') === 'facebook' ? ' active' : '' ?>"
        data-sales-platform-filter="facebook"
        aria-pressed="<?= ($activeChannel ?? 'all') === 'facebook' ? 'true' : 'false' ?>"
        title="Facebook"
    >
        <svg class="sales-platform-icon facebook" viewBox="0 0 24 24" aria-hidden="true"><path d="M13.6 21v-8h2.7l.4-3.1h-3.1V8c0-.9.3-1.5 1.6-1.5h1.7V3.7c-.3 0-1.3-.1-2.5-.1-2.5 0-4.2 1.5-4.2 4.3v2H7.4V13h2.8v8h3.4Z"/></svg>
        Facebook
    </button>
    <button
        type="button"
        class="sales-platform-filter-button<?= ($activeChannel ?? 'all') === 'instagram' ? ' active' : '' ?>"
        data-sales-platform-filter="instagram"
        aria-pressed="<?= ($activeChannel ?? 'all') === 'instagram' ? 'true' : 'false' ?>"
        title="Instagram"
    >
        <svg class="sales-platform-icon instagram" viewBox="0 0 24 24" aria-hidden="true"><path d="M7.4 2h9.2A5.4 5.4 0 0 1 22 7.4v9.2a5.4 5.4 0 0 1-5.4 5.4H7.4A5.4 5.4 0 0 1 2 16.6V7.4A5.4 5.4 0 0 1 7.4 2Zm0 2A3.4 3.4 0 0 0 4 7.4v9.2A3.4 3.4 0 0 0 7.4 20h9.2a3.4 3.4 0 0 0 3.4-3.4V7.4A3.4 3.4 0 0 0 16.6 4H7.4Zm9.8 1.5a1.3 1.3 0 1 1 0 2.6 1.3 1.3 0 0 1 0-2.6ZM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10Zm0 2a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z"/></svg>
        Instagram
    </button>
    <button
        type="button"
        class="sales-platform-filter-button<?= ($activeChannel ?? 'all') === 'offerup' ? ' active' : '' ?>"
        data-sales-platform-filter="offerup"
        aria-pressed="<?= ($activeChannel ?? 'all') === 'offerup' ? 'true' : 'false' ?>"
        title="OfferUp"
    >
        <svg class="sales-platform-icon offerup" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h12a3 3 0 0 1 3 3v8a3 3 0 0 1-3 3h-5l-4.5 4v-4H6a3 3 0 0 1-3-3V6a3 3 0 0 1 3-3Zm6 3.5A3.5 3.5 0 1 0 12 13.5a3.5 3.5 0 0 0 0-7Zm0 2a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Z"/></svg>
        OfferUp
    </button>
    <button
        type="button"
        class="sales-platform-filter-button<?= ($activeChannel ?? 'all') === 'craigslist' ? ' active' : '' ?>"
        data-sales-platform-filter="craigslist"
        aria-pressed="<?= ($activeChannel ?? 'all') === 'craigslist' ? 'true' : 'false' ?>"
        title="Craigslist"
    >
        <svg class="sales-platform-icon craigslist" viewBox="0 0 24 24" aria-hidden="true"><path d="M11 3h2v6.1l4.3-4.3 1.4 1.4-4.3 4.3H21v2h-6.6l4.3 4.3-1.4 1.4-4.3-4.3V21h-2v-7.1l-4.3 4.3-1.4-1.4 4.3-4.3H3v-2h6.6L5.3 6.2l1.4-1.4L11 9.1V3Z"/></svg>
        Craigslist
    </button>
</div>
</div>
        </div>
    </div>

    <div class="sales-chart-legend">
        <span><i class="good"></i><b data-sales-i18n="good">Good</b></span>
        <span><i class="bad"></i><b data-sales-i18n="issues">Bad</b></span>
        <span><i class="unreviewed"></i><b data-sales-i18n="unreviewed">Unreviewed</b></span>
    </div>

    <div class="sales-chart-shell">
        <div
            class="sales-chart-y-axis"
            id="salesChartYAxis"
            aria-hidden="true"
        >
            <div
                class="sales-chart-y-axis-ticks"
                id="salesChartYAxisTicks"
            >
                <?php foreach ($chartTicks as $tick): ?>
                    <?php
                    $tickTop=
                        $chartPlotHeight
                        *(1-((float)$tick/$chartCap));
                    ?>
                    <span
                        class="sales-chart-y-tick<?= abs((float)$tick-$chartTarget)<0.0001 ? ' target' : '' ?>"
                        style="top:<?= round($tickTop,4) ?>px"
                    >
                        <?= Util::e(
                            floor((float)$tick)==(float)$tick
                                ?(string)(int)$tick
                                :rtrim(
                                    rtrim(
                                        number_format(
                                            (float)$tick,
                                            1,
                                            '.',
                                            ''
                                        ),
                                        '0'
                                    ),
                                    '.'
                                )
                        ) ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="sales-chart-scroll" id="salesChartScroll">
            <div
                class="sales-chart-canvas"
                id="salesChartCanvas"
                style="width:max(100%,<?= (int)$chartInitialWidth ?>px)"
            >
                <div
                    class="sales-chart-grid-lines"
                    id="salesChartGridLines"
                    aria-hidden="true"
                >
                    <?php foreach ($chartTicks as $tick): ?>
                        <?php
                        $tickTop=
                            $chartPlotHeight
                            *(1-((float)$tick/$chartCap));
                        ?>
                        <span
                            class="sales-chart-grid-line<?= abs((float)$tick-$chartTarget)<0.0001 ? ' target' : '' ?>"
                            style="top:<?= round($tickTop,4) ?>px"
                        ></span>
                    <?php endforeach; ?>
                </div>

                <div
                    class="sales-chart-target-line"
                    id="salesChartTargetLine"
                    style="top:<?= round($chartTargetTop,4) ?>px"
                >
                    <span>
                        <span data-sales-i18n="targetLine">Daily target</span>
                        <b id="salesChartTargetLineValue"><?= (int)$dailyTarget ?></b>
                    </span>
                </div>

                <div
                    class="sales-chart-bars"
                    id="salesChartBars"
                    aria-label="Daily post progress chart"
                    style="
                        grid-template-columns:repeat(<?= max(1,count($chartDates)) ?>,minmax(0,1fr));
                        --sales-chart-bar-width:20px;
                        --sales-chart-gap:3px;
                    "
                >
                    <?php foreach ($chartDates as $chartDate): ?>
                        <?php
                        $chartRow=$chartByDate[$chartDate] ?? [
                            'post_count'=>0,
                            'good_count'=>0,
                            'bad_count'=>0,
                            'unreviewed_count'=>0,
                        ];

                        $actual=(int)$chartRow['post_count'];
                        $scale=$actual>$chartCap
                            ?$chartCap/$actual
                            :1;

                        $goodH=((int)$chartRow['good_count']*$scale/$chartCap)*100;
                        $badH=((int)$chartRow['bad_count']*$scale/$chartCap)*100;
                        $unreviewedH=((int)$chartRow['unreviewed_count']*$scale/$chartCap)*100;

                        $actualH=min(
                            100,
                            (min($actual,$chartCap)/$chartCap)*100
                        );

                        $missing=max(0,$chartTarget-$actual);
                        $missingH=(
                            $actual>0
                            &&$missing>0
                        )
                            ?max(0,$chartTargetPercent-$actualH)
                            :0;

                        $labelDate=DateTimeImmutable::createFromFormat(
                            'Y-m-d',
                            $chartDate
                        );
                        ?>
                        <div
                            class="sales-chart-day"
                            tabindex="0"
                            data-chart-date="<?= Util::e($chartDate) ?>"
                            data-chart-total="<?= $actual ?>"
                            data-chart-good="<?= (int)$chartRow['good_count'] ?>"
                            data-chart-bad="<?= (int)$chartRow['bad_count'] ?>"
                            data-chart-unreviewed="<?= (int)$chartRow['unreviewed_count'] ?>"
                            data-chart-missing="<?= $missing ?>"
                        >
                            <div class="sales-chart-day-plot">

                                <div class="sales-chart-stack">
                                    <span
                                        class="sales-chart-segment good"
                                        style="height:<?= round($goodH,4) ?>%"
                                    ></span>
                                    <span
                                        class="sales-chart-segment bad"
                                        style="height:<?= round($badH,4) ?>%"
                                    ></span>
                                    <span
                                        class="sales-chart-segment unreviewed"
                                        style="height:<?= round($unreviewedH,4) ?>%"
                                    ></span>
                                </div>

                                <?php if ($actual>$chartCap): ?>
                                    <span class="sales-chart-over-cap">120%+</span>
                                <?php endif; ?>
                            </div>

                            <span class="sales-chart-x-label">
                                <?= Util::e(
                                    $labelDate
                                        ?$labelDate->format('n/j')
                                        :$chartDate
                                ) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div
        class="sales-chart-tooltip hidden"
        id="salesChartTooltip"
        role="status"
    ></div>
</section>

<script type="application/json" id="salesChartInitialData"><?= json_encode(
    [
        'from'=>$from,
        'to'=>$to,
        'daily_target'=>(int)$dailyTarget,
        'rows'=>$chartRows,
    ],
    JSON_UNESCAPED_SLASHES
    | JSON_UNESCAPED_UNICODE
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
) ?></script>

<div class="sales-range-post-stage<?= $posts ? '' : ' sales-range-post-stage-empty' ?>" id="salesDailyStage">
    <div id="dailyPosts" class="sales-range-post-wrap" data-from="<?= Util::e($from) ?>" data-to="<?= Util::e($to) ?>">
        <?php require __DIR__ . '/_post_range_section.php'; ?>
    </div>
</div>

<div class="sales-submit-modal-backdrop hidden" id="salesSubmitModal" aria-hidden="true">
    <section class="sales-submit-modal" role="dialog" aria-modal="true" aria-labelledby="salesSubmitModalTitle">
        <div class="sales-submit-modal-head">
            <div>
                <div class="eyebrow">New Post</div>
                <h2 id="salesSubmitModalTitle" data-sales-i18n="submitTitle">Submit Marketplace Post</h2>
            </div>
            <button type="button" class="icon-close" id="salesSubmitModalClose" aria-label="Close submit post" title="Close">×</button>
        </div>
        <div class="sales-submit-modal-scroll">
            <?php require __DIR__ . '/_submit_form.php'; ?>
        </div>
    </section>
</div>

<div class="sales-submit-modal-backdrop hidden" id="salesBulkSubmitModal" aria-hidden="true">
    <section class="sales-submit-modal" role="dialog" aria-modal="true" aria-labelledby="salesBulkSubmitModalTitle">
        <div class="sales-submit-modal-head">
            <div>
                <div class="eyebrow" data-sales-i18n="bulkSubmit">Bulk Submit</div>
                <h2 id="salesBulkSubmitModalTitle" data-sales-i18n="bulkSubmitPost">Bulk Submit Post</h2>
            </div>
            <button type="button" class="icon-close" id="salesBulkSubmitModalClose" aria-label="Close bulk submit post" title="Close">×</button>
        </div>
        <div class="sales-submit-modal-scroll">
            <?php require __DIR__ . '/_bulk_submit_form.php'; ?>
        </div>
    </section>
</div>

<div
    class="sales-post-detail-backdrop hidden"
    id="salesPostDetailModal"
    aria-hidden="true"
>
    <section
        class="sales-post-detail-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="salesPostDetailTitle"
    >
        <div class="sales-post-detail-head">
            <div>
                <span
                    class="sales-post-detail-platform"
                    id="salesPostDetailPlatform"
                >
                    Marketplace
                </span>
                <h2 id="salesPostDetailTitle">Post details</h2>
            </div>

            <button
                type="button"
                class="icon-close sales-post-detail-close"
                id="salesPostDetailClose"
                aria-label="Close post details"
                title="Close"
            >
                ×
            </button>
        </div>

        <div class="sales-post-detail-scroll">
            <button
                type="button"
                class="sales-post-detail-image-button hidden"
                id="salesPostDetailImageButton"
                aria-label="View larger image"
            >
                <img
                    id="salesPostDetailImage"
                    src=""
                    alt=""
                >
                <span class="sales-post-detail-image-zoom">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M10.5 4a6.5 6.5 0 1 1-4.6 11.1l-3.5 3.5 1.4 1.4 3.5-3.5A6.5 6.5 0 0 1 10.5 4Zm0 2a4.5 4.5 0 1 0 0 9 4.5 4.5 0 0 0 0-9Zm-.9 1.6h1.8v2h2v1.8h-2v2H9.6v-2h-2V9.6h2v-2Z"/>
                    </svg>
                </span>
            </button>

            <div
                class="sales-post-detail-no-image"
                id="salesPostDetailNoImage"
            >
                <span data-sales-i18n="noImage">No listing image</span>
            </div>

            <div class="sales-post-detail-content">
                <div class="sales-post-detail-status-row">
                    <span
                        class="sales-post-detail-status"
                        id="salesPostDetailStatus"
                    >
                        Unreviewed
                    </span>
                </div>

                <div class="sales-post-detail-date">
                    <span data-sales-i18n="published">Published</span>
                    <strong id="salesPostDetailPublished">—</strong>
                </div>

                <h3 id="salesPostDetailContentTitle">—</h3>

                <p id="salesPostDetailDescription">—</p>

                <dl class="sales-post-detail-facts">
                    <div>
                        <dt data-sales-i18n="platform">Platform</dt>
                        <dd id="salesPostDetailPlatformValue">—</dd>
                    </div>

                    <div>
                        <dt data-sales-i18n="postId">Post ID</dt>
                        <dd id="salesPostDetailExternalId">—</dd>
                    </div>

                    <div id="salesPostDetailAccountFact" class="hidden">
                        <dt data-sales-i18n="platformAccount">Account</dt>
                        <dd id="salesPostDetailAccount">—</dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="sales-post-detail-footer">
            <button
                type="button"
                class="btn"
                id="salesPostDetailFooterClose"
            >
                <span data-sales-i18n="close">Close</span>
            </button>

            <button type="button" class="btn danger-soft" id="salesPostDeleteRequestOpen">Request deletion</button>

            <a
                class="btn primary"
                id="salesPostDetailOriginal"
                target="_blank"
                rel="noopener"
                href="#"
            >
                <span data-sales-i18n="openOriginal">Open original</span>
            </a>
        </div>

        <form class="sales-delete-request-form hidden" id="salesPostDeleteRequestForm" action="<?= Util::e($config['app']['base_path']) ?>/sales/delete-request">
            <input type="hidden" name="_csrf" value="<?= Util::e(\App\Core\Csrf::token()) ?>">
            <input type="hidden" name="post_id" id="salesPostDeleteRequestId" value="">
            <label>Reason for deletion
                <input type="text" name="reason" id="salesPostDeleteRequestReason" maxlength="1000" required placeholder="Why should Admin delete this post?">
            </label>
            <div class="sales-delete-request-actions">
                <span id="salesPostDeleteRequestMessage" aria-live="polite"></span>
                <button type="button" class="btn" id="salesPostDeleteRequestCancel">Cancel</button>
                <button type="submit" class="btn danger-soft" id="salesPostDeleteRequestSend">Send request</button>
            </div>
        </form>
    </section>
</div>

<div
    class="sales-image-lightbox hidden"
    id="salesImageLightbox"
    aria-hidden="true"
>
    <button
        type="button"
        class="sales-image-lightbox-close"
        id="salesImageLightboxClose"
        aria-label="Close image"
    >
        ×
    </button>
    <img
        id="salesImageLightboxImage"
        src=""
        alt=""
    >
</div>
