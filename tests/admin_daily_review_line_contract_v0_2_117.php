<?php
/**
 * EN: Contract checks for v0.2.117 modal-only Daily Review and line-trend ratings.
 * 中文：检查 v0.2.117 Daily Review 仅弹窗显示，以及评分折线趋势图行为。
 */
$root=dirname(__DIR__);
$version=trim((string)file_get_contents($root.'/VERSION'));
$view=(string)file_get_contents($root.'/app/Views/admin/dashboard.php');
$js=(string)file_get_contents($root.'/public/assets/app.js');
$css=(string)file_get_contents($root.'/public/assets/app.css');
$checks=[
    'version is >= 0.2.117'=>version_compare($version,'0.2.117','>='),
    'inline expanded review panel removed'=>strpos($view,'id="salesExpandedReview"')===false,
    'chart has review line svg'=>strpos($view,'id="adminSalesChartReviewLine"')!==false,
    'legend uses line marker instead of star text'=>strpos($view,'class="admin-daily-rating-legend"><i aria-hidden="true"></i>')!==false,
    'daily review renderer remains modal-only'=>strpos($js,"Daily Review is modal-only")!==false && strpos($js,"\$expandedReview.addClass('hidden');")!==false,
    'old per-day star markup removed'=>strpos($js,'sales-chart-rating-star\" style=')===false,
    'review trend path rendered'=>strpos($js,'sales-chart-review-trend-path')!==false,
    'review trend points rendered'=>strpos($js,'sales-chart-review-trend-point')!==false,
    'true 1-5 right-axis mapping'=>strpos($js,'1-((rating-1)/4)')!==false,
    'left cap rule preserved'=>strpos($js,'const cap=Math.max(1,maxTarget*1.2,maxPosts+1);')!==false,
    'trend CSS scoped to admin chart'=>strpos($css,'.admin-sales-activity-panel .sales-chart-review-line')!==false,
];
$failed=[];
foreach($checks as $label=>$ok){if(!$ok){$failed[]=$label;}}
if($failed){fwrite(STDERR,"FAILED: ".implode('; ',$failed).PHP_EOL);exit(1);} 
echo 'OK Admin Daily Review modal-only line trend v0.2.117'.PHP_EOL;
