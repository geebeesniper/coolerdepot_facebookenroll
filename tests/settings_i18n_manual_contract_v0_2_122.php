<?php
$root=dirname(__DIR__);
$app=file_get_contents($root.'/public/assets/app.js');
$settings=file_get_contents($root.'/app/Views/admin/settings.php');
$admin=file_get_contents($root.'/app/Views/help/admin.php');
$sales=file_get_contents($root.'/app/Views/help/sales.php');
$adminHtml=file_get_contents($root.'/docs/user-guides/admin.html');
$salesHtml=file_get_contents($root.'/docs/user-guides/sales.html');
$checks=[
    [str_contains($settings,'data-app-i18n="settingsApiProviders"'),'Settings API Providers uses explicit i18n'],
    [str_contains($settings,'data-app-i18n="settingsApplicationSettings"'),'Application Settings uses explicit i18n'],
    [str_contains($settings,'data-app-i18n="settingsProviderChain"'),'Provider Chain uses explicit i18n'],
    [str_contains($settings,'data-app-i18n="settingsRecentProviderJobs"'),'Provider Jobs uses explicit i18n'],
    [str_contains($settings,'data-app-i18n="settingsWebsiteLibrary"'),'Website Library uses explicit i18n'],
    [str_contains($app,"settingsApiProviders:'API 服务提供商'"),'Simplified Chinese Settings heading exists'],
    [str_contains($app,"settingsApiProviders:'API 服務提供商'"),'Traditional Chinese Settings heading exists'],
    [str_contains($app,"settingsApiProviders:'Proveedores API'"),'Spanish Settings heading exists'],
    [str_contains($app,'function settingsPageTranslateDynamicPhrase'),'Settings dynamic phrase translator exists'],
    [!str_contains($app,'document.createTreeWalker(root,NodeFilter.SHOW_TEXT)'),'Settings translation no longer depends on TreeWalker/NodeFilter'],
    [str_contains($admin,'Current date picker rule:'),'Admin manual has current To/From date rule'],
    [str_contains($admin,'Complete / Incomplete'),'Admin manual has Complete/Incomplete'],
    [str_contains($admin,'Daily Activity Calendar'),'Admin manual has shared Daily Activity Calendar'],
    [str_contains($admin,'highest Daily target × 120%'),'Admin manual has chart left-axis rule'],
    [str_contains($admin,'Post Search'),'Admin manual has Post Search'],
    [str_contains($sales,'Current date picker rule:'),'Sales manual has current To/From date rule'],
    [str_contains($sales,'Verification Queue'),'Sales manual has Verification Queue'],
    [str_contains($sales,'Bulk Submit'),'Sales manual has Bulk Submit'],
    [str_contains($sales,'Save &amp; Wait'),'Sales manual has Save & Wait'],
    [str_contains($adminHtml,'V0.2.122'),'Admin bundled HTML is v0.2.122'],
    [str_contains($salesHtml,'V0.2.122'),'Sales bundled HTML is v0.2.122'],
];
foreach($checks as [$ok,$label]){
    if(!$ok){fwrite(STDERR,"FAIL: $label\n"); exit(1);}    
}
echo "OK Settings i18n + manuals v0.2.122\n";
