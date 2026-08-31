<?php use App\Core\Util; ?>
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

            <div class="report-control report-run-control">
                <span class="report-control-label" aria-hidden="true">&nbsp;</span>
                <button class="btn report-run-button" id="reportRunButton" type="submit">Run</button>
            </div>
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
                Good/Bad is post-level review. Reviewed Days and Avg Rating summarize the Admin Daily Review for this range.
            </p>
        </div>
    </div>

    <div class="tablewrap report-table-wrap" id="reportTableArea">
        <table>
            <tr>
                <th>Sales</th>
                <th>Total</th>
                <th>Facebook</th>
                <th>OfferUp</th>
                <th>Craigslist</th>
                <th>Good</th>
                <th>Bad</th>
                <th>Good %</th>
                <th>Reviewed Days</th>
                <th>Avg Rating</th>
            </tr>

            <?php foreach($rows as $r):
                $reviewed=(int)$r['good_posts']+(int)$r['bad_posts'];
                $pct=$reviewed
                    ?round((int)$r['good_posts']/$reviewed*100,1)
                    :0;
            ?>
                <tr>
                    <td><?= Util::e($r['display_name']) ?></td>
                    <td><?= (int)$r['total_posts'] ?></td>
                    <td><?= (int)$r['facebook_posts'] ?></td>
                    <td><?= (int)$r['offerup_posts'] ?></td>
                    <td><?= (int)$r['craigslist_posts'] ?></td>
                    <td><?= (int)$r['good_posts'] ?></td>
                    <td><?= (int)$r['bad_posts'] ?></td>
                    <td><?= $pct ?>%</td>
                    <td><?= (int)($r['daily_review_days']??0) ?></td>
                    <td><?php
                        $avgRating=(float)($r['avg_daily_rating']??0);
                        echo $avgRating>0
                            ? Util::e(number_format($avgRating,1).'/5')
                            : '—';
                    ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
