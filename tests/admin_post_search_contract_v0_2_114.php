<?php
/**
 * EN: Contract checks for Admin Sales/Post Search exact original-listing lookup.
 * 中文：检查 Admin Sales/Post Search 原帖精确搜索行为的契约测试。
 */
$root=dirname(__DIR__);
$view=file_get_contents($root.'/app/Views/admin/dashboard.php');
$js=file_get_contents($root.'/public/assets/app.js');
$model=file_get_contents($root.'/app/Models/Post.php');
$controller=file_get_contents($root.'/app/Controllers/AdminController.php');
$routes=file_get_contents($root.'/index.php');
$css=file_get_contents($root.'/public/assets/app.css');

$checks=[
    'dashboard exposes post-search endpoint'=>str_contains($view,'data-post-search-url='),
    'search placeholder includes original Post link'=>str_contains($view,'Search name, Sales ID or original Post link'),
    'post-search results are component scoped'=>str_contains($view,'id="adminSalesPostSearchResults"'),
    'route registered'=>str_contains($routes,"/admin/dashboard/post-search"),
    'controller requires admin'=>str_contains($controller,'public function dashboardPostSearch():void')
        &&str_contains($controller,"Auth::requireRole('admin');"),
    'model searches original Post URL data'=>str_contains($model,'adminSearchOriginalPosts')
        &&str_contains($model,'p.canonical_url LIKE ?')
        &&str_contains($model,'p.submitted_url LIKE ?')
        &&str_contains($model,'p.resolved_url LIKE ?')
        &&str_contains($model,'p.external_post_id LIKE ?'),
    'matched Sales cards remain visible'=>str_contains($js,'salesPostSearchSalesIds.has(salesId)'),
    'search result click identifies exact Post'=>str_contains($js,"[data-post-search-post-id]")
        &&str_contains($js,"data-post-search-sales-id"),
    'CSS scoped to Sales directory'=>str_contains($css,'#adminSalesDirectoryTools .admin-sales-post-search-results'),
];

$failed=[];
foreach($checks as $label=>$ok){
    if(!$ok){$failed[]=$label;}
}
if($failed){
    fwrite(STDERR,"FAILED: ".implode(', ',$failed).PHP_EOL);
    exit(1);
}
echo "OK admin post search v0.2.114".PHP_EOL;
