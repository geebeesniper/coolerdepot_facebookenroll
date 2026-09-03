<?php
/** V0.2.78 Application Settings layout regression contract. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root=dirname(__DIR__);
$fail=[];
$check=static function(bool $ok,string $name)use(&$fail):void{
    echo ($ok?'[PASS] ':'[FAIL] ').$name.PHP_EOL;
    if(!$ok){$fail[]=$name;}
};
$read=static fn(string $f):string=>(string)file_get_contents($root.'/'.$f);
$version=trim($read('VERSION'));
$view=$read('app/Views/admin/settings.php');
$css=$read('public/assets/app.css');

$check(version_compare($version,'0.2.78','>='),'version >= 0.2.78');
$check(str_contains($view,'class="application-setting-control-row"'),'settings markup uses control row');
$check(substr_count($view,'class="btn primary application-setting-save"')>=2,'both settings use compact save button');
$check(str_contains($css,'v0.2.78 — Application Settings compact control rows'),'v0.2.78 settings CSS present');
$check(str_contains($css,'#application-settings .application-setting-form{'),'correct singular form selector');
$check(str_contains($css,'#application-settings .application-setting-control-row{'),'control row selector present');
$check(str_contains($css,'grid-template-columns:minmax(0,1fr) auto;'),'desktop input and button stay on one row');
$check(str_contains($css,'@media(max-width:560px)'),'mobile stacking breakpoint present');
$check(str_contains($css,'#application-settings .application-setting-save{'),'save button sizing rule present');

if($fail){fwrite(STDERR,'V0.2.78 settings layout contract failed: '.implode(', ',$fail).PHP_EOL);exit(1);}
echo 'V0.2.78 Application Settings layout contract passed.'.PHP_EOL;
