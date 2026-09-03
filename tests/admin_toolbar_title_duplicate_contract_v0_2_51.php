<?php
$root = dirname(__DIR__);
$dashboard = file_get_contents($root . '/app/Views/admin/dashboard.php');
$css = file_get_contents($root . '/public/assets/app.css');
$js = file_get_contents($root . '/public/assets/app.js');
$checks = [
    'toolbar has dedicated balanced range row' => strpos($dashboard, 'class="admin-activity-range-row"') !== false,
    'period and dates remain in balanced range row' => strpos($css, 'grid-template-columns:minmax(0,1fr) minmax(0,1fr)') !== false,
    'search is its own full-width row' => strpos($css, '.admin-dashboard-range-bar .admin-sales-search-field') !== false && strpos($css, 'width:100% !important;') !== false,
    'location filter remains its own wrap row' => strpos($css, '.admin-dashboard-range-bar .admin-sales-location-filter-wrap') !== false && strpos($css, 'flex-wrap:wrap !important;') !== false,
    'old one-row toolbar override removed' => strpos($css, 'V0.2.50 — one-row Admin activity toolbar') === false,
    'title duplicate has explicit link label' => strpos($js, "exact_title:'Title duplicate'") !== false,
    'title duplicate banner is explicit' => strpos($js, "'TITLE DUPLICATE — '+rawResultMessage") !== false,
    'title duplicate inspection state is explicit' => strpos($js, "'Title duplicate':'Issue'") !== false,
    'duplicate source accepts duplicate kind' => strpos($js, 'function setSalesDuplicateSource(url,kind)') !== false,
    'matching post link no longer says generic duplicate URL' => strpos($js, ".text(duplicateLabel+' — open matching post ↗')") !== false,
    'preflight forwards duplicate kind' => strpos($js, 'setSalesDuplicateSource(preflight.duplicate_url,preflight.duplicate_kind)') !== false,
    'inspection forwards duplicate kind' => strpos($js, 'setSalesDuplicateSource(d.duplicate_url,d.duplicate_kind)') !== false,
];
$failed=[];
foreach($checks as $label=>$ok){
    if(!$ok){$failed[]=$label;}
}
if($failed){
    fwrite(STDERR, "V0.2.51 contract failures:\n- ".implode("\n- ",$failed)."\n");
    exit(1);
}
echo 'V0.2.51 contract checks passed: '.count($checks).PHP_EOL;
