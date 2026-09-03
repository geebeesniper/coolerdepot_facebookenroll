<?php
$root=dirname(__DIR__);
$checks=[
    'version'=>trim((string)file_get_contents($root.'/VERSION'))==='0.2.35',
    'resume route'=>str_contains((string)file_get_contents($root.'/index.php'),'/admin/website/products/scan-resume'),
    'resume controller'=>str_contains((string)file_get_contents($root.'/app/Controllers/AdminSettingsController.php'),'resumeWebsiteProductScan'),
    'delete blocked while running'=>str_contains((string)file_get_contents($root.'/app/Controllers/AdminSettingsController.php'),'Stop the active website scan before deleting this website.'),
    'persistent page errors table'=>str_contains((string)file_get_contents($root.'/app/Services/WebsiteScanJob.php'),'cdsp_website_scan_errors'),
    'resume keeps queue'=>str_contains((string)file_get_contents($root.'/app/Services/WebsiteScanJob.php'),"status='running',last_error=NULL"),
    'input clears after start'=>str_contains((string)file_get_contents($root.'/public/assets/app.js'),'if(selector){$(selector).val(\'\');}'),
    'continue button'=>str_contains((string)file_get_contents($root.'/app/Views/admin/settings.php'),'Continue Scanning'),
    'stop button'=>str_contains((string)file_get_contents($root.'/app/Views/admin/settings.php'),'Stop Scanning'),
    'scanner state label'=>str_contains((string)file_get_contents($root.'/public/assets/app.js'),'Website scanning'),
    'http interruption recovery'=>str_contains((string)file_get_contents($root.'/public/assets/app.js'),'Use Continue Scanning; completed pages will not be lost.'),
    'compact list header'=>str_contains((string)file_get_contents($root.'/public/assets/app.js'),'website-source-product-list-head'),
    'left image right title description'=>str_contains((string)file_get_contents($root.'/public/assets/app.js'),'website-source-product-body') && str_contains((string)file_get_contents($root.'/public/assets/app.js'),'No description'),
    'page error clickable url'=>str_contains((string)file_get_contents($root.'/public/assets/app.js'),'website-scan-error-row'),
];
$failed=[];foreach($checks as $name=>$ok){echo ($ok?'PASS':'FAIL')." - {$name}\n";if(!$ok)$failed[]=$name;}
if($failed){fwrite(STDERR,'Failed: '.implode(', ',$failed)."\n");exit(1);} echo "V0.2.35 contract OK\n";
