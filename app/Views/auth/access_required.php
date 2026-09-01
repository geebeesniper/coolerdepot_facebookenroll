<?php
/**
 * File / 文件：app/Views/auth/access_required.php
 * EN: Renders the auth/access_required application view template.
 * 中文：渲染应用视图模板 auth/access_required。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
?>
<div class="auth"><div class="panel auth-card"><div class="eyebrow"><?= \App\Core\Util::e($companyName ?? 'CoolerDepot') ?></div><h1>Open from <?= \App\Core\Util::e($companyName ?? 'CoolerDepot') ?> Portal</h1><p>This module receives the current user from the <?= \App\Core\Util::e($companyName ?? 'CoolerDepot') ?> Admin/Sales portal. Direct password login is disabled.</p><p>Admin is routed to Admin review; Sales is routed to the Sales dashboard.</p></div></div>