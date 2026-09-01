/**
 * File / 文件：app/Views/layout/footer.php
 * EN: Server-rendered view for this screen or partial.
 * 中文：该文件负责此页面或局部组件的服务端渲染。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
</main>
<footer class="app-footer">
    <span><?= \App\Core\Util::e($companyName ?? 'CoolerDepot') ?> Sales Post Tracker</span>
    <span class="footer-version">v<?= \App\Core\Util::e($config['app']['version']) ?></span>
</footer>
<script>
window.CD_BASE_PATH = <?= json_encode($config['app']['base_path']) ?>;
window.CD_APP_VERSION = <?= json_encode($config['app']['version']) ?>;
</script>
<script src="<?= \App\Core\Util::e($config['app']['base_path']) ?>/public/assets/app.js?v=<?= rawurlencode($config['app']['version']) ?>"></script>
<script src="<?= \App\Core\Util::e($config['app']['base_path']) ?>/public/assets/sales-dashboard.js?v=<?= rawurlencode($config['app']['version']) ?>"></script>
</body>
</html>
