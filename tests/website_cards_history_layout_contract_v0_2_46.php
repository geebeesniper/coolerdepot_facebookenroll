<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$view=file_get_contents($root.'/app/Views/admin/settings.php')?:'';
$css=file_get_contents($root.'/public/assets/app.css')?:'';
$version=trim((string)@file_get_contents($root.'/VERSION'));
$checks=[];
$checks['version']=$version==='0.2.46';
$checks['card_meta']=str_contains($view,'website-source-card-meta');
$checks['history_grouped']=str_contains($view,'$sourceScanHistory=array_values(array_filter');
$checks['history_inside_card']=str_contains($view,'website-source-card-history') && str_contains($view,'$renderWebsiteHistory($sourceScanHistory,\'No Website Scan history yet.\',false)');
$checks['one_scan_history_heading']=substr_count($view,'Product Scan History')===1;
$checks['compact_history_helper']=str_contains($view,'bool $showWebsite=true') && str_contains($view,'website-history-table-compact');
$checks['saved_cards_grid']=str_contains($css,'grid-template-columns:repeat(auto-fill,minmax(260px,320px))');
$checks['expanded_card_full_row']=str_contains($css,'.website-tool-detail-one .website-product-source.is-expanded') && str_contains($css,'grid-column:1/-1');
$checks['compact_buttons']=str_contains($css,'width:auto!important;') && str_contains($css,'min-width:92px;');
$checks['history_inset']=str_contains($css,'.website-tool-detail-two>.website-history-heading') && str_contains($css,'margin:12px 14px 0;');
$checks['mobile_csv_height_fix']=str_contains($css,'.website-tool-form-csv .website-card-actions{height:auto!important;min-height:0!important}');
$failed=array_keys(array_filter($checks,static fn(bool $ok): bool => !$ok));
if($failed){fwrite(STDERR,'V0.2.46 contract failed: '.implode(', ',$failed).PHP_EOL);exit(1);} 
echo 'V0.2.46 contract checks passed: '.count($checks).PHP_EOL;
