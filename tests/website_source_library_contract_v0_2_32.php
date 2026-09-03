<?php
/**
 * v0.2.32 website-source management contract.
 * v0.2.32 网站来源与扫描 URL 管理契约检查。
 */
$root=dirname(__DIR__);
$checks=[];
$need=function(string $file,string $needle,string $label)use(&$checks,$root):void{
    $text=file_get_contents($root.'/'.$file);
    $checks[]=[str_contains((string)$text,$needle),$label];
};
$checks[]=[trim((string)file_get_contents($root.'/VERSION'))==='0.2.32','VERSION is 0.2.32'];
$need('index.php',"/admin/website/source', [AdminSettingsController::class, 'websiteSourceDetail']",'website source detail route exists');
$need('app/Services/WebsiteCatalog.php',"DELETE FROM cdsp_website_references WHERE source_host=?",'deleting a website cascades to source URLs');
$need('app/Services/WebsiteCatalog.php',"source_host=?",'source-scoped reference search exists');
$need('app/Services/WebsiteCatalog.php','beginTransaction()','website source removal uses a transaction');
$need('app/Controllers/AdminSettingsController.php','websiteSourceDetail','source detail controller exists');
$need('app/Controllers/AdminSettingsController.php',"Website source deleted with ",'delete result reports related URLs');
$need('app/Views/admin/settings.php','View scanned URLs / Manage products','website list links to scoped products');
$need('app/Views/admin/settings.php','Delete Website','website list includes delete action');
$need('app/Views/admin/website-source.php','Search scanned URLs','source detail has top search');
$need('app/Views/admin/website-source.php','Add URL manually','source detail supports manual URL add');
$need('app/Views/admin/website-source.php','website-reference-delete','source detail supports per-record delete');
$need('public/assets/app.js',"data('source-host')",'AJAX search carries source host');
$need('public/assets/app.js','Confirm Delete ','website delete requires second click');
$failed=array_filter($checks,static fn($c)=>!$c[0]);
foreach($checks as [$ok,$label]){echo ($ok?'PASS':'FAIL')." - {$label}\n";}
if($failed){exit(1);} echo "ALL PASS\n";
