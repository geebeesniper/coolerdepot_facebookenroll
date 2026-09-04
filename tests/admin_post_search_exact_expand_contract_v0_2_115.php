<?php
/**
 * EN: Contract checks for v0.2.115 exact/matched Post-only expansion from Admin Sales/Post Search.
 * 中文：检查 v0.2.115 Admin Sales/Post Search 只展开精确/匹配 Post 的行为。
 */
$root=dirname(__DIR__);
$js=file_get_contents($root.'/public/assets/app.js');
$model=file_get_contents($root.'/app/Models/Post.php');
$controller=file_get_contents($root.'/app/Controllers/AdminController.php');
$version=trim((string)file_get_contents($root.'/VERSION'));

$checks=[
    'version is >= 0.2.115'=>version_compare($version,'0.2.115','>='),
    'search matches retained client side'=>str_contains($js,'let salesPostSearchMatches=[];'),
    'exact result expands one matched post'=>str_contains($js,'openSalesPostSearchMatches($card,[match]);'),
    'Sales card uses matched search subset'=>str_contains($js,'openSalesPostSearchMatches($card,matchedRows);'),
    'search-only grid mode exists'=>str_contains($js,'search_only:true')
        &&str_contains($js,'const searchOnly=Boolean(data&&data.search_only);'),
    'search-only mode hides chart and period review'=>str_contains($js,"\$adminSalesActivity.addClass('hidden');")
        &&str_contains($js,"\$expandedReview.addClass('hidden');"),
    'search-only review save cannot overwrite period counts'=>str_contains($js,'if(currentExpandedData&&currentExpandedData.search_only){'),
    'search result tile opens normal review modal'=>str_contains($js,"\$expandedList.on('click', '.sales-post-tile'")
        &&str_contains($js,'openReviewModal($(this).data(\'post-id\'));'),
    'search query returns current review status'=>str_contains($model,'AS current_review_status')
        &&str_contains($controller,"'status'=>in_array("),
];

$failed=[];
foreach($checks as $label=>$ok){
    if(!$ok){$failed[]=$label;}
}
if($failed){
    fwrite(STDERR,"FAILED: ".implode(', ',$failed).PHP_EOL);
    exit(1);
}
echo "OK admin exact Post-only search expansion v0.2.115".PHP_EOL;
