<?php
/**
 * File / 文件：tests/calendar_admin_ui_contract_v0_2_24.php
 * EN: Static regression contract for the v0.2.24 Sales status calendar and compact Admin Sales-card behavior.
 * 中文：V0.2.24 Sales 状态日历与紧凑 Admin Sales 卡片行为的静态回归契约。
 */

$root=dirname(__DIR__);
$checks=[
    'sales calendar markup'=>[
        $root.'/app/Views/sales/dashboard.php',
        ['salesPostCalendar','salesPostCalendarGrid','salesPostCalendarToggle'],
    ],
    'sales calendar controller'=>[
        $root.'/public/assets/sales-dashboard.js',
        ['aggregateCalendarRows','loadPostCalendarMonth','data-calendar-date','calendarStatus'],
    ],
    'admin new-post badge'=>[
        $root.'/app/Views/admin/dashboard.php',
        ['data-new-posts-badge','data-new-posts-count'],
    ],
    'admin row-local expansion'=>[
        $root.'/public/assets/app.js',
        ['placeExpandedAfterCardRow','data-new-posts-badge','newPostBadge'],
    ],
    'compact card and calendar styles'=>[
        $root.'/public/assets/app.css',
        ['sales-progress-badges','sales-new-posts-badge','sales-post-calendar-day','grid-column:1 / -1'],
    ],
];

$failed=[];
foreach($checks as $label=>[$file,$needles]){
    $content=@file_get_contents($file);
    if($content===false){
        $failed[]=$label.': missing file '.$file;
        continue;
    }
    foreach($needles as $needle){
        if(strpos($content,$needle)===false){
            $failed[]=$label.': missing '.$needle;
        }
    }
}

if($failed){
    fwrite(STDERR,"FAIL\n".implode("\n",$failed)."\n");
    exit(1);
}

echo "PASS v0.2.24 Sales calendar/Admin card UI contract\n";
