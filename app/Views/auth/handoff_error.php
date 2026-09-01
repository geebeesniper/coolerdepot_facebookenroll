<?php
/**
 * File / 文件：app/Views/auth/handoff_error.php
 * EN: Server-rendered view for this screen or partial.
 * 中文：该文件负责此页面或局部组件的服务端渲染。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
 use App\Core\Util;?><div class="auth"><div class="panel auth-card"><div class="eyebrow">Authentication</div><h1>Access handoff failed</h1><div class="notice bad"><?=Util::e($message??'Authentication failed.')?></div><p>Return to the <?= \App\Core\Util::e($companyName ?? 'CoolerDepot') ?> portal and open Sales Post Tracker again.</p></div></div>