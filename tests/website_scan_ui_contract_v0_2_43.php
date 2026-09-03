<?php
$root=dirname(__DIR__);
$js=file_get_contents($root.'/public/assets/app.js');
$css=file_get_contents($root.'/public/assets/app.css');
$job=file_get_contents($root.'/app/Services/WebsiteScanJob.php');
$catalog=file_get_contents($root.'/app/Services/WebsiteCatalog.php');
$view=file_get_contents($root.'/app/Views/admin/settings.php');
$checks=[
    'website details collapsed by default'=>str_contains($view,'website-source-card-detail hidden')&&str_contains($view,'data-website-source-detail'),
    'website card opens details'=>str_contains($js,"slideDown(180")&&str_contains($js,"data-website-source-detail"),
    'product list opens only with website card'=>str_contains($js,"Scanned products")&&str_contains($js,"openInlineSource"),
    'standard caret geometry'=>str_contains($css,'border-right:2px solid currentColor')&&str_contains($css,'border-bottom:2px solid currentColor')&&str_contains($css,'rotate(-135deg)'),
    'csv fields share alignment wrappers'=>str_contains($view,'website-tool-form-csv')&&substr_count($view,'website-tool-action-field')>=2,
    'sitemap fields share alignment wrappers'=>str_contains($view,'website-tool-form-sitemap')&&str_contains($view,'websiteSitemapUrl'),
    'repeat scan skips only product detail'=>str_contains($job,'WebsiteCatalog::isProductDetailUrl')&&str_contains($catalog,'public static function isProductDetailUrl'),
    'navigation pages are still fetched'=>str_contains($job,'Listing/category/navigation pages')&&str_contains($job,'must be re-fetched'),
    'queue is globally prioritized'=>str_contains($job,'WebsiteCatalog::crawlPriority')&&str_contains($catalog,'public static function crawlPriority'),
    'wordpress sitemap seed'=>str_contains($catalog,"'/wp-sitemap.xml'"),
    'product category pagination supported'=>str_contains($catalog,'product-category')&&str_contains($catalog,"/page/\\d+"),
    'progress distinguishes library and run'=>str_contains($js,"Library '+Number(stats.total||0)+' unique products")&&str_contains($js,"This run '+Number(state.products||0)+' product pages"),
];
$failed=[];
foreach($checks as $name=>$ok){if(!$ok)$failed[]=$name;}
if($failed){fwrite(STDERR,'FAILED: '.implode(', ',$failed)."\n");exit(1);}
echo "V0.2.43 contract checks passed: ".count($checks)."\n";
