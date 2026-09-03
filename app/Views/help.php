<?php
/**
 * File / 文件：app/Views/help.php
 * EN: Renders role-specific Help inside the normal application header/navigation/footer.
 * 中文：在系统标准 Header / Navigation / Footer 中渲染当前角色的 Help 内容。
 */
use App\Core\Util;

$role = in_array(($helpRole ?? ''), ['admin', 'sales'], true) ? (string)$helpRole : 'sales';
$partial = __DIR__ . '/help/' . $role . '.php';
?>
<section
    class="app-help-page help-lang-en"
    id="appHelpPage"
    data-help-role="<?= Util::e($role) ?>"
>
    <div class="app-help-guide-layout">
        <?php require $partial; ?>
    </div>
</section>
<script>
(function($){
    'use strict';
    const $root=$('#appHelpPage');
    if(!$root.length){return;}

    const supported=['en','zh-CN','zh-TW','es'];
    const classes=['help-lang-en','help-lang-zh-CN','help-lang-zh-TW','help-lang-es'];

    function resolveLanguage(candidate){
        const lang=String(candidate||localStorage.getItem('cdsp-admin-language')||'en');
        return supported.includes(lang)?lang:'en';
    }

    function applyHelpLanguage(candidate){
        const lang=resolveLanguage(candidate);
        $root.removeClass(classes.join(' ')).addClass('help-lang-'+lang);
        $root.attr('data-help-language',lang);
        document.documentElement.lang=lang;

        $root.find('[data-help-static-en]').each(function(){
            const $node=$(this);
            const english=String($node.attr('data-help-static-en')||'');
            const spanish=$node.attr('data-help-static-es');
            if(lang==='es' && spanish!==undefined){
                $node.text(String(spanish));
            }else{
                $node.text(english);
            }
        });

        const role=String($root.attr('data-help-role')||'sales');
        const titles=role==='admin'
            ?{en:'Admin User Guide','zh-CN':'Admin 使用说明','zh-TW':'Admin 使用說明',es:'Guía de usuario de Admin'}
            :{en:'Sales User Guide','zh-CN':'Sales 使用说明','zh-TW':'Sales 使用說明',es:'Guía de usuario de Ventas'};
        $root.find('[data-help-title]').text(titles[lang]||titles.en);
    }

    applyHelpLanguage();

    $(document).on('cdsp:language-changed.helpPage',function(_event,lang){
        applyHelpLanguage(lang);
    });

    window.addEventListener('storage',function(event){
        if(event.key==='cdsp-admin-language'){
            applyHelpLanguage(event.newValue);
        }
    });
})();
</script>
