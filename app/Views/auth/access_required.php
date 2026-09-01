/**
 * File / 文件：app/Views/auth/access_required.php
 * EN: Server-rendered view for this screen or partial.
 * 中文：该文件负责此页面或局部组件的服务端渲染。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
<div class="auth"><div class="panel auth-card"><div class="eyebrow"><?= \App\Core\Util::e($companyName ?? 'CoolerDepot') ?></div><h1>Open from <?= \App\Core\Util::e($companyName ?? 'CoolerDepot') ?> Portal</h1><p>This module receives the current user from the <?= \App\Core\Util::e($companyName ?? 'CoolerDepot') ?> Admin/Sales portal. Direct password login is disabled.</p><p>Admin is routed to Admin review; Sales is routed to the Sales dashboard.</p></div></div>