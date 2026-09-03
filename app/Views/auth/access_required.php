<?php
/**
 * File / 文件：app/Views/auth/access_required.php
 * EN: Re-check the external-auth browser session once before returning to the configured Portal.
 * 中文：外部认证不可用时先重新检查一次浏览器 Session，失败后返回管理员配置的 Portal。
 */
$authBase = $config['app']['base_path'];
$authFallback = trim((string)($authFailureRedirectUrl ?? ''));
?>
<div
    class="auth"
    id="authAccessRequired"
    data-auth-recheck-url="<?= \App\Core\Util::e($authBase) ?>/auth/recheck"
    data-auth-fallback-url="<?= \App\Core\Util::e($authFallback) ?>"
>
    <div class="panel auth-card">
        <div class="eyebrow"><?= \App\Core\Util::e($companyName ?? 'CoolerDepot') ?></div>
        <h1>Open from <?= \App\Core\Util::e($companyName ?? 'CoolerDepot') ?> Portal</h1>
        <p>This module receives the current user from the <?= \App\Core\Util::e($companyName ?? 'CoolerDepot') ?> Admin/Sales portal. Direct password login is disabled.</p>
        <p id="authRecheckStatus" aria-live="polite">Checking your Portal session…</p>
        <?php if ($authFallback !== ''): ?>
            <p><a class="btn primary" href="<?= \App\Core\Util::e($authFallback) ?>">Return to Portal</a></p>
        <?php endif; ?>
    </div>
</div>
<script>
(function(){
    'use strict';
    var root=document.getElementById('authAccessRequired');
    var status=document.getElementById('authRecheckStatus');
    if(!root){return;}
    var recheckUrl=String(root.getAttribute('data-auth-recheck-url')||'');
    var fallback=String(root.getAttribute('data-auth-fallback-url')||'');

    function validPortalUrl(value){
        try{
            var parsed=new URL(String(value||''),window.location.href);
            return (parsed.protocol==='http:'||parsed.protocol==='https:') ? parsed.href : '';
        }catch(error){
            return '';
        }
    }

    function failToPortal(payload){
        var target=validPortalUrl(payload&&payload.redirect_url ? payload.redirect_url : fallback);
        if(target){
            window.location.replace(target);
            return;
        }
        if(status){
            status.textContent='Portal access is required. Open this module from the Portal.';
        }
    }

    if(!recheckUrl){
        failToPortal(null);
        return;
    }

    fetch(recheckUrl,{
        method:'GET',
        credentials:'same-origin',
        cache:'no-store',
        headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}
    }).then(function(response){
        if(!response.ok){throw new Error('Auth recheck failed with HTTP '+response.status);}
        return response.json();
    }).then(function(data){
        if(data&&data.authenticated){
            window.location.reload();
            return;
        }
        failToPortal(data||null);
    }).catch(function(){
        failToPortal(null);
    });
})();
</script>
