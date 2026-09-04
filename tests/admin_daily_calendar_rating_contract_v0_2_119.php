<?php
/**
 * EN: Contract checks for v0.2.119 shared Daily Activity Calendar and visible Daily Review ratings.
 * 中文：检查 v0.2.119 共用 Daily Activity Calendar、评分数据一致性及评分折线可见性。
 */
$root=dirname(__DIR__);
$version=trim((string)file_get_contents($root.'/VERSION'));
$controller=(string)file_get_contents($root.'/app/Controllers/AdminController.php');
$js=(string)file_get_contents($root.'/public/assets/app.js');
$css=(string)file_get_contents($root.'/public/assets/app.css');
$view=(string)file_get_contents($root.'/app/Views/admin/dashboard.php');
$checks=[
    'version is >= 0.2.119'=>version_compare($version,'0.2.119','>='),
    'one shared Daily Activity Calendar remains'=>substr_count($view,'id="dailyWorkflowCalendarBackdrop"')===1,
    'calendar and chart use shared daily review state helper'=>substr_count($controller,'dashboardDailyReviewStatesForRange(')>=3,
    'calendar returns rating with reviewed state'=>str_contains($controller,"'rating'=>(int)(\$state['rating']??0)"),
    'chart ratings derive from shared review state'=>str_contains($controller,'foreach($this->dashboardDailyReviewStatesForRange($salesUserId,$from,$to) as $date=>$state)'),
    'legacy daily review rating fallback is supported'=>str_contains($controller,'FROM cdsp_daily_sales_reviews'),
    'deleted unified history prevents legacy resurrection'=>str_contains($controller,'isset($historyDates[$date])||isset($states[$date])'),
    'calendar Complete includes actual target met'=>str_contains($controller,"\$days[\$date]['actual_target_met']=true") && str_contains($controller,"\$days[\$date]['completed']=true"),
    'calendar consumes review rating'=>str_contains($js,'const reviewRating=Math.max(0,Math.min(5,parseInt(status.rating,10)||0));'),
    'review line stays above post bars'=>str_contains($css,'.admin-sales-activity-panel .sales-chart-review-line') && str_contains($css,'z-index:8;'),
    'line and points are still rendered'=>str_contains($js,'sales-chart-review-trend-path') && str_contains($js,'sales-chart-review-trend-point'),
    'left axis cap rule remains unchanged'=>str_contains($js,'const cap=Math.max(1,maxTarget*1.2,maxPosts+1);'),
];
$failed=[];
foreach($checks as $label=>$ok){if(!$ok){$failed[]=$label;}}
if($failed){fwrite(STDERR,"FAILED: ".implode('; ',$failed).PHP_EOL);exit(1);}
echo 'OK shared Daily Activity Calendar + visible Daily Review rating v0.2.119'.PHP_EOL;
