<?php
/** V0.2.109 contract: Passed is one-click acknowledgement; redundant History/Open duplicate controls are removed. */
declare(strict_types=1);
$root=dirname(__DIR__);
$fails=[];
$check=static function(bool $ok,string $label)use(&$fails):void{
    printf('[%s] %s\n',$ok?'PASS':'FAIL',$label);
    if(!$ok)$fails[]=$label;
};
$version=trim((string)@file_get_contents($root.'/VERSION'));
$js=(string)@file_get_contents($root.'/public/assets/app.js');
$css=(string)@file_get_contents($root.'/public/assets/app.css');
$model=(string)@file_get_contents($root.'/app/Models/VerificationQueue.php');

$check(version_compare($version,'0.2.109','>='),'VERSION is >= 0.2.109');
$check(str_contains($model,"['waiting','passed','failed','duplicate','invalid']"),'Passed queue row may be cleared without touching the formal Post');
$check(str_contains($js,'function vqAcknowledgePassed($row)')&&str_contains($js,"'/api/verification-queue/delete'"),'Passed card click persists acknowledgement through existing AJAX delete endpoint');
$check(str_contains($js,"if(String(item.status||'').toLowerCase()==='passed'){vqAcknowledgePassed($(this));return;}"),'Passed card click acknowledges instead of opening queue details');
$check(str_contains($js,'function vqRevealPassedPost(item,$panel)')&&str_contains($js,"addClass('sales-verification-passed-post')")&&str_contains($js,'scrollIntoView'),'acknowledged Passed item reveals and marks its formal Post');
$check(!str_contains($js,"data-vq-action','view_post'")&&!str_contains($js,"data-vq-action','history'"),'Passed/failed queue cards no longer render View Post or History buttons');
$check(!str_contains($js,'Open duplicate ↗'),'Duplicate card no longer renders redundant Open duplicate button');
$check(str_contains($js,"if(status==='passed'){\n            \$url.text"),'Passed URL is plain card content so whole-card acknowledgement is not stolen by a link');

$marker='/* v0.2.109 — Verification Queue acknowledgement/highlight only.';
$pos=strpos($css,$marker);
$v109=$pos===false?'':substr($css,$pos);
$check($v109!==''&&str_contains($v109,'.sales-verification-queue .sales-vq-row.status-passed{')&&str_contains($v109,'border-top-color:#22c55e;'),'Passed queue card is marked with component-scoped green border');
$check(str_contains($v109,'#salesPortalDashboard .sales-self-post-card.sales-verification-passed-post{')&&str_contains($v109,'outline:2px solid #22c55e;'),'formal saved Post gets a Sales-Dashboard-scoped green marker');
$check(!str_contains($v109,'.admin-')&&!str_contains($v109,'.sales-submit-')&&!str_contains($v109,'.sales-range-')&&!preg_match('/(^|\n)\s*\.btn\b/',$v109),'V0.2.109 CSS does not touch Admin, Submit, range, or generic button layout');

if($fails){fwrite(STDERR,"V0.2.109 acknowledgement cleanup contract failed: ".implode('; ',$fails)."\n");exit(1);}
echo "V0.2.109 acknowledgement cleanup contract passed.\n";
