<?php
/**
 * File / 文件：app/Views/layout/footer.php
 * EN: Renders the layout/footer application view template.
 * 中文：渲染应用视图模板 layout/footer。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
?>
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
<script>
/* v0.2.133 — reveal only after all previously registered app language-ready handlers have run. */
(function(){
    function revealTranslatedPage(){
        var reveal=function(){document.documentElement.removeAttribute('data-cdsp-language-pending');};
        if(window.requestAnimationFrame){window.requestAnimationFrame(reveal);}else{window.setTimeout(reveal,0);}
    }
    if(window.jQuery){
        window.jQuery(function(){revealTranslatedPage();});
    }else if(document.readyState==='loading'){
        document.addEventListener('DOMContentLoaded',revealTranslatedPage,{once:true});
    }else{
        revealTranslatedPage();
    }
})();
</script>
</body>
</html>
