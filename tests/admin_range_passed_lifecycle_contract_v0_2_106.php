<?php
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
$check(str_contains($model,'$where.=" AND status<>\'passed\'";'),
    'default Verification Queue excludes already-promoted Passed rows');
$check(str_contains($model,"if(\$status!=='passed')\$counts['all']+=\$count;"),
    'All counter represents active/actionable queue only');
$check(str_contains($model,"['waiting','verifying','passed','failed','duplicate','invalid']"),
    'Passed filter remains available as history');
$check(str_contains($js,'function vqRefreshFormalPostsAfterPass()')
    && str_contains($js,"'verification-passed'"),
    'Passed transition refreshes the formal Posts grid through existing AJAX loader');
$check(str_contains($js,'const passedAdvanced=previousPassed!==undefined&&currentPassed>Number(previousPassed||0);'),
    'AJAX polling detects a new Passed transition without page reload');
$check(str_contains($js,'if(filter===\'all\'&&Number(counts.all||0)===0)vqSetCollapsed($panel,true,false);'),
    'Queue compacts after the last active item is promoted');
$check(str_contains($js,'Passed items move into Posts automatically; use Passed for history.'),
    'Queue help explains Passed lifecycle');

if($fail){
    fwrite(STDERR,"V0.2.106 contract failed: ".implode('; ',$fail)."\n");
    exit(1);
}
echo "V0.2.106 Admin range + Passed lifecycle contract passed.\n";
