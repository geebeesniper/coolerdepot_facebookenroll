<?php use App\Core\Csrf;use App\Core\Util;?>
<div class="auth"><div class="panel auth-card"><div class="eyebrow">CoolerDepot</div><h1>Sales Post Tracker</h1><p>Sign in with your Sales ID or admin account.</p>
<?php if($error):?><div class="notice bad"><?=Util::e($error)?></div><?php endif;?>
<form method="post" action="<?=$config['app']['base_path']?>/login"><input type="hidden" name="_csrf" value="<?=Util::e(Csrf::token())?>">
<label>Username / Sales ID</label><input name="username" required autocomplete="username"><label>Password</label><input type="password" name="password" required autocomplete="current-password">
<button class="btn primary full">Sign in</button></form></div></div>
