<?php
$root=dirname(__DIR__);
$files=[
    'controller'=>file_get_contents($root.'/app/Controllers/AdminSettingsController.php'),
    'model'=>file_get_contents($root.'/app/Models/Location.php'),
    'view'=>file_get_contents($root.'/app/Views/admin/settings.php'),
    'index'=>file_get_contents($root.'/index.php'),
    'js'=>file_get_contents($root.'/public/assets/app.js'),
    'css'=>file_get_contents($root.'/public/assets/app.css'),
];
$checks=[
    'update route'=>str_contains($files['index'],'/admin/settings/location/update'),
    'update controller'=>str_contains($files['controller'],'public function updateLocation(): void'),
    'ajax mode'=>str_contains($files['controller'],"'ajax'] ?? ''"),
    'model rename'=>str_contains($files['model'],'public static function rename(int $id, string $name): ?array'),
    'rename preserves id'=>str_contains($files['model'],'WHERE id=? AND active=1'),
    'section endpoints'=>str_contains($files['view'],'data-location-update-url='),
    'add ajax hook'=>str_contains($files['view'],'js-location-add-form'),
    'edit hook'=>str_contains($files['view'],'data-location-edit'),
    'edit form'=>str_contains($files['view'],'js-location-edit-form'),
    'delete ajax hook'=>str_contains($files['view'],'js-location-delete-form'),
    'prevent default add'=>str_contains($files['js'],'.js-location-add-form') && str_contains($files['js'],'event.preventDefault();'),
    'animated add'=>str_contains($files['js'],'location-card-enter'),
    'animated update'=>str_contains($files['js'],'location-card-updated'),
    'animated delete'=>str_contains($files['js'],'location-card-leave'),
    'inline edit slide'=>str_contains($files['js'],'.slideDown(160'),
    'location updated translation'=>str_contains($files['js'],"locationUpdated:'Location updated.'"),
    'edit styling'=>str_contains($files['css'],'.sales-location-edit-form{'),
    'no location anchor jump'=>!str_contains($files['js'],"window.location='#sales-locations'"),
];
$failed=[];
foreach($checks as $name=>$ok){if(!$ok)$failed[]=$name;}
if($failed){fwrite(STDERR,'V0.2.49 contract failed: '.implode(', ',$failed).PHP_EOL);return 1;}
echo 'V0.2.49 contract checks passed: '.count($checks).PHP_EOL;
return 0;
