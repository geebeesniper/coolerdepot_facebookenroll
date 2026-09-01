<?php
/**
 * File / 文件：app/Views/auth/login.php
 * EN: Server-rendered view for this screen or partial.
 * 中文：该文件负责此页面或局部组件的服务端渲染。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
 use App\Core\Csrf;use App\Core\Util;?>
<div class="auth"><div class="panel auth-card"><div class="eyebrow"><?= \App\Core\Util::e($companyName ?? 'CoolerDepot') ?></div><h1>Sales Post Tracker</h1><p>Sign in with your Sales ID or admin account.</p>
<?php if($error):?><div class="notice bad"><?=Util::e($error)?></div><?php endif;?>
<form method="post" action="<?=$config['app']['base_path']?>/login"><input type="hidden" name="_csrf" value="<?=Util::e(Csrf::token())?>">
<label>Username / Sales ID</label><input name="username" required autocomplete="username"><label>Password</label><input type="password" name="password" required autocomplete="current-password">
<button class="btn primary full">Sign in</button></form></div></div>
