<?php
$root=dirname(__DIR__);
$app=file_get_contents($root.'/public/assets/app.js');
$dashboard=file_get_contents($root.'/app/Views/admin/dashboard.php');
$adminHelp=file_get_contents($root.'/app/Views/help/admin.php');
$salesHelp=file_get_contents($root.'/app/Views/help/sales.php');
$version=trim(file_get_contents($root.'/VERSION'));
$checks=[
    [$version==='0.2.121','VERSION is 0.2.121'],
    [str_contains($app,'const settingsPageTextDictionary='),'settings page translation dictionary exists'],
    [str_contains($app,"'Application Settings':'应用设置'"),'Application Settings Chinese translation exists'],
    [str_contains($app,"'Company Website Library':'公司网站资料库'"),'Website Library Chinese translation exists'],
    [str_contains($app,"'Recent Provider Jobs':'最近 Provider Jobs'") || str_contains($app,"'Recent Provider Jobs':'最近服务商任务'"),'Provider Jobs Chinese translation exists'],
    [str_contains($app,'applySettingsPageLanguage();'),'settings language applies with global language switch'],
    [str_contains($app,'startSettingsPageLanguageObserver();'),'dynamic settings content language observer exists'],
    [str_contains($dashboard,'aria-labelledby="dashboardReviewModalTitle"') && str_contains($dashboard,'tabindex="-1"'),'Post Review dialog is programmatically focusable'],
    [str_contains($app,'function resetReviewModalViewport(focusDialog)'),'Post Review viewport reset helper exists'],
    [str_contains($app,"scrollEl.scrollTop=0"),'Post Review resets scroll to top'],
    [str_contains($app,"dialog.focus({preventScroll:true})"),'Post Review focuses dialog without scrolling down'],
    [str_contains($adminHelp,'Daily Review, completion, chart and Post Search'),'Admin manual includes current daily workflow'],
    [str_contains($adminHelp,'Current date picker rule:'),'Admin manual includes current To/From rule'],
    [str_contains($adminHelp,'Settings page follows the same English / Simplified Chinese / Traditional Chinese / Spanish switch'),'Admin manual documents Settings language follow'],
    [str_contains($salesHelp,'Current verification and background queue workflow'),'Sales manual includes Verification Queue workflow'],
    [str_contains($salesHelp,'Manually changing From always switches to Custom'),'Sales manual includes current date behavior'],
];
foreach($checks as [$ok,$label]){
    if(!$ok){fwrite(STDERR,"FAIL: $label\n");exit(1);}
}
echo "OK Settings i18n + Post Review top focus + current manuals v0.2.121\n";
