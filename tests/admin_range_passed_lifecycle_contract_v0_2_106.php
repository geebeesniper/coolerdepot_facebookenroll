<?php
/** V0.2.106 compatibility contract, updated for V0.2.110 queue-filter semantics. */
declare(strict_types=1);
$root=dirname(__DIR__);
$fail=[];
$check=function(bool $ok,string $label)use(&$fail){
    if($ok){echo "[PASS] {$label}\n";}else{echo "[FAIL] {$label}\n";$fail[]=$label;}
};
$version=trim((string)@file_get_contents($root.'/VERSION'));
$responsive=(string)@file_get_contents($root.'/public/assets/responsive.css');
$js=(string)@file_get_contents($root.'/public/assets/app.js');
$model=(string)@file_get_contents($root.'/app/Models/VerificationQueue.php');

$check(version_compare($version,'0.2.106','>='),'VERSION is >= 0.2.106');
$check(str_contains($responsive,'V0.2.106 — restore the approved Admin desktop date/filter geometry'),'final responsive layer owns Admin desktop range geometry');
$check(str_contains($responsive,'.admin-activity-head .admin-dashboard-range-bar .admin-toolbar-row-filter')
    && str_contains($responsive,'grid-template-columns:max-content minmax(24px,1fr) max-content !important;'),
    'Admin period stays left and From/To stay at far right');
$check(str_contains($responsive,'.admin-activity-head .admin-dashboard-range-bar .admin-portal-head-actions')
    && str_contains($responsive,'display:block !important;'),
    'Admin range row spans full width in the stylesheet loaded last');
$check(str_contains($model,'$counts[\'all\']+=$count;')
    && str_contains($model,'$counts[\'error\']=$counts[\'failed\']+$counts[\'duplicate\']+$counts[\'invalid\'];'),
    'V0.2.110 All means every current queue row and Error combines all action failures');
$check(str_contains($model,"['waiting','verifying','passed','failed','duplicate','invalid']"),
    'Passed rows remain queryable until Sales acknowledges the saved Post');
$check(str_contains($js,'function vqRefreshFormalPostsAfterPass()')
    && str_contains($js,"'verification-passed'"),
    'Passed transition refreshes the formal Posts grid through existing AJAX loader');
$check(str_contains($js,'const passedAdvanced=previousPassed!==undefined&&currentPassed>Number(previousPassed||0);'),
    'AJAX polling detects a new Passed transition without page reload');
$check(str_contains($js,'Passed items are already saved in Posts; click a Passed card to clear it and show the saved Post.'),
    'Queue help explains the Passed acknowledgement lifecycle');

if($fail){
    fwrite(STDERR,"V0.2.106 compatibility contract failed: ".implode('; ',$fail)."\n");
    exit(1);
}
echo "V0.2.106 Admin range + Passed lifecycle compatibility contract passed.\n";
