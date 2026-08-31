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
        <p id="reportHeadRange"><?= Util::e($start) ?> → <?= Util::e($end) ?></p>
    </div>

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
</div>

<div class="panel report-result-panel" id="reportResultPanel" aria-live="polite">
    <div class="report-result-head">
        <div>
            <div class="eyebrow"><?= Util::e($periodLabels[$period]??'Custom') ?> Range</div>
            <h2><?= Util::e($selectedSalesLabel) ?></h2>
            <p><?= Util::e($start) ?> → <?= Util::e($end) ?></p>
        </div>

        <a
            class="btn primary report-download-button"
            href="<?= Util::e($base) ?>/admin/reports/download?<?= Util::e($downloadQuery) ?>"
        >Download CSV</a>
    </div>

    <div class="tablewrap report-table-wrap">
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
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
