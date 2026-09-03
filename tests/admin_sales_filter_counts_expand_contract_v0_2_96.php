<?php
/**
 * V0.2.96 contract: Admin Sales directory filters must update both Sales and
 * Post summary counts to the visible cards and must not disable View Posts.
 */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);
$version=trim((string)file_get_contents($root.'/VERSION'));
$js=(string)file_get_contents($root.'/public/assets/app.js');
$checks=[];
$checks['VERSION is >= 0.2.96']=version_compare($version,'0.2.96','>=');
$checks['visible Post total is accumulated from visible Sales cards']=
    str_contains($js,'let visiblePostCount=0;') &&
    str_contains($js,"visiblePostCount+=Math.max(") &&
    str_contains($js,"parseInt(\$card.attr('data-post-count'),10)||0");
$checks['filtered summary updates Sales count']=str_contains($js,"\$('#dashboardSalesCount').text(visibleCount);");
$checks['filtered summary updates Post count']=str_contains($js,"\$('#dashboardPostCount').text(visiblePostCount);");
$checks['old filter-active View Posts blocker is removed']=
    !str_contains($js,"if(salesDirectoryFilteringActive()){\n            closeExpandedPosts();\n            return;\n        }");
$checks['visible filtered Sales card can open View Posts']=
    str_contains($js,"if(\$card.hasClass('sales-directory-hidden')){\n            return;\n        }");
$checks['expanded panel closes only if selected Sales becomes hidden']=
    str_contains($js,"||\$expandedCard.hasClass('sales-directory-hidden')") &&
    str_contains($js,'closeExpandedPosts();');
$checks['expanded panel repositions when filters keep selected Sales visible']=
    str_contains($js,'placeExpandedAfterCardRow($expandedCard);') &&
    str_contains($js,"\$expanded.removeClass('hidden');");
$checks['hidden Sales cards cannot affect expanded row placement']=
    str_contains($js,"if(\$candidate.hasClass('sales-directory-hidden')){") &&
    str_contains($js,'const $candidate=$(this);');

$failed=[];
foreach($checks as $label=>$ok){
    echo ($ok?'[PASS] ':'[FAIL] ').$label.PHP_EOL;
    if(!$ok){$failed[]=$label;}
}
if($failed){
    fwrite(STDERR,'V0.2.96 Admin Sales filter/count/expand contract failed: '.implode(', ',$failed).PHP_EOL);
    exit(1);
}
echo count($checks).' V0.2.96 Admin Sales filter/count/expand checks passed.'.PHP_EOL;
