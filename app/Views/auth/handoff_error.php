<?php
/**
 * File / 文件：app/Views/auth/handoff_error.php
 * EN: Renders the auth/handoff_error application view template.
 * 中文：渲染应用视图模板 auth/handoff_error。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
 use App\Core\Util;?><div class="auth"><div class="panel auth-card"><div class="eyebrow">Authentication</div><h1>Access handoff failed</h1><div class="notice bad"><?=Util::e($message??'Authentication failed.')?></div><p>Return to the <?= \App\Core\Util::e($companyName ?? 'CoolerDepot') ?> portal and open Sales Post Tracker again.</p></div></div>