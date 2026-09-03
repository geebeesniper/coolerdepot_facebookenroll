<?php
$root=dirname(__DIR__);
$checks=[
    'VERSION is 0.2.36'=>trim((string)file_get_contents($root.'/VERSION'))==='0.2.36',
    'three horizontal tool cards exist'=>substr_count((string)file_get_contents($root.'/app/Views/admin/settings.php'),'data-website-tool-toggle=')===3,
    'tool panels 1 2 3 exist'=>str_contains((string)file_get_contents($root.'/app/Views/admin/settings.php'),'website-tool-panel-1')
        && str_contains((string)file_get_contents($root.'/app/Views/admin/settings.php'),'website-tool-panel-2')
        && str_contains((string)file_get_contents($root.'/app/Views/admin/settings.php'),'website-tool-panel-3'),
    'saved websites are inside step 1'=>str_contains((string)file_get_contents($root.'/app/Views/admin/settings.php'),'<strong>Saved Websites</strong>'),
    'scan history shown'=>str_contains((string)file_get_contents($root.'/app/Views/admin/settings.php'),'<strong>Product Scan History</strong>'),
    'csv history shown'=>str_contains((string)file_get_contents($root.'/app/Views/admin/settings.php'),'<strong>CSV Import History</strong>'),
    'advanced history shown'=>str_contains((string)file_get_contents($root.'/app/Views/admin/settings.php'),'<strong>Scan &amp; Import History</strong>'),
    'old separate secondary layout removed'=>!str_contains((string)file_get_contents($root.'/app/Views/admin/settings.php'),'website-secondary-tools'),
    'history service exists'=>is_file($root.'/app/Services/WebsiteActivityHistory.php'),
    'history database table exists in service'=>str_contains((string)file_get_contents($root.'/app/Services/WebsiteActivityHistory.php'),'cdsp_website_activity_history'),
    'product scan history sync exists'=>str_contains((string)file_get_contents($root.'/app/Services/WebsiteScanJob.php'),'history_id')
        && str_contains((string)file_get_contents($root.'/app/Services/WebsiteScanJob.php'),'syncHistory'),
    'controller loads scan/csv/advanced history'=>str_contains((string)file_get_contents($root.'/app/Controllers/AdminSettingsController.php'),"recent(['product_scan']")
        && str_contains((string)file_get_contents($root.'/app/Controllers/AdminSettingsController.php'),"recent(['csv_import']")
        && str_contains((string)file_get_contents($root.'/app/Controllers/AdminSettingsController.php'),"recent(['advanced_import']"),
    'three distinct detail colors'=>str_contains((string)file_get_contents($root.'/public/assets/app.css'),'.website-tool-detail-one{border-color:#2563eb}')
        && str_contains((string)file_get_contents($root.'/public/assets/app.css'),'.website-tool-detail-two{border-color:#15803d}')
        && str_contains((string)file_get_contents($root.'/public/assets/app.css'),'.website-tool-detail-three{border-color:#c2410c}'),
    'top cards use 3-column grid'=>str_contains((string)file_get_contents($root.'/public/assets/app.css'),'.website-tools-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr))'),
    'accordion remembers open panel'=>str_contains((string)file_get_contents($root.'/public/assets/app.js'),'cdspWebsiteToolPanel'),
    'ssh migration script exists'=>is_file($root.'/scripts/migrate_v0_2_36.php'),
];
$failed=[];
foreach($checks as $name=>$ok){if(!$ok){$failed[]=$name;}}
if($failed){fwrite(STDERR,"FAILED:\n - ".implode("\n - ",$failed)."\n");exit(1);}
echo 'V0.2.36 website tools/history contract: '.count($checks)." checks passed.\n";
