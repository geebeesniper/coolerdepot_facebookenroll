<?php
use App\Core\Auth;use App\Core\Csrf;use App\Core\Util;
$u=Auth::user();$base=$config['app']['base_path'];
$ok=$_SESSION['flash_success']??null;$bad=$_SESSION['flash_error']??null;
unset($_SESSION['flash_success'],$_SESSION['flash_error']);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=Util::e($config['app']['name'])?></title>
<link rel="stylesheet" href="<?=Util::e($base)?>/public/assets/app.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script></head><body>
<header class="topbar"><a class="brand" href="<?=Util::e($base)?>/">CoolerDepot <span>Sales Posts</span></a>
<?php if($u):?><div class="nav"><b><?=Util::e($u['display_name']?:$u['username'])?></b>
<?php if($u['role']==='sales'):?><a href="<?=$base?>/sales">Dashboard</a><a href="<?=$base?>/sales/submit">Submit</a>
<?php else:?><a href="<?=$base?>/admin">Admin</a><a href="<?=$base?>/admin/reports">Reports</a><?php endif;?>
<form method="post" action="<?=$base?>/logout"><input type="hidden" name="_csrf" value="<?=Util::e(Csrf::token())?>"><button>Sign out</button></form></div><?php endif;?></header>
<main class="container"><?php if($ok):?><div class="notice ok"><?=Util::e($ok)?></div><?php endif;?><?php if($bad):?><div class="notice bad"><?=Util::e($bad)?></div><?php endif;?>
