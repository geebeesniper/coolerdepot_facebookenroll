<?php
/**
 * v0.2.34 persistent website product scan + inline grid contract.
 * v0.2.34 持久化官网产品扫描 + 内联产品网格契约检查。
 */
$root=dirname(__DIR__);
$checks=[];
$need=function(string $file,string $needle,string $label)use(&$checks,$root):void{
    $text=file_get_contents($root.'/'.$file);
    $checks[]=[str_contains((string)$text,$needle),$label];
};
$checks[]=[trim((string)file_get_contents($root.'/VERSION'))==='0.2.34','VERSION is 0.2.34'];
$need('app/Services/WebsiteScanJob.php','cdsp_website_scan_jobs','scan queue persists in database');
$need('app/Services/WebsiteScanJob.php','GET_LOCK(?,0)','one source batch is concurrency guarded');
$need('app/Controllers/AdminSettingsController.php','startWebsiteProductScan','browser can start persistent scan');
$need('app/Controllers/AdminSettingsController.php','websiteProductScanStatus','refresh can recover scan status');
$need('index.php',"/admin/website/products/scan-status",'scan status route exists');
$need('app/Views/admin/settings.php','website-source-expand-arrow','website source has inline expand arrow');
$need('public/assets/app.css','.website-source-list{grid-template-columns:repeat(3','website sources render three per row');
$need('public/assets/app.css','.website-source-inline-panel{grid-column:1/-1','expanded website panel spans its grid row');
$need('public/assets/app.js','Refresh-safe resume','scan auto-resumes after refresh');
$need('public/assets/app.js',"showToast('Scan complete: ",'scan completion uses in-app notification');
$need('public/assets/app.js','first images found','scan distinguishes found image URLs from exact fingerprints');
$need('app/Services/WebsiteCatalog.php','firstDomProductImage','lazy/gallery product image extraction exists');
$need('app/Services/ImageFingerprint.php',"'Referer: '.\$referer",'website fingerprint fetch can send product-page referer');
$failed=array_filter($checks,static fn($c)=>!$c[0]);
foreach($checks as [$ok,$label]){echo ($ok?'PASS':'FAIL')." - {$label}\n";}
if($failed){exit(1);} echo "ALL PASS\n";
