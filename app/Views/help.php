<?php
/**
 * File / 文件：app/Views/help.php
 * EN: Role-specific Help rendered inside the normal application layout and driven by the same language source as the app.
 * 中文：角色专属 Help 作为系统标准页面渲染，并且只跟随系统本身的语言状态。
 */
use App\Core\Util;

$role = in_array(($helpRole ?? ''), ['admin', 'sales'], true) ? (string)$helpRole : 'sales';
$partial = __DIR__ . '/help/' . $role . '.php';
$helpVersion = trim((string)($config['app']['version'] ?? ''));
if ($helpVersion === '') {
    $versionFile = dirname(__DIR__, 2) . '/VERSION';
    $helpVersion = is_file($versionFile) ? trim((string)file_get_contents($versionFile)) : '';
}
?>
<section
    class="app-help-page help-language-pending"
    id="appHelpPage"
    data-help-role="<?= Util::e($role) ?>"
    data-help-version="<?= Util::e($helpVersion) ?>"
>
    <div class="app-help-guide-layout">
        <?php require $partial; ?>
    </div>
</section>
<script>
(function(){
    'use strict';

    const root=document.getElementById('appHelpPage');
    if(!root){return;}

    const supported=['en','zh-CN','zh-TW','es'];
    const languageClasses=['help-lang-en','help-lang-zh-CN','help-lang-zh-TW','help-lang-es'];
    const staticTranslations={
        'zh-CN':{
            'Dashboard':'主页 / Dashboard','Reports':'报表','Settings':'设置','Help':'帮助','Sign out':'退出',
            'Language':'语言','Action':'操作','Area':'区域','Button':'按钮','Effect':'结果','Element':'项目','Item':'项目',
            'Meaning':'含义','Meaning / Action':'含义 / 操作','Message / Situation':'提示 / 情况','What to do':'处理方法',
            'Control':'控件','Control / Column':'控件 / 列','Date range':'日期范围','From':'开始','To':'结束','Time filter':'时间筛选',
            'Status / HTTP / Error':'状态 / HTTP / 错误','Priority':'优先级','Last test':'最近测试','Enabled':'启用','Remove':'删除',
            'Live':'实时','Pagination':'分页','Search / Delete':'搜索 / 删除','Display Name':'显示名称','Sales count':'Sales 数量',
            'Posts count':'Post 数量','Post count / target':'Post 数量 / 目标','Download CSV':'下载 CSV','Return to Dashboard':'返回主页',
            'Submit':'提交',
            'Checking date':'检查日期','Detecting platform':'识别平台','Fetching post':'抓取 Post','Checking duplicates':'检查重复',
            'Image comparison':'图片比较','Title / description comparison':'标题 / 描述比较','URL / Post ID duplicate':'URL / Post ID 重复',
            'Website reference comparison':'网站资料库比较','Final duplicate check':'最终重复检查','Unsupported URL':'不支持的 URL',
            'Malformed URL':'URL 格式错误','Duplicate found':'发现重复','Verification expired or invalid':'验证已过期或无效',
            'No listing image':'没有 Listing 图片','Bad status':'Bad 状态','Open original':'打开原帖','Post on Marketplace':'发布到 Marketplace',
            'Read Verification Result':'读取验证结果','Track Review':'查看 Review',
            'Back to today':'返回今天','All':'全部','Unreviewed':'未审核','Weekly':'每周','Monthly':'每月','Custom':'自定义',
            '1 Day':'1 天','3 Days':'3 天','Good':'Good','Bad':'Bad','Sales':'Sales','Sales Review':'Sales Review','Post Review':'Post Review',
            'Sales Rating':'Sales Rating','Daily Review':'Daily Review','Target met':'已达到目标','Add Note':'添加 Note','Save Review':'保存 Review',
            'Delete Post':'删除 Post','Refresh Content':'刷新内容','Refresh notice':'刷新提示','Facebook Marketplace Provider Chain':'Facebook Marketplace Provider Chain',
            'Recent Provider Jobs':'最近 Provider Jobs','Token: Stored / None':'Token：已保存 / 无','Tested / Needs test':'已测试 / 需要测试',
            'Last 1 Hour / Last 24 Hours / Last 7 Days / Last 30 Days / All Time':'最近 1 小时 / 24 小时 / 7 天 / 30 天 / 全部时间',
            'Company / Sales Posts':'公司 / Sales Posts','Mobile menu ☰':'手机菜单 ☰','Open original':'打开原帖','Post card / View details':'Post 卡片 / 查看详情',
            'Click behavior':'点击行为','Click behavior / 点击行为':'点击行为','What happens when clicked / 点击以后':'点击以后','Meaning / 含义':'含义','Meaning / 行为':'行为',
            '1. Website URL':'1. Website URL','2. Website / Sitemap Scan':'2. Website / Sitemap Scan','3. URL CSV':'3. URL CSV',
            '+ Add Provider':'+ 添加 Provider','+ Manual Reference':'+ 手动 Reference','Search / Delete':'搜索 / 删除','Image':'图片'
        },
        'zh-TW':{
            'Dashboard':'主頁 / Dashboard','Reports':'報表','Settings':'設定','Help':'幫助','Sign out':'登出',
            'Language':'語言','Action':'操作','Area':'區域','Button':'按鈕','Effect':'結果','Element':'項目','Item':'項目',
            'Meaning':'含義','Meaning / Action':'含義 / 操作','Message / Situation':'提示 / 情況','What to do':'處理方法',
            'Control':'控制項','Control / Column':'控制項 / 欄','Date range':'日期範圍','From':'開始','To':'結束','Time filter':'時間篩選',
            'Status / HTTP / Error':'狀態 / HTTP / 錯誤','Priority':'優先順序','Last test':'最近測試','Enabled':'啟用','Remove':'刪除',
            'Live':'即時','Pagination':'分頁','Search / Delete':'搜尋 / 刪除','Display Name':'顯示名稱','Sales count':'Sales 數量',
            'Posts count':'Post 數量','Post count / target':'Post 數量 / 目標','Download CSV':'下載 CSV','Return to Dashboard':'返回主頁',
            'Submit':'提交',
            'Checking date':'檢查日期','Detecting platform':'識別平台','Fetching post':'抓取 Post','Checking duplicates':'檢查重複',
            'Image comparison':'圖片比較','Title / description comparison':'標題 / 描述比較','URL / Post ID duplicate':'URL / Post ID 重複',
            'Website reference comparison':'網站資料庫比較','Final duplicate check':'最終重複檢查','Unsupported URL':'不支援的 URL',
            'Malformed URL':'URL 格式錯誤','Duplicate found':'發現重複','Verification expired or invalid':'驗證已過期或無效',
            'No listing image':'沒有 Listing 圖片','Bad status':'Bad 狀態','Open original':'打開原帖','Post on Marketplace':'發佈到 Marketplace',
            'Read Verification Result':'讀取驗證結果','Track Review':'查看 Review',
            'Back to today':'返回今天','All':'全部','Unreviewed':'未審核','Weekly':'每週','Monthly':'每月','Custom':'自訂',
            '1 Day':'1 天','3 Days':'3 天','Good':'Good','Bad':'Bad','Sales':'Sales','Sales Review':'Sales Review','Post Review':'Post Review',
            'Sales Rating':'Sales Rating','Daily Review':'Daily Review','Target met':'已達到目標','Add Note':'新增 Note','Save Review':'儲存 Review',
            'Delete Post':'刪除 Post','Refresh Content':'重新整理內容','Refresh notice':'重新整理提示','Facebook Marketplace Provider Chain':'Facebook Marketplace Provider Chain',
            'Recent Provider Jobs':'最近 Provider Jobs','Token: Stored / None':'Token：已儲存 / 無','Tested / Needs test':'已測試 / 需要測試',
            'Last 1 Hour / Last 24 Hours / Last 7 Days / Last 30 Days / All Time':'最近 1 小時 / 24 小時 / 7 天 / 30 天 / 全部時間',
            'Company / Sales Posts':'公司 / Sales Posts','Mobile menu ☰':'手機選單 ☰','Post card / View details':'Post 卡片 / 查看詳情',
            'Click behavior':'點擊行為','Click behavior / 点击行为':'點擊行為','What happens when clicked / 点击以后':'點擊以後','Meaning / 含义':'含義','Meaning / 行为':'行為',
            '+ Add Provider':'+ 新增 Provider','+ Manual Reference':'+ 手動 Reference','Image':'圖片'
        }
    };

    function storedLanguage(){
        try{
            return String(window.localStorage.getItem('cdsp-admin-language')||'en');
        }catch(_error){
            return 'en';
        }
    }

    function resolveLanguage(candidate){
        const requested=String(candidate||storedLanguage()||'en');
        return supported.includes(requested)?requested:'en';
    }

    function staticText(node,lang){
        const english=String(node.getAttribute('data-help-static-en')||'');
        if(lang==='es'){
            return String(node.getAttribute('data-help-static-es')||english);
        }
        if(lang==='zh-CN' || lang==='zh-TW'){
            return String((staticTranslations[lang]&&staticTranslations[lang][english])||english);
        }
        return english;
    }

    function applyHelpLanguage(candidate){
        const lang=resolveLanguage(candidate);
        root.classList.remove(...languageClasses);
        root.classList.add('help-lang-'+lang);
        root.classList.remove('help-language-pending');
        root.setAttribute('data-help-language',lang);
        document.documentElement.lang=lang;

        root.querySelectorAll('[data-help-static-en]').forEach(function(node){
            node.textContent=staticText(node,lang);
        });

        const role=String(root.getAttribute('data-help-role')||'sales');
        const titles=role==='admin'
            ?{en:'Admin User Guide','zh-CN':'Admin 使用说明','zh-TW':'Admin 使用說明',es:'Guía de usuario de Admin'}
            :{en:'Sales User Guide','zh-CN':'Sales 使用说明','zh-TW':'Sales 使用說明',es:'Guía de usuario de Ventas'};
        root.querySelectorAll('[data-help-title]').forEach(function(node){
            node.textContent=titles[lang]||titles.en;
        });
    }

    applyHelpLanguage();

    const switcher=document.getElementById('appLanguageSwitch');
    if(switcher){
        switcher.addEventListener('click',function(event){
            const button=event.target.closest('[data-app-lang]');
            if(!button){return;}
            const lang=String(button.getAttribute('data-app-lang')||'');
            window.setTimeout(function(){applyHelpLanguage(lang);},0);
        });
    }

    if(window.jQuery){
        window.jQuery(document).on('cdsp:language-changed.helpPage',function(_event,lang){
            applyHelpLanguage(lang);
        });
    }

    window.addEventListener('storage',function(event){
        if(event.key==='cdsp-admin-language'){
            applyHelpLanguage(event.newValue);
        }
    });
    window.addEventListener('focus',function(){applyHelpLanguage();});
})();
</script>
