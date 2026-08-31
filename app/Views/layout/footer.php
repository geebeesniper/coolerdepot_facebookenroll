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
