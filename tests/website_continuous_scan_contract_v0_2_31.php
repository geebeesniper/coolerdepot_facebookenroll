<?php
/**
 * V0.2.31 contract: Admin can enter a company website and start a one-click
 * continuous product crawl without first saving the source separately.
 * V0.2.31 契约：Admin 输入官网后可直接一键连续扫描产品，无需先单独保存来源。
 */
$root=dirname(__DIR__);
$failures=[];
$mustContain=function(string $file,array $needles)use($root,&$failures):void{
    $path=$root.'/'.$file;
    $body=is_file($path)?file_get_contents($path):false;
    if($body===false){$failures[]="Missing $file";return;}
    foreach($needles as $needle){if(!str_contains($body,$needle)){$failures[]="$file missing: $needle";}}
};
$mustContain('VERSION',['0.2.31']);
$mustContain('app/Views/admin/settings.php',[
    'id="companyWebsiteScanUrl"',
    'Save Website',
    'Scan Products',
    'data-website-input="#companyWebsiteScanUrl"',
    'website-product-scan-primary-progress'
]);
$mustContain('app/Controllers/AdminSettingsController.php',[
    'WebsiteCatalog::ensureSource($website,(int)$admin[\'id\'])',
    'scanProductBatch($website,$urls)'
]);
$mustContain('app/Services/WebsiteCatalog.php',[
    'public static function ensureSource',
    'private static function productScanSeeds',
    "'/products'",
    'extractProductMeta'
]);
$mustContain('public/assets/app.js',[
    'continuous company product scanner',
    "origin+'/products'",
    "origin+'/sitemap.xml'",
    'window.setTimeout(nextBatch,30)',
    'Scanning continuously',
    'maxPages=5000'
]);
if($failures){fwrite(STDERR,"FAIL\n - ".implode("\n - ",$failures)."\n");exit(1);} 
echo "PASS v0.2.31 continuous website scan contract\n";
