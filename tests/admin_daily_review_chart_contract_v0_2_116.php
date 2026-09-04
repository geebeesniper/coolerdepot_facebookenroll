<?php
/**
 * EN: Contract checks for v0.2.116 Daily Review date editing, reversible completion, and dual-axis chart ratings.
 * 中文：检查 v0.2.116 Daily Review 日期切换、可取消 Complete，以及双 Y 轴星级图表行为。
 */
declare(strict_types=1);

$root=dirname(__DIR__);
$version=trim((string)file_get_contents($root.'/VERSION'));
$view=(string)file_get_contents($root.'/app/Views/admin/dashboard.php');
$controller=(string)file_get_contents($root.'/app/Controllers/AdminController.php');
$js=(string)file_get_contents($root.'/public/assets/app.js');
$css=(string)file_get_contents($root.'/public/assets/app.css');

$checks=[
    'version is 0.2.116'=>$version==='0.2.116',
    'outer Sales-card calendar buttons removed'=>!str_contains($view,'data-daily-calendar-trigger')
        &&!str_contains($view,'sales-daily-action-date'),
    'Daily Review stays available for every selected range'=>str_contains($view,'class="sales-daily-task-group sales-daily-review-group"')
        &&str_contains($js,"\$dailyReviewGroup.removeClass('hidden')"),
    'Daily Review date opens calendar inside review modal'=>str_contains($view,'id="salesPeriodReviewDateTrigger"')
        &&str_contains($js,"$('#salesPeriodReviewDateTrigger').on('click'"),
    'Daily Review always loads a one-day date'=>str_contains($js,"period:'day'")
        &&str_contains($js,"preset:'single'")
        &&str_contains($js,'function openDailyReviewOnly($card,requestedDate)'),
    'completion can be unmarked'=>str_contains($controller,"DELETE FROM cdsp_daily_sales_completions")
        &&str_contains($js,"completed:nextCompleted?'1':'0'")
        &&str_contains($js,"unmarkComplete:'Unmark Complete'"),
    'daily rating range returned by API'=>str_contains($controller,"'daily_ratings'=>\$this->dashboardDailyRatingsForRange")
        &&str_contains($controller,"period_type='day'")
        &&str_contains($controller,'deleted_at IS NULL'),
    'Admin chart has right 1-5 star axis'=>str_contains($view,'id="adminSalesChartRatingAxis"')
        &&str_contains($js,'function renderAdminSalesRatingAxis(plotHeight)')
        &&str_contains($js,'for(let rating=5;rating>=1;rating-=1)'),
    'daily rating star is plotted inside each chart day'=>str_contains($js,'class="sales-chart-rating-star"')
        &&str_contains($js,"data-chart-rating="),
    'left chart cap remains target/post only'=>str_contains($js,'const cap=Math.max(1,maxTarget*1.2,maxPosts+1);')
        &&str_contains($js,'The 1–5 rating uses its own right axis.'),
    'new chart CSS is Admin-scoped'=>str_contains($css,'.admin-sales-activity-panel .sales-chart-rating-axis')
        &&str_contains($css,'.admin-sales-activity-panel .sales-chart-rating-star')
        &&str_contains($css,'@media(max-width:760px)'),
];

$failed=[];
foreach($checks as $label=>$ok){
    if(!$ok){$failed[]=$label;}
}
if($failed){
    fwrite(STDERR,'FAILED: '.implode(', ',$failed).PHP_EOL);
    exit(1);
}
echo 'OK Admin Daily Review dual-axis workflow v0.2.116'.PHP_EOL;
