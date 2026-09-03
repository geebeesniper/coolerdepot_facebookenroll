<?php
$root=dirname(__DIR__);
$view=file_get_contents($root.'/app/Views/admin/settings.php');
$controller=file_get_contents($root.'/app/Controllers/AdminSettingsController.php');
$service=file_get_contents($root.'/app/Services/WebsiteCatalog.php');
$css=file_get_contents($root.'/public/assets/app.css');
$checks=[];
$checks['csv source selector removed']=!str_contains($view,'websiteCsvSource');
$checks['sitemap source selector removed']=!str_contains($view,'websiteSitemapSource');
$checks['csv field remains']=str_contains($view,'id="websiteCsvFile"');
$checks['sitemap url remains']=str_contains($view,'id="websiteSitemapUrl"');
$checks['csv import enabled without preselected website']=str_contains($view,'<button class="btn" type="submit">Import CSV</button>');
$checks['sitemap import enabled without preselected website']=str_contains($view,'<button class="btn" type="submit">Scan &amp; Import</button>');
$checks['csv auto source controller']=str_contains($controller,'WebsiteCatalog::inferCsvSource');
$checks['sitemap auto source controller']=str_contains($controller,'WebsiteCatalog::ensureSourceForUrl');
$checks['service can infer URL source']=str_contains($service,'public static function ensureSourceForUrl');
$checks['service can infer CSV source']=str_contains($service,'public static function inferCsvSource');
$checks['csv rows still locked to one host']=str_contains($service,'URL is outside the configured website.');
$checks['tablet keeps three cards']=str_contains($css,'@media(min-width:600px) and (max-width:980px)')
    &&str_contains($css,'.website-tools-grid{')
    &&str_contains($css,'grid-template-columns:repeat(3,minmax(0,1fr));');
$checks['phone stacks cards']=str_contains($css,'@media(max-width:680px)')&&str_contains($css,'grid-template-columns:1fr;');
$checks['csv source-free grid']=str_contains($css,'.website-tool-form-csv{')&&str_contains($css,'grid-template-columns:minmax(0,1fr) auto;');
$checks['sitemap source-free grid']=str_contains($css,'.website-tool-form-sitemap{')&&str_contains($css,'grid-template-columns:minmax(0,1fr) 160px;');
$checks['buttons vertically centered']=str_contains($css,'align-items:center!important;')&&str_contains($css,'line-height:1!important;');
$failed=array_keys(array_filter($checks,fn($ok)=>!$ok));
if($failed){fwrite(STDERR,"V0.2.45 contract failures:\n - ".implode("\n - ",$failed)."\n");exit(1);} 
echo 'V0.2.45 contract checks passed: '.count($checks).PHP_EOL;
