<?php
/** V0.2.112 contract: the entire Verification Queue header toggles open/closed without hijacking header controls. */
declare(strict_types=1);
$root=dirname(__DIR__);
$fails=[];
$check=static function(bool $ok,string $label)use(&$fails):void{
    printf('[%s] %s\n',$ok?'PASS':'FAIL',$label);
    if(!$ok)$fails[]=$label;
};
$version=trim((string)@file_get_contents($root.'/VERSION'));
$js=(string)@file_get_contents($root.'/public/assets/app.js');

$check(version_compare($version,'0.2.112','>='),'VERSION is >= 0.2.112');
$needle="$(document).on('click','[data-verification-queue-panel] .sales-verification-queue-head',function(event){";
$start=strpos($js,$needle);
$end=$start===false?false:strpos($js,"$(document).on('keydown','[data-verification-queue-panel] [data-vq-title-toggle]'",$start);
$block=($start===false||$end===false)?'':substr($js,$start,$end-$start);
$check($block!=='','Verification Queue whole header has a click handler');
$check($block!==''&&str_contains($block,"closest('button,a,input,textarea,select,label')"),'Header click excludes Refresh/arrow/interactive controls');
$check($block!==''&&str_contains($block,'vqSetCollapsed($panel,!$panel.hasClass(\'is-collapsed\'),true);'),'Header click toggles the existing collapsed state');
$check(str_contains($js,"$(document).on('click','[data-verification-queue-panel] [data-vq-collapse-toggle]'"),'Existing arrow toggle remains independently wired');
$check(str_contains($js,"$(document).on('click','[data-verification-queue-panel] [data-verification-queue-refresh]'"),'Existing Refresh remains independently wired');

if($fails){fwrite(STDERR,"V0.2.112 queue-header-click contract failed: ".implode('; ',$fails)."\n");exit(1);}
echo "V0.2.112 queue-header-click contract passed.\n";
