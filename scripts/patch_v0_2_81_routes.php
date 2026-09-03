<?php
/** v0.2.81 route patch: add the missing exact-run scan resume endpoint. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);
$file=$root.'/index.php';
$code=(string)file_get_contents($file);
$route='$router->post(\'/admin/website/products/scan-resume\', [AdminSettingsController::class, \'resumeWebsiteProductScan\']);';
if(str_contains($code,$route)){
    echo "scan-resume route already present.\n";
    exit(0);
}
$marker='$router->post(\'/admin/website/products/scan-stop\', [AdminSettingsController::class, \'stopWebsiteProductScan\']);';
if(!str_contains($code,$marker)){
    fwrite(STDERR,"Could not locate scan-stop route in index.php.\n");
    exit(1);
}
$code=str_replace($marker,$marker."\n".$route,$code,$count);
if($count!==1){
    fwrite(STDERR,"Unexpected scan-stop route count in index.php.\n");
    exit(1);
}
if(file_put_contents($file,$code)===false){
    fwrite(STDERR,"Could not update index.php.\n");
    exit(1);
}
echo "scan-resume route installed.\n";
