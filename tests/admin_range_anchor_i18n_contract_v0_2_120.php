<?php
/**
 * EN: Contract checks for v0.2.120 Admin To-date anchor behavior and Dashboard range i18n.
 * 中文：检查 v0.2.120 Admin To 日期锚点规则及 Dashboard 日期范围语言联动。
 */
$root=dirname(__DIR__);
$version=trim((string)file_get_contents($root.'/VERSION'));
$js=(string)file_get_contents($root.'/public/assets/app.js');
$view=(string)file_get_contents($root.'/app/Views/admin/dashboard.php');

$checks=[
    'version is >= 0.2.120'=>version_compare($version,'0.2.120','>='),
    'To picker has no server min restriction'=>substr_count($view,'id="dashboardToInput"')===1
        && substr_count($view,'id="dashboardStickyTo"')===1
        && !preg_match('/id="dashboardToInput"[\\s\\S]{0,220}min=/', $view)
        && !preg_match('/id="dashboardStickyTo"[\\s\\S]{0,220}min=/', $view),
    'JS removes To min restriction'=>substr_count($js,".removeAttr('min')")>=4,
    'inside current range becomes Custom'=>str_contains($js,'const insideLoadedRange=(')
        && str_contains($js,'to>=loadedFrom')
        && str_contains($js,'to<=loadedTo')
        && str_contains($js,"if(insideLoadedRange){")
        && str_contains($js,"currentPreset='custom';"),
    'outside current named preset shifts whole range'=>str_contains($js,"if(preset!=='custom'){")
        && str_contains($js,'const shifted=adminPresetRange(preset,to);')
        && str_contains($js,'loadProgress({from:shifted.from,to:shifted.to,preset:preset});'),
    'single-day outside anchor remains single'=>str_contains($js,"loadProgress({date:shifted.to,period:'day',preset:'single'});"),
    'From still always creates Custom'=>str_contains($js,'Existing From behavior: editing From always creates Custom.')
        && str_contains($js,"loadProgress({\n            from:from,\n            to:to,\n            preset:'custom'"),
    'To is still capped at today'=>str_contains($js,'if(today&&to>today){')
        && str_contains($view,'max="<?= Util::e($today) ?>"'),
    'range preset buttons have dashboard i18n keys'=>substr_count($view,'data-dashboard-i18n="<?= Util::e($presetI18nKey) ?>"')===2,
    'Settings language event updates Dashboard range'=>str_contains($js,"cdsp:language-changed.cdspAdminDashboard")
        && str_contains($js,'dashboardLanguage=lang;')
        && str_contains($js,'applyDashboardLanguage();'),
    'persisted language applies before initial AJAX'=>str_contains($js,'dashboardLanguage=currentAppLanguage();')
        && str_contains($js,'Apply persisted Settings language immediately'),
    'Simplified Chinese range labels exist'=>str_contains($js,"oneDay:'1天'")
        && str_contains($js,"threeDays:'3天'")
        && str_contains($js,"weekly:'每周'")
        && str_contains($js,"monthly:'每月'")
        && str_contains($js,"custom:'自定义'")
        && str_contains($js,"from:'开始'")
        && str_contains($js,"to:'结束'"),
];

$failed=[];
foreach($checks as $label=>$ok){
    if(!$ok){$failed[]=$label;}
}
if($failed){
    fwrite(STDERR,"FAILED: ".implode('; ',$failed).PHP_EOL);
    exit(1);
}
echo 'OK Admin To anchor + range i18n v0.2.120'.PHP_EOL;
