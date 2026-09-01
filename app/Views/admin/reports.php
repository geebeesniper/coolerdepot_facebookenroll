<?php
/**
 * File / 文件：app/Views/admin/reports.php
 * EN: Renders the admin/reports application view template.
 * 中文：渲染应用视图模板 admin/reports。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
 use App\Core\Util; ?>
<?php
$base=$config['app']['base_path'];
$today=date('Y-m-d');
$periodLabels=[
    'single'=>'1 Day',
    'day'=>'3 Days',
    'week'=>'Weekly',
    'month'=>'Monthly',
    'custom'=>'Custom',
];
$selectedSalesLabel='All Sales';
if($salesUserId>0){
    foreach($sales as $salesUser){
        if((int)$salesUser['id']===$salesUserId){
            $selectedSalesLabel=(string)$salesUser['display_name'];
            break;
        }
    }
}
$downloadQuery=http_build_query([
    'period'=>$period,
    'from'=>$start,
    'to'=>$end,
    'sales_id'=>$salesUserId,
]);
?>

<div
    class="page-head sales-portal-head report-page-head"
    id="managementReports"
    data-today="<?= Util::e($today) ?>"
    data-period="<?= Util::e($period) ?>"
>
    <div class="report-page-copy">
        <div class="eyebrow">Management Reports</div>
        <h1>Sales Report</h1>
    </div>
</div>

<div class="panel report-result-panel" id="reportResultPanel" aria-live="polite">
    <div class="report-result-head">
        <form
            class="report-toolbar"
            id="reportRangeForm"
            method="get"
            action="<?= Util::e($base) ?>/admin/reports"
            novalidate
        >
            <input type="hidden" name="period" id="reportPeriodValue" value="<?= Util::e($period) ?>">

            <div class="report-control report-range-control">
                <span class="report-control-label">Range</span>
                <div
                    class="sales-period-switch sales-head-period-switch"
                    id="reportPeriodSwitch"
                    role="group"
                    aria-label="Report date range"
                >
                    <?php foreach($periodLabels as $key=>$label): ?>
                        <button
                            type="button"
                            class="sales-period-button<?= $period===$key?' active':'' ?>"
                            data-report-period="<?= Util::e($key) ?>"
                            aria-pressed="<?= $period===$key?'true':'false' ?>"
                        ><?= Util::e($label) ?></button>
                    <?php endforeach; ?>
                </div>
            </div>

            <label class="report-control report-date-field">
                <span class="report-control-label">From</span>
                <input
                    type="date"
                    name="from"
                    id="reportRangeFrom"
                    value="<?= Util::e($start) ?>"
                    max="<?= Util::e(min($end,$today)) ?>"
                >
            </label>

            <label class="report-control report-date-field">
                <span class="report-control-label">To</span>
                <input
                    type="date"
                    name="to"
                    id="reportRangeTo"
                    value="<?= Util::e($end) ?>"
                    min="<?= Util::e($start) ?>"
                    max="<?= Util::e($today) ?>"
                >
            </label>

            <label class="report-control report-sales-field">
                <span class="report-control-label">Sales</span>
                <select name="sales_id" id="reportSalesSelect">
                    <option value="0" <?= $salesUserId===0?'selected':'' ?>>All</option>
                    <?php foreach($sales as $s): ?>
                        <option
                            value="<?= (int)$s['id'] ?>"
                            <?= $salesUserId===(int)$s['id']?'selected':'' ?>
                        ><?= Util::e($s['display_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

        </form>

        <a
            class="btn primary report-download-button"
            id="reportDownloadButton"
            href="<?= Util::e($base) ?>/admin/reports/download?<?= Util::e($downloadQuery) ?>"
        >Download CSV</a>
    </div>

    <div class="report-result-meta">
        <div>
            <h2 id="reportSelectedSalesTitle"><?= Util::e($selectedSalesLabel) ?></h2>
            <p class="report-review-explainer">
                One row per Sales person per day. Post Review and Sales Review are separate: Good/Bad measures posts; Sales Rating measures the employee for that date.
            </p>
        </div>
    </div>

    <div class="tablewrap report-table-wrap" id="reportTableArea">
        <table class="management-report-table">
            <thead>
                <tr class="report-group-head">
                    <th rowspan="2">Date</th>
                    <th rowspan="2">Sales</th>
                    <th rowspan="2">Total</th>
                    <th rowspan="2">Facebook</th>
                    <th rowspan="2">OfferUp</th>
                    <th rowspan="2">Craigslist</th>
                    <th colspan="3" class="report-post-review-group">Post Review</th>
                    <th colspan="1" class="report-person-review-group">Sales Review</th>
                </tr>
                <tr class="report-sub-head">
                    <th>Good</th>
                    <th>Bad</th>
                    <th>Good %</th>
                    <th>Sales Rating</th>
                </tr>
            </thead>

            <?php foreach($rows as $r):
                $reviewed=(int)$r['good_posts']+(int)$r['bad_posts'];
                $pct=$reviewed
                    ?round((int)$r['good_posts']/$reviewed*100,1)
                    :0;
                $dailyRating=(int)($r['daily_rating']??0);
            ?>
                <tr>
                    <td><?= Util::e($r['work_date']) ?></td>
                    <td><?= Util::e($r['display_name']) ?></td>
                    <td><?= (int)$r['total_posts'] ?></td>
                    <td><?= (int)$r['facebook_posts'] ?></td>
                    <td><?= (int)$r['offerup_posts'] ?></td>
                    <td><?= (int)$r['craigslist_posts'] ?></td>
                    <td><?= (int)$r['good_posts'] ?></td>
                    <td><?= (int)$r['bad_posts'] ?></td>
                    <td><?= $pct ?>%</td>
                    <td title="<?= Util::e((string)($r['daily_review_note']??'')) ?>"><?= $dailyRating>0 ? Util::e($dailyRating.'/5') : '—' ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
