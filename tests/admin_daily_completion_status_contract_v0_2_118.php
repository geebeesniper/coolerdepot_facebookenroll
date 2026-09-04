<?php
/**
 * EN: Contract checks for v0.2.118 Complete/Incomplete status semantics.
 * 中文：检查 v0.2.118 Complete/Incomplete 状态、真实达标锁定及 Target met 联动。
 */
$root=dirname(__DIR__);
$version=trim((string)file_get_contents($root.'/VERSION'));
$controller=(string)file_get_contents($root.'/app/Controllers/AdminController.php');
$view=(string)file_get_contents($root.'/app/Views/admin/dashboard.php');
$js=(string)file_get_contents($root.'/public/assets/app.js');
$css=(string)file_get_contents($root.'/public/assets/app.css');
$checks=[
    'version is >= 0.2.118'=>version_compare($version,'0.2.118','>='),
    'button exposes effective completion state'=>str_contains($view,'data-effective-complete'),
    'button exposes real target lock state'=>str_contains($view,'data-completion-target-met'),
    'initial labels are Complete or Incomplete'=>str_contains($view,"? 'Complete' : 'Incomplete'"),
    'real target met backend guard exists'=>str_contains($controller,'dailyTargetActuallyMet') && str_contains($controller,'cannot be set to Incomplete'),
    'manual completion contributes to Target met'=>str_contains($controller,"\$row['target_met']=\$actualPeriodTargetMet || \$manualCompleted"),
    'endpoint returns effective completion'=>str_contains($controller,"'effective_completed'=>\$effectiveCompleted"),
    'frontend uses Complete and Incomplete labels'=>str_contains($js,"effectiveComplete?tr('complete'):tr('incomplete')"),
    'real target met locks frontend control'=>str_contains($js,".prop('disabled',completionTargetMet)") && str_contains($js,"data-completion-target-met"),
    'manual Complete updates Target met badge'=>str_contains($js,'const targetMet=actualPeriodTargetMet||manualCompleted'),
    'incomplete style is gray'=>str_contains($css,'background:#f1f5f9;') && str_contains($css,'color:#64748b;'),
    'complete style remains green'=>str_contains($css,'.sales-progress-card .sales-daily-complete.is-completed') && str_contains($css,'background:#dcfce7;'),
];
$failed=[];
foreach($checks as $label=>$ok){if(!$ok){$failed[]=$label;}}
if($failed){fwrite(STDERR,"FAILED: ".implode('; ',$failed).PHP_EOL);exit(1);}
echo 'OK Admin Complete/Incomplete target lock v0.2.118'.PHP_EOL;
