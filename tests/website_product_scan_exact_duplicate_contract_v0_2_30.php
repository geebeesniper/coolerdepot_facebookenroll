<?php
/**
 * v0.2.30 contract: multi-site product scanning + exact-only image duplicate blocking.
 * v0.2.30 契约：多网站产品扫描 + 仅完全一致图片阻止重复。
 */
$root=dirname(__DIR__);
$catalog=file_get_contents($root.'/app/Services/WebsiteCatalog.php');
$duplicate=file_get_contents($root.'/app/Services/DuplicateIndex.php');
$controller=file_get_contents($root.'/app/Controllers/AdminSettingsController.php');
$view=file_get_contents($root.'/app/Views/admin/settings.php');
$routes=file_get_contents($root.'/index.php');
$js=file_get_contents($root.'/public/assets/app.js');
$checks=[
    'multi source setting'=>str_contains($catalog,'company_website_sources_json'),
    'product batch service'=>str_contains($catalog,'scanProductBatch'),
    'json-ld product parser'=>str_contains($catalog,"strcasecmp(\$candidate,'Product')"),
    'first image helper'=>str_contains($catalog,'firstJsonImage'),
    'website image indexing'=>str_contains($catalog,'indexReferenceImage'),
    'product batch route'=>str_contains($routes,'/admin/website/products/scan-batch'),
    'product batch controller'=>str_contains($controller,'scanWebsiteProductsBatch'),
    'multi source UI'=>str_contains($view,'Website URL') && str_contains($view,'Scan Products') && str_contains($view,'Remove Source'),
    'browser batch crawler'=>str_contains($js,'website-product-scan-button'),
    'exact website title block'=>str_contains($duplicate,'WHERE BINARY title=BINARY ?'),
    'exact website image block'=>str_contains($duplicate,"website_exact_image"),
    'no perceptual distance comparison'=>!str_contains($duplicate,'ImageFingerprint::distance('),
    'no similar image warning'=>!str_contains($duplicate,'Possible similar image') && !str_contains($duplicate,'A similar image appears'),
];
$failed=[];
foreach($checks as $name=>$ok){echo ($ok?'PASS ':'FAIL ').$name.PHP_EOL;if(!$ok){$failed[]=$name;}}
exit($failed?1:0);
