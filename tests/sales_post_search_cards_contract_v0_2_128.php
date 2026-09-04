<?php
$root=dirname(__DIR__);
$js=(string)@file_get_contents($root.'/public/assets/app.js');
$css=(string)@file_get_contents($root.'/public/assets/app.css');
$controller=(string)@file_get_contents($root.'/app/Controllers/SalesController.php');
$view=(string)@file_get_contents($root.'/app/Views/sales/dashboard.php');
$manual=(string)@file_get_contents($root.'/app/Views/help/sales.php');

$checks=[
    trim((string)@file_get_contents($root.'/VERSION'))==='0.2.128',
    strpos($view,'id="salesPostSearchPanel"')!==false,
    strpos($view,'id="salesPostSearchResults"')!==false,
    strpos($controller,"Post::salesSearchOriginalPosts((int)\$u['id'],\$query,40)")!==false,
    strpos($controller,"'published_display'")!==false,
    strpos($js,'function renderSalesSelfPostSearchResults(rows,query)')!==false,
    strpos($js,'sales-post-card-grid sales-post-search-card-grid')!==false,
    strpos($js,'sales-self-post-card sales-post-search-card')!==false,
    strpos($js,'data-sales-post-id=')!==false,
    strpos($js,'data-view-sales-post')!==false,
    strpos($js,"\$salesPostSearchResults.on('click','.sales-post-search-result'")===false,
    strpos($css,'#salesPostSearchPanel .sales-post-search-card-grid')!==false,
    strpos($css,'#salesPostSearchPanel .sales-post-search-card')!==false,
    strpos($manual,'不再出现中间结果列表')!==false,
];

foreach($checks as $ok){
    if(!$ok){
        fwrite(STDERR,"V0.2.128 Sales Post Search card contract failed.\n");
        exit(1);
    }
}

echo "OK Sales Post Search standard-card results v0.2.128\n";
