<?php
/**
 * File / 文件：tests/sales_location_filter_contract_v0_2_47.php
 * EN: Static release contract for V0.2.47 Sales locations, assignment controls, search and multi-select filtering.
 * 中文：V0.2.47 Sales Location、分配控件、搜索与多选筛选的静态发布契约测试。
 */
declare(strict_types=1);
$root=dirname(__DIR__);
$version=trim((string)@file_get_contents($root.'/VERSION'));
$schema=(string)@file_get_contents($root.'/database/schema.sql');
$migration=(string)@file_get_contents($root.'/scripts/migrate_v0_2_47.php');
$model=(string)@file_get_contents($root.'/app/Models/Location.php');
$user=(string)@file_get_contents($root.'/app/Models/User.php');
$post=(string)@file_get_contents($root.'/app/Models/Post.php');
$admin=(string)@file_get_contents($root.'/app/Controllers/AdminController.php');
$settingsController=(string)@file_get_contents($root.'/app/Controllers/AdminSettingsController.php');
$settings=(string)@file_get_contents($root.'/app/Views/admin/settings.php');
$dashboard=(string)@file_get_contents($root.'/app/Views/admin/dashboard.php');
$routes=(string)@file_get_contents($root.'/index.php');
$js=(string)@file_get_contents($root.'/public/assets/app.js');
$css=(string)@file_get_contents($root.'/public/assets/app.css');

$checks=[];
$checks['version']=$version==='0.2.47';
$checks['location table schema']=str_contains($schema,'CREATE TABLE IF NOT EXISTS cdsp_locations')
    && str_contains($schema,'location_id INT UNSIGNED NULL')
    && str_contains($schema,'KEY idx_users_location(location_id)');
$checks['idempotent migration']=str_contains($migration,'information_schema.COLUMNS')
    && str_contains($migration,'information_schema.STATISTICS')
    && str_contains($migration,'CREATE TABLE IF NOT EXISTS cdsp_locations');
$checks['location model counts']=str_contains($model,'allWithSalesCounts')
    && str_contains($model,"COUNT(u.id) AS sales_count")
    && str_contains($model,'unassignedSalesCount');
$checks['sales model assignment']=str_contains($user,'setSalesSettings')
    && str_contains($user,'location_id=?')
    && str_contains($user,'l.name AS location_name');
$checks['progress includes location']=str_contains($post,'u.location_id')
    && str_contains($post,"COALESCE(l.name,'') AS location_name");
$checks['settings routes']=str_contains($routes,"'/admin/settings/location/add'")
    && str_contains($routes,"'/admin/settings/location/delete'");
$checks['settings controller manages locations']=str_contains($settingsController,'Location::create')
    && str_contains($settingsController,'Location::deleteIfUnused')
    && str_contains($settingsController,'Location::allWithSalesCounts');
$checks['settings location UI']=str_contains($settings,'id="sales-locations"')
    && str_contains($settings,'sales-location-add-form')
    && str_contains($settings,'sales-location-card')
    && str_contains($settings,'$locationSalesCount');
$checks['admin loads locations']=str_contains($admin,'Location::allWithSalesCounts')
    && str_contains($admin,'Location::unassignedSalesCount')
    && str_contains($admin,'location_counts');
$checks['sales search UI']=str_contains($dashboard,'id="salesCardSearch"')
    && str_contains($dashboard,'data-dashboard-i18n-placeholder="salesSearchPlaceholder"');
$checks['button location filter with counts']=str_contains($dashboard,'id="salesLocationFilter"')
    && str_contains($dashboard,'data-location-filter="all"')
    && str_contains($dashboard,'data-location-count')
    && str_contains($dashboard,'data-location-filter="0"');
$checks['sales card location metadata']=str_contains($dashboard,'data-location-id=')
    && str_contains($dashboard,'data-location-name=')
    && str_contains($dashboard,'data-sales-location-label');
$checks['sales settings location selector']=str_contains($dashboard,'id="salesPersonLocation"')
    && str_contains($dashboard,'data-dashboard-i18n="unassigned"');
$checks['multi select implementation']=str_contains($js,'const selectedLocationFilters=new Set()')
    && str_contains($js,'selectedLocationFilters.add(key)')
    && str_contains($js,'selectedLocationFilters.delete(key)')
    && str_contains($js,'selectedLocationFilters.has(locationId)');
$checks['search and location combine']=str_contains($js,'const show=matchesSearch&&matchesLocation')
    && str_contains($js,"\$salesDirectorySearch.on('input'");
$checks['location counts update after assignment']=str_contains($js,'updateSalesLocationFilterCounts(data)')
    && str_contains($js,".attr('data-location-id',savedLocationId)");
$checks['location i18n four languages']=substr_count($js,"salesSearch:'")>=4
    && substr_count($js,"allLocations:'")>=4
    && substr_count($js,"locationAssignmentHelp:'")>=4;
$checks['filter buttons responsive']=str_contains($css,'.admin-sales-location-button')
    && str_contains($css,'@media(max-width:520px)')
    && str_contains($css,'grid-template-columns:repeat(2,minmax(0,1fr));')
    && str_contains($css,'@media(max-width:360px)');
$checks['settings locations responsive']=str_contains($css,'.sales-location-list')
    && str_contains($css,'.sales-location-add-row')
    && str_contains($css,'grid-template-columns:1fr;');

$failed=array_keys(array_filter($checks,static fn(bool $ok): bool => !$ok));
if($failed){
    fwrite(STDERR,'V0.2.47 contract failed: '.implode(', ',$failed).PHP_EOL);
    exit(1);
}
echo 'V0.2.47 contract checks passed: '.count($checks).PHP_EOL;
