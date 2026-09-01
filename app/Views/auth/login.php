<?php
/**
 * File / 文件：app/Views/auth/login.php
 * EN: Renders the auth/login application view template.
 * 中文：渲染应用视图模板 auth/login。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
 use App\Core\Csrf;use App\Core\Util;?>
<div class="auth"><div class="panel auth-card"><div class="eyebrow"><?= \App\Core\Util::e($companyName ?? 'CoolerDepot') ?></div><h1>Sales Post Tracker</h1><p>Sign in with your Sales ID or admin account.</p>
<?php if($error):?><div class="notice bad"><?=Util::e($error)?></div><?php endif;?>
<form method="post" action="<?=$config['app']['base_path']?>/login"><input type="hidden" name="_csrf" value="<?=Util::e(Csrf::token())?>">
<label>Username / Sales ID</label><input name="username" required autocomplete="username"><label>Password</label><input type="password" name="password" required autocomplete="current-password">
<button class="btn primary full">Sign in</button></form></div></div>
