<?php
/**
 * File / 文件：tests/admin_daily_workflow_contract_v0_2_113.php
 * EN: Static contract for V0.2.113 daily-target history, adaptive chart cap, and Admin Daily Review/Complete workflow.
 * 中文：V0.2.113 Daily Target 历史、自适应图表上限以及 Admin Daily Review/Complete 流程静态契约。
 */

declare(strict_types=1);

$root=dirname(__DIR__);
$fails=[];

/**
 * EN: Record one static release-contract assertion.
 * 中文：记录一条静态发布契约断言。
 *
 * @param bool $ok Whether the assertion passed. / 断言是否通过。
 * @param string $message Failure message. / 失败提示。
 * @return void No value is returned. / 无返回值。
 */
function v02113Check(bool $ok,string $message):void{
    global $fails;
    if(!$ok){$fails[]=$message;}
}

$version=trim((string)file_get_contents($root.'/VERSION'));
$adminController=(string)file_get_contents($root.'/app/Controllers/AdminController.php');
$salesController=(string)file_get_contents($root.'/app/Controllers/SalesController.php');
$userModel=(string)file_get_contents($root.'/app/Models/User.php');
$schemaCompat=(string)file_get_contents($root.'/app/Core/SchemaCompatibility.php');
$adminView=(string)file_get_contents($root.'/app/Views/admin/dashboard.php');
$salesView=(string)file_get_contents($root.'/app/Views/sales/dashboard.php');
$appJs=(string)file_get_contents($root.'/public/assets/app.js');
$salesJs=(string)file_get_contents($root.'/public/assets/sales-dashboard.js');
$appCss=(string)file_get_contents($root.'/public/assets/app.css');
$routes=(string)file_get_contents($root.'/index.php');

v02113Check(version_compare($version,'0.2.113','>='),'VERSION must be >= 0.2.113');
v02113Check(str_contains($schemaCompat,'cdsp_sales_daily_target_history'),'daily target history table compatibility is missing');
v02113Check(str_contains($schemaCompat,'cdsp_daily_sales_completions'),'daily completion table compatibility is missing');
v02113Check(str_contains($userModel,'dailyPostTargetsForRange'),'date-effective daily target resolver is missing');
v02113Check(str_contains($adminController,"'daily_targets'=>\$dailyTargets"),'Admin chart response must return daily_targets');
v02113Check(str_contains($salesController,"'daily_targets'=>\$dailyTargets"),'Sales chart response must return daily_targets');
v02113Check(str_contains($appJs,'Math.max(1,maxTarget*1.2,maxPosts+1)'),'Admin chart cap must use max(target*120%, posts+1)');
v02113Check(str_contains($salesJs,'maxTarget*1.2')&&str_contains($salesJs,'maxPosts+1'),'Sales chart cap must use max(target*120%, posts+1)');
v02113Check(str_contains($salesView,'$chartCap=max(1,$chartMaxTarget*1.2,$chartMaxPosts+1);'),'Sales server fallback cap contract is missing');
v02113Check(str_contains($appJs,'data-chart-target="'),'Admin chart must expose per-day target data');
v02113Check(str_contains($appCss,'.sales-activity-chart-panel .sales-chart-day-target'),'per-day target line CSS must remain chart-scoped');
v02113Check(str_contains($adminView,'data-daily-complete'),'Admin Mark as Complete control is missing');
v02113Check(str_contains($adminView,'data-daily-calendar-trigger')||str_contains($adminView,'id="salesPeriodReviewDateTrigger"'),'Daily Review calendar trigger is missing');
v02113Check(str_contains($adminView,'id="dailyWorkflowCalendarBackdrop"'),'Daily workflow calendar modal is missing');
v02113Check(str_contains($appJs,"dailyReview:'Daily Review'"),'Daily Sales Review must be renamed Daily Review');
v02113Check(str_contains($appJs,'dashboardDaily')===false,'frontend should use route data attributes rather than hard-coded controller names');
v02113Check(str_contains($routes,"'/admin/dashboard/daily-status'"),'daily status route is missing');
v02113Check(str_contains($routes,"'/admin/dashboard/daily-complete'"),'daily completion route is missing');
v02113Check(str_contains($appJs,"row.completion_date")&&str_contains($appJs,"currentTo"),'Complete action must target the selected range end date');
v02113Check(str_contains($appJs,"\$dailyCompleteGroup.removeClass('hidden')"),'Complete action must remain available for range views');

if($fails){
    fwrite(STDERR,"V0.2.113 daily-workflow contract failed: ".implode('; ',$fails)."\n");
    exit(1);
}

echo "V0.2.113 daily-workflow contract passed.\n";
