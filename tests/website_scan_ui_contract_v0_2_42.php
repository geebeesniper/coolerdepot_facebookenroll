<?php
$root=dirname(__DIR__);
$js=file_get_contents($root.'/public/assets/app.js');
$css=file_get_contents($root.'/public/assets/app.css');
$job=file_get_contents($root.'/app/Services/WebsiteScanJob.php');
$catalog=file_get_contents($root.'/app/Services/WebsiteCatalog.php');
$view=file_get_contents($root.'/app/Views/admin/settings.php');
$checks=[
    'animated accordion'=>str_contains($js,'slideDown(200')&&str_contains($js,'slideUp(180'),
    'clean symmetric chevron'=>str_contains($css,'.website-tool-arrow::after')&&str_contains($css,'rotate(-40deg)'),
    'live checked counter'=>str_contains($view,'data-source-stat="checked"')&&str_contains($js,"data-source-stat=\"checked\""),
    'live history'=>str_contains($view,'data-website-history-id')&&str_contains($js,'data-history-processed'),
    'repeat scan existing URL skip'=>str_contains($job,'skipped_existing')&&str_contains($catalog,'referenceUrlExists'),
    'skip count exposed'=>str_contains($js,'existing URLs skipped'),
];
$failed=[];foreach($checks as $name=>$ok){if(!$ok)$failed[]=$name;}
if($failed){fwrite(STDERR,'FAILED: '.implode(', ',$failed)."\n");exit(1);} 
echo "V0.2.42 contract checks passed: ".count($checks)."\n";
