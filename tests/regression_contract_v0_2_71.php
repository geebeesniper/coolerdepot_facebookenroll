<?php
/**
 * V0.2.71 legacy regression contract.
 * Forward-compatible: the feature contract remains valid on later releases.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root=dirname(__DIR__);
$read=static fn(string $f):string=>(string)file_get_contents($root.'/'.$f);
$header=$read('app/Views/layout/header.php');
$css=$read('public/assets/app.css');
$post=$read('app/Models/Post.php');
$dash=$read('app/Views/admin/dashboard.php');
$js=$read('public/assets/app.js');
$access=$read('app/Views/auth/access_required.php');
$index=$read('index.php');
$version=trim($read('VERSION'));
$checks=[];
$checks['version']=version_compare($version,'0.2.71','>=');
$checks['menu_path_parser']=str_contains($header, "\$_SERVER['REQUEST_URI']") && str_contains($header, '$navActive');
$checks['menu_active_markup']=str_contains($header, "? ' active' : ''") && str_contains($header, 'aria-current="page"');
$checks['menu_active_css']=str_contains($css, '.app-nav-link.active') && str_contains($css, 'aria-current="page"');
$checks['external_id_global']=str_contains($post, 'WHERE platform=? AND external_post_id=?') && !str_contains($post, 'WHERE platform=? AND external_post_id=? AND sales_user_id=?') && str_contains($post, "'kind']='external_id'");
$checks['url_scoped']=str_contains($post, 'WHERE sales_user_id=? AND platform=? AND canonical_url_hash=?') && str_contains($post, "'kind']='url'");
$checks['title_scope_preserved']=str_contains($post, 'WHERE sales_user_id=? AND platform=? AND BINARY title=BINARY ?');
$checks['description_not_duplicate']=!str_contains($js, "description:'Description duplicate'") && !str_contains($post, "'kind']='description'");
$checks['one_day_review_server']=str_contains($dash, "sales-daily-review<?= \$adminPreset === 'single'");
$checks['one_day_review_client']=str_contains($js, "if(currentPreset==='single')") && str_contains($js, '$dailyReview.removeClass');
$checks['three_days_preserved']=str_contains($dash, "'day'=>'3 Days'") && str_contains($js, "day:tr('threeDays')");
$checks['auth_route']=str_contains($index, "'/auth/recheck'") && str_contains($index, "AuthController::class, 'recheck'");
$checks['auth_recheck_client']=str_contains($access, 'fetch(recheckUrl') && str_contains($access, 'window.location.reload()');
$checks['auth_fallback_client']=str_contains($access, 'window.location.replace(target)') && str_contains($access, 'data-auth-fallback-url');
$failed=array_keys(array_filter($checks,static fn($ok)=>!$ok));
foreach($checks as $label=>$ok){echo ($ok?'[PASS] ':'[FAIL] ').$label.PHP_EOL;}
if($failed){fwrite(STDERR,'V0.2.71 legacy contract failed: '.implode(', ',$failed).PHP_EOL);exit(1);}
echo count($checks).' V0.2.71 legacy contract checks passed.'.PHP_EOL;
