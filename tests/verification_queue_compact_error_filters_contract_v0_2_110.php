<?php
/** V0.2.110 contract: compact queue cards, title collapse, true All count, and one classified Error bucket. */
declare(strict_types=1);
$root=dirname(__DIR__);
$fails=[];
$check=static function(bool $ok,string $label)use(&$fails):void{
    printf('[%s] %s\n',$ok?'PASS':'FAIL',$label);
    if(!$ok)$fails[]=$label;
};
$version=trim((string)@file_get_contents($root.'/VERSION'));
$model=(string)@file_get_contents($root.'/app/Models/VerificationQueue.php');
$controller=(string)@file_get_contents($root.'/app/Controllers/VerificationQueueController.php');
$view=(string)@file_get_contents($root.'/app/Views/sales/_verification_queue.php');
$js=(string)@file_get_contents($root.'/public/assets/app.js');
$css=(string)@file_get_contents($root.'/public/assets/app.css');

$check(version_compare($version,'0.2.110','>='),'VERSION is >= 0.2.110');
$check(str_contains($view,'data-vq-title-toggle')&&str_contains($js,"[data-vq-title-toggle]"),'Verification Queue title toggles collapse/expand');
$check(str_contains($view,'data-vq-filter="all"')&&str_contains($view,'data-vq-filter="waiting"')&&str_contains($view,'data-vq-filter="verifying"')&&str_contains($view,'data-vq-filter="passed"')&&str_contains($view,'data-vq-filter="error"'),'Queue exposes All / Waiting / Verifying / Passed / Errors filters');
$check(!str_contains($view,'data-vq-filter="failed"')&&!str_contains($view,'data-vq-filter="duplicate"')&&!str_contains($view,'data-vq-filter="invalid"')&&!str_contains($view,'data-vq-filter="needs_action"'),'overlapping Failed/Duplicate/Invalid/Needs Action filters are removed');
$check(str_contains($model,'$counts[\'all\']+=$count;'),'All counter includes every current queue status');
$check(str_contains($model,"\$counts['error']=\$counts['failed']+\$counts['duplicate']+\$counts['invalid'];")&&str_contains($model,"['error','errors','needs_action']"),'Error bucket combines failed + duplicate + invalid while preserving legacy API alias');
$check(str_contains($controller,"'error','errors','failed','duplicate','invalid','needs_action'"),'Queue API accepts the new Error filter while keeping old status URLs compatible');
$check(str_contains($js,"queueErrorDuplicateId:'Duplicated ID'")&&str_contains($js,"queueErrorDuplicateTitle:'Duplicated Title'")&&str_contains($js,"queueErrorDuplicatePhoto:'Duplicated Photo'")&&str_contains($js,"queueErrorTimeout:'System Timeout'"),'error cards classify duplicate ID/title/photo and timeout explicitly');
$check(str_contains($js,"kind==='external_id'||kind==='queue_external_id'")&&str_contains($js,"kind==='same_account_title'||kind==='exact_title'||kind==='website_exact_title'")&&str_contains($js,"kind==='same_account_image'||kind==='same_platform_image'||kind==='website_exact_image'||code==='DUPLICATE_IMAGE'"),'duplicate_kind is mapped into distinct user-visible error types');

$marker='/* v0.2.110 — Verification Queue compact cards only.';
$pos=strpos($css,$marker);
$v110=$pos===false?'':substr($css,$pos);
$check($v110!==''&&str_contains($v110,'grid-template-columns:repeat(auto-fill,minmax(190px,220px));')&&str_contains($v110,'min-height:112px;'),'Verification Queue cards are compact and capped around 220px wide');
$check($v110!==''&&!str_contains($v110,'.admin-')&&!str_contains($v110,'.sales-submit-')&&!str_contains($v110,'.sales-range-')&&!preg_match('/(^|\n)\s*\.btn\b/',$v110),'V0.2.110 CSS is isolated from Admin, Submit, date-range, and generic button layout');
$lines=preg_split('/\R/',$v110)?:[];
$selectorViolation=false;
foreach($lines as $line){
    $trim=trim($line);
    if($trim===''||str_starts_with($trim,'/*')||str_starts_with($trim,'*')||str_starts_with($trim,'@')||str_starts_with($trim,'}')||str_contains($trim,':')&&!str_ends_with($trim,','))continue;
    if((str_ends_with($trim,'{')||str_ends_with($trim,','))&&!str_starts_with($trim,'.sales-verification-queue')){$selectorViolation=true;break;}
}
$check(!$selectorViolation,'new V0.2.110 selectors stay under .sales-verification-queue namespace');

if($fails){fwrite(STDERR,"V0.2.110 compact/error-filter contract failed: ".implode('; ',$fails)."\n");exit(1);}
echo "V0.2.110 compact/error-filter contract passed.\n";
