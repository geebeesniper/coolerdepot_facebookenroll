<?php
/**
 * Contract test for V0.2.126 Sales all-date Post Search.
 */
$root=dirname(__DIR__);
$fail=[];

$files=[
    'route'=>file_get_contents($root.'/index.php'),
    'controller'=>file_get_contents($root.'/app/Controllers/SalesController.php'),
    'model'=>file_get_contents($root.'/app/Models/Post.php'),
    'view'=>file_get_contents($root.'/app/Views/sales/dashboard.php'),
    'js'=>file_get_contents($root.'/public/assets/app.js'),
    'css'=>file_get_contents($root.'/public/assets/app.css'),
    'help'=>file_get_contents($root.'/app/Views/help/sales.php'),
    'guide'=>file_get_contents($root.'/docs/user-guides/sales.html'),
];

$checks=[
    'sales post-search route'=>str_contains($files['route'],"/sales/post-search")
        &&str_contains($files['route'],"'postSearch'"),
    'sales-only controller auth'=>str_contains($files['controller'],'public function postSearch():void')
        &&str_contains($files['controller'],"Auth::requireRole('sales')"),
    'search ignores dashboard date range'=>str_contains($files['controller'],'salesSearchOriginalPosts((int)$u[\'id\'],$query,40)'),
    'model ownership scope'=>str_contains($files['model'],'public static function salesSearchOriginalPosts')
        &&str_contains($files['model'],'WHERE p.sales_user_id=?')
        &&str_contains($files['model'],'p.canonical_url LIKE ?')
        &&str_contains($files['model'],'p.external_post_id LIKE ?')
        &&str_contains($files['model'],'p.title LIKE ?'),
    'search panel and endpoint'=>str_contains($files['view'],'id="salesPostSearchPanel"')
        &&str_contains($files['view'],'data-post-search-url=')
        &&str_contains($files['view'],'id="salesPostSearchInput"'),
    'exact post opens existing detail'=>str_contains($files['js'],"searchSalesPostsAcrossAllDates")
        &&str_contains($files['js'],"openSalesPostDetail($(this))")
        &&str_contains($files['js'],"data-sales-post-id"),
    'search mode hides normal dated content'=>str_contains($files['css'],'#salesPostSearchPanel[data-search-active="1"] ~ #salesActivityChartPanel')
        &&str_contains($files['css'],'#salesPostSearchPanel[data-search-active="1"] ~ #salesDailyStage'),
    'responsive scoped css'=>str_contains($files['css'],'/* v0.2.126 — Sales all-date Post Search only.')
        &&str_contains($files['css'],'@media (max-width:700px)'),
    'search i18n'=>str_contains($files['js'],"salesPostSearchLabel:'Post Search'")
        &&str_contains($files['js'],"salesPostSearchLabel:'Post 搜索'")
        &&str_contains($files['js'],"salesPostSearchLabel:'Post 搜尋'")
        &&str_contains($files['js'],"salesPostSearchLabel:'Buscar publicaciones'"),
    'sales manuals updated'=>str_contains($files['help'],'只搜索当前 Sales 自己保存的 Posts，而且跨所有日期')
        &&str_contains($files['guide'],'V0.2.126')
        &&str_contains($files['guide'],'all dates'),
];

foreach($checks as $label=>$ok){
    if(!$ok){$fail[]=$label;}
}

if($fail){
    fwrite(STDERR,"FAIL V0.2.126 Sales all-date Post Search:\n - ".implode("\n - ",$fail)."\n");
    exit(1);
}

echo "OK Sales all-date Post Search v0.2.126\n";
