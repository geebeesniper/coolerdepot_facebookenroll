<?php
/** V0.2.107 compatibility contract, updated for V0.2.110 non-overlapping queue filters. */
declare(strict_types=1);
$root=dirname(__DIR__);
$fails=0;
$check=static function(bool $ok,string $label)use(&$fails):void{
    printf('[%s] %s\n',$ok?'PASS':'FAIL',$label);
    if(!$ok)$fails++;
};
$version=trim((string)@file_get_contents($root.'/VERSION'));
$view=(string)@file_get_contents($root.'/app/Views/sales/_verification_queue.php');
$css=(string)@file_get_contents($root.'/public/assets/app.css');
$check(version_compare($version,'0.2.107','>='),'VERSION is >= 0.2.107');
$headStart=strpos($view,'<div class="sales-verification-queue-head">');
$bodyStart=strpos($view,'<div class="sales-verification-queue-body"');
$head=($headStart!==false&&$bodyStart!==false&&$bodyStart>$headStart)?substr($view,$headStart,$bodyStart-$headStart):'';
$body=($bodyStart!==false)?substr($view,$bodyStart):'';
$check($head!==''&&!str_contains($head,'sales-verification-queue-summary'),'collapsed header has no fake filter chips');
$check(substr_count($head,'data-verification-queue-refresh')===1&&substr_count($head,'data-vq-collapse-toggle')===1,'collapsed header exposes only Refresh and collapse controls');
$check(str_contains($body,'data-vq-filter="all"')&&str_contains($body,'data-vq-filter="error"')
    && !str_contains($body,'data-vq-filter="needs_action"')&&!str_contains($body,'data-vq-filter="duplicate"')&&!str_contains($body,'data-vq-filter="invalid"'),
    'expanded Queue uses one Error filter instead of overlapping Failed/Duplicate/Invalid/Needs Action filters');
$check(str_contains($css,'.sales-verification-queue-head-actions > [data-verification-queue-refresh],')&&str_contains($css,'.sales-verification-queue-head-actions > [data-vq-collapse-toggle]{'),'queue header control sizing remains scoped to the two actual controls');
$check(str_contains($css,'height:30px !important;')&&str_contains($css,'min-height:30px !important;')&&str_contains($css,'max-height:30px !important;'),'Refresh and collapse remain the same 30px rendered height');
if($fails){fwrite(STDERR,"V0.2.107 queue header controls compatibility contract failed: {$fails} check(s).\n");exit(1);}
printf("V0.2.107 queue header controls compatibility contract passed.\n");
