/**
 * File / 文件：public/assets/app.js
 * EN: Primary browser interaction controller for shared Admin/Sales UI behavior.
 * 中文：该文件负责 Admin/Sales 共用界面的主要浏览器交互。
 * Maintenance / 维护：Feature ownership and error paths should stay explicit and centrally diagnosable.
 * 维护要求：功能归属与错误路径必须清晰，并可进入中央诊断。
 */
$(function(){

/**
 * EN: Escape a value before inserting it into HTML assembled by the shared Sales/Admin browser controller.
 * 中文：在 Sales/Admin 共用浏览器控制器拼接 HTML 前，对值进行转义。
 *
 * @param {string|number|null|undefined|*} value Value that will be rendered as text. / 将作为文本渲染的值。
 *
 * @returns {string} HTML-escaped text. / 已进行 HTML 转义的文本。
 */
function escapeHtml(value){
    return $('<div>').text(
        value == null ? '' : String(value)
    ).html();
}

const appLanguageDictionary={
    en:{
        dashboard:'Dashboard',
        submit:'Submit',
        bulkSubmit:'Bulk Submit',
        admin:'Admin',
        reports:'Reports',
        settings:'Settings',
        help:'Help',
        signOut:'Sign out',
        verificationControl:'Verification Recovery',
        postVerificationLocks:'In Case: Manual Unlock',
        verificationLocksHelp:'Verification locks recover automatically when a verification process disappears or reaches the safety timeout. Use Manual Unlock only as an in-case fallback.',
        verificationLocksActive:'{count} active',
        verificationLocksReadError:'Verification locks could not be read',
        verificationLocksEmpty:'Automatic recovery is active. No Sales verification currently needs a manual unlock.',
        started:'Started',
        unlock:'Manual Unlock',
        verificationUnlockWarning:'Manual Unlock is only a fallback if automatic recovery does not resolve the problem. It allows a new verification to start without forcibly terminating an older provider request.',
        verificationRecoveryTimeoutLabel:'Auto recovery timeout',
        verificationRecoveryTimeoutHelp:'Only applies when a verification process still looks alive but is stuck. Dead or disconnected processes recover immediately. Allowed range: 5–60 minutes.',
        minutes:'minutes',
        saveRecoveryTimeout:'Save timeout',
        sales:'Sales',
        salesOrganization:'Sales Organization',
        locations:'Locations',
        locationsLower:'locations',
        locationsHelp:'Create locations here, then assign one from each Sales card Settings button on the Admin dashboard.',
        locationName:'Location name',
        addLocation:'Add Location',
        editLocation:'Edit',
        saveLocation:'Save',
        cancel:'Cancel',
        deleteLocation:'Delete',
        noLocations:'No locations yet. Add the first location above.',
        salesUnassignedLocation:'Sales currently have no location assigned.',
        locationAdded:'Location added.',
        locationUpdated:'Location updated.',
        locationDeleted:'Location deleted.',
        locationDuplicate:'That location already exists.',
        locationInvalid:'Enter a location name from 1 to 120 characters.',
        locationInUse:'Deleting a location moves assigned Sales to Unassigned.',
        locationMissing:'Location was not found.',
        locationError:'Location could not be saved.'
    },
    'zh-CN':{
        dashboard:'主页',
        submit:'提交',
        bulkSubmit:'批量提交',
        admin:'管理',
        reports:'报表',
        settings:'设置',
        help:'帮助',
        signOut:'退出',
        verificationControl:'验证自动恢复',
        postVerificationLocks:'兜底：手动解锁',
        verificationLocksHelp:'验证锁会在验证进程消失或达到安全超时后自动恢复。只有自动恢复仍未解决问题时，才使用手动解锁作为兜底。',
        verificationLocksActive:'{count} 个活动锁',
        verificationLocksReadError:'无法读取验证锁',
        verificationLocksEmpty:'自动恢复已启用，当前没有需要手动解锁的 Sales 验证。',
        started:'开始时间',
        unlock:'手动解锁',
        verificationUnlockWarning:'手动解锁只用于自动恢复仍未解决问题的兜底情况。它允许重新开始验证，但不会强制终止仍在执行中的旧 Provider 请求。',
        verificationRecoveryTimeoutLabel:'自动恢复等待时间',
        verificationRecoveryTimeoutHelp:'只用于验证进程看起来仍在线但实际卡死的情况。进程死亡或断线仍会立即自动恢复。可设置范围：5–60 分钟。',
        minutes:'分钟',
        saveRecoveryTimeout:'保存等待时间',
        sales:'销售',
        salesOrganization:'销售组织',
        locations:'地点',
        locationsLower:'个地点',
        locationsHelp:'先在这里建立地点，再从 Admin Dashboard 每个 Sales Card 的设置中分配地点。',
        locationName:'地点名称',
        addLocation:'添加地点',
        editLocation:'修改',
        saveLocation:'保存',
        cancel:'取消',
        deleteLocation:'删除',
        noLocations:'还没有地点，请先在上方添加。',
        salesUnassignedLocation:'名销售目前还没有分配地点。',
        locationAdded:'地点已添加。',
        locationUpdated:'地点已修改。',
        locationDeleted:'地点已删除。',
        locationDuplicate:'这个地点已经存在。',
        locationInvalid:'地点名称必须为 1–120 个字符。',
        locationInUse:'删除地点时，已分配的 Sales 会自动移到未分配。',
        locationMissing:'没有找到这个地点。',
        locationError:'无法保存地点。'
    },
    'zh-TW':{
        dashboard:'主頁',
        submit:'提交',
        bulkSubmit:'批量提交',
        admin:'管理',
        reports:'報表',
        settings:'設定',
        help:'幫助',
        signOut:'登出',
        verificationControl:'驗證自動恢復',
        postVerificationLocks:'備用：手動解鎖',
        verificationLocksHelp:'驗證鎖會在驗證程序消失或達到安全逾時後自動恢復。只有自動恢復仍未解決問題時，才使用手動解鎖作為備用。',
        verificationLocksActive:'{count} 個活動鎖',
        verificationLocksReadError:'無法讀取驗證鎖',
        verificationLocksEmpty:'自動恢復已啟用，目前沒有需要手動解鎖的 Sales 驗證。',
        started:'開始時間',
        unlock:'手動解鎖',
        verificationUnlockWarning:'手動解鎖只用於自動恢復仍未解決問題的備用情況。它允許重新開始驗證，但不會強制終止仍在執行中的舊 Provider 請求。',
        verificationRecoveryTimeoutLabel:'自動恢復等待時間',
        verificationRecoveryTimeoutHelp:'只用於驗證程序看起來仍在線但實際卡死的情況。程序死亡或斷線仍會立即自動恢復。可設定範圍：5–60 分鐘。',
        minutes:'分鐘',
        saveRecoveryTimeout:'儲存等待時間',
        sales:'銷售',
        salesOrganization:'銷售組織',
        locations:'地點',
        locationsLower:'個地點',
        locationsHelp:'先在這裡建立地點，再從 Admin Dashboard 每個 Sales Card 的設定中分配地點。',
        locationName:'地點名稱',
        addLocation:'新增地點',
        editLocation:'修改',
        saveLocation:'儲存',
        cancel:'取消',
        deleteLocation:'刪除',
        noLocations:'目前沒有地點，請先在上方新增。',
        salesUnassignedLocation:'名銷售目前尚未分配地點。',
        locationAdded:'地點已新增。',
        locationUpdated:'地點已修改。',
        locationDeleted:'地點已刪除。',
        locationDuplicate:'這個地點已存在。',
        locationInvalid:'地點名稱必須為 1–120 個字元。',
        locationInUse:'刪除地點時，已分配的 Sales 會自動移到未分配。',
        locationMissing:'找不到這個地點。',
        locationError:'無法儲存地點。'
    },
    es:{
        dashboard:'Panel',
        submit:'Enviar',
        bulkSubmit:'Envío masivo',
        admin:'Admin',
        reports:'Informes',
        settings:'Configuración',
        help:'Ayuda',
        signOut:'Salir',
        verificationControl:'Recuperación de verificación',
        postVerificationLocks:'En caso necesario: desbloqueo manual',
        verificationLocksHelp:'Los bloqueos de verificación se recuperan automáticamente si el proceso desaparece o alcanza el tiempo de seguridad. Usa el desbloqueo manual solo como último recurso.',
        verificationLocksActive:'{count} activos',
        verificationLocksReadError:'No se pudieron leer los bloqueos de verificación',
        verificationLocksEmpty:'La recuperación automática está activa. Ninguna verificación necesita desbloqueo manual.',
        started:'Iniciado',
        unlock:'Desbloqueo manual',
        verificationUnlockWarning:'El desbloqueo manual es solo un recurso de respaldo si la recuperación automática no resuelve el problema. Permite iniciar otra verificación sin finalizar a la fuerza una solicitud anterior.',
        verificationRecoveryTimeoutLabel:'Tiempo de recuperación automática',
        verificationRecoveryTimeoutHelp:'Solo se aplica cuando el proceso aún parece activo pero está bloqueado. Los procesos terminados o desconectados se recuperan de inmediato. Rango permitido: 5–60 minutos.',
        minutes:'minutos',
        saveRecoveryTimeout:'Guardar tiempo',
        sales:'Ventas',
        salesOrganization:'Organización de ventas',
        locations:'Ubicaciones',
        locationsLower:'ubicaciones',
        locationsHelp:'Crea ubicaciones aquí y asígnalas desde Configuración en cada tarjeta de ventas del panel Admin.',
        locationName:'Nombre de ubicación',
        addLocation:'Añadir ubicación',
        editLocation:'Editar',
        saveLocation:'Guardar',
        cancel:'Cancelar',
        deleteLocation:'Eliminar',
        noLocations:'Aún no hay ubicaciones. Añade la primera arriba.',
        salesUnassignedLocation:'vendedores no tienen ubicación asignada.',
        locationAdded:'Ubicación añadida.',
        locationUpdated:'Ubicación actualizada.',
        locationDeleted:'Ubicación eliminada.',
        locationDuplicate:'Esa ubicación ya existe.',
        locationInvalid:'El nombre debe tener entre 1 y 120 caracteres.',
        locationInUse:'Al eliminar una ubicación, los vendedores asignados pasan a Sin asignar.',
        locationMissing:'No se encontró la ubicación.',
        locationError:'No se pudo guardar la ubicación.'
    }

};

/* v0.2.122 — Settings headings/primary controls use the same explicit i18n path as the proven global menu. */
Object.assign(appLanguageDictionary.en,{
    settingsAdministrator:'Administrator',settingsApiProviders:'API Providers',settingsProviderPriorityHelp:'Providers are tried from top to bottom. Drag them to change failover priority.',settingsAddProvider:'Add Provider',
    settingsApplication:'Application',settingsApplicationSettings:'Application Settings',settingsApplicationHelp:'Set the display name and the Portal address used when login re-check fails.',settingsCompanyName:'Company name',settingsSaveName:'Save Name',settingsPortalFallbackUrl:'Portal fallback URL',settingsSaveUrl:'Save URL',settingsPortalHelp:'If session re-check fails, the browser redirects to this fixed http/https address.',
    settingsProviderChain:'Facebook Marketplace Provider Chain',settingsProviderChainHelp:'Only providers that passed a real Marketplace test can be added. Disabled providers stay in the list but are skipped.',settingsRecentProviderJobs:'Recent Provider Jobs',settingsRecentProviderJobsHelp:'Test attempts and live failover attempts are logged here.',settingsDuplicateSources:'Duplicate Sources',settingsWebsiteLibrary:'Company Website Library',settingsWebsiteLibraryHelp:'Website Scan opens one detailed workspace. Scanned Products lives inside that workspace below Website Scan and opens or closes independently. URL CSV and Page / Sitemap Import remain separate tools.'
});
Object.assign(appLanguageDictionary['zh-CN'],{
    settingsAdministrator:'管理员',settingsApiProviders:'API 服务提供商',settingsProviderPriorityHelp:'Provider 按从上到下的顺序尝试。拖动可调整故障切换优先级。',settingsAddProvider:'添加服务商',
    settingsApplication:'应用',settingsApplicationSettings:'应用设置',settingsApplicationHelp:'设置显示名称，以及登录重新检查失败时使用的 Portal 地址。',settingsCompanyName:'公司名称',settingsSaveName:'保存名称',settingsPortalFallbackUrl:'Portal 备用 URL',settingsSaveUrl:'保存 URL',settingsPortalHelp:'如果 Session 重新检查失败，浏览器会跳转到这个固定的 http/https 地址。',
    settingsProviderChain:'Facebook Marketplace 服务商链',settingsProviderChainHelp:'只有通过真实 Marketplace 测试的 Provider 才能加入。已禁用的 Provider 会保留在列表中，但运行时会跳过。',settingsRecentProviderJobs:'最近服务商任务',settingsRecentProviderJobsHelp:'测试请求和实时 Failover 请求都会记录在这里。',settingsDuplicateSources:'重复来源',settingsWebsiteLibrary:'公司网站资料库',settingsWebsiteLibraryHelp:'Website Scan 会打开一个独立的详细工作区。Scanned Products 位于 Website Scan 工作区内并可单独展开/收起。URL CSV 与 Page / Sitemap Import 仍是独立工具。'
});
Object.assign(appLanguageDictionary['zh-TW'],{
    settingsAdministrator:'管理員',settingsApiProviders:'API 服務提供商',settingsProviderPriorityHelp:'Provider 依由上到下的順序嘗試。拖曳可調整故障切換優先順序。',settingsAddProvider:'新增服務商',
    settingsApplication:'應用',settingsApplicationSettings:'應用設定',settingsApplicationHelp:'設定顯示名稱，以及登入重新檢查失敗時使用的 Portal 位址。',settingsCompanyName:'公司名稱',settingsSaveName:'儲存名稱',settingsPortalFallbackUrl:'Portal 備用 URL',settingsSaveUrl:'儲存 URL',settingsPortalHelp:'如果 Session 重新檢查失敗，瀏覽器會跳轉到這個固定的 http/https 位址。',
    settingsProviderChain:'Facebook Marketplace 服務商鏈',settingsProviderChainHelp:'只有通過真實 Marketplace 測試的 Provider 才能加入。已停用的 Provider 會保留在清單中，但執行時會略過。',settingsRecentProviderJobs:'最近服務商任務',settingsRecentProviderJobsHelp:'測試請求和即時 Failover 請求都會記錄在這裡。',settingsDuplicateSources:'重複來源',settingsWebsiteLibrary:'公司網站資料庫',settingsWebsiteLibraryHelp:'Website Scan 會開啟一個獨立的詳細工作區。Scanned Products 位於 Website Scan 工作區內並可單獨展開/收合。URL CSV 與 Page / Sitemap Import 仍是獨立工具。'
});
Object.assign(appLanguageDictionary.es,{
    settingsAdministrator:'Administrador',settingsApiProviders:'Proveedores API',settingsProviderPriorityHelp:'Los proveedores se prueban de arriba abajo. Arrástralos para cambiar la prioridad de failover.',settingsAddProvider:'Añadir proveedor',
    settingsApplication:'Aplicación',settingsApplicationSettings:'Configuración de la aplicación',settingsApplicationHelp:'Configura el nombre mostrado y la dirección del Portal usada cuando falla la nueva comprobación de inicio de sesión.',settingsCompanyName:'Nombre de la empresa',settingsSaveName:'Guardar nombre',settingsPortalFallbackUrl:'URL alternativa del Portal',settingsSaveUrl:'Guardar URL',settingsPortalHelp:'Si falla la nueva comprobación de sesión, el navegador redirige a esta dirección http/https fija.',
    settingsProviderChain:'Cadena de proveedores de Facebook Marketplace',settingsProviderChainHelp:'Solo se pueden añadir proveedores que hayan superado una prueba real de Marketplace. Los proveedores deshabilitados permanecen en la lista, pero se omiten.',settingsRecentProviderJobs:'Trabajos recientes de proveedores',settingsRecentProviderJobsHelp:'Aquí se registran los intentos de prueba y de failover en vivo.',settingsDuplicateSources:'Fuentes de duplicados',settingsWebsiteLibrary:'Biblioteca del sitio web de la empresa',settingsWebsiteLibraryHelp:'Website Scan abre un espacio de trabajo detallado. Scanned Products vive dentro de ese espacio y se abre o cierra de forma independiente. URL CSV y Page / Sitemap Import siguen siendo herramientas separadas.'
});

/* v0.2.121 — Settings page complete language follow.
 * EN: Settings historically translated only the global menu and a few location/lock labels.
 * This dictionary translates the remaining Settings UI without changing provider/scan behavior.
 * 中文：Settings 过去只翻译了顶部菜单和少量地点/验证锁文本；这里补齐其余设置界面的语言联动，不改 Provider/扫描行为。
 */
const settingsPageTextDictionary={
    'zh-CN':{
        'Administrator':'管理员',
        'API Providers':'API 服务提供商',
        'Providers are tried from top to bottom. Drag them to change failover priority.':'Provider 按从上到下的顺序尝试。拖动可调整故障切换优先级。',
        'Add Provider':'添加服务商',
        '+ Add Provider':'+ 添加服务商',
        'Provider Registry migration has not been enabled yet. Run the v0.1.12 provider registry migration before using this page.':'Provider Registry 迁移尚未启用。使用此页面前请先运行 v0.1.12 Provider Registry 迁移。',
        'Application':'应用',
        'Application Settings':'应用设置',
        'Set the display name and the Portal address used when login re-check fails.':'设置显示名称，以及登录重新检查失败时使用的 Portal 地址。',
        'Company name':'公司名称',
        'Save Name':'保存名称',
        'Portal fallback URL':'Portal 备用 URL',
        'Save URL':'保存 URL',
        'If session re-check fails, the browser redirects to this fixed http/https address.':'如果 Session 重新检查失败，浏览器会跳转到这个固定的 http/https 地址。',
        'Facebook Marketplace Provider Chain':'Facebook Marketplace 服务商链',
        'Only providers that passed a real Marketplace test can be added. Disabled providers stay in the list but are skipped.':'只有通过真实 Marketplace 测试的 Provider 才能加入。已禁用的 Provider 会保留在列表中，但运行时会跳过。',
        'Priority':'优先级',
        'Website ↗':'网站 ↗',
        'Token:':'Token：',
        'Stored':'已保存',
        'None':'无',
        'Last test:':'最近测试：',
        'Tested':'已测试',
        'Needs test':'需要测试',
        'Enabled':'已启用',
        'Disabled':'已禁用',
        'Remove':'移除',
        'No providers yet':'还没有 Provider',
        'Click + Add Provider, test it, then add it to the chain.':'点击 + 添加 Provider，完成测试后再加入 Provider 链。',
        'Recent Provider Jobs':'最近服务商任务',
        'Test attempts and live failover attempts are logged here.':'测试请求和实时 Failover 请求都会记录在这里。',
        'Live':'实时',
        'Time':'时间',
        'Last 1 Hour':'最近 1 小时',
        'Last 24 Hours':'最近 24 小时',
        'Last 7 Days':'最近 7 天',
        'Last 30 Days':'最近 30 天',
        'All Time':'全部时间',
        'jobs':'个任务','providers':'个服务商','existing product URLs skipped':'个已存在产品 URL 已跳过','or':'或',', then':'，然后',
        'User':'用户',
        'Provider':'服务商',
        'Item':'项目',
        'Status':'状态',
        'HTTP':'HTTP',
        'Error':'错误',
        'Ready':'就绪',
        'Failed':'失败',
        'Running':'运行中',
        'Starting':'启动中',
        'No provider jobs in this time range.':'这个时间范围内没有 Provider Job。',
        'Previous':'上一页',
        'Page':'第',
        'of':'共',
        'Next':'下一页',
        'Close':'关闭',
        'Enter the provider/API settings, run a real test, then Add Provider becomes available.':'填写 Provider/API 设置并执行真实测试，通过后才可以添加 Provider。',
        'Provider Type':'服务商类型',
        'Name':'名称',
        'Website Link':'网站链接',
        'API Endpoint':'API Endpoint',
        'Token / API Key':'Token / API Key',
        'Stored encrypted after the provider passes its test.':'Provider 测试通过后会加密保存。',
        'Test Facebook Marketplace URL':'测试 Facebook Marketplace URL',
        'The test must return ID, title, description, and a real listing date.':'测试结果必须返回 ID、标题、描述和真实 Listing 日期。',
        'Marketplace Dataset ID':'Marketplace Dataset ID',
        'Max Wait':'最长等待',
        'Poll Every':'轮询间隔',
        'sec':'秒',
        'Request Method':'请求方法',
        'Authentication':'身份验证',
        'Bearer Token':'Bearer Token',
        'Custom Header':'自定义 Header',
        'Query Parameter':'Query 参数',
        'No Authentication':'无需验证',
        'Header / Query Name':'Header / Query 名称',
        'Listing URL Input':'Listing URL 输入方式',
        'JSON Body':'JSON Body',
        'Listing URL Field Name':'Listing URL 字段名',
        'JSON Field Mapping':'JSON 字段映射',
        'Dot notation is supported, for example':'支持点号路径，例如',
        'Item ID Path':'Item ID 路径',
        'Title Path':'标题路径',
        'Description Path':'描述路径',
        'Listing Date Path':'Listing 日期路径',
        'Canonical URL Path':'Canonical URL 路径',
        'Test Provider':'测试服务商',
        'Testing...':'测试中...',
        'Test passed.':'测试通过。',
        'Provider test failed.':'Provider 测试失败。',
        'Listing date':'Listing 日期',
        'Title':'标题',
        'Description':'描述',
        'Duplicate Sources':'重复来源',
        'Company Website Library':'公司网站资料库',
        'Website Scan opens one detailed workspace. Scanned Products lives inside that workspace below Website Scan and opens or closes independently. URL CSV and Page / Sitemap Import remain separate tools.':'Website Scan 会打开一个独立的详细工作区。Scanned Products 位于 Website Scan 工作区内并可单独展开/收起。URL CSV 与 Page / Sitemap Import 仍是独立工具。',
        'references':'条 Reference',
        'images pending':'张图片待索引',
        'Website Scan':'网站扫描',
        'Add websites, run scans and review each website\'s scan history.':'添加网站、运行扫描并查看每个网站的扫描历史。',
        'website':'个网站',
        'websites':'个网站',
        'URL CSV':'URL CSV',
        'Import a prepared CSV; the website is detected from its URLs automatically.':'导入准备好的 CSV；系统会根据其中的 URL 自动识别网站。',
        'import record':'条导入记录',
        'import records':'条导入记录',
        'Page / Sitemap Import':'页面 / Sitemap 导入',
        'Scan one page or sitemap; its website is detected automatically from the URL.':'扫描单个页面或 Sitemap；系统会根据 URL 自动识别网站。',
        'scan/import record':'条扫描/导入记录',
        'scan/import records':'条扫描/导入记录',
        'The Website list belongs inside Step 1. Starting a scan saves a new website automatically.':'网站列表属于步骤 1。开始扫描时会自动保存新网站。',
        'No website sources yet. Enter a URL above and click Scan Website.':'还没有网站来源。请在上方输入 URL，然后点击“网站扫描”。',
        'Saved Websites':'已保存网站',
        'Click a website card to open scan controls, live counters and scan history.':'点击网站卡片可打开扫描控制、实时计数和扫描历史。',
        'Open Website ↗':'打开网站 ↗',
        'Delete Website':'删除网站',
        'History for this website only.':'这里只显示该网站的历史。',
        'Product Scan History':'产品扫描历史',
        'Scanned Products':'已扫描产品',
        'Search, open, add or delete saved product URLs without opening an individual Website Scan card.':'无需打开单个 Website Scan 卡片，也可以搜索、打开、添加或删除已保存的产品 URL。',
        'matching products':'个匹配产品',
        'unique products':'个唯一产品',
        'products ·':'个产品 ·',
        'first images':'张首图',
        'pages checked':'个页面已检查',
        'Search':'搜索',
        '+ Add URL':'+ 添加 URL',
        'Add URL':'添加 URL',
        'Website URL':'网站 URL',
        'Page URL':'页面 URL',
        'First image URL':'首图 URL',
        'Action':'操作',
        'Actions':'操作',
        'Save':'保存',
        'Delete':'删除',
        'Image':'图片',
        'Title / Description':'标题 / 描述',
        'Indexed':'已索引',
        'No saved websites':'还没有已保存网站',
        'No saved websites yet.':'还没有已保存网站。',
        'Open Scanned Products to load products.':'打开 Scanned Products 以加载产品。',
        'CSV file':'CSV 文件',
        'Import CSV':'导入 CSV',
        'Download Sample CSV':'下载 CSV 示例',
        'CSV Import History':'CSV 导入历史',
        'Processed, saved and failed row counts are kept per run.':'每次运行都会保存处理、保存和失败的行数。',
        'records':'条记录',
        'Page / Sitemap URL':'页面 / Sitemap URL',
        'Scan & Import':'扫描并导入',
        'Scan & Import History':'扫描并导入历史',
        'Every manual page/sitemap run is recorded with its source URL and result.':'每次手动页面/Sitemap 运行都会记录来源 URL 和结果。',
        'Started':'开始时间',
        'Website':'网站',
        'Processed':'已处理',
        'Saved':'已保存',
        'Details':'详情',
        'No CSV import history yet.':'还没有 CSV 导入历史。',
        'No Page / Sitemap import history yet.':'还没有 Page / Sitemap 导入历史。',
        'No scan history yet.':'还没有扫描历史。',
        'Scan Website':'扫描网站',
        'Run':'运行',
        'total':'总计',
        'product reference':'条产品 Reference',
        'fingerprints':'个指纹',
        'images':'张图片',
        'active':'个活动锁',
        'Enter a page or sitemap URL; its website is detected automatically. History stays below.':'输入页面或 Sitemap URL；系统会自动识别网站，历史记录保留在下方。',
        'Upload a CSV; its URL column determines the website automatically. Import history stays below.':'上传 CSV；系统会根据 URL 列自动识别网站，导入历史保留在下方。',
        'Search/add/delete applies only to':'搜索/添加/删除只作用于',
        'Save a website in Website Scan first.':'请先在 Website Scan 中保存一个网站。',
        'No description':'无描述',
        'Image found':'已找到图片',
        'No image':'无图片',
        'Loading products…':'正在加载产品…',
        'Could not load products.':'无法加载产品。',
        'No matching scanned products for this website.':'这个网站没有匹配的已扫描产品。',
        'Pause this scan':'暂停此扫描',
        'Continue this scan':'继续此扫描',
        'Scan stopped':'扫描已停止',
        'Scan completed':'扫描完成',
        'Scan failed':'扫描失败',
        'Scan status':'扫描状态',
        'Starting scan':'正在启动扫描',
        'Processing log':'处理日志',
        'The run is being created now.':'正在创建本次运行。',
        'Each scanned URL is recorded here as it finishes.':'每个扫描 URL 完成后都会记录在这里。',
        'Preparing next URL…':'正在准备下一个 URL…',
        'Now':'现在',
        'Scanning':'扫描中',
        'Request in progress…':'请求处理中…',
        'No per-URL records yet.':'还没有逐 URL 记录。',
        'Processing log could not be loaded.':'无法加载处理日志。',
        'No per-URL processing records were stored for this older scan.':'这个较早的扫描没有保存逐 URL 处理记录。',
        'No per-URL processing records were stored for this scan.':'本次扫描没有保存逐 URL 处理记录。',
        'Enter a company website first.':'请先输入公司网站。',
        'Enter a valid HTTPS website URL.':'请输入有效的 HTTPS 网站 URL。',
        'Company website scanning requires https://.':'公司网站扫描要求使用 https://。',
        'Select a website first.':'请先选择一个网站。',
        'Website URL added.':'网站 URL 已添加。',
        'Location name':'地点名称'
    },
    'zh-TW':{
        'Administrator':'管理員',
        'API Providers':'API 服務提供商',
        'Providers are tried from top to bottom. Drag them to change failover priority.':'Provider 依由上到下的順序嘗試。拖曳可調整故障切換優先順序。',
        'Add Provider':'新增服務商',
        '+ Add Provider':'+ 新增服務商',
        'Provider Registry migration has not been enabled yet. Run the v0.1.12 provider registry migration before using this page.':'Provider Registry 遷移尚未啟用。使用此頁面前請先執行 v0.1.12 Provider Registry 遷移。',
        'Application':'應用',
        'Application Settings':'應用設定',
        'Set the display name and the Portal address used when login re-check fails.':'設定顯示名稱，以及登入重新檢查失敗時使用的 Portal 位址。',
        'Company name':'公司名稱',
        'Save Name':'儲存名稱',
        'Portal fallback URL':'Portal 備用 URL',
        'Save URL':'儲存 URL',
        'If session re-check fails, the browser redirects to this fixed http/https address.':'如果 Session 重新檢查失敗，瀏覽器會跳轉到這個固定的 http/https 位址。',
        'Facebook Marketplace Provider Chain':'Facebook Marketplace 服務商鏈',
        'Only providers that passed a real Marketplace test can be added. Disabled providers stay in the list but are skipped.':'只有通過真實 Marketplace 測試的 Provider 才能加入。已停用的 Provider 會保留在清單中，但執行時會略過。',
        'Priority':'優先順序','Website ↗':'網站 ↗','Token:':'Token：','Stored':'已儲存','None':'無','Last test:':'最近測試：','Tested':'已測試','Needs test':'需要測試','Enabled':'已啟用','Disabled':'已停用','Remove':'移除',
        'No providers yet':'尚無 Provider','Click + Add Provider, test it, then add it to the chain.':'點擊 + 新增 Provider，完成測試後再加入 Provider 鏈。',
        'Recent Provider Jobs':'最近服務商任務','Test attempts and live failover attempts are logged here.':'測試請求和即時 Failover 請求都會記錄在這裡。','Live':'即時','Time':'時間','Last 1 Hour':'最近 1 小時','Last 24 Hours':'最近 24 小時','Last 7 Days':'最近 7 天','Last 30 Days':'最近 30 天','All Time':'全部時間','jobs':'個任務','providers':'個服務商','existing product URLs skipped':'個已存在產品 URL 已略過','or':'或',', then':'，然後','User':'使用者','Provider':'服務商','Item':'項目','Status':'狀態','HTTP':'HTTP','Error':'錯誤','Ready':'就緒','Failed':'失敗','Running':'執行中','Starting':'啟動中','No provider jobs in this time range.':'這個時間範圍內沒有 Provider Job。','Previous':'上一頁','Page':'第','of':'共','Next':'下一頁','Close':'關閉',
        'Enter the provider/API settings, run a real test, then Add Provider becomes available.':'填寫 Provider/API 設定並執行真實測試，通過後才可以新增 Provider。','Provider Type':'服務商類型','Name':'名稱','Website Link':'網站連結','API Endpoint':'API Endpoint','Token / API Key':'Token / API Key','Stored encrypted after the provider passes its test.':'Provider 測試通過後會加密儲存。','Test Facebook Marketplace URL':'測試 Facebook Marketplace URL','The test must return ID, title, description, and a real listing date.':'測試結果必須回傳 ID、標題、描述和真實 Listing 日期。','Marketplace Dataset ID':'Marketplace Dataset ID','Max Wait':'最長等待','Poll Every':'輪詢間隔','sec':'秒','Request Method':'請求方式','Authentication':'身分驗證','Bearer Token':'Bearer Token','Custom Header':'自訂 Header','Query Parameter':'Query 參數','No Authentication':'無需驗證','Header / Query Name':'Header / Query 名稱','Listing URL Input':'Listing URL 輸入方式','JSON Body':'JSON Body','Listing URL Field Name':'Listing URL 欄位名','JSON Field Mapping':'JSON 欄位映射','Dot notation is supported, for example':'支援點號路徑，例如','Item ID Path':'Item ID 路徑','Title Path':'標題路徑','Description Path':'描述路徑','Listing Date Path':'Listing 日期路徑','Canonical URL Path':'Canonical URL 路徑','Test Provider':'測試服務商','Testing...':'測試中...','Test passed.':'測試通過。','Provider test failed.':'Provider 測試失敗。','Listing date':'Listing 日期','Title':'標題','Description':'描述',
        'Duplicate Sources':'重複來源','Company Website Library':'公司網站資料庫','Website Scan opens one detailed workspace. Scanned Products lives inside that workspace below Website Scan and opens or closes independently. URL CSV and Page / Sitemap Import remain separate tools.':'Website Scan 會開啟一個獨立的詳細工作區。Scanned Products 位於 Website Scan 工作區內並可單獨展開/收合。URL CSV 與 Page / Sitemap Import 仍是獨立工具。','references':'筆 Reference','images pending':'張圖片待索引','Website Scan':'網站掃描','Add websites, run scans and review each website\'s scan history.':'新增網站、執行掃描並查看每個網站的掃描歷史。','website':'個網站','websites':'個網站','URL CSV':'URL CSV','Import a prepared CSV; the website is detected from its URLs automatically.':'匯入準備好的 CSV；系統會根據其中的 URL 自動辨識網站。','import record':'筆匯入記錄','import records':'筆匯入記錄','Page / Sitemap Import':'頁面 / Sitemap 匯入','Scan one page or sitemap; its website is detected automatically from the URL.':'掃描單一頁面或 Sitemap；系統會根據 URL 自動辨識網站。','scan/import record':'筆掃描/匯入記錄','scan/import records':'筆掃描/匯入記錄','The Website list belongs inside Step 1. Starting a scan saves a new website automatically.':'網站清單屬於步驟 1。開始掃描時會自動儲存新網站。','No website sources yet. Enter a URL above and click Scan Website.':'尚無網站來源。請在上方輸入 URL，然後點擊「網站掃描」。','Saved Websites':'已儲存網站','Click a website card to open scan controls, live counters and scan history.':'點擊網站卡片可開啟掃描控制、即時計數與掃描歷史。','Open Website ↗':'開啟網站 ↗','Delete Website':'刪除網站','History for this website only.':'這裡只顯示該網站的歷史。','Product Scan History':'產品掃描歷史','Scanned Products':'已掃描產品','Search, open, add or delete saved product URLs without opening an individual Website Scan card.':'不需開啟單一 Website Scan 卡片，也可以搜尋、開啟、新增或刪除已儲存的產品 URL。','matching products':'個符合產品','unique products':'個唯一產品','products ·':'個產品 ·','first images':'張首圖','pages checked':'個頁面已檢查','Search':'搜尋','+ Add URL':'+ 新增 URL','Add URL':'新增 URL','Website URL':'網站 URL','Page URL':'頁面 URL','First image URL':'首圖 URL','Action':'操作','Actions':'操作','Save':'儲存','Delete':'刪除','Image':'圖片','Title / Description':'標題 / 描述','Indexed':'已索引','No saved websites':'尚無已儲存網站','No saved websites yet.':'尚無已儲存網站。','Open Scanned Products to load products.':'開啟 Scanned Products 以載入產品。','CSV file':'CSV 檔案','Import CSV':'匯入 CSV','Download Sample CSV':'下載 CSV 範例','CSV Import History':'CSV 匯入歷史','Processed, saved and failed row counts are kept per run.':'每次執行都會保存處理、儲存與失敗的列數。','records':'筆記錄','Page / Sitemap URL':'頁面 / Sitemap URL','Scan & Import':'掃描並匯入','Scan & Import History':'掃描並匯入歷史','Every manual page/sitemap run is recorded with its source URL and result.':'每次手動頁面/Sitemap 執行都會記錄來源 URL 與結果。','Started':'開始時間','Website':'網站','Processed':'已處理','Saved':'已儲存','Details':'詳情','No CSV import history yet.':'尚無 CSV 匯入歷史。','No Page / Sitemap import history yet.':'尚無 Page / Sitemap 匯入歷史。','No scan history yet.':'尚無掃描歷史。','Scan Website':'掃描網站','Run':'執行','total':'總計','product reference':'筆產品 Reference','fingerprints':'個指紋','images':'張圖片','active':'個活動鎖','Enter a page or sitemap URL; its website is detected automatically. History stays below.':'輸入頁面或 Sitemap URL；系統會自動辨識網站，歷史記錄保留在下方。','Upload a CSV; its URL column determines the website automatically. Import history stays below.':'上傳 CSV；系統會根據 URL 欄自動辨識網站，匯入歷史保留在下方。','Search/add/delete applies only to':'搜尋/新增/刪除只作用於','Save a website in Website Scan first.':'請先在 Website Scan 中儲存一個網站。','No description':'無描述','Image found':'已找到圖片','No image':'無圖片','Loading products…':'正在載入產品…','Could not load products.':'無法載入產品。','No matching scanned products for this website.':'這個網站沒有符合的已掃描產品。','Pause this scan':'暫停此掃描','Continue this scan':'繼續此掃描','Scan stopped':'掃描已停止','Scan completed':'掃描完成','Scan failed':'掃描失敗','Scan status':'掃描狀態','Starting scan':'正在啟動掃描','Processing log':'處理日誌','The run is being created now.':'正在建立本次執行。','Each scanned URL is recorded here as it finishes.':'每個掃描 URL 完成後都會記錄在這裡。','Preparing next URL…':'正在準備下一個 URL…','Now':'現在','Scanning':'掃描中','Request in progress…':'請求處理中…','No per-URL records yet.':'尚無逐 URL 記錄。','Processing log could not be loaded.':'無法載入處理日誌。','No per-URL processing records were stored for this older scan.':'這個較早的掃描沒有保存逐 URL 處理記錄。','No per-URL processing records were stored for this scan.':'本次掃描沒有保存逐 URL 處理記錄。','Enter a company website first.':'請先輸入公司網站。','Enter a valid HTTPS website URL.':'請輸入有效的 HTTPS 網站 URL。','Company website scanning requires https://.':'公司網站掃描要求使用 https://。','Select a website first.':'請先選擇一個網站。','Website URL added.':'網站 URL 已新增。','Location name':'地點名稱'
    },
    es:{
        'Administrator':'Administrador','API Providers':'Proveedores API','Providers are tried from top to bottom. Drag them to change failover priority.':'Los proveedores se prueban de arriba hacia abajo. Arrástralos para cambiar la prioridad de conmutación por error.','Add Provider':'Añadir proveedor','+ Add Provider':'+ Añadir proveedor','Provider Registry migration has not been enabled yet. Run the v0.1.12 provider registry migration before using this page.':'La migración del registro de proveedores aún no está habilitada. Ejecuta la migración v0.1.12 antes de usar esta página.','Application':'Aplicación','Application Settings':'Configuración de la aplicación','Set the display name and the Portal address used when login re-check fails.':'Configura el nombre mostrado y la dirección del Portal usada cuando falla la nueva comprobación de inicio de sesión.','Company name':'Nombre de la empresa','Save Name':'Guardar nombre','Portal fallback URL':'URL alternativa del Portal','Save URL':'Guardar URL','If session re-check fails, the browser redirects to this fixed http/https address.':'Si falla la nueva comprobación de la sesión, el navegador redirige a esta dirección http/https fija.','Facebook Marketplace Provider Chain':'Cadena de proveedores de Facebook Marketplace','Only providers that passed a real Marketplace test can be added. Disabled providers stay in the list but are skipped.':'Solo se pueden añadir proveedores que hayan superado una prueba real de Marketplace. Los deshabilitados permanecen en la lista, pero se omiten.','Priority':'Prioridad','Website ↗':'Sitio web ↗','Token:':'Token:','Stored':'Guardado','None':'Ninguno','Last test:':'Última prueba:','Tested':'Probado','Needs test':'Necesita prueba','Enabled':'Habilitado','Disabled':'Deshabilitado','Remove':'Quitar','No providers yet':'Aún no hay proveedores','Click + Add Provider, test it, then add it to the chain.':'Haz clic en + Añadir proveedor, pruébalo y luego añádelo a la cadena.','Recent Provider Jobs':'Trabajos recientes de proveedores','Test attempts and live failover attempts are logged here.':'Aquí se registran los intentos de prueba y los intentos reales de failover.','Live':'En vivo','Time':'Hora','Last 1 Hour':'Última hora','Last 24 Hours':'Últimas 24 horas','Last 7 Days':'Últimos 7 días','Last 30 Days':'Últimos 30 días','All Time':'Todo el tiempo','jobs':'trabajos','providers':'proveedores','existing product URLs skipped':'URL de productos existentes omitidas','or':'o',', then':', luego','User':'Usuario','Provider':'Proveedor','Item':'Elemento','Status':'Estado','HTTP':'HTTP','Error':'Error','Ready':'Listo','Failed':'Fallido','Running':'En ejecución','Starting':'Iniciando','No provider jobs in this time range.':'No hay trabajos de proveedores en este intervalo.','Previous':'Anterior','Page':'Página','of':'de','Next':'Siguiente','Close':'Cerrar','Enter the provider/API settings, run a real test, then Add Provider becomes available.':'Introduce la configuración del proveedor/API, ejecuta una prueba real y después se habilitará Añadir proveedor.','Provider Type':'Tipo de proveedor','Name':'Nombre','Website Link':'Enlace del sitio web','API Endpoint':'Endpoint API','Token / API Key':'Token / Clave API','Stored encrypted after the provider passes its test.':'Se guarda cifrado después de que el proveedor supera la prueba.','Test Facebook Marketplace URL':'URL de Facebook Marketplace para prueba','The test must return ID, title, description, and a real listing date.':'La prueba debe devolver ID, título, descripción y una fecha real del anuncio.','Marketplace Dataset ID':'ID del dataset de Marketplace','Max Wait':'Espera máxima','Poll Every':'Consultar cada','sec':'s','Request Method':'Método de solicitud','Authentication':'Autenticación','Bearer Token':'Token Bearer','Custom Header':'Header personalizado','Query Parameter':'Parámetro de consulta','No Authentication':'Sin autenticación','Header / Query Name':'Nombre de Header / Query','Listing URL Input':'Entrada de URL del anuncio','JSON Body':'Cuerpo JSON','Listing URL Field Name':'Nombre del campo URL del anuncio','JSON Field Mapping':'Mapeo de campos JSON','Dot notation is supported, for example':'Se admite notación con puntos, por ejemplo','Item ID Path':'Ruta del ID','Title Path':'Ruta del título','Description Path':'Ruta de la descripción','Listing Date Path':'Ruta de la fecha del anuncio','Canonical URL Path':'Ruta de la URL canónica','Test Provider':'Probar proveedor','Testing...':'Probando...','Test passed.':'Prueba superada.','Provider test failed.':'Falló la prueba del proveedor.','Listing date':'Fecha del anuncio','Title':'Título','Description':'Descripción','Duplicate Sources':'Fuentes de duplicados','Company Website Library':'Biblioteca del sitio web de la empresa','Website Scan opens one detailed workspace. Scanned Products lives inside that workspace below Website Scan and opens or closes independently. URL CSV and Page / Sitemap Import remain separate tools.':'Website Scan abre un espacio de trabajo detallado. Scanned Products vive dentro de ese espacio y se abre o cierra de forma independiente. URL CSV y Page / Sitemap Import siguen siendo herramientas separadas.','references':'referencias','images pending':'imágenes pendientes','Website Scan':'Escaneo del sitio web','Add websites, run scans and review each website\'s scan history.':'Añade sitios web, ejecuta escaneos y revisa el historial de cada sitio.','website':'sitio web','websites':'sitios web','URL CSV':'CSV de URL','Import a prepared CSV; the website is detected from its URLs automatically.':'Importa un CSV preparado; el sitio web se detecta automáticamente a partir de sus URL.','import record':'registro de importación','import records':'registros de importación','Page / Sitemap Import':'Importación de página / Sitemap','Scan one page or sitemap; its website is detected automatically from the URL.':'Escanea una página o sitemap; el sitio web se detecta automáticamente a partir de la URL.','scan/import record':'registro de escaneo/importación','scan/import records':'registros de escaneo/importación','The Website list belongs inside Step 1. Starting a scan saves a new website automatically.':'La lista de sitios pertenece al Paso 1. Al iniciar un escaneo se guarda automáticamente un sitio nuevo.','No website sources yet. Enter a URL above and click Scan Website.':'Aún no hay fuentes web. Introduce una URL arriba y pulsa Escanear sitio web.','Saved Websites':'Sitios guardados','Click a website card to open scan controls, live counters and scan history.':'Haz clic en una tarjeta de sitio para abrir controles, contadores en vivo e historial de escaneo.','Open Website ↗':'Abrir sitio ↗','Delete Website':'Eliminar sitio','History for this website only.':'Historial solo para este sitio.','Product Scan History':'Historial de escaneo de productos','Scanned Products':'Productos escaneados','Search, open, add or delete saved product URLs without opening an individual Website Scan card.':'Busca, abre, añade o elimina URL de productos guardadas sin abrir una tarjeta individual de Website Scan.','matching products':'productos coincidentes','unique products':'productos únicos','products ·':'productos ·','first images':'primeras imágenes','pages checked':'páginas comprobadas','Search':'Buscar','+ Add URL':'+ Añadir URL','Add URL':'Añadir URL','Website URL':'URL del sitio','Page URL':'URL de la página','First image URL':'URL de la primera imagen','Action':'Acción','Actions':'Acciones','Save':'Guardar','Delete':'Eliminar','Image':'Imagen','Title / Description':'Título / Descripción','Indexed':'Indexado','No saved websites':'No hay sitios guardados','No saved websites yet.':'Aún no hay sitios guardados.','Open Scanned Products to load products.':'Abre Scanned Products para cargar productos.','CSV file':'Archivo CSV','Import CSV':'Importar CSV','Download Sample CSV':'Descargar CSV de ejemplo','CSV Import History':'Historial de importación CSV','Processed, saved and failed row counts are kept per run.':'Se conservan los recuentos de filas procesadas, guardadas y fallidas por ejecución.','records':'registros','Page / Sitemap URL':'URL de página / Sitemap','Scan & Import':'Escanear e importar','Scan & Import History':'Historial de escaneo e importación','Every manual page/sitemap run is recorded with its source URL and result.':'Cada ejecución manual de página/sitemap se registra con su URL de origen y resultado.','Started':'Iniciado','Website':'Sitio web','Processed':'Procesado','Saved':'Guardado','Details':'Detalles','No CSV import history yet.':'Aún no hay historial de importación CSV.','No Page / Sitemap import history yet.':'Aún no hay historial de importación de página/Sitemap.','No scan history yet.':'Aún no hay historial de escaneo.','Scan Website':'Escanear sitio web','Run':'Ejecutar','total':'total','product reference':'referencia de producto','fingerprints':'huellas','images':'imágenes','active':'activos','Enter a page or sitemap URL; its website is detected automatically. History stays below.':'Introduce una URL de página o sitemap; el sitio se detecta automáticamente. El historial queda abajo.','Upload a CSV; its URL column determines the website automatically. Import history stays below.':'Sube un CSV; su columna URL determina automáticamente el sitio. El historial de importación queda abajo.','Search/add/delete applies only to':'Buscar/añadir/eliminar se aplica solo a','Save a website in Website Scan first.':'Guarda primero un sitio en Website Scan.','No description':'Sin descripción','Image found':'Imagen encontrada','No image':'Sin imagen','Loading products…':'Cargando productos…','Could not load products.':'No se pudieron cargar los productos.','No matching scanned products for this website.':'No hay productos escaneados coincidentes para este sitio.','Pause this scan':'Pausar este escaneo','Continue this scan':'Continuar este escaneo','Scan stopped':'Escaneo detenido','Scan completed':'Escaneo completado','Scan failed':'Escaneo fallido','Scan status':'Estado del escaneo','Starting scan':'Iniciando escaneo','Processing log':'Registro de procesamiento','The run is being created now.':'La ejecución se está creando ahora.','Each scanned URL is recorded here as it finishes.':'Cada URL escaneada se registra aquí al terminar.','Preparing next URL…':'Preparando la siguiente URL…','Now':'Ahora','Scanning':'Escaneando','Request in progress…':'Solicitud en curso…','No per-URL records yet.':'Aún no hay registros por URL.','Processing log could not be loaded.':'No se pudo cargar el registro de procesamiento.','No per-URL processing records were stored for this older scan.':'No se guardaron registros por URL para este escaneo anterior.','No per-URL processing records were stored for this scan.':'No se guardaron registros por URL para este escaneo.','Enter a company website first.':'Introduce primero el sitio web de la empresa.','Enter a valid HTTPS website URL.':'Introduce una URL HTTPS válida.','Company website scanning requires https://.':'El escaneo del sitio requiere https://.','Select a website first.':'Selecciona primero un sitio.','Website URL added.':'URL del sitio añadida.','Location name':'Nombre de ubicación'
    }
};

/* v0.2.122 — exact Settings strings that were still able to remain in English. */
Object.assign(settingsPageTextDictionary['zh-CN'],{
    'Custom JSON API':'自定义 JSON API','Drag to reorder':'拖动重新排序','Drag to change priority':'拖动调整优先级',
    'Provider jobs time range':'Provider 任务时间范围','Close Website Scan':'关闭网站扫描','Search title, URL or description':'搜索标题、URL 或描述',
    'Close URL CSV':'关闭 URL CSV','Close Page / Sitemap Import':'关闭页面 / Sitemap 导入','Click to view processing log.':'点击查看处理日志。',
    'No additional details recorded.':'没有记录更多详情。','Click this row to load the per-URL processing log.':'点击此行加载逐 URL 处理日志。',
    'Result':'结果','Updated':'更新时间','Completed':'已完成','Paused':'已暂停','Stopped':'已停止'
});
Object.assign(settingsPageTextDictionary['zh-TW'],{
    'Custom JSON API':'自訂 JSON API','Drag to reorder':'拖曳重新排序','Drag to change priority':'拖曳調整優先順序',
    'Provider jobs time range':'Provider 任務時間範圍','Close Website Scan':'關閉網站掃描','Search title, URL or description':'搜尋標題、URL 或描述',
    'Close URL CSV':'關閉 URL CSV','Close Page / Sitemap Import':'關閉頁面 / Sitemap 匯入','Click to view processing log.':'點擊查看處理記錄。',
    'No additional details recorded.':'沒有記錄更多詳情。','Click this row to load the per-URL processing log.':'點擊此列載入逐 URL 處理記錄。',
    'Result':'結果','Updated':'更新時間','Completed':'已完成','Paused':'已暫停','Stopped':'已停止'
});
Object.assign(settingsPageTextDictionary.es,{
    'Custom JSON API':'API JSON personalizada','Drag to reorder':'Arrastrar para reordenar','Drag to change priority':'Arrastrar para cambiar la prioridad',
    'Provider jobs time range':'Rango de tiempo de trabajos del proveedor','Close Website Scan':'Cerrar escaneo del sitio web','Search title, URL or description':'Buscar título, URL o descripción',
    'Close URL CSV':'Cerrar CSV de URL','Close Page / Sitemap Import':'Cerrar importación de página / Sitemap','Click to view processing log.':'Haz clic para ver el registro de procesamiento.',
    'No additional details recorded.':'No se registraron más detalles.','Click this row to load the per-URL processing log.':'Haz clic en esta fila para cargar el registro por URL.',
    'Result':'Resultado','Updated':'Actualizado','Completed':'Completado','Paused':'Pausado','Stopped':'Detenido'
});

const settingsPageOriginalText=new WeakMap();
const settingsPageOriginalAttributes=new WeakMap();
let settingsPageLanguageApplying=false;
let settingsPageMutationTimer=null;
let settingsPageMutationObserver=null;

function settingsPageTranslateCountPhrase(base,lang){
    const match=String(base||'').match(/^(\d+)\s+(providers|jobs|records|references|websites|images|fingerprints|active)$/i);
    if(!match || lang==='en') return '';
    const count=match[1],unit=match[2].toLowerCase();
    if(lang==='zh-CN'){
        const units={providers:'个 Provider',jobs:'个任务',records:'条记录',references:'条 Reference',websites:'个网站',images:'张图片',fingerprints:'个指纹',active:'个活动锁'};
        return count+' '+(units[unit]||unit);
    }
    if(lang==='zh-TW'){
        const units={providers:'個 Provider',jobs:'個任務',records:'筆記錄',references:'筆 Reference',websites:'個網站',images:'張圖片',fingerprints:'個指紋',active:'個活動鎖'};
        return count+' '+(units[unit]||unit);
    }
    if(lang==='es'){
        const units={providers:'proveedores',jobs:'trabajos',records:'registros',references:'referencias',websites:'sitios web',images:'imágenes',fingerprints:'huellas',active:'activos'};
        return count+' '+(units[unit]||unit);
    }
    return '';
}

function settingsPageTranslateDynamicPhrase(normalized,lang,dictionary){
    if(!normalized || lang==='en') return '';
    let match=normalized.match(/^Token:\s*(Stored|None)$/i);
    if(match) return (dictionary['Token:']||'Token:')+' '+(dictionary[match[1]]||match[1]);
    match=normalized.match(/^Last test:\s*(.+)$/i);
    if(match) return (dictionary['Last test:']||'Last test:')+' '+match[1];
    match=normalized.match(/^Updated\s+(.+)$/i);
    if(match) return (dictionary['Updated']||'Updated')+' '+match[1];
    match=normalized.match(/^(\d+)\s+products\s+·\s+(\d+)\s+images$/i);
    if(match){
        if(lang==='zh-CN') return match[1]+' 个产品 · '+match[2]+' 张图片';
        if(lang==='zh-TW') return match[1]+' 個產品 · '+match[2]+' 張圖片';
        if(lang==='es') return match[1]+' productos · '+match[2]+' imágenes';
    }
    const unitPatterns={
        'product reference':['条产品 Reference','筆產品 Reference','referencias de producto'],
        'product references':['条产品 Reference','筆產品 Reference','referencias de producto'],
        'matching products':['个匹配产品','個符合產品','productos coincidentes'],
        'unique products':['个唯一产品','個唯一產品','productos únicos'],
        'first images':['张首图','張首圖','primeras imágenes'],
        'pages checked':['个页面已检查','個頁面已檢查','páginas comprobadas'],
        'import record':['条导入记录','筆匯入記錄','registro de importación'],
        'import records':['条导入记录','筆匯入記錄','registros de importación'],
        'scan/import record':['条扫描/导入记录','筆掃描/匯入記錄','registro de escaneo/importación'],
        'scan/import records':['条扫描/导入记录','筆掃描/匯入記錄','registros de escaneo/importación']
    };
    match=normalized.match(/^(\d+)\s+(.+)$/);
    if(match && unitPatterns[match[2]]){
        const idx=lang==='zh-CN'?0:(lang==='zh-TW'?1:2);
        return match[1]+' '+unitPatterns[match[2]][idx];
    }
    return '';
}

function settingsPageTranslateText(base,lang){
    const raw=String(base==null?'':base);
    const normalized=raw.replace(/\s+/g,' ').trim();
    if(!normalized || lang==='en') return raw;
    const dictionary=settingsPageTextDictionary[lang]||{};
    let translated=dictionary[normalized]||settingsPageTranslateCountPhrase(normalized,lang)||settingsPageTranslateDynamicPhrase(normalized,lang,dictionary)||'';
    if(!translated){
        const pageMatch=normalized.match(/^Page\s+(\d+)\s+of\s+(\d+)$/i);
        if(pageMatch){
            if(lang==='zh-CN') translated='第 '+pageMatch[1]+' 页，共 '+pageMatch[2]+' 页';
            else if(lang==='zh-TW') translated='第 '+pageMatch[1]+' 頁，共 '+pageMatch[2]+' 頁';
            else if(lang==='es') translated='Página '+pageMatch[1]+' de '+pageMatch[2];
        }
    }
    if(!translated) return raw;
    const leading=(raw.match(/^\s*/)||[''])[0];
    const trailing=(raw.match(/\s*$/)||[''])[0];
    return leading+translated+trailing;
}

function settingsPageTranslateAttribute(element,name,lang){
    if(!element || !element.hasAttribute(name)) return;
    let originals=settingsPageOriginalAttributes.get(element);
    if(!originals){ originals={}; settingsPageOriginalAttributes.set(element,originals); }
    if(!(name in originals)) originals[name]=element.getAttribute(name)||'';
    const translated=settingsPageTranslateText(originals[name],lang);
    if(element.getAttribute(name)!==translated) element.setAttribute(name,translated);
}

function applySettingsPageLanguage(){
    if(settingsPageLanguageApplying || !document.getElementById('providerAddOpen')) return;
    const root=document.querySelector('main.container')||document.querySelector('main');
    if(!root) return;
    settingsPageLanguageApplying=true;
    try{
        const lang=currentAppLanguage();
        /* v0.2.122: do not depend on NodeFilter/TreeWalker for Settings translation.
         * Walk direct text-node children of every element instead. This keeps the
         * proven data-app-i18n path intact and also covers legacy Settings markup.
         */
        const elements=[root].concat(Array.from(root.querySelectorAll('*')));
        elements.forEach(function(parent){
            if(!parent || !parent.childNodes) return;
            if(parent.matches && parent.matches('script,style,pre,code,[data-app-i18n],[data-app-i18n-count],[data-nav-i18n]')) return;
            if(parent.closest && parent.closest('[data-app-i18n],[data-app-i18n-count],[data-nav-i18n]')) return;
            Array.from(parent.childNodes).forEach(function(node){
                if(!node || node.nodeType!==3) return;
                try{
                    if(!settingsPageOriginalText.has(node)) settingsPageOriginalText.set(node,node.nodeValue||'');
                    const original=settingsPageOriginalText.get(node)||'';
                    const translated=settingsPageTranslateText(original,lang);
                    if(node.nodeValue!==translated) node.nodeValue=translated;
                }catch(_error){
                    /* One unusual node must never stop the rest of Settings from translating. */
                }
            });
        });
        root.querySelectorAll('[placeholder],[title],[aria-label]').forEach(function(element){
            ['placeholder','title','aria-label'].forEach(function(name){
                try{ settingsPageTranslateAttribute(element,name,lang); }catch(_error){}
            });
        });
    }finally{
        settingsPageLanguageApplying=false;
    }
}

function startSettingsPageLanguageObserver(){
    if(settingsPageMutationObserver || !document.getElementById('providerAddOpen')) return;
    const root=document.querySelector('main.container');
    if(!root || typeof MutationObserver==='undefined') return;
    settingsPageMutationObserver=new MutationObserver(function(){
        if(settingsPageLanguageApplying) return;
        window.clearTimeout(settingsPageMutationTimer);
        settingsPageMutationTimer=window.setTimeout(applySettingsPageLanguage,0);
    });
    settingsPageMutationObserver.observe(root,{subtree:true,childList:true,characterData:true,attributes:true,attributeFilter:['placeholder','title','aria-label']});
}

/**
 * EN: Perform the current app language behavior used by the application UI.
 * 中文：执行application UI 使用的“current app language”行为。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function currentAppLanguage(){
    const lang=localStorage.getItem('cdsp-admin-language')||'en';
    return appLanguageDictionary[lang]?lang:'en';
}

/**
 * EN: Update the apply global menu language behavior used by the application UI.
 * 中文：更新application UI 使用的“apply global menu language”行为。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function applyGlobalMenuLanguage(){
    const lang=currentAppLanguage();
    const dict=appLanguageDictionary[lang];

    $('[data-nav-i18n]').each(function(){
        const key=String($(this).data('nav-i18n')||'');

        if(dict[key]){
            $(this).text(dict[key]);
        }
    });

    $('[data-app-i18n]').each(function(){
        const key=String($(this).data('app-i18n')||'');
        if(dict[key]){
            $(this).text(dict[key]);
        }
    });

    $('[data-app-i18n-count]').each(function(){
        const key=String($(this).data('app-i18n-count')||'');
        const count=String($(this).data('i18n-count')??'0');
        if(dict[key]){
            $(this).text(String(dict[key]).replace('{count}',count));
        }
    });

    $('#appLanguageSwitch [data-app-lang]').each(function(){
        const active=String($(this).data('app-lang'))===lang;

        $(this)
            .toggleClass('active',active)
            .attr('aria-pressed',active?'true':'false');
    });

    document.documentElement.lang=lang;
    applySettingsPageLanguage();
}

applyGlobalMenuLanguage();
startSettingsPageLanguageObserver();

$('#appLanguageSwitch').on(
    'click',
    '[data-app-lang]',
    function(){
        const lang=String($(this).data('app-lang')||'en');

        if(!appLanguageDictionary[lang]){
            return;
        }

        localStorage.setItem('cdsp-admin-language',lang);
        applyGlobalMenuLanguage();

        $(document).trigger('cdsp:language-changed',[lang]);
    }
);

/*
 * EN: Mobile navigation controller. The compact header stays one line tall;
 * the route list opens only when the hamburger is explicitly toggled.
 * 中文：手机导航控制器。紧凑顶栏保持单行，只有用户点击汉堡按钮时才展开路由菜单。
 */
/**
 * EN: Update the set mobile navigation open behavior used by the application UI.
 * 中文：更新application UI 使用的“set mobile navigation open”行为。
 *
 * @param {*} open Open value used by this function. / 本函数使用的“open”参数值。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function setMobileNavigationOpen(open){
    const $nav=$('.topbar .nav');
    const $toggle=$('#mobileNavToggle');
    const shouldOpen=Boolean(open);

    if(shouldOpen){
        $('#adminInfoPanel').addClass('hidden');
        $('#adminInfoToggle').attr('aria-expanded','false');
    }

    $nav.toggleClass('mobile-open',shouldOpen);
    $toggle.attr('aria-expanded',shouldOpen?'true':'false');
    $toggle.attr('aria-label',shouldOpen?'Close navigation menu':'Open navigation menu');
}

$('#mobileNavToggle').on('click',function(event){
    event.preventDefault();
    event.stopPropagation();
    setMobileNavigationOpen(!$('.topbar .nav').hasClass('mobile-open'));
});

$('#appPrimaryNav').on('click','a,button',function(){
    if(window.matchMedia('(max-width:1050px)').matches){
        setMobileNavigationOpen(false);
    }
});

$(document).on('click.cdspMobileNav',function(event){
    if(!window.matchMedia('(max-width:1050px)').matches){
        return;
    }
    if($(event.target).closest('.topbar .nav').length===0){
        setMobileNavigationOpen(false);
    }
});

$(document).on('keydown.cdspMobileNav',function(event){
    if(event.key==='Escape'){
        setMobileNavigationOpen(false);
    }
});

$(window).on('resize.cdspMobileNav',function(){
    if(!window.matchMedia('(max-width:1050px)').matches){
        setMobileNavigationOpen(false);
    }
});

    $('#adminInfoToggle').on('click',function(event){
        event.preventDefault();
        event.stopPropagation();
        const $panel=$('#adminInfoPanel');
        const opening=$panel.hasClass('hidden');

        if(opening && window.matchMedia('(max-width:1050px)').matches){
            setMobileNavigationOpen(false);
        }

        $panel.toggleClass('hidden',!opening);
        $(this).attr('aria-expanded',opening?'true':'false');
    });

    $('#adminInfoPanel').on('click',function(event){
        event.stopPropagation();
    });

    const $deleteRequestModal=$('#adminDeleteRequestPostModal');
    let activeDeleteRequestId=0;
    let activeDeletePostId=0;
    let activeDeleteRequestRow=null;
    let deleteRequestPostXhr=null;

    /**
     * EN: Perform the info escape html behavior used by the application UI.
     * 中文：执行application UI 使用的“info escape html”行为。
     *
     * @param {string|*} value Value read, transformed, or applied by this function. / 本函数读取、转换或应用的值。
     *
     * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
     */
    function infoEscapeHtml(value){
        return $('<div>').text(value==null?'':String(value)).html();
    }

    /**
     * EN: Update the update admin info count behavior used by the application UI.
     * 中文：更新application UI 使用的“update admin info count”行为。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function updateAdminInfoCount(){
        const count=$('#adminInfoList .admin-info-item').length;
        $('#adminInfoPendingCount').text(count+' pending');
        const $badge=$('.admin-info-badge');
        $badge.text(count).toggleClass('hidden',count<1);
        if(count<1){
            $('#adminInfoList').html('<div class="admin-info-empty">No new notifications.</div>');
        }
    }

    /**
     * EN: Close or clear the close delete request post modal behavior used by the application UI.
     * 中文：关闭或清理application UI 使用的“close delete request post modal”行为。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function closeDeleteRequestPostModal(){
        if(deleteRequestPostXhr&&deleteRequestPostXhr.readyState!==4){
            deleteRequestPostXhr.abort();
        }
        deleteRequestPostXhr=null;
        activeDeleteRequestId=0;
        activeDeletePostId=0;
        activeDeleteRequestRow=null;
        $deleteRequestModal.addClass('hidden').attr('aria-hidden','true');
        $('body').removeClass('admin-delete-request-modal-open');
        $('#adminDeleteRequestBody,#adminDeleteRequestFooter').addClass('hidden');
        $('#adminDeleteRequestLoading').removeClass('hidden').text('Loading post…');
        $('#adminDeleteRequestStatus').removeClass('error ok').text('');
        $('#adminDeleteRequestPhotos').empty();
        $('#adminDeleteRequestOriginal').addClass('hidden').attr('href','#');
        $('#adminDeleteRequestApprove,#adminDeleteRequestReject').prop('disabled',false);
    }

    /**
     * EN: Delete or remove the delete request photo html behavior used by the application UI.
     * 中文：删除或移除application UI 使用的“delete request photo html”行为。
     *
     * @param {string|*} url URL read, generated, or requested by this function. / 本函数读取、生成或请求的 URL。
     *
     * @returns {string} String result produced by this UI helper. / 本 UI 辅助函数生成的字符串结果。
     */
    function deleteRequestPhotoHtml(url){
        const safe=String(url||'');
        if(!safe)return '';
        return '<a href="'+infoEscapeHtml(safe)+'" target="_blank" rel="noopener noreferrer" class="admin-delete-request-photo">'
            +'<img src="'+infoEscapeHtml(safe)+'" alt="Post image" loading="lazy">'
            +'</a>';
    }

    /**
     * EN: Open or show the open delete request post modal behavior used by the application UI.
     * 中文：打开或显示application UI 使用的“open delete request post modal”行为。
     *
     * @param {*} $row $row value used by this function. / 本函数使用的“$row”参数值。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function openDeleteRequestPostModal($row){
        const requestId=parseInt($row.attr('data-info-request-id')||'0',10)||0;
        const postId=parseInt($row.attr('data-info-post-id')||'0',10)||0;
        if(!requestId||!postId||!$deleteRequestModal.length)return;

        if(deleteRequestPostXhr&&deleteRequestPostXhr.readyState!==4){
            deleteRequestPostXhr.abort();
        }

        activeDeleteRequestId=requestId;
        activeDeletePostId=postId;
        activeDeleteRequestRow=$row;
        $('#adminInfoPanel').addClass('hidden');
        $('#adminInfoToggle').attr('aria-expanded','false');
        $('#adminDeleteRequestBody,#adminDeleteRequestFooter').addClass('hidden');
        $('#adminDeleteRequestLoading').removeClass('hidden').text('Loading post…');
        $('#adminDeleteRequestStatus').removeClass('error ok').text('');
        $('#adminDeleteRequestReason').text(String($row.attr('data-info-reason')||'—'));
        renderMarketplaceAccount($('#adminDeleteRequestAccountFact'),$('#adminDeleteRequestAccount'),null);
        $deleteRequestModal.removeClass('hidden').attr('aria-hidden','false');
        $('body').addClass('admin-delete-request-modal-open');

        deleteRequestPostXhr=$.ajax({
            url:$deleteRequestModal.attr('data-post-url'),
            method:'GET',
            dataType:'json',
            cache:false,
            data:{id:postId,_:Date.now()},
            headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}
        }).done(function(data){
            if(!data||!data.ok||activeDeletePostId!==postId)return;
            const post=data.post||{};
            const content=data.content||{};
            $('#adminDeleteRequestTitle').text(content.title||'Post details');
            $('#adminDeleteRequestSubtitle').text('Delete request · '+(post.sales_name||'Sales'));
            $('#adminDeleteRequestSales').text(post.sales_name||'—');
            $('#adminDeleteRequestPlatform').text(post.platform||'—');
            $('#adminDeleteRequestPublished').text(post.published_at||post.published_date||'—');
            $('#adminDeleteRequestPostId').text(post.external_post_id||post.id||'—');
            renderMarketplaceAccount(
                $('#adminDeleteRequestAccountFact'),
                $('#adminDeleteRequestAccount'),
                {id:post.platform_account_id||'',name:post.platform_account_name||'',url:post.platform_account_url||''}
            );
            $('#adminDeleteRequestPostTitle').text(content.title||'Untitled post');
            $('#adminDeleteRequestDescription').text(content.description||'No description saved.');
            const photos=Array.isArray(content.photos)?content.photos:[];
            $('#adminDeleteRequestPhotos').html(photos.map(deleteRequestPhotoHtml).join(''));
            if(post.canonical_url){
                $('#adminDeleteRequestOriginal').removeClass('hidden').attr('href',post.canonical_url);
            }else{
                $('#adminDeleteRequestOriginal').addClass('hidden').attr('href','#');
            }
            $('#adminDeleteRequestLoading').addClass('hidden');
            $('#adminDeleteRequestBody,#adminDeleteRequestFooter').removeClass('hidden');
        }).fail(function(xhr,status){
            if(status==='abort')return;
            const data=xhr.responseJSON||{};
            $('#adminDeleteRequestLoading').text(data.message||'Post could not be loaded.');
        });
    }

    $('#adminInfoPanel').on('click','[data-info-open-post]',function(event){
        event.preventDefault();
        event.stopPropagation();
        openDeleteRequestPostModal($(this).closest('.admin-info-item'));
    });

    $(document).on('click','[data-delete-request-modal-close]',function(event){
        event.preventDefault();
        closeDeleteRequestPostModal();
    });

    /**
     * EN: Submit or persist the submit delete request action behavior used by the application UI.
     * 中文：提交或保存application UI 使用的“submit delete request action”行为。
     *
     * @param {*} action Action value used by this function. / 本函数使用的“action”参数值。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function submitDeleteRequestAction(action){
        if(!activeDeleteRequestId)return;
        const $approve=$('#adminDeleteRequestApprove');
        const $reject=$('#adminDeleteRequestReject');
        const $status=$('#adminDeleteRequestStatus');
        $approve.add($reject).prop('disabled',true);
        $status.removeClass('error ok').text(action==='approve'?'Deleting post…':'Rejecting request…');
        $.ajax({
            url:$deleteRequestModal.attr('data-action-url'),
            method:'POST',
            dataType:'json',
            data:{
                _csrf:$deleteRequestModal.attr('data-csrf'),
                request_id:activeDeleteRequestId,
                action:action
            },
            headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}
        }).done(function(data){
            if(!data||!data.ok){
                $status.addClass('error').text((data&&data.message)||'Request could not be updated.');
                $approve.add($reject).prop('disabled',false);
                return;
            }
            $status.addClass('ok').text(data.message||'Updated.');
            const $row=activeDeleteRequestRow;
            window.setTimeout(function(){
                closeDeleteRequestPostModal();
                if($row&&$row.length){
                    $row.stop(true,true).slideUp(160,function(){
                        $(this).remove();
                        updateAdminInfoCount();
                    });
                }else{
                    updateAdminInfoCount();
                }
            },280);
        }).fail(function(xhr){
            $status.addClass('error').text((xhr.responseJSON&&xhr.responseJSON.message)||'Request could not be updated.');
            $approve.add($reject).prop('disabled',false);
        });
    }

    $('#adminDeleteRequestApprove').on('click',function(){submitDeleteRequestAction('approve');});
    $('#adminDeleteRequestReject').on('click',function(){submitDeleteRequestAction('reject');});

    $(document).on('keydown',function(event){
        if(event.key==='Escape'&&!$deleteRequestModal.hasClass('hidden')){
            closeDeleteRequestPostModal();
        }
    });

    $(document).on('click',function(){
        $('#adminInfoPanel').addClass('hidden');
        $('#adminInfoToggle').attr('aria-expanded','false');
    });

    /**
     * EN: Perform the detect platform behavior used by the application UI.
     * 中文：执行application UI 使用的“detect platform”行为。
     *
     * @param {string|*} url URL read, generated, or requested by this function. / 本函数读取、生成或请求的 URL。
     *
     * @returns {string} String result produced by this UI helper. / 本 UI 辅助函数生成的字符串结果。
     */
    function detectPlatform(url){
        try{
            const u = new URL((url || '').trim());
            const h = u.hostname.toLowerCase().replace(/^www\./,'');

            if(h === 'facebook.com' || h.endsWith('.facebook.com')) return 'facebook';
            if(h === 'offerup.com' || h.endsWith('.offerup.com') || h === 'offerup.co') return 'offerup';
            if(h === 'craigslist.org' || h.endsWith('.craigslist.org')) return 'craigslist';
        }catch(e){}
        return '';
    }

    /**
     * EN: Format or normalize the normalize post url behavior used by the application UI.
     * 中文：格式化或规范化application UI 使用的“normalize post url”行为。
     *
     * @param {string|*} url URL read, generated, or requested by this function. / 本函数读取、生成或请求的 URL。
     * @param {*} platform Platform value used by this function. / 本函数使用的“platform”参数值。
     *
     * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
     */
    function normalizePostUrl(url, platform){
        const raw = (url || '').trim();

        if(platform === 'facebook'){
            const m = raw.match(/https?:\/\/(?:[a-z0-9-]+\.)?facebook\.com\/marketplace\/item\/(\d+)/i);
            if(m){
                return 'https://www.facebook.com/marketplace/item/' + m[1];
            }
        }

        if(platform === 'offerup'){
            const m = raw.match(/https?:\/\/(?:www\.)?offerup\.com\/item\/detail\/([a-z0-9-]+)/i);
            if(m){
                return 'https://offerup.com/item/detail/' + m[1];
            }
        }

        if(platform === 'craigslist'){
            const m = raw.match(/https?:\/\/(?:[a-z0-9-]+\.)?craigslist\.org\/[^\s]*?\/\d{8,}\.html/i);
            if(m){
                return m[0];
            }
        }

        return raw;
    }

    /**
     * EN: Perform the platform label behavior used by the application UI.
     * 中文：执行application UI 使用的“platform label”行为。
     *
     * @param {*} platform Platform value used by this function. / 本函数使用的“platform”参数值。
     *
     * @returns {string} String result produced by this UI helper. / 本 UI 辅助函数生成的字符串结果。
     */
    function platformLabel(platform){
        if(platform === 'facebook') return 'Facebook';
        if(platform === 'instagram') return 'Instagram';
        if(platform === 'offerup') return 'OfferUp';
        if(platform === 'craigslist') return 'Craigslist';
        return '';
    }

    // EN: The Submit modal can be closed while /api/inspect keeps running on the
    // server. Keep a page-level busy flag so reopening the modal cannot re-enable
    // Check Post before the server-side process lock has been released.
    // 中文：用户可以在 /api/inspect 仍在服务器运行时关闭 Submit 弹窗。这里保留页面级
    // busy 状态，确保重新打开弹窗时不会在服务器进程锁释放前重新启用 Check Post。
    const SALES_INSPECTION_BUSY_KEY='cdsp-sales-inspection-busy';
    let salesInspectionBusy=false;
    let salesInspectionRequest=null;
    let salesInspectionStatusTimer=null;
    // v0.2.97: full verification may continue only after the two fast preflight checks pass.
    let salesContinueAfterPreflight=false;

    try{
        salesInspectionBusy=sessionStorage.getItem(SALES_INSPECTION_BUSY_KEY)==='1';
    }catch(error){
        salesInspectionBusy=false;
    }

    /**
     * EN: Update the update detected platform behavior used by the application UI.
     * 中文：更新application UI 使用的“update detected platform”行为。
     *
     * @returns {boolean} Boolean result produced by this UI helper. / 本 UI 辅助函数生成的布尔结果。
     */
    function updateDetectedPlatform(){
        const originalUrl = $('#postUrl').val() || '';
        const platform = detectPlatform(originalUrl);
        const url = platform ? normalizePostUrl(originalUrl, platform) : originalUrl;
        const $label = $('#detectedPlatform');

        if(platform && url && url !== originalUrl.trim()){
            $('#postUrl').val(url);
        }

        $('#detectedPlatformValue').val(platform);
        $('#inspectButton').prop('disabled', salesInspectionBusy || !platform);
        $('#saveWaitButton').prop('disabled',salesInspectionBusy||!platform||$('#salesPreflightActions').hasClass('hidden'));

        $label
            .removeClass('facebook offerup craigslist empty-platform')
            .addClass(platform || 'empty-platform')
            .text(
                platform
                    ?platformLabel(platform)
                    :(url.trim()
                        ?salesTr('unsupportedUrl')
                        :salesTr('pasteSupported'))
            );

        return platform;
    }


const salesI18n={
    en:{
        greeting:'Hi, {name}',
        dashboardTitle:'My Sales Activity',
        dashboardSubtitle:'Review your verified Marketplace posts and Admin review status.',
        salesPostSearchLabel:'Post Search',
        salesPostSearchPlaceholder:'Search original Post link, Post ID, title or platform',
        salesPostSearchHelp:'Search all of your saved Posts across every date.',
        salesPostSearchMatches:'Search results',
        salesPostSearching:'Searching Posts',
        salesPostSearchEmpty:'No matching Posts found.',
        salesPostSearchFailed:'Could not search Posts.',
        salesPostSearchClear:'Clear Post Search',
        activityChart:'Posting Activity',
        dailyProgress:'Daily Post Progress',
        targetLine:'Daily target',
        channels:'Channels',
        allPlatforms:'All',
        missing:'Missing',
        total:'Total',
        allPosts:'All',
        viewDetails:'View details',
        noImage:'No listing image',
        close:'Close',
        daily:'Daily',
        oneDay:'1 Day',
        oneDayProgressTitle:'1-Day Post Progress',
        noFilteredPosts:'No {status} posts in this range.',
        threeDays:'3 Days',
        dailyProgressTitle:'3-Day Post Progress',
        weeklyProgressTitle:'Weekly Post Progress',
        monthlyProgressTitle:'Monthly Post Progress',
        weekly:'Weekly',
        monthly:'Monthly',
        customRange:'Custom',
        customProgressTitle:'Custom Range Progress',
        noPostsDay:'No posts on this day.',
        from:'From',
        to:'To',
        backToday:'Back to today',
        calendar:'Calendar',
        calendarReviewed:'Reviewed',
        calendarLoading:'Loading calendar…',
        calendarStatus:'{unreviewed} unreviewed · {reviewed} reviewed',
        apply:'Apply',
        submitPost:'Submit Post',
        bulkSubmitPost:'Bulk Submit Post',
        bulkSubmitHelp:'Paste one Marketplace listing URL per line. Each valid, non-duplicate listing is saved to the background Verification Queue.',
        preflightPassed:'First two checks passed.',
        preflightChoice:'Save & Wait now, or continue with the full verification.',
        continueVerification:'Continue Verification',
        preflightPassedMessage:'Platform and hard duplicate checks passed. Choose Save & Wait or Continue Verification.',
        posts:'Posts',
        selectedRange:'Selected range',
        good:'Good',
        passedReview:'Passed review',
        issues:'Bad',
        needsAttention:'Needs attention',
        unreviewed:'Unreviewed',
        awaitingReview:'Awaiting Admin review',
        dailyPosts:'Daily Posts',
        published:'Published',
        postDate:'Post date',
        openOriginal:'Open original',
        requestDeletion:'Request deletion',
        reason:'Reason',
        cancel:'Cancel',
        sendRequest:'Send request',
        deletionSent:'Deletion request sent.',
        empty:'Empty',
        noPostsRange:'No posts in this date range.',
        loadEarlier:'Load earlier days',
        loading:'Loading…',
        loadingEarlier:'Loading earlier days…',
        allDaysLoaded:'All days loaded.',
        loadEarlierFailed:'Could not load earlier days.',
        noDescription:'No description available.',
        submitTitle:'Submit Marketplace Post',
        submitSubtitle:'Verify the listing first. Only verified posts can be saved.',
        backDashboard:'Back to Dashboard',
        stepOne:'Step 1',
        verifyListing:'Verify Listing',
        postUrl:'Post URL / Share Link',
        platform:'Platform',
        pasteSupported:'Paste a supported URL',
        unsupportedUrl:'Unsupported URL',
        checkPost:'Check Post',
        checking:'Checking…',
        verificationInProgress:'A verification is already running. Wait for the current check to finish.',
        detectingPlatform:'Detecting platform…',
        checkingDuplicates:'Checking duplicates…',
        fetchingPost:'Fetching verified post information…',
        checkingDate:'Checking listing date…',
        finalDuplicate:'Final duplicate check…',
        stepTwo:'Step 2',
        verificationResult:'Verification Result',
        readyToVerify:'Ready to verify',
        pasteAndCheck:'Paste a listing URL and click Check Post.',
        verified:'VERIFIED ✓',
        blocked:'BLOCKED',
        inspectionFailed:'Inspection failed.',
        useSupported:'Use Facebook Marketplace, OfferUp, or Craigslist.',
        publishedLabel:'Published',
        postId:'Post ID',
        platformAccount:'Account',
        originalUrl:'Original URL',
        saveVerified:'Save Verified Post',
        saveForAdminReview:'Save for Admin Review',
        limited:'LIMITED',
        manualVerificationTitle:'Manual marketplace verification',
        manualVerificationHelp:'Direct verification and automatic provider fallback were unavailable. Confirm the listing details below; Admin will review this post.',
        manualTitleLabel:'Listing title',
        manualDateLabel:'Published date',
        manualDescriptionLabel:'Description (optional)',
        continueManualVerification:'Continue Manual Verification',
        manualChecking:'Checking manual details…',
        manualAccepted:'Manual details accepted. Save this post for Admin verification.',
        manualTitleRequired:'Enter the marketplace listing title.',
        manualDateRequired:'Enter the marketplace published date.',
        saveAndWait:'Save & Wait',
        saveAndWaitHelp:'Checks platform + hard duplicate first, then verifies in the background.',
        bulkSubmit:'Bulk Submit',
        bulkUrlsLabel:'One listing URL per line',
        addToQueue:'Add to Verification Queue',
        verificationQueueEyebrow:'Background Verification',
        verificationQueueTitle:'Verification Queue',
        verificationQueueHelp:'Waiting, verifying and error items are not counted as Posts. Passed items are already saved in Posts; click a Passed card to clear it and show the saved Post.',
        refreshQueue:'Refresh',
        queueAll:'All',
        queueWaiting:'Waiting',
        queueVerifying:'Verifying',
        queuePassed:'Passed',
        queueErrors:'Errors',
        queueErrorDuplicateId:'Duplicated ID',
        queueErrorDuplicateTitle:'Duplicated Title',
        queueErrorDuplicatePhoto:'Duplicated Photo',
        queueErrorDuplicateUrl:'Duplicated URL',
        queueErrorInvalidUrl:'Invalid URL',
        queueErrorTimeout:'System Timeout',
        queueErrorProvider:'Provider Error',
        queueErrorUnavailable:'Unavailable',
        queueErrorDate:'Date Not Verified',
        queueErrorTitleCheck:'Title Not Verified',
        queueErrorManual:'Manual Verification Required',
        queueErrorSystem:'System Error',
        queueErrorGeneric:'Verification Error',
        queueFailed:'Failed',
        queueDuplicate:'Duplicate',
        queueInvalid:'Invalid',
        queueNeedsAction:'Needs Action',
        queueEmptyTitle:'No verification items',
        queueEmptyHelp:'Use Save & Wait or Bulk Submit Post to add listings.',
        queueRetry:'Retry',
        queueEdit:'Edit & Re-verify',
        queueDelete:'Delete',
        queueHistory:'History',
        queueViewPost:'View Post',
        queueSaveReverify:'Save & Re-verify',
        queueEditUrlHelp:'Edit the original listing URL below. Platform and Post ID are detected automatically from the URL.',
        queueNotCounted:'This submission was not counted as a post.',
        queuePassedHelp:'Verified and saved as a counted Post.',
        reasonPlaceholder:'Why should this post be removed?'
    },
    'zh-CN':{
        greeting:'你好，{name}',
        dashboardTitle:'我的销售活动',
        dashboardSubtitle:'查看已验证的 Marketplace 帖子以及管理员审核状态。',
        salesPostSearchLabel:'Post 搜索',
        salesPostSearchPlaceholder:'搜索原帖链接、Post ID、标题或平台',
        salesPostSearchHelp:'搜索你自己所有日期中已保存的 Posts。',
        salesPostSearchMatches:'搜索结果',
        salesPostSearching:'正在搜索 Posts',
        salesPostSearchEmpty:'没有找到匹配的 Posts。',
        salesPostSearchFailed:'无法搜索 Posts。',
        salesPostSearchClear:'清除 Post 搜索',
        activityChart:'发帖活动',
        dailyProgress:'每日发帖进度',
        targetLine:'每日目标',
        channels:'渠道',
        allPlatforms:'全部',
        missing:'缺少',
        total:'总数',
        allPosts:'全部',
        viewDetails:'查看详情',
        noImage:'没有帖子图片',
        close:'关闭',
        daily:'每日',
        oneDay:'1天',
        oneDayProgressTitle:'1天发布进度',
        noFilteredPosts:'此日期范围内没有“{status}”帖子。',
        threeDays:'3天',
        dailyProgressTitle:'3天發佈進度',
        weeklyProgressTitle:'每週發佈進度',
        monthlyProgressTitle:'每月發佈進度',
        dailyProgressTitle:'3天发布进度',
        weeklyProgressTitle:'每周发布进度',
        monthlyProgressTitle:'每月发布进度',
        weekly:'每周',
        monthly:'每月',
        customRange:'自訂',
        customProgressTitle:'自訂範圍發佈進度',
        noPostsDay:'當天沒有發佈。',
        customRange:'自定义',
        customProgressTitle:'自定义范围发布进度',
        noPostsDay:'当天没有发布。',
        from:'开始',
        to:'结束',
        backToday:'返回今天',
        calendar:'日曆',
        calendarReviewed:'已審核',
        calendarLoading:'正在載入日曆…',
        calendarStatus:'{unreviewed} 未審核 · {reviewed} 已審核',
        calendar:'日历',
        calendarReviewed:'已审核',
        calendarLoading:'正在加载日历…',
        calendarStatus:'{unreviewed} 未审核 · {reviewed} 已审核',
        apply:'应用',
        submitPost:'提交帖子',
        bulkSubmitPost:'批量提交 Post',
        bulkSubmitHelp:'每行粘贴一个 Marketplace 帖子链接。有效且不重复的记录会进入后台 Verification Queue。',
        preflightPassed:'前两项检查已通过。',
        preflightChoice:'现在可以 Save & Wait，或继续完整验证。',
        continueVerification:'继续验证',
        preflightPassedMessage:'平台识别和硬查重已通过。请选择 Save & Wait 或继续验证。',
        posts:'帖子',
        selectedRange:'所选日期范围',
        good:'通过',
        passedReview:'审核通过',
        issues:'不合格',
        needsAttention:'需要处理',
        unreviewed:'未审核',
        awaitingReview:'等待管理员审核',
        dailyPosts:'每日帖子',
        published:'发布',
        postDate:'发布日期',
        openOriginal:'打开原帖',
        requestDeletion:'申请删除',
        reason:'原因',
        cancel:'取消',
        sendRequest:'发送申请',
        deletionSent:'删除申请已发送。',
        noPostsRange:'这个日期范围内没有帖子。',
        loadEarlier:'加载更早日期',
        loading:'加载中…',
        loadingEarlier:'正在加载更早日期…',
        allDaysLoaded:'已加载全部日期。',
        loadEarlierFailed:'无法加载更早日期。',
        noDescription:'暂无描述。',
        submitTitle:'提交 Marketplace 帖子',
        submitSubtitle:'先验证帖子。只有验证通过的帖子才能保存。',
        backDashboard:'返回主页',
        stepOne:'第 1 步',
        verifyListing:'验证帖子',
        postUrl:'帖子 URL / 分享链接',
        platform:'平台',
        pasteSupported:'粘贴支持的平台链接',
        unsupportedUrl:'不支持的链接',
        checkPost:'检查帖子',
        checking:'检查中…',
        verificationInProgress:'已有一个帖子正在验证，请等待当前验证完成后再检查下一个帖子。',
        detectingPlatform:'正在识别平台…',
        checkingDuplicates:'正在检查重复…',
        fetchingPost:'正在获取已验证的帖子信息…',
        checkingDate:'正在检查发布日期…',
        finalDuplicate:'最后检查重复…',
        stepTwo:'第 2 步',
        verificationResult:'验证结果',
        readyToVerify:'可以开始验证',
        pasteAndCheck:'粘贴帖子链接后点击“检查帖子”。',
        verified:'验证通过 ✓',
        blocked:'已阻止',
        inspectionFailed:'验证失败。',
        useSupported:'请使用 Facebook Marketplace、OfferUp 或 Craigslist。',
        publishedLabel:'发布',
        postId:'帖子 ID',
        platformAccount:'平台账号',
        originalUrl:'原始 URL',
        saveVerified:'保存已验证帖子',
        saveForAdminReview:'保存并交管理员审核',
        limited:'受限验证',
        manualVerificationTitle:'Marketplace 手动验证',
        manualVerificationHelp:'直接验证和自动 Provider 回退均不可用。请确认下面的帖子信息，保存后由管理员审核。',
        manualTitleLabel:'帖子标题',
        manualDateLabel:'发布日期',
        manualDescriptionLabel:'描述（可选）',
        continueManualVerification:'继续手动验证',
        manualChecking:'正在检查手动信息…',
        manualAccepted:'手动信息已通过检查。请保存并交管理员审核。',
        manualTitleRequired:'请输入 Marketplace 帖子标题。',
        manualDateRequired:'请输入 Marketplace 发布日期。',
        saveAndWait:'保存并等待',
        saveAndWaitHelp:'先检查平台和硬重复，再放到后台完成验证。',
        bulkSubmit:'批量提交',
        bulkUrlsLabel:'每行一个帖子链接',
        addToQueue:'加入验证队列',
        verificationQueueEyebrow:'后台验证',
        verificationQueueTitle:'验证队列',
        verificationQueueHelp:'等待中、验证中和错误记录不计入 Post；通过后已保存到正式 Posts，点击“已通过”卡片可清除队列记录并定位到该 Post。',
        refreshQueue:'刷新',
        queueAll:'全部',
        queueWaiting:'等待中',
        queueVerifying:'验证中',
        queuePassed:'已通过',
        queueErrors:'错误',
        queueErrorDuplicateId:'重复 ID',
        queueErrorDuplicateTitle:'重复标题',
        queueErrorDuplicatePhoto:'重复图片',
        queueErrorDuplicateUrl:'重复 URL',
        queueErrorInvalidUrl:'无效 URL',
        queueErrorTimeout:'系统超时',
        queueErrorProvider:'Provider 错误',
        queueErrorUnavailable:'帖子不可用',
        queueErrorDate:'日期未验证',
        queueErrorTitleCheck:'标题未验证',
        queueErrorManual:'需要手动验证',
        queueErrorSystem:'系统错误',
        queueErrorGeneric:'验证错误',
        queueFailed:'失败',
        queueDuplicate:'重复',
        queueInvalid:'无效',
        queueNeedsAction:'需要处理',
        queueEmptyTitle:'暂无验证记录',
        queueEmptyHelp:'使用“保存并等待”或“批量提交”加入队列。',
        queueRetry:'重试',
        queueEdit:'修改并重新验证',
        queueDelete:'删除',
        queueHistory:'历史',
        queueViewPost:'查看 Post',
        queueSaveReverify:'保存并重新验证',
        queueEditUrlHelp:'请在下面修改原始帖子链接。平台和 Post ID 会根据链接自动识别。',
        queueNotCounted:'此记录未计入 Post 数量。',
        queuePassedHelp:'验证通过，已自动保存并计入 Post。',
        reasonPlaceholder:'为什么要删除这个帖子？'
    },
    'zh-TW':{
        greeting:'你好，{name}',
        dashboardTitle:'我的銷售活動',
        dashboardSubtitle:'查看已驗證的 Marketplace 貼文以及管理員審核狀態。',
        salesPostSearchLabel:'Post 搜尋',
        salesPostSearchPlaceholder:'搜尋原貼連結、Post ID、標題或平台',
        salesPostSearchHelp:'搜尋你自己所有日期中已儲存的 Posts。',
        salesPostSearchMatches:'搜尋結果',
        salesPostSearching:'正在搜尋 Posts',
        salesPostSearchEmpty:'沒有找到匹配的 Posts。',
        salesPostSearchFailed:'無法搜尋 Posts。',
        salesPostSearchClear:'清除 Post 搜尋',
        activityChart:'發文活動',
        dailyProgress:'每日發文進度',
        targetLine:'每日目標',
        allPlatforms:'全部',
        missing:'缺少',
        total:'總數',
        allPosts:'全部',
        viewDetails:'查看詳情',
        noImage:'沒有貼文圖片',
        close:'關閉',
        daily:'每日',
        oneDay:'1天',
        oneDayProgressTitle:'1天發文進度',
        noFilteredPosts:'此日期範圍內沒有「{status}」貼文。',
        threeDays:'3天',
        dailyProgressTitle:'3天發文進度',
        weeklyProgressTitle:'每週發文進度',
        monthlyProgressTitle:'每月發文進度',
        customRange:'自訂',
        customProgressTitle:'自訂範圍發文進度',
        weekly:'每週',
        monthly:'每月',
        from:'開始',
        to:'結束',
        backToday:'返回今天',
        apply:'套用',
        submitPost:'提交貼文',
        bulkSubmitPost:'批量提交 Post',
        bulkSubmitHelp:'每行貼上一個 Marketplace 貼文連結。有效且不重複的記錄會進入背景 Verification Queue。',
        preflightPassed:'前兩項檢查已通過。',
        preflightChoice:'現在可以 Save & Wait，或繼續完整驗證。',
        continueVerification:'繼續驗證',
        preflightPassedMessage:'平台識別和硬查重已通過。請選擇 Save & Wait 或繼續驗證。',
        posts:'貼文',
        selectedRange:'所選日期範圍',
        good:'通過',
        passedReview:'審核通過',
        issues:'不合格',
        needsAttention:'需要處理',
        unreviewed:'未審核',
        awaitingReview:'等待管理員審核',
        dailyPosts:'每日貼文',
        published:'發布',
        postDate:'發佈日期',
        openOriginal:'開啟原貼',
        requestDeletion:'申請刪除',
        reason:'原因',
        cancel:'取消',
        sendRequest:'送出申請',
        deletionSent:'刪除申請已送出。',
        noPostsRange:'此日期範圍內沒有貼文。',
        loadEarlier:'載入更早日期',
        loading:'載入中…',
        loadingEarlier:'正在載入更早日期…',
        allDaysLoaded:'已載入全部日期。',
        loadEarlierFailed:'無法載入更早日期。',
        noDescription:'暫無描述。',
        submitTitle:'提交 Marketplace 貼文',
        submitSubtitle:'先驗證貼文。只有驗證通過的貼文才能儲存。',
        backDashboard:'返回主頁',
        stepOne:'第 1 步',
        verifyListing:'驗證貼文',
        postUrl:'貼文 URL / 分享連結',
        platform:'平台',
        pasteSupported:'貼上支援的平台連結',
        unsupportedUrl:'不支援的連結',
        checkPost:'檢查貼文',
        checking:'檢查中…',
        verificationInProgress:'已有一個帖子正在驗證，請等待目前驗證完成後再檢查下一個帖子。',
        detectingPlatform:'正在辨識平台…',
        checkingDuplicates:'正在檢查重複…',
        fetchingPost:'正在取得已驗證的貼文資訊…',
        checkingDate:'正在檢查發布日期…',
        finalDuplicate:'最後檢查重複…',
        stepTwo:'第 2 步',
        verificationResult:'驗證結果',
        readyToVerify:'可以開始驗證',
        pasteAndCheck:'貼上貼文連結後點擊「檢查貼文」。',
        verified:'驗證通過 ✓',
        blocked:'已阻止',
        inspectionFailed:'驗證失敗。',
        useSupported:'請使用 Facebook Marketplace、OfferUp 或 Craigslist。',
        publishedLabel:'發布',
        postId:'貼文 ID',
        platformAccount:'平台帳號',
        originalUrl:'原始 URL',
        saveVerified:'儲存已驗證貼文',
        saveForAdminReview:'儲存並交管理員審核',
        limited:'受限驗證',
        manualVerificationTitle:'Marketplace 手動驗證',
        manualVerificationHelp:'直接驗證與自動 Provider 回退均不可用。請確認下方貼文資訊，儲存後由管理員審核。',
        manualTitleLabel:'貼文標題',
        manualDateLabel:'發布日期',
        manualDescriptionLabel:'描述（可選）',
        continueManualVerification:'繼續手動驗證',
        manualChecking:'正在檢查手動資訊…',
        manualAccepted:'手動資訊已通過檢查。請儲存並交管理員審核。',
        manualTitleRequired:'請輸入 Marketplace 貼文標題。',
        manualDateRequired:'請輸入 Marketplace 發布日期。',
        saveAndWait:'儲存並等待',
        saveAndWaitHelp:'先檢查平台與硬重複，再放到背景完成驗證。',
        bulkSubmit:'批次提交',
        bulkUrlsLabel:'每行一個貼文連結',
        addToQueue:'加入驗證佇列',
        verificationQueueEyebrow:'背景驗證',
        verificationQueueTitle:'驗證佇列',
        verificationQueueHelp:'等待中、驗證中和錯誤記錄不計入 Post；通過後已保存到正式 Posts，點擊「已通過」卡片可清除佇列記錄並定位到該 Post。',
        refreshQueue:'重新整理',
        queueAll:'全部',
        queueWaiting:'等待中',
        queueVerifying:'驗證中',
        queuePassed:'已通過',
        queueErrors:'錯誤',
        queueErrorDuplicateId:'重複 ID',
        queueErrorDuplicateTitle:'重複標題',
        queueErrorDuplicatePhoto:'重複圖片',
        queueErrorDuplicateUrl:'重複 URL',
        queueErrorInvalidUrl:'無效 URL',
        queueErrorTimeout:'系統逾時',
        queueErrorProvider:'Provider 錯誤',
        queueErrorUnavailable:'貼文不可用',
        queueErrorDate:'日期未驗證',
        queueErrorTitleCheck:'標題未驗證',
        queueErrorManual:'需要手動驗證',
        queueErrorSystem:'系統錯誤',
        queueErrorGeneric:'驗證錯誤',
        queueFailed:'失敗',
        queueDuplicate:'重複',
        queueInvalid:'無效',
        queueNeedsAction:'需要處理',
        queueEmptyTitle:'暫無驗證記錄',
        queueEmptyHelp:'使用「儲存並等待」或「批次提交」加入佇列。',
        queueRetry:'重試',
        queueEdit:'修改並重新驗證',
        queueDelete:'刪除',
        queueHistory:'歷史',
        queueViewPost:'查看 Post',
        queueSaveReverify:'儲存並重新驗證',
        queueEditUrlHelp:'請在下面修改原始貼文連結。平台和 Post ID 會根據連結自動識別。',
        queueNotCounted:'此記錄未計入 Post 數量。',
        queuePassedHelp:'驗證通過，已自動儲存並計入 Post。',
        reasonPlaceholder:'為什麼要刪除這篇貼文？'
    },
    es:{
        greeting:'Hola, {name}',
        dashboardTitle:'Mi actividad de ventas',
        dashboardSubtitle:'Revisa tus publicaciones verificadas y el estado de revisión del administrador.',
        salesPostSearchLabel:'Buscar publicaciones',
        salesPostSearchPlaceholder:'Busca enlace original, ID, título o plataforma',
        salesPostSearchHelp:'Busca todas tus publicaciones guardadas sin límite de fecha.',
        salesPostSearchMatches:'Resultados de búsqueda',
        salesPostSearching:'Buscando publicaciones',
        salesPostSearchEmpty:'No se encontraron publicaciones.',
        salesPostSearchFailed:'No se pudieron buscar las publicaciones.',
        salesPostSearchClear:'Limpiar búsqueda',
        activityChart:'Actividad de publicaciones',
        dailyProgress:'Progreso diario de publicaciones',
        targetLine:'Meta diaria',
        channels:'Canales',
        allPlatforms:'Todas',
        missing:'Faltantes',
        total:'Total',
        viewDetails:'Ver detalles',
        noImage:'Sin imagen',
        close:'Cerrar',
        daily:'Diario',
        oneDay:'1 día',
        oneDayProgressTitle:'Progreso de publicaciones de 1 día',
        noFilteredPosts:'No hay publicaciones con estado «{status}» en este rango.',
        threeDays:'3 días',
        dailyProgressTitle:'Progreso de publicaciones de 3 días',
        weeklyProgressTitle:'Progreso semanal de publicaciones',
        monthlyProgressTitle:'Progreso mensual de publicaciones',
        weekly:'Semanal',
        monthly:'Mensual',
        customRange:'Personal.',
        customProgressTitle:'Progreso del rango personalizado',
        noPostsDay:'No hay publicaciones este día.',
        from:'Desde',
        to:'Hasta',
        backToday:'Volver a hoy',
        calendar:'Calendario',
        calendarReviewed:'Revisado',
        calendarLoading:'Cargando calendario…',
        calendarStatus:'{unreviewed} sin revisar · {reviewed} revisado',
        apply:'Aplicar',
        submitPost:'Enviar publicación',
        bulkSubmitPost:'Envío masivo de publicaciones',
        bulkSubmitHelp:'Pega una URL de Marketplace por línea. Cada entrada válida y no duplicada se guarda en la cola de verificación en segundo plano.',
        preflightPassed:'Las dos primeras comprobaciones pasaron.',
        preflightChoice:'Guarda y espera ahora o continúa con la verificación completa.',
        continueVerification:'Continuar verificación',
        preflightPassedMessage:'La plataforma y la comprobación de duplicado duro pasaron. Elige Guardar y esperar o Continuar verificación.',
        posts:'Publicaciones',
        selectedRange:'Rango seleccionado',
        good:'Aprobado',
        passedReview:'Revisión aprobada',
        issues:'Malo',
        needsAttention:'Requiere atención',
        unreviewed:'Sin revisar',
        awaitingReview:'Esperando revisión del administrador',
        dailyPosts:'Publicaciones diarias',
        published:'Publicado',
        postDate:'Fecha de publicación',
        openOriginal:'Abrir original',
        requestDeletion:'Solicitar eliminación',
        reason:'Motivo',
        cancel:'Cancelar',
        sendRequest:'Enviar solicitud',
        deletionSent:'Solicitud de eliminación enviada.',
        empty:'Vacío',
        noPostsRange:'No hay publicaciones en este rango de fechas.',
        loadEarlier:'Cargar días anteriores',
        loading:'Cargando…',
        loadingEarlier:'Cargando días anteriores…',
        allDaysLoaded:'Todos los días cargados.',
        loadEarlierFailed:'No se pudieron cargar días anteriores.',
        noDescription:'Sin descripción.',
        submitTitle:'Enviar publicación de Marketplace',
        submitSubtitle:'Verifica la publicación primero. Solo se pueden guardar publicaciones verificadas.',
        backDashboard:'Volver al panel',
        stepOne:'Paso 1',
        verifyListing:'Verificar publicación',
        postUrl:'URL / enlace compartido',
        platform:'Plataforma',
        pasteSupported:'Pega un enlace compatible',
        unsupportedUrl:'URL no compatible',
        checkPost:'Comprobar publicación',
        checking:'Comprobando…',
        verificationInProgress:'Ya hay una verificación en curso. Espere a que termine antes de comprobar otra publicación.',
        detectingPlatform:'Detectando plataforma…',
        checkingDuplicates:'Comprobando duplicados…',
        fetchingPost:'Obteniendo información verificada…',
        checkingDate:'Comprobando fecha de publicación…',
        finalDuplicate:'Comprobación final de duplicados…',
        stepTwo:'Paso 2',
        verificationResult:'Resultado de verificación',
        readyToVerify:'Listo para verificar',
        pasteAndCheck:'Pega una URL y pulsa Comprobar publicación.',
        verified:'VERIFICADO ✓',
        blocked:'BLOQUEADO',
        inspectionFailed:'La verificación falló.',
        useSupported:'Usa Facebook Marketplace, OfferUp o Craigslist.',
        publishedLabel:'Publicado',
        postId:'ID de publicación',
        platformAccount:'Cuenta',
        originalUrl:'URL original',
        saveVerified:'Guardar publicación verificada',
        saveForAdminReview:'Guardar para revisión del administrador',
        limited:'VERIFICACIÓN LIMITADA',
        manualVerificationTitle:'Verificación manual del marketplace',
        manualVerificationHelp:'La verificación directa y el proveedor automático no estuvieron disponibles. Confirma los datos; el administrador revisará la publicación.',
        manualTitleLabel:'Título del anuncio',
        manualDateLabel:'Fecha de publicación',
        manualDescriptionLabel:'Descripción (opcional)',
        continueManualVerification:'Continuar verificación manual',
        manualChecking:'Comprobando datos manuales…',
        manualAccepted:'Datos manuales aceptados. Guarda la publicación para revisión del administrador.',
        manualTitleRequired:'Introduce el título del anuncio del marketplace.',
        manualDateRequired:'Introduce la fecha de publicación del marketplace.',
        saveAndWait:'Guardar y esperar',
        saveAndWaitHelp:'Comprueba plataforma y duplicado duro, luego verifica en segundo plano.',
        bulkSubmit:'Envío masivo',
        bulkUrlsLabel:'Una URL por línea',
        addToQueue:'Agregar a la cola de verificación',
        verificationQueueEyebrow:'Verificación en segundo plano',
        verificationQueueTitle:'Cola de verificación',
        verificationQueueHelp:'Los elementos en espera, verificación o error no cuentan como Posts. Los aprobados ya están en Posts; haz clic en una tarjeta aprobada para quitarla de la cola y mostrar el Post guardado.',
        refreshQueue:'Actualizar',
        queueAll:'Todos',
        queueWaiting:'En espera',
        queueVerifying:'Verificando',
        queuePassed:'Aprobados',
        queueErrors:'Errores',
        queueErrorDuplicateId:'ID duplicado',
        queueErrorDuplicateTitle:'Título duplicado',
        queueErrorDuplicatePhoto:'Foto duplicada',
        queueErrorDuplicateUrl:'URL duplicada',
        queueErrorInvalidUrl:'URL no válida',
        queueErrorTimeout:'Tiempo de espera agotado',
        queueErrorProvider:'Error del proveedor',
        queueErrorUnavailable:'No disponible',
        queueErrorDate:'Fecha no verificada',
        queueErrorTitleCheck:'Título no verificado',
        queueErrorManual:'Verificación manual requerida',
        queueErrorSystem:'Error del sistema',
        queueErrorGeneric:'Error de verificación',
        queueFailed:'Fallidos',
        queueDuplicate:'Duplicados',
        queueInvalid:'Inválidos',
        queueNeedsAction:'Requiere acción',
        queueEmptyTitle:'No hay verificaciones',
        queueEmptyHelp:'Usa Guardar y esperar o Envío masivo para agregar publicaciones.',
        queueRetry:'Reintentar',
        queueEdit:'Editar y verificar de nuevo',
        queueDelete:'Eliminar',
        queueHistory:'Historial',
        queueViewPost:'Ver publicación',
        queueSaveReverify:'Guardar y verificar de nuevo',
        queueEditUrlHelp:'Edita abajo la URL original. La plataforma y el Post ID se detectan automáticamente desde la URL.',
        queueNotCounted:'Este envío no se contó como Post.',
        queuePassedHelp:'Verificado, guardado y contado como Post.',
        reasonPlaceholder:'¿Por qué se debe eliminar esta publicación?'
    }
};

/**
 * EN: Perform the sales language behavior used by the application UI.
 * 中文：执行application UI 使用的“sales language”行为。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function salesLanguage(){
    const lang=currentAppLanguage();
    return salesI18n[lang]?lang:'en';
}

/**
 * EN: Perform the sales tr behavior used by the application UI.
 * 中文：执行application UI 使用的“sales tr”行为。
 *
 * @param {string|*} key Key used to identify the requested value. / 用于标识目标值的键。
 * @param {*} vars Vars value used by this function. / 本函数使用的“vars”参数值。
 *
 * @returns {boolean} Boolean result produced by this UI helper. / 本 UI 辅助函数生成的布尔结果。
 */
function salesTr(key,vars){
    const lang=salesLanguage();
    const dict=salesI18n[lang]||salesI18n.en;
    let value=String(dict[key]??salesI18n.en[key]??key);

    Object.entries(vars||{}).forEach(function(entry){
        value=value.replace(
            new RegExp('\\{'+entry[0]+'\\}','g'),
            String(entry[1])
        );
    });

    return value;
}

/**
 * EN: Update the apply sales language behavior used by the application UI.
 * 中文：更新application UI 使用的“apply sales language”行为。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function applySalesLanguage(){
    $('[data-sales-i18n]').each(function(){
        const key=String($(this).attr('data-sales-i18n')||'');

        if(!key){
            return;
        }

        if(key==='greeting'){
            const name=String($(this).data('sales-name')||'Sales');
            $(this).text(salesTr(key,{name:name}));
            return;
        }

        $(this).text(salesTr(key));
    });

    $('[data-sales-i18n-placeholder]').each(function(){
        const key=String($(this).attr('data-sales-i18n-placeholder')||'');
        if(key){
            $(this).attr('placeholder',salesTr(key));
        }
    });

    $('#salesPostSearchClear')
        .attr('aria-label',salesTr('salesPostSearchClear'))
        .attr('title',salesTr('salesPostSearchClear'));

    $('[data-sales-placeholder="reason"]').attr(
        'placeholder',
        salesTr('reasonPlaceholder')
    );

    $('[data-sales-placeholder="postUrl"]').attr(
        'placeholder',
        salesLanguage()==='es'
            ?'Pega una URL de Facebook, OfferUp o Craigslist'
            :salesLanguage()==='zh-CN'
                ?'粘贴 Facebook、OfferUp 或 Craigslist 链接'
                :salesLanguage()==='zh-TW'
                    ?'貼上 Facebook、OfferUp 或 Craigslist 連結'
                    :'Paste Facebook, OfferUp, or Craigslist URL'
    );

    const platform=$('#detectedPlatformValue').val();

    if($('#detectedPlatform').length){
        $('#detectedPlatform').text(
            platform
                ?platformLabel(platform)
                :($('#postUrl').val()
                    ?salesTr('unsupportedUrl')
                    :salesTr('pasteSupported'))
        );
    }

    applyGlobalMenuLanguage();
}

window.cdspSalesLanguage={translate:salesTr,apply:applySalesLanguage};

$(document).on('cdsp:language-changed',function(){
    applySalesLanguage();
    if(typeof window.cdspRefreshVerificationQueue==='function'){window.cdspRefreshVerificationQueue();}

    if($('#salesActivityChartPanel').length){
        renderSalesChart();
    }
});

applySalesLanguage();

/**
 * EN: Perform the sales today value behavior used by the application UI.
 * 中文：执行application UI 使用的“sales today value”行为。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function salesTodayValue(){
    return String(
        $('#salesPortalDashboard').attr('data-today')
        ||''
    );
}

/**
 * EN: Update the update sales back today behavior used by the application UI.
 * 中文：更新application UI 使用的“update sales back today”行为。
 *
 * @param {*} range Range value used by this function. / 本函数使用的“range”参数值。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function updateSalesBackToday(range){
    const $back=$('#salesBackToday');
    const $to=$('#salesRangeTo');

    if(!$back.length||!$to.length){
        return;
    }

    const today=String(
        salesTodayValue()||''
    );

    const pickerMax=String(
        $to.attr('max')||''
    );

    const to=String(
        range
            ?range.to
            :$to.val()||''
    );

    /*
     * Treat the picker's own maximum as the authoritative "latest day"
     * as well. This avoids timezone/cache drift where the UI can already
     * be at its newest selectable date but Back to today still appears.
     */
    const atLatest=Boolean(
        (today&&to===today)
        ||(pickerMax&&to===pickerMax)
    );

    $back.toggleClass(
        'hidden',
        atLatest
    );
}

/**
 * EN: Perform the sales iso date behavior used by the application UI.
 * 中文：执行application UI 使用的“sales iso date”行为。
 *
 * @param {string|*} date Date value used by the calculation or filter. / 计算或筛选使用的日期值。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function salesIsoDate(date){
    const year=date.getFullYear();
    const month=String(
        date.getMonth()+1
    ).padStart(2,'0');
    const day=String(
        date.getDate()
    ).padStart(2,'0');

    return year+'-'+month+'-'+day;
}

/**
 * EN: Perform the sales parse iso date behavior used by the application UI.
 * 中文：执行application UI 使用的“sales parse iso date”行为。
 *
 * @param {string|*} value Value read, transformed, or applied by this function. / 本函数读取、转换或应用的值。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function salesParseIsoDate(value){
    const match=String(value||'').match(
        /^(\d{4})-(\d{2})-(\d{2})$/
    );

    if(!match){
        return null;
    }

    const date=new Date(
        parseInt(match[1],10),
        parseInt(match[2],10)-1,
        parseInt(match[3],10),
        12,0,0
    );

    return Number.isNaN(date.getTime())
        ?null
        :date;
}

/**
 * EN: Perform the sales preset range behavior used by the application UI.
 * 中文：执行application UI 使用的“sales preset range”行为。
 *
 * @param {*} period Period value used by this function. / 本函数使用的“period”参数值。
 * @param {*} anchorValue Anchor value value used by this function. / 本函数使用的“anchor value”参数值。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function salesPresetRange(period,anchorValue){
    const todayValue=salesTodayValue();
    const today=salesParseIsoDate(todayValue);
    let anchor=salesParseIsoDate(anchorValue);

    if(!anchor){
        anchor=today;
    }

    if(!anchor){
        return null;
    }

    if(
        today
        &&anchor.getTime()>today.getTime()
    ){
        anchor=new Date(today);
    }

    const to=new Date(anchor);
    let from=new Date(anchor);

    if(period==='single'){
        // One selected day; keep period=day compatible with old 3-Day URLs.
        from=new Date(to);
    }else if(period==='day'){
        // Rolling three-day range ending at To.
        from.setDate(
            from.getDate()-2
        );
    }else if(period==='week'){
        // Rolling seven-day range ending at To.
        from.setDate(
            from.getDate()-6
        );
    }else if(period==='month'){
        /*
         * Rolling one-calendar-month range ending at To.
         * Example:
         *   To 08/31 -> From 08/01
         *   To 08/20 -> From 07/21
         *
         * Clamp the day when the previous month is shorter.
         */
        const anchorYear=to.getFullYear();
        const anchorMonth=to.getMonth();
        const anchorDay=to.getDate();

        const previousMonthDate=new Date(
            anchorYear,
            anchorMonth-1,
            1,
            12,0,0
        );

        const previousMonthLastDay=new Date(
            anchorYear,
            anchorMonth,
            0,
            12,0,0
        ).getDate();

        const previousDay=Math.min(
            anchorDay,
            previousMonthLastDay
        );

        from=new Date(
            previousMonthDate.getFullYear(),
            previousMonthDate.getMonth(),
            previousDay,
            12,0,0
        );

        from.setDate(
            from.getDate()+1
        );
    }

    return {
        from:salesIsoDate(from),
        to:salesIsoDate(to)
    };
}
/**
 * EN: Update the set sales range period behavior used by the application UI.
 * 中文：更新application UI 使用的“set sales range period”行为。
 *
 * @param {*} period Period value used by this function. / 本函数使用的“period”参数值。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function setSalesRangePeriod(period){
    salesRangePeriod=String(
        period||'custom'
    );

    $('#salesPortalDashboard').attr(
        'data-range-period',
        salesRangePeriod
    );

    $('#salesPeriodSwitch')
        .find('[data-sales-period]')
        .each(function(){
            const active=
                String(
                    $(this).attr('data-sales-period')
                )===salesRangePeriod;

            $(this)
                .toggleClass('active',active)
                .attr(
                    'aria-pressed',
                    active?'true':'false'
                );
        });

    const titleKey=
        salesRangePeriod==='single'
            ?'oneDayProgressTitle'
            :salesRangePeriod==='week'
            ?'weeklyProgressTitle'
            :(
                salesRangePeriod==='month'
                    ?'monthlyProgressTitle'
                    :(
                        salesRangePeriod==='custom'
                            ?'customProgressTitle'
                            :'dailyProgressTitle'
                    )
            );

    $('#salesChartPeriodTitle')
        .attr(
            'data-sales-i18n',
            titleKey
        )
        .text(
            salesTr(titleKey)
        );
}

/**
 * EN: Perform the detect sales range period behavior used by the application UI.
 * 中文：执行application UI 使用的“detect sales range period”行为。
 *
 * @param {*} from From value used by this function. / 本函数使用的“from”参数值。
 * @param {*} to To value used by this function. / 本函数使用的“to”参数值。
 *
 * @returns {string} String result produced by this UI helper. / 本 UI 辅助函数生成的字符串结果。
 */
function detectSalesRangePeriod(from,to){
    const toDate=salesParseIsoDate(to);

    if(!toDate){
        return 'custom';
    }

    if(from===to){return 'single';}

    const threeDays=salesPresetRange(
        'day',
        to
    );

    if(
        threeDays
        &&threeDays.from===from
        &&threeDays.to===to
    ){
        return 'day';
    }

    const week=salesPresetRange(
        'week',
        to
    );

    if(
        week
        &&week.from===from
        &&week.to===to
    ){
        return 'week';
    }

    const month=salesPresetRange(
        'month',
        to
    );

    if(
        month
        &&month.from===from
        &&month.to===to
    ){
        return 'month';
    }

    return 'custom';
}

/**
 * EN: Update the sync sales range constraints behavior used by the application UI.
 * 中文：更新application UI 使用的“sync sales range constraints”行为。
 *
 * @param {*} changed Changed value used by this function. / 本函数使用的“changed”参数值。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function syncSalesRangeConstraints(changed){
    const $from=$('#salesRangeFrom');
    const $to=$('#salesRangeTo');

    if(!$from.length||!$to.length){
        return null;
    }

    const today=salesTodayValue();

    let from=String($from.val()||'');
    let to=String($to.val()||'');

    if(
        !/^\d{4}-\d{2}-\d{2}$/.test(from)
        ||!/^\d{4}-\d{2}-\d{2}$/.test(to)
    ){
        return null;
    }

    if(today&&to>today){
        to=today;
        $to.val(to);
    }

    if(today&&from>today){
        from=today;
        $from.val(from);
    }

    if(changed==='from'&&from>to){
        to=from;
        $to.val(to);
    }else if(changed==='to'&&to<from){
        from=to;
        $from.val(from);
    }else if(from>to){
        from=to;
        $from.val(from);
    }

    $from.attr('max',to);

    $to
        .attr('min',from)
        .attr('max',today||'');

    const range={
        from:from,
        to:to
    };

    updateSalesBackToday(range);

    return range;
}
let salesRangeRequest=null;
let salesRangeRequestSeq=0;
let salesChartRows=[];
let salesChartDailyTarget=10;
let salesPlatformFilter=String(
    $('#salesPortalDashboard').attr('data-channel')
    ||'all'
).trim().toLowerCase();
let salesRangePeriod=String(
    $('#salesPortalDashboard').attr('data-range-period')
    ||'custom'
);
let salesTouchChartDay=null;
let salesChartHoverTimer=null;
let salesChartHoverDay=null;
let salesChartHoverPoint=null;
let salesRangeVisualTimer=null;

/**
 * EN: Close or clear the clear sales range visual state behavior used by the application UI.
 * 中文：关闭或清理application UI 使用的“clear sales range visual state”行为。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function clearSalesRangeVisualState(){
    if(salesRangeVisualTimer){
        window.clearTimeout(
            salesRangeVisualTimer
        );
        salesRangeVisualTimer=null;
    }

    $('#salesActivityChartPanel')
        .removeClass(
            'sales-range-loading'
        )
        .attr(
            'aria-busy',
            'false'
        );

    $('#salesActivityChartPanel .sales-chart-shell')
        .removeClass(
            'sales-range-loading sales-content-changing sales-channel-changing'
        );

    $('#dailyPosts')
        .removeClass(
            'sales-range-loading'
        )
        .attr(
            'aria-busy',
            'false'
        );

    $('#salesDailyStage')
        .removeClass(
            'sales-content-changing sales-channel-changing'
        );

    $('#salesPlatformFilter')
        .removeClass(
            'sales-channel-loading'
        )
        .find(
            '[data-sales-platform-filter]'
        )
        .removeClass(
            'sales-channel-button-loading'
        );
}

/**
 * EN: Schedule or start the start sales range visual state behavior used by the application UI.
 * 中文：调度或启动application UI 使用的“start sales range visual state”行为。
 *
 * @param {*} reason Reason value used by this function. / 本函数使用的“reason”参数值。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function startSalesRangeVisualState(reason){
    clearSalesRangeVisualState();

    const $chartBody=$(
        '#salesActivityChartPanel .sales-chart-shell'
    );

    const $dailyStage=$(
        '#salesDailyStage'
    );

    $chartBody
        .addClass(
            'sales-range-loading sales-content-changing'
        )
        .attr(
            'aria-busy',
            'true'
        );

    $dailyStage
        .addClass(
            'sales-content-changing'
        );

    $('#dailyPosts')
        .addClass(
            'sales-range-loading'
        )
        .attr(
            'aria-busy',
            'true'
        );

    if(String(reason||'range')==='channel'){
        $('#salesPlatformFilter')
            .addClass(
                'sales-channel-loading'
            );
    }

    /*
     * Visual loading is deliberately short-lived.
     * Network work may continue in the background, but controls never
     * remain dimmed or spinning indefinitely.
     */
    salesRangeVisualTimer=
        window.setTimeout(
            function(){
                clearSalesRangeVisualState();
            },
            900
        );
}


const $salesSubmitModal=$('#salesSubmitModal');
const $salesBulkSubmitModal=$('#salesBulkSubmitModal');
const $salesPostDetailModal=$('#salesPostDetailModal');
const $salesPostDetailImageButton=$('#salesPostDetailImageButton');
const $salesImageLightbox=$('#salesImageLightbox');
const $salesChartTooltip=$('#salesChartTooltip');
const $salesPostSearchPanel=$('#salesPostSearchPanel');
const $salesPostSearchInput=$('#salesPostSearchInput');
const $salesPostSearchClear=$('#salesPostSearchClear');
const $salesPostSearchResults=$('#salesPostSearchResults');
let salesSelfPostSearchRequest=null;
let salesSelfPostSearchTimer=0;
let salesSelfPostSearchSeq=0;
let salesSelfPostSearchRows=[];
let salesSelfPostSearchQuery='';

/*
 * Keep the fixed-position hover card outside the chart panel. The chart card
 * intentionally clips its scrolling plot with overflow:hidden; leaving the
 * tooltip inside that card clips it at the panel edge even though the tooltip
 * is positioned against the viewport.
 */
if($salesChartTooltip.length&&!$salesChartTooltip.parent().is('body')){
    $salesChartTooltip.appendTo(document.body);
}

/*
 * Sales dashboard tooltip ownership is intentionally split by scope:
 * sales-dashboard.js owns #salesPortalDashboard; this shared file only handles
 * chart-day tooltips outside that portal (for example Admin activity charts).
 * Keeping the scope check below prevents two positioning systems from binding
 * to the same Sales chart.
 */

/**
 * EN: Parse or extract the parse sales chart initial data behavior used by the application UI.
 * 中文：解析或提取application UI 使用的“parse sales chart initial data”行为。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function parseSalesChartInitialData(){
    const node=document.getElementById('salesChartInitialData');

    if(!node){
        return;
    }

    try{
        const data=JSON.parse(node.textContent||'{}');

        salesChartRows=Array.isArray(data.rows)
            ?data.rows
            :[];
        salesChartDailyTarget=Math.max(
            1,
            parseInt(data.daily_target,10)||10
        );
    }catch(error){
        salesChartRows=[];
        salesChartDailyTarget=10;
    }
}

/**
 * EN: Perform the sales post status label behavior used by the application UI.
 * 中文：执行application UI 使用的“sales post status label”行为。
 *
 * @param {*} status Status value used by this function. / 本函数使用的“status”参数值。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function salesPostStatusLabel(status){
    if(status==='good'){
        return salesTr('good');
    }

    if(status==='bad'){
        return salesTr('issues');
    }

    return salesTr('unreviewed');
}

/**
 * EN: Perform the sales date range behavior used by the application UI.
 * 中文：执行application UI 使用的“sales date range”行为。
 *
 * @param {*} from From value used by this function. / 本函数使用的“from”参数值。
 * @param {*} to To value used by this function. / 本函数使用的“to”参数值。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function salesDateRange(from,to){
    const dates=[];
    const start=new Date(from+'T12:00:00');
    const end=new Date(to+'T12:00:00');

    if(
        Number.isNaN(start.getTime())
        ||Number.isNaN(end.getTime())
    ){
        return dates;
    }

    let guard=0;

    for(
        let day=new Date(start);
        day<=end&&guard<1000;
        day.setDate(day.getDate()+1)
    ){
        const year=day.getFullYear();
        const month=String(day.getMonth()+1).padStart(2,'0');
        const date=String(day.getDate()).padStart(2,'0');

        dates.push(year+'-'+month+'-'+date);
        guard++;
    }

    return dates;
}

/**
 * EN: Perform the sales short date behavior used by the application UI.
 * 中文：执行application UI 使用的“sales short date”行为。
 *
 * @param {string|*} value Value read, transformed, or applied by this function. / 本函数读取、转换或应用的值。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function salesShortDate(value){
    const d=new Date(value+'T12:00:00');

    if(Number.isNaN(d.getTime())){
        return value;
    }

    return d.toLocaleDateString(
        salesLanguage()==='zh-CN'
            ?'zh-CN'
            :salesLanguage()==='zh-TW'
                ?'zh-TW'
                :salesLanguage()==='es'
                    ?'es-US'
                    :'en-US',
        {
            month:'numeric',
            day:'numeric'
        }
    );
}

/**
 * EN: Perform the merge sales chart rows from dom behavior used by the application UI.
 * 中文：执行application UI 使用的“merge sales chart rows from dom”行为。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function mergeSalesChartRowsFromDom(){
    const replacements={};

    $('.sales-day-section').each(function(){
        const date=String(
            $(this).attr('data-post-date')||''
        );

        if(!date){
            return;
        }

        $(this)
            .find('.sales-self-post-card')
            .each(function(){
                const platform=String(
                    $(this).attr(
                        'data-sales-post-platform'
                    )||''
                ).toLowerCase()||'unknown';

                const status=String(
                    $(this).attr(
                        'data-sales-post-status'
                    )||'unreviewed'
                );

                const key=date+'|'+platform;

                if(!replacements[key]){
                    replacements[key]={
                        date:date,
                        platform:platform,
                        post_count:0,
                        good_count:0,
                        bad_count:0,
                        unreviewed_count:0
                    };
                }

                const row=replacements[key];

                row.post_count++;

                if(status==='good'){
                    row.good_count++;
                }else if(status==='bad'){
                    row.bad_count++;
                }else{
                    row.unreviewed_count++;
                }
            });
    });

    const keys=new Set(
        Object.keys(replacements)
    );

    salesChartRows=salesChartRows.filter(function(row){
        const key=
            String(row.date||'')
            +'|'
            +String(row.platform||'').toLowerCase();

        return !keys.has(key);
    });

    salesChartRows=salesChartRows.concat(
        Object.values(replacements)
    );
}

/**
 * EN: Build the aggregate sales chart date behavior used by the application UI.
 * 中文：构建application UI 使用的“aggregate sales chart date”行为。
 *
 * @param {string|*} date Date value used by the calculation or filter. / 计算或筛选使用的日期值。
 * @param {*} platform Platform value used by this function. / 本函数使用的“platform”参数值。
 *
 * @returns {boolean} Boolean result produced by this UI helper. / 本 UI 辅助函数生成的布尔结果。
 */
function aggregateSalesChartDate(date,platform){
    const result={
        date:date,
        post_count:0,
        good_count:0,
        bad_count:0,
        unreviewed_count:0
    };

    salesChartRows.forEach(function(row){
        if(String(row.date)!==date){
            return;
        }

        const rowPlatform=String(
            row.platform||''
        ).toLowerCase();

        if(
            platform!=='all'
            &&rowPlatform!==String(platform).toLowerCase()
        ){
            return;
        }

        result.post_count+=parseInt(row.post_count,10)||0;
        result.good_count+=parseInt(row.good_count,10)||0;
        result.bad_count+=parseInt(row.bad_count,10)||0;
        result.unreviewed_count+=
            parseInt(row.unreviewed_count,10)||0;
    });

    return result;
}

/**
 * EN: Build the build sales chart tooltip html behavior used by the application UI.
 * 中文：构建application UI 使用的“build sales chart tooltip html”行为。
 *
 * @param {Object|*} data Structured data consumed by this function. / 本函数使用的结构化数据。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function buildSalesChartTooltipHtml(data){
    const dayTarget=Math.max(
        1,
        parseInt(data.daily_target,10)||salesChartDailyTarget||10
    );
    const missing=Math.max(
        0,
        dayTarget-data.post_count
    );

    return (
        '<strong>'+escapeHtml(data.date)+'</strong>'
        +'<span>'
            +escapeHtml(salesTr('total'))
            +': <b>'+data.post_count+'</b>'
        +'</span>'
        +'<span class="good">'
            +escapeHtml(salesTr('good'))
            +': <b>'+data.good_count+'</b>'
        +'</span>'
        +'<span class="bad">'
            +escapeHtml(salesTr('issues'))
            +': <b>'+data.bad_count+'</b>'
        +'</span>'
        +'<span class="unreviewed">'
            +escapeHtml(salesTr('unreviewed'))
            +': <b>'+data.unreviewed_count+'</b>'
        +'</span>'
        +'<span class="missing">'
            +escapeHtml(salesTr('missing'))
            +': <b>'+missing+'</b>'
        +'</span>'
        +'<span>'
            +escapeHtml(salesTr('targetLine'))
            +': <b>'+dayTarget+'</b>'
        +'</span>'
    );
}

/**
 * EN: Perform the sales chart tick step behavior used by the application UI.
 * 中文：执行application UI 使用的“sales chart tick step”行为。
 *
 * @param {*} maxValue Max value value used by this function. / 本函数使用的“max value”参数值。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function salesChartTickStep(maxValue){
    maxValue=Math.max(
        1,
        Number(maxValue)||1
    );

    /*
     * Aim for about six intervals.
     * Target 10 -> cap 12 -> step 2, giving:
     * 0, 2, 4, 6, 8, 10, 12
     */
    const rough=maxValue/6;

    if(rough<=1){
        return 1;
    }

    if(rough<=2){
        return 2;
    }

    if(rough<=3){
        return 3;
    }

    if(rough<=5){
        return 5;
    }

    const magnitude=Math.pow(
        10,
        Math.floor(
            Math.log10(rough)
        )
    );

    const normalized=
        rough/magnitude;

    let nice=10;

    if(normalized<=1){
        nice=1;
    }else if(normalized<=2){
        nice=2;
    }else if(normalized<=5){
        nice=5;
    }

    return nice*magnitude;
}

/**
 * EN: Render the render sales chart yaxis behavior used by the application UI.
 * 中文：渲染application UI 使用的“render sales chart yaxis”行为。
 *
 * @param {*} cap Cap value used by this function. / 本函数使用的“cap”参数值。
 * @param {Element|*} target Target DOM node or application object. / 目标 DOM 节点或应用对象。
 * @param {*} plotHeight Plot height value used by this function. / 本函数使用的“plot height”参数值。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function renderSalesChartYAxis(
    cap,
    target,
    plotHeight
){
    const $ticks=$(
        '#salesChartYAxisTicks'
    );
    const $grid=$(
        '#salesChartGridLines'
    );

    if(!$ticks.length){
        return;
    }

    const step=salesChartTickStep(
        cap
    );

    const values=[];

    for(
        let value=0;
        value<=cap+0.0001;
        value+=step
    ){
        values.push(
            Number(
                value.toFixed(4)
            )
        );
    }

    if(
        !values.length
        ||Math.abs(
            values[values.length-1]-cap
        )>0.0001
    ){
        values.push(cap);
    }

    const seen=new Set();
    let ticksHtml='';
    let gridHtml='';

    values.forEach(function(value){
        const key=String(value);

        if(seen.has(key)){
            return;
        }

        seen.add(key);

        /*
         * Every vertical value is measured from the TOP of the same plot:
         * cap => 0px
         * 0   => plotHeight
         */
        const top=
            plotHeight
            *(1-(value/cap));

        const label=
            Number.isInteger(value)
                ?String(value)
                :String(
                    Number(
                        value.toFixed(1)
                    )
                );

        ticksHtml+=(
            '<span'
                +' class="sales-chart-y-tick'
                +(Math.abs(value-target)<0.0001
                    ?' target'
                    :'')
                +'"'
                +' style="top:'
                    +top
                    +'px"'
            +'>'
                +escapeHtml(label)
            +'</span>'
        );

        gridHtml+=(
            '<span'
                +' class="sales-chart-grid-line'
                +(Math.abs(value-target)<0.0001
                    ?' target'
                    :'')
                +'"'
                +' style="top:'
                    +top
                    +'px"'
            +'></span>'
        );
    });

    $ticks.html(
        ticksHtml
    );

    if($grid.length){
        $grid.html(
            gridHtml
        );
    }
}

/**
 * EN: Render the render sales chart behavior used by the application UI.
 * 中文：渲染application UI 使用的“render sales chart”行为。
 *
 * @param {Object|*} options Optional settings that control this function. / 控制本函数行为的可选设置。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function renderSalesChart(options){
    // The isolated controller owns current rows, range and animation.
    if(typeof window.renderSalesChart==='function'){
        window.renderSalesChart(options);
        return;
    }
    // Sales pages load sales-dashboard.js immediately after this file.
    // Keep the server chart until that controller is ready.
    if(document.getElementById('salesPortalDashboard')){
        return;
    }
    const $bars=$('#salesChartBars');
    const $canvas=$('#salesChartCanvas');
    const $panel=$('#salesActivityChartPanel');
    const $scroll=$('#salesChartScroll');
    const $yAxis=$('#salesChartYAxis');

    if(
        !$bars.length
        ||!$panel.length
        ||!$canvas.length
    ){
        return;
    }

    /*
     * Canonical chart geometry.
     * Do not measure a previously styled DOM height and then try to infer
     * the plot from it. All chart layers use these exact dimensions.
     */
    const chartHeight=280;
    const xAxisHeight=32;
    const plotHeight=
        chartHeight-xAxisHeight;

    const from=String(
        $('#salesRangeFrom').val()
        ||''
    );

    const to=String(
        $('#salesRangeTo').val()
        ||''
    );

    const dates=salesDateRange(
        from,
        to
    );

    if(!dates.length){
        $bars.empty();
        $('#salesChartYAxisTicks').empty();
        $('#salesChartGridLines').empty();
        return;
    }

    const target=Math.max(
        1,
        salesChartDailyTarget
    );

    /*
     * Keep exactly 20% headroom.
     * Target 10 => cap 12.
     */
    const cap=Math.max(
        target,
        target*1.2
    );

    $('#salesChartTargetCopy,#salesChartTargetLineValue')
        .text(target);

    $canvas.css({
        'height':chartHeight+'px',
        '--sales-chart-height':
            chartHeight+'px',
        '--sales-plot-height':
            plotHeight+'px',
        '--sales-x-axis-height':
            xAxisHeight+'px'
    });

    $yAxis.css(
        'height',
        chartHeight+'px'
    );

    renderSalesChartYAxis(
        cap,
        target,
        plotHeight
    );

    /*
     * Target line uses the exact same top-origin coordinate as Y ticks.
     */
    const targetTop=
        plotHeight
        *(1-(target/cap));

    $('#salesChartTargetLine')
        .css(
            'top',
            targetTop+'px'
        );

    const availableWidth=Math.max(
        320,
        Math.floor(
            (
                $scroll.innerWidth()
                ||$panel.innerWidth()
                ||720
            )-2
        )
    );

    const dayCount=dates.length;
    const coarse=Boolean(
        window.matchMedia
        &&window.matchMedia(
            '(pointer:coarse)'
        ).matches
    );

    /*
     * Make short ranges visually useful, but keep long ranges scrollable.
     */
    let minimumSlot;

    if(dayCount<=3){
        minimumSlot=coarse
            ?96
            :82;
    }else if(dayCount<=7){
        minimumSlot=coarse
            ?64
            :52;
    }else{
        minimumSlot=coarse
            ?40
            :34;
    }

    const naturalSlot=
        availableWidth/dayCount;

    const needsScroll=
        naturalSlot<minimumSlot;

    const canvasWidth=needsScroll
        ?Math.max(
            availableWidth,
            dayCount*minimumSlot
        )
        :availableWidth;

    const slotWidth=
        canvasWidth/dayCount;

    let barWidth;

    if(dayCount<=3){
        barWidth=Math.min(
            74,
            Math.max(
                46,
                slotWidth*.46
            )
        );
    }else if(dayCount<=7){
        barWidth=Math.min(
            48,
            Math.max(
                24,
                slotWidth*.45
            )
        );
    }else{
        barWidth=Math.min(
            34,
            Math.max(
                12,
                slotWidth*.58
            )
        );
    }

    let html='';

    dates.forEach(function(date){
        const raw=
            aggregateSalesChartDate(
                date,
                salesPlatformFilter
            );

        const actual=Math.max(
            0,
            parseInt(
                raw.post_count,
                10
            )||0
        );

        const good=Math.min(
            actual,
            Math.max(
                0,
                parseInt(
                    raw.good_count,
                    10
                )||0
            )
        );

        const bad=Math.min(
            Math.max(
                0,
                actual-good
            ),
            Math.max(
                0,
                parseInt(
                    raw.bad_count,
                    10
                )||0
            )
        );

        const unreviewed=
            Math.max(
                0,
                actual-good-bad
            );

        /*
         * Heights are percentages of the EXACT plotHeight.
         * Therefore target posts and target line have identical pixels.
         */
        const visibleTotal=
            Math.min(
                actual,
                cap
            );

        const scale=
            actual>0
                ?visibleTotal/actual
                :0;

        const goodH=
            (good*scale/cap)*100;

        const badH=
            (bad*scale/cap)*100;

        const unreviewedH=
            (unreviewed*scale/cap)*100;

        const missing=Math.max(
            0,
            target-actual
        );

        html+=(
            '<div'
                +' class="sales-chart-day"'
                +' tabindex="0"'
                +' data-chart-date="'
                    +escapeHtml(date)
                +'"'
                +' data-chart-total="'
                    +actual
                +'"'
                +' data-chart-good="'
                    +good
                +'"'
                +' data-chart-bad="'
                    +bad
                +'"'
                +' data-chart-unreviewed="'
                    +unreviewed
                +'"'
                +' data-chart-missing="'
                    +missing
                +'"'
            +'>'
                +'<div class="sales-chart-day-plot">'
                    +'<div class="sales-chart-stack">'
                        +'<span'
                            +' class="sales-chart-segment good"'
                            +' style="height:'
                                +goodH
                                +'%"'
                        +'></span>'
                        +'<span'
                            +' class="sales-chart-segment bad"'
                            +' style="height:'
                                +badH
                                +'%"'
                        +'></span>'
                        +'<span'
                            +' class="sales-chart-segment unreviewed"'
                            +' style="height:'
                                +unreviewedH
                                +'%"'
                        +'></span>'
                    +'</div>'
                    +(actual>cap
                        ?'<span'
                            +' class="sales-chart-over-cap"'
                            +'>120%+</span>'
                        :'')
                +'</div>'
                +'<span class="sales-chart-x-label">'
                    +escapeHtml(
                        salesShortDate(date)
                    )
                +'</span>'
            +'</div>'
        );
    });

    $bars.html(html);

    $canvas.css(
        'width',
        Math.round(
            canvasWidth
        )+'px'
    );

    $bars.css({
        'grid-template-columns':
            'repeat('
            +dayCount
            +',minmax(0,1fr))',
        'grid-auto-flow':'row',
        'grid-auto-columns':'unset',
        '--sales-chart-bar-width':
            Math.round(
                barWidth
            )+'px'
    });

    $panel
        .attr(
            'data-range-days',
            dayCount
        )
        .attr(
            'data-chart-from',
            from
        )
        .attr(
            'data-chart-to',
            to
        )
        .toggleClass(
            'sales-chart-short-range',
            dayCount<=7
        )
        .toggleClass(
            'sales-chart-scrollable',
            needsScroll
        );
}

/**
 * EN: Perform the sales chart event point behavior used by the application UI.
 * 中文：执行application UI 使用的“sales chart event point”行为。
 *
 * @param {Event|*} event DOM event that triggered the operation. / 触发本操作的 DOM Event。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function salesChartEventPoint(event){
    const raw=event&&event.originalEvent
        ?event.originalEvent
        :event;

    if(
        raw
        &&typeof raw.clientX==='number'
        &&typeof raw.clientY==='number'
    ){
        return {
            x:raw.clientX,
            y:raw.clientY,
            pointerType:String(raw.pointerType||'')
        };
    }

    return null;
}

/**
 * EN: Position the position sales chart tooltip behavior used by the application UI.
 * 中文：定位application UI 使用的“position sales chart tooltip”行为。
 *
 * @param {*} $day $day value used by this function. / 本函数使用的“$day”参数值。
 * @param {Event|*} event DOM event that triggered the operation. / 触发本操作的 DOM Event。
 * @param {*} mode Mode value used by this function. / 本函数使用的“mode”参数值。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function positionSalesChartTooltip($day,event,mode){
    if(!$day||!$day.length||!$salesChartTooltip.length){
        return;
    }

    const tooltip=$salesChartTooltip[0];
    const width=$salesChartTooltip.outerWidth()||176;
    const height=$salesChartTooltip.outerHeight()||120;
    const viewportWidth=document.documentElement.clientWidth||window.innerWidth;
    const viewportHeight=document.documentElement.clientHeight||window.innerHeight;
    const edge=8;
    const gap=12;
    const point=salesChartEventPoint(event);
    const rect=$day[0].getBoundingClientRect();

    let left;
    let top;

    if(mode==='pointer'&&point){
        left=point.x+gap;
        top=point.y+gap;

        if(left+width+edge>viewportWidth){
            left=point.x-width-gap;
        }

        if(top+height+edge>viewportHeight){
            top=point.y-height-gap;
        }
    }else{
        /*
         * Touch and keyboard interaction stays anchored to the selected day
         * instead of following a finger and being covered by it.
         */
        left=rect.left+(rect.width/2)-(width/2);
        top=rect.top-height-gap;

        if(top<edge){
            top=rect.bottom+gap;
        }
    }

    left=Math.max(
        edge,
        Math.min(
            Math.max(edge,viewportWidth-width-edge),
            left
        )
    );
    top=Math.max(
        edge,
        Math.min(
            Math.max(edge,viewportHeight-height-edge),
            top
        )
    );

    tooltip.style.left=Math.round(left)+'px';
    tooltip.style.top=Math.round(top)+'px';
}

/**
 * EN: Open or show the show sales chart tooltip behavior used by the application UI.
 * 中文：打开或显示application UI 使用的“show sales chart tooltip”行为。
 *
 * @param {*} $day $day value used by this function. / 本函数使用的“$day”参数值。
 * @param {Event|*} event DOM event that triggered the operation. / 触发本操作的 DOM Event。
 * @param {*} mode Mode value used by this function. / 本函数使用的“mode”参数值。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function showSalesChartTooltip($day,event,mode){
    if(!$day||!$day.length||!$salesChartTooltip.length){
        return;
    }

    const data={
        date:String($day.attr('data-chart-date')||''),
        post_count:parseInt($day.attr('data-chart-total'),10)||0,
        good_count:parseInt($day.attr('data-chart-good'),10)||0,
        bad_count:parseInt($day.attr('data-chart-bad'),10)||0,
        unreviewed_count:parseInt(
            $day.attr('data-chart-unreviewed'),
            10
        )||0,
        daily_target:parseInt(
            $day.attr('data-chart-target'),
            10
        )||salesChartDailyTarget
    };

    $salesChartTooltip
        .html(buildSalesChartTooltipHtml(data))
        .removeClass('hidden');

    positionSalesChartTooltip(
        $day,
        event,
        mode||'anchor'
    );
}

/**
 * EN: Position the move sales chart tooltip with pointer behavior used by the application UI.
 * 中文：定位application UI 使用的“move sales chart tooltip with pointer”行为。
 *
 * @param {*} $day $day value used by this function. / 本函数使用的“$day”参数值。
 * @param {Event|*} event DOM event that triggered the operation. / 触发本操作的 DOM Event。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function moveSalesChartTooltipWithPointer($day,event){
    if(
        !$day
        ||!$day.length
        ||!$salesChartTooltip.length
        ||$salesChartTooltip.hasClass('hidden')
    ){
        return;
    }

    positionSalesChartTooltip(
        $day,
        event,
        'pointer'
    );
}

/**
 * EN: Check the cancel sales chart hover timer behavior used by the application UI.
 * 中文：检查application UI 使用的“cancel sales chart hover timer”行为。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function cancelSalesChartHoverTimer(){
    if(salesChartHoverTimer){
        window.clearTimeout(salesChartHoverTimer);
        salesChartHoverTimer=null;
    }

    salesChartHoverDay=null;
    salesChartHoverPoint=null;
}

/**
 * EN: Close or clear the hide sales chart tooltip behavior used by the application UI.
 * 中文：关闭或清理application UI 使用的“hide sales chart tooltip”行为。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function hideSalesChartTooltip(){
    cancelSalesChartHoverTimer();
    $salesChartTooltip.addClass('hidden');
    salesTouchChartDay=null;
}

/**
 * EN: Update the update sales day status counts behavior used by the application UI.
 * 中文：更新application UI 使用的“update sales day status counts”行为。
 *
 * @param {*} $section $section value used by this function. / 本函数使用的“$section”参数值。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function updateSalesDayStatusCounts($section){
    const $all=$section.find('.sales-self-post-card');
    const $platformCards=$all.filter(function(){
        const cardPlatform=String(
            $(this).attr(
                'data-sales-post-platform'
            )||''
        ).trim().toLowerCase();

        return (
            salesPlatformFilter==='all'
            ||cardPlatform===salesPlatformFilter
        );
    });

    const counts={
        all:$platformCards.length,
        good:$platformCards.filter(
            '[data-sales-post-status="good"]'
        ).length,
        bad:$platformCards.filter(
            '[data-sales-post-status="bad"]'
        ).length,
        unreviewed:$platformCards.filter(
            '[data-sales-post-status="unreviewed"]'
        ).length
    };

    $section
        .find('[data-sales-day-filter]')
        .each(function(){
            const type=String(
                $(this).data('sales-day-filter')||'all'
            );

            $(this)
                .find('strong')
                .text(counts[type]||0);
        });

    return counts;
}

/**
 * EN: Perform the sales prefers reduced motion behavior used by the application UI.
 * 中文：执行application UI 使用的“sales prefers reduced motion”行为。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function salesPrefersReducedMotion(){
    return Boolean(
        window.matchMedia
        &&window.matchMedia(
            '(prefers-reduced-motion: reduce)'
        ).matches
    );
}

/**
 * EN: Perform the animate sales content in behavior used by the application UI.
 * 中文：执行application UI 使用的“animate sales content in”行为。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function animateSalesContentIn(){
    const $stage=$('#salesDailyStage');
    const $chartBody=$(
        '#salesActivityChartPanel .sales-chart-shell'
    );

    $stage.removeClass(
        'sales-content-changing sales-channel-changing'
    );
    $chartBody.removeClass(
        'sales-content-changing sales-channel-changing'
    );

    if(salesPrefersReducedMotion()){
        return;
    }

    $stage
        .removeClass('sales-content-enter')
        .addClass('sales-content-enter');

    $chartBody
        .removeClass('sales-chart-enter')
        .addClass('sales-chart-enter');

    $stage
        .find('.sales-day-section')
        .each(function(sectionIndex){
            this.style.setProperty(
                '--sales-section-index',
                sectionIndex
            );

            $(this)
                .find('.sales-self-post-card')
                .each(function(cardIndex){
                    this.style.setProperty(
                        '--sales-card-index',
                        cardIndex
                    );
                });
        });

    window.setTimeout(
        function(){
            $stage.removeClass(
                'sales-content-enter'
            );
            $chartBody.removeClass(
                'sales-chart-enter'
            );
        },
        520
    );
}

/**
 * EN: Update the apply sales day filter behavior used by the application UI.
 * 中文：更新application UI 使用的“apply sales day filter”行为。
 *
 * @param {*} $section $section value used by this function. / 本函数使用的“$section”参数值。
 * @param {*} filter Filter value used by this function. / 本函数使用的“filter”参数值。
 * @param {*} animate Animate value used by this function. / 本函数使用的“animate”参数值。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function applySalesDayFilter($section,filter,animate){
    const $cards=$section.find(
        '.sales-self-post-card'
    );
    const counts=updateSalesDayStatusCounts(
        $section
    );

    filter=String(filter||'all');

    const reduced=
        salesPrefersReducedMotion();
    const useAnimation=
        Boolean(animate&&!reduced);

    let targetVisible=0;

    $cards.each(function(){
        const card=this;
        const $card=$(card);

        const status=String(
            $card.attr(
                'data-sales-post-status'
            )||'unreviewed'
        );

        const platform=String(
            $card.attr(
                'data-sales-post-platform'
            )||''
        ).trim().toLowerCase();

        const platformMatch=
            salesPlatformFilter==='all'
            ||platform===salesPlatformFilter;

        const statusMatch=
            filter==='all'
            ||status===filter;

        const show=
            platformMatch&&statusMatch;

        const oldTimer=$card.data(
            'sales-filter-timer'
        );

        if(oldTimer){
            window.clearTimeout(
                oldTimer
            );
            $card.removeData(
                'sales-filter-timer'
            );
        }

        if(show){
            targetVisible++;

            const wasHidden=
                card.hidden
                ||$card.hasClass(
                    'sales-filter-hidden'
                );

            card.hidden=false;

            $card
                .removeClass(
                    'sales-filter-hidden sales-filter-leaving'
                )
                .attr(
                    'aria-hidden',
                    'false'
                );

            if(useAnimation&&wasHidden){
                $card
                    .removeClass(
                        'sales-filter-entering'
                    );

                void card.offsetWidth;

                $card.addClass(
                    'sales-filter-entering'
                );

                const timer=
                    window.setTimeout(
                        function(){
                            $card.removeClass(
                                'sales-filter-entering'
                            );
                        },
                        240
                    );

                $card.data(
                    'sales-filter-timer',
                    timer
                );
            }
        }else{
            $card.attr(
                'aria-hidden',
                'true'
            );

            if(
                useAnimation
                &&!card.hidden
                &&!$card.hasClass(
                    'sales-filter-hidden'
                )
            ){
                $card
                    .removeClass(
                        'sales-filter-entering'
                    )
                    .addClass(
                        'sales-filter-leaving'
                    );

                const timer=
                    window.setTimeout(
                        function(){
                            card.hidden=true;

                            $card
                                .removeClass(
                                    'sales-filter-leaving'
                                )
                                .addClass(
                                    'sales-filter-hidden'
                                );
                        },
                        135
                    );

                $card.data(
                    'sales-filter-timer',
                    timer
                );
            }else{
                card.hidden=true;

                $card
                    .removeClass(
                        'sales-filter-leaving sales-filter-entering'
                    )
                    .addClass(
                        'sales-filter-hidden'
                    );
            }
        }
    });

    $section.toggleClass(
        'sales-platform-section-empty',
        counts.all===0
    );

    let $empty=$section.find(
        '[data-sales-filter-empty]'
    );

    if(!$empty.length){
        $empty=$(
            '<div'
                +' class="sales-filter-empty hidden"'
                +' data-sales-filter-empty'
            +'>'
                +'<div class="sales-filter-empty-card">'
                    +'<span class="sales-filter-empty-icon" aria-hidden="true">'
                        +'<svg viewBox="0 0 24 24">'
                            +'<path d="M4 4h16v16H4V4Zm2 2v12h12V6H6Zm2 2h8v2H8V8Zm0 4h5v2H8v-2Z"/>'
                        +'</svg>'
                    +'</span>'
                    +'<strong data-sales-filter-empty-title></strong>'
                    +'<span data-sales-filter-empty-message></span>'
                +'</div>'
            +'</div>'
        );

        $section
            .find(
                '.sales-post-card-grid'
            )
            .append($empty);
    }

    $empty
        .find(
            '[data-sales-filter-empty-title]'
        )
        .text(
            salesTr('empty')
        );

    $empty
        .find(
            '[data-sales-filter-empty-message]'
        )
        .text(
            filter==='all'
                ?salesTr('noPostsRange')
                :(
                    salesTr('noPostsRange')
                    +' · '
                    +salesPostStatusLabel(
                        filter
                    )
                )
        );

    const oldEmptyTimer=$empty.data(
        'sales-empty-timer'
    );

    if(oldEmptyTimer){
        window.clearTimeout(
            oldEmptyTimer
        );
        $empty.removeData(
            'sales-empty-timer'
        );
    }

    if(targetVisible===0){
        if(useAnimation){
            const emptyTimer=
                window.setTimeout(
                    function(){
                        $empty
                            .removeClass(
                                'hidden sales-filter-empty-enter'
                            );

                        void $empty[0].offsetWidth;

                        $empty.addClass(
                            'sales-filter-empty-enter'
                        );

                        $empty.removeData(
                            'sales-empty-timer'
                        );
                    },
                    110
                );

            $empty.data(
                'sales-empty-timer',
                emptyTimer
            );
        }else{
            $empty.removeClass(
                'hidden sales-filter-empty-enter'
            );
        }
    }else{
        $empty
            .addClass('hidden')
            .removeClass(
                'sales-filter-empty-enter'
            );
    }

    $section
        .find(
            '[data-sales-day-filter]'
        )
        .each(function(){
            const active=
                String(
                    $(this).data(
                        'sales-day-filter'
                    )
                )===filter;

            $(this)
                .toggleClass(
                    'active',
                    active
                )
                .attr(
                    'aria-pressed',
                    active
                        ?'true'
                        :'false'
                );
        });

    $section.attr(
        'data-active-status-filter',
        filter
    );
}

/**
 * EN: Update the apply sales platform filter to cards behavior used by the application UI.
 * 中文：更新application UI 使用的“apply sales platform filter to cards”行为。
 *
 * @param {*} animate Animate value used by this function. / 本函数使用的“animate”参数值。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function applySalesPlatformFilterToCards(animate){
    $('.sales-day-section').each(function(){
        const $section=$(this);
        const active=String(
            $section
                .find('[data-sales-day-filter].active')
                .data('sales-day-filter')
            ||'all'
        );

        applySalesDayFilter($section,active,animate);
    });
}

/**
 * EN: Render the render sales range data behavior used by the application UI.
 * 中文：渲染application UI 使用的“render sales range data”行为。
 *
 * @param {Object|*} data Structured data consumed by this function. / 本函数使用的结构化数据。
 * @param {*} range Range value used by this function. / 本函数使用的“range”参数值。
 * @param {*} period Period value used by this function. / 本函数使用的“period”参数值。
 * @param {*} channel Channel value used by this function. / 本函数使用的“channel”参数值。
 * @param {*} reason Reason value used by this function. / 本函数使用的“reason”参数值。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function renderSalesRangeData(data,range,period,channel,reason){
    const $wrap=$('#dailyPosts');
    const $empty=$('#dailyPostsEmpty');
    const $load=$('#loadMoreDailyPosts');

    range={
        from:String(
            data.from
            ||range.from
            ||''
        ),
        to:String(
            data.to
            ||range.to
            ||''
        )
    };

    period=String(
        data.period
        ||period
        ||'custom'
    );

    $('#salesRangeFrom').val(
        range.from
    );

    $('#salesRangeTo').val(
        range.to
    );

    syncSalesRangeConstraints('');

    salesPlatformFilter=String(
        channel
        ||data.channel
        ||salesPlatformFilter
        ||'all'
    ).trim().toLowerCase();

    $('#salesPortalDashboard').attr(
        'data-channel',
        salesPlatformFilter
    );

    $wrap
        .html(data.html||'')
        .attr('data-from',range.from)
        .attr('data-to',range.to)
        .attr(
            'data-offset',
            data.next_offset||0
        );

    /*
     * chart_rows covers the COMPLETE selected range.
     * Do not replace it with only the currently paged DOM cards.
     */
    salesChartRows=Array.isArray(
        data.chart_rows
    )
        ?data.chart_rows
        :[];

    salesChartDailyTarget=Math.max(
        1,
        parseInt(data.daily_target,10)||10
    );

    const hasDays=
        (parseInt(data.total_days,10)||0)>0;
    const $dailyStage=$('#salesDailyStage');

    $empty.toggleClass(
        'hidden',
        hasDays
    );

    if($dailyStage.length){
        const preserveFilterHeight=
            String(reason||'range')
                ==='channel';

        $dailyStage.toggleClass(
            'sales-daily-stage-empty',
            !hasDays
        );

        $dailyStage.toggleClass(
            'sales-daily-stage-preserved',
            !hasDays
            &&preserveFilterHeight
        );

        if(
            hasDays
            ||!preserveFilterHeight
        ){
            $dailyStage.css(
                '--sales-preserved-height',
                ''
            );
        }
    }

    if(data.has_more){
        $load
            .prop('disabled',false)
            .show()
            .find('[data-sales-i18n="loadEarlier"]')
            .text(salesTr('loadEarlier'));
    }else{
        $load
            .prop('disabled',true)
            .hide();
    }

    $('#dailyLoadStatus').text('');
    $('#salesRangeStatus').text('');

    setSalesRangePeriod(
        period
        ||detectSalesRangePeriod(
            range.from,
            range.to
        )
    );

    applySalesLanguage();
    renderSalesChart();
    applySalesPlatformFilterToCards();
    updateSalesBackToday(range);

    const url=new URL(
        window.location.href
    );

    url.searchParams.set(
        'from',
        range.from
    );
    url.searchParams.set(
        'to',
        range.to
    );

    url.searchParams.set(
        'period',
        salesRangePeriod
    );

    if(salesPlatformFilter==='all'){
        url.searchParams.delete('channel');
    }else{
        url.searchParams.set(
            'channel',
            salesPlatformFilter
        );
    }

    window.history.replaceState(
        {},
        '',
        url.toString()
    );

    animateSalesContentIn();
}

/**
 * EN: Read or load the load sales range behavior used by the application UI.
 * 中文：读取或加载application UI 使用的“load sales range”行为。
 *
 * @param {*} range Range value used by this function. / 本函数使用的“range”参数值。
 * @param {*} period Period value used by this function. / 本函数使用的“period”参数值。
 * @param {*} channel Channel value used by this function. / 本函数使用的“channel”参数值。
 * @param {*} reason Reason value used by this function. / 本函数使用的“reason”参数值。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function loadSalesRange(range,period,channel,reason){
    if(!range){
        return;
    }

    reason=String(
        reason||'range'
    );

    const $dailyStage=$(
        '#salesDailyStage'
    );
    const $chartBody=$(
        '#salesActivityChartPanel .sales-chart-shell'
    );

    if($dailyStage.length){
        if(reason==='channel'){
            const currentHeight=Math.ceil(
                $dailyStage.outerHeight()||0
            );

            if(currentHeight>0){
                $dailyStage.css(
                    '--sales-preserved-height',
                    currentHeight+'px'
                );
            }
        }else{
            $dailyStage.css(
                '--sales-preserved-height',
                ''
            );
        }
    }

    $dailyStage
        .removeClass(
            'sales-content-enter sales-channel-enter'
        )
        .attr(
            'data-transition-reason',
            reason
        );

    $chartBody
        .removeClass(
            'sales-chart-enter sales-channel-enter'
        );

    startSalesRangeVisualState(
        reason
    );

    const requestSeq=
        ++salesRangeRequestSeq;

    if(
        salesRangeRequest
        &&salesRangeRequest.readyState!==4
    ){
        salesRangeRequest.abort();
    }

    $('#salesRangeStatus')
        .removeClass('error')
        .text('');

    $('#salesActivityChartPanel')
        .attr('aria-busy','true');

    salesRangeRequest=$.ajax({
        url:window.CD_BASE_PATH+'/sales/daily-posts',
        method:'GET',
        dataType:'json',
        cache:false,
        timeout:15000,
        data:{
            from:range.from,
            to:range.to,
            offset:0,
            limit:parseInt(
                $('#dailyPosts').data('limit')||3,
                10
            ),
            channel:String(
                channel
                ||salesPlatformFilter
                ||'all'
            ).trim().toLowerCase(),
            period:String(
                period
                ||salesRangePeriod
                ||'custom'
            )
        }
    })
    .done(function(data){
        if(requestSeq!==salesRangeRequestSeq){
            return;
        }

        if(!data||!data.ok){
            $('#salesRangeStatus')
                .addClass('error')
                .text(
                    (data&&data.message)
                    ||salesTr('loadEarlierFailed')
                );
            return;
        }

        renderSalesRangeData(
            data,
            range,
            period,
            channel,
            reason
        );
    })
    .fail(function(xhr,status){
        if(
            status==='abort'
            ||requestSeq!==salesRangeRequestSeq
        ){
            return;
        }

        $('#salesRangeStatus')
            .addClass('error')
            .text(
                (
                    xhr.responseJSON
                    &&xhr.responseJSON.message
                )
                ||salesTr('loadEarlierFailed')
            );
    })
    .always(function(){
        if(requestSeq!==salesRangeRequestSeq){
            return;
        }

        clearSalesRangeVisualState();
    });
}

/**
 * EN: Open or show the show sales overlay behavior used by the application UI.
 * 中文：打开或显示application UI 使用的“show sales overlay”行为。
 *
 * @param {*} $overlay $overlay value used by this function. / 本函数使用的“$overlay”参数值。
 * @param {*} onShown On shown value used by this function. / 本函数使用的“on shown”参数值。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function showSalesOverlay($overlay,onShown){
    if(!$overlay||!$overlay.length){return;}
    $overlay.stop(true,true).removeClass('hidden').attr('aria-hidden','false');
    if(salesPrefersReducedMotion()){
        $overlay.show();
        if(typeof onShown==='function')onShown();
        return;
    }
    $overlay.hide().fadeIn(150,function(){
        if(typeof onShown==='function')onShown();
    });
}

/**
 * EN: Close or clear the hide sales overlay behavior used by the application UI.
 * 中文：关闭或清理application UI 使用的“hide sales overlay”行为。
 *
 * @param {*} $overlay $overlay value used by this function. / 本函数使用的“$overlay”参数值。
 * @param {*} onHidden On hidden value used by this function. / 本函数使用的“on hidden”参数值。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function hideSalesOverlay($overlay,onHidden){
    if(!$overlay||!$overlay.length){return;}
    const finish=function(){
        $overlay.addClass('hidden').attr('aria-hidden','true').removeAttr('style');
        if(typeof onHidden==='function')onHidden();
    };
    if(salesPrefersReducedMotion()){
        finish();
        return;
    }
    $overlay.stop(true,true).fadeOut(120,finish);
}

/**
 * EN: Open or show the open sales submit modal behavior used by the application UI.
 * 中文：打开或显示application UI 使用的“open sales submit modal”行为。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function openSalesSubmitModal(){
    if(!$salesSubmitModal.length){return false;}
    $('body').addClass('sales-submit-modal-open');
    showSalesOverlay($salesSubmitModal,function(){
        $('#postUrl').trigger('focus');
        updateDetectedPlatform();
        syncSalesInspectionProcessState(true);
    });
    return true;
}

/**
 * EN: Close or clear the close sales submit modal behavior used by the application UI.
 * 中文：关闭或清理application UI 使用的“close sales submit modal”行为。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function closeSalesSubmitModal(){
    if(!$salesSubmitModal.length){return;}

    // EN: Closing the modal is only a visual action. Never abort an active
    // verification request; the server process lock stays authoritative until
    // the request finishes. Reopening the modal will query that lock again.
    // 中文：关闭弹窗只影响界面，不取消正在运行的验证请求。服务器进程锁会一直保持到
    // 请求结束；再次打开弹窗时会重新查询服务器锁状态。
    stopSalesInspectionStatusTimer();
    hideSalesOverlay($salesSubmitModal,function(){
        $('body').removeClass('sales-submit-modal-open');
    });
}

/**
 * EN: Open Bulk Submit Post with the same modal shell and interaction pattern
 * as the approved single Submit Post workflow.
 * 中文：Bulk Submit Post 复用已经通过的 Submit Post 弹窗外壳与交互方式。
 */
function openSalesBulkSubmitModal(){
    if(!$salesBulkSubmitModal.length){return false;}
    $('body').addClass('sales-submit-modal-open');
    showSalesOverlay($salesBulkSubmitModal,function(){
        $('#salesBulkUrls').trigger('focus');
    });
    return true;
}

function closeSalesBulkSubmitModal(){
    if(!$salesBulkSubmitModal.length){return;}
    hideSalesOverlay($salesBulkSubmitModal,function(){
        $('body').removeClass('sales-submit-modal-open');
    });
}

/**
 * EN: Open or show the open sales post detail behavior used by the application UI.
 * 中文：打开或显示application UI 使用的“open sales post detail”行为。
 *
 * @param {*} $card $card value used by this function. / 本函数使用的“$card”参数值。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function openSalesPostDetail($card){
    if(!$card||!$card.length){
        return;
    }

    const postId=String(
        $card.attr('data-sales-post-id')||''
    );
    const deleteStatus=String(
        $card.attr('data-sales-post-delete-status')||''
    ).toLowerCase();
    const platform=String(
        $card.attr('data-sales-post-platform')||''
    );
    const title=String(
        $card.attr('data-sales-post-title')||''
    );
    const description=String(
        $card.attr('data-sales-post-description')||''
    );
    const published=String(
        $card.attr('data-sales-post-published')||''
    );
    const originalUrl=String(
        $card.attr('data-sales-post-url')||''
    );
    const image=String(
        $card.attr('data-sales-post-image')||''
    );
    const status=String(
        $card.attr('data-sales-post-status')
        ||'unreviewed'
    );
    const externalId=String(
        $card.attr('data-sales-post-external-id')||''
    );
    const platformAccount={
        id:String($card.attr('data-sales-post-account-id')||''),
        name:String($card.attr('data-sales-post-account-name')||''),
        url:String($card.attr('data-sales-post-account-url')||'')
    };

    $('#salesPostDetailPlatform').text(
        platformLabel(platform)
        ||platform
        ||'Marketplace'
    );
    $('#salesPostDetailPlatformValue').text(
        platformLabel(platform)
        ||platform
        ||'—'
    );
    $('#salesPostDetailTitle').text(
        title||'Post details'
    );
    $('#salesPostDetailContentTitle').text(
        title||'—'
    );
    $('#salesPostDetailDescription').text(
        description||salesTr('noDescription')
    );
    $('#salesPostDetailPublished').text(
        published||'—'
    );
    $('#salesPostDetailExternalId').text(
        externalId||'—'
    );
    renderMarketplaceAccount(
        $('#salesPostDetailAccountFact'),
        $('#salesPostDetailAccount'),
        platformAccount
    );
    $('#salesPostDeleteRequestId').val(postId);
    $('#salesPostDeleteRequestForm').addClass('hidden');
    $('#salesPostDeleteRequestReason').val('');
    $('#salesPostDeleteRequestMessage').text('');
    $('#salesPostDeleteRequestOpen')
        .prop('disabled',deleteStatus==='pending')
        .toggleClass('delete-requested',deleteStatus==='pending')
        .text(deleteStatus==='pending'?'Deletion requested ✓':'Request deletion');

    $('#salesPostDetailStatus')
        .attr(
            'class',
            'sales-post-detail-status '+status
        )
        .text(
            salesPostStatusLabel(status)
        );

    $('#salesPostDetailOriginal')
        .attr('href',originalUrl||'#')
        .toggleClass(
            'disabled',
            !originalUrl
        );

    if(image){
        $('#salesPostDetailImage')
            .attr('src',image);
        $('#salesImageLightboxImage')
            .attr('src',image);
        $salesPostDetailImageButton
            .removeClass('hidden');
        $('#salesPostDetailNoImage')
            .addClass('hidden');
    }else{
        $('#salesPostDetailImage')
            .attr('src','');
        $('#salesImageLightboxImage')
            .attr('src','');
        $salesPostDetailImageButton
            .addClass('hidden');
        $('#salesPostDetailNoImage')
            .removeClass('hidden');
    }

    $('body').addClass('sales-detail-open');
    showSalesOverlay($salesPostDetailModal,function(){
        $('#salesPostDetailClose').trigger('focus');
    });
}

/**
 * EN: Close or clear the close sales post detail behavior used by the application UI.
 * 中文：关闭或清理application UI 使用的“close sales post detail”行为。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function closeSalesPostDetail(){
    hideSalesOverlay($salesPostDetailModal,function(){
        $('body').removeClass('sales-detail-open');
        $('#salesPostDeleteRequestForm').addClass('hidden').removeAttr('style');
    });
}


/**
 * EN: Toggle Sales all-date Post Search mode without changing the selected dashboard date range.
 * 中文：切换 Sales 全历史 Post 搜索模式，同时保持当前 Dashboard 日期范围不变。
 */
function setSalesSelfPostSearchActive(active){
    if(!$salesPostSearchPanel.length){
        return;
    }

    $salesPostSearchPanel.attr(
        'data-search-active',
        active?'1':'0'
    );

    $salesPostSearchClear.toggleClass(
        'hidden',
        !active
    );

    if(!active){
        $salesPostSearchResults
            .empty()
            .addClass('hidden');
    }
}

/**
 * EN: Render only the Posts matched by Sales all-date search.
 * 中文：仅渲染 Sales 全历史搜索真正命中的 Posts。
 */
function salesPostSearchPlatformIcon(platform){
    platform=String(platform||'').toLowerCase();
    if(platform==='facebook'){
        return '<span class="platform-logo platform-logo-facebook" title="Facebook" aria-label="Facebook">'
            +'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13.8 21v-8h2.7l.4-3.1h-3.1v-2c0-.9.3-1.5 1.6-1.5H17V3.6c-.3 0-1.3-.1-2.5-.1-2.5 0-4.2 1.5-4.2 4.3v2.1H7.5V13h2.8v8h3.5Z"/></svg></span>';
    }
    if(platform==='instagram'){
        return '<span class="platform-logo platform-logo-instagram" title="Instagram" aria-label="Instagram">'
            +'<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4.5" y="4.5" width="15" height="15" rx="4"/><circle cx="12" cy="12" r="3.4"/><circle cx="17.2" cy="6.8" r="1"/></svg></span>';
    }
    if(platform==='offerup'){
        return '<span class="platform-logo platform-logo-offerup" title="OfferUp" aria-label="OfferUp">'
            +'<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="8" cy="12" r="5.2"/><circle cx="16" cy="12" r="5.2"/><path d="M7.8 8.7v6.6M16.2 8.7v6.6"/></svg></span>';
    }
    if(platform==='craigslist'){
        return '<span class="platform-logo platform-logo-craigslist" title="Craigslist" aria-label="Craigslist">'
            +'<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M12 4v16M12 12l-5.2 4M12 12l5.2 4"/></svg></span>';
    }
    return '<span class="platform-logo platform-logo-generic">'
        +'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4V5Zm2 2v10h12V7H6Z"/></svg></span>';
}

function salesPostSearchDisplayDate(row){
    const explicit=String(row&&row.published_display||'').trim();
    if(explicit){
        return explicit;
    }
    const raw=String(
        row&&(row.published_at||row.published_date)||''
    ).trim();
    return raw||'—';
}

/**
 * EN: Render Sales all-date Post Search with the exact same Post-card visual
 * language as the normal Sales Post grid. There is no intermediate result row.
 * 中文：Sales 全历史 Post 搜索直接使用正常 Post Grid 的卡片样式渲染，
 * 不再显示中间的“Search results”文字列表。
 */
function renderSalesSelfPostSearchResults(rows,query){
    if(!$salesPostSearchResults.length){
        return;
    }

    rows=Array.isArray(rows)?rows:[];
    salesSelfPostSearchRows=rows;
    salesSelfPostSearchQuery=String(query||'');

    if(!rows.length){
        $salesPostSearchResults
            .removeClass('hidden')
            .html(
                '<div class="sales-post-search-empty">'
                +escapeHtml(salesTr('salesPostSearchEmpty'))
                +'</div>'
            );
        return;
    }

    const cards=rows.map(function(row){
        const platform=String(row.platform||'').toLowerCase();
        const title=String(row.title||'').trim()||'Post details';
        const description=String(row.description||'').trim();
        const published=String(row.published_at||row.published_date||'');
        const displayDate=salesPostSearchDisplayDate(row);
        const status=String(row.status||'unreviewed').toLowerCase();
        const normalizedStatus=['good','bad'].includes(status)?status:'unreviewed';
        const statusClass=normalizedStatus==='good'
            ?' review-good'
            :normalizedStatus==='bad'
                ?' review-bad'
                :'';
        const statusLabel=salesPostStatusLabel(normalizedStatus);
        const image=String(row.thumbnail_url||'').trim();
        const icon=salesPostSearchPlatformIcon(platform);

        return (
            '<article class="sales-self-post-card sales-post-search-card'+statusClass+'"'
            +' data-sales-post-id="'+escapeHtml(String(row.post_id||''))+'"'
            +' data-sales-post-platform="'+escapeHtml(platform)+'"'
            +' data-sales-post-title="'+escapeHtml(title)+'"'
            +' data-sales-post-description="'+escapeHtml(description)+'"'
            +' data-sales-post-published="'+escapeHtml(published)+'"'
            +' data-sales-post-date="'+escapeHtml(String(row.published_date||''))+'"'
            +' data-sales-post-url="'+escapeHtml(String(row.original_url||''))+'"'
            +' data-sales-post-image="'+escapeHtml(image)+'"'
            +' data-sales-post-status="'+escapeHtml(normalizedStatus)+'"'
            +' data-sales-post-external-id="'+escapeHtml(String(row.external_post_id||''))+'"'
            +' data-sales-post-account-id="'+escapeHtml(String(row.platform_account_id||''))+'"'
            +' data-sales-post-account-name="'+escapeHtml(String(row.platform_account_name||''))+'"'
            +' data-sales-post-account-url="'+escapeHtml(String(row.platform_account_url||''))+'"'
            +' data-sales-post-delete-status="'+escapeHtml(String(row.deletion_request_status||''))+'"'
            +' role="button" tabindex="0" aria-label="View post details: '+escapeHtml(title)+'">'
                +'<div class="sales-self-post-media">'
                    +(image
                        ?'<img src="'+escapeHtml(image)+'" loading="lazy" alt="">'
                        :'<div class="sales-self-post-placeholder">'+icon+'<span>'
                            +escapeHtml(platformLabel(platform)||platform||'Marketplace')
                            +'</span></div>')
                    +'<span class="sales-self-post-platform">'+icon+'</span>'
                +'</div>'
                +'<div class="sales-self-post-body">'
                    +'<div class="sales-self-post-date"><span data-sales-i18n="postDate">'
                        +escapeHtml(salesTr('postDate'))
                        +'</span> · '+escapeHtml(displayDate)+'</div>'
                    +'<h3>'+escapeHtml(title)+'</h3>'
                    +'<p>'+escapeHtml(description||salesTr('noDescription'))+'</p>'
                +'</div>'
                +'<div class="sales-self-post-footer">'
                    +'<button type="button" class="sales-view-details" data-view-sales-post>'
                        +'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5c5.5 0 9.5 5.2 9.5 7s-4 7-9.5 7S2.5 13.8 2.5 12 6.5 5 12 5Zm0 2C8.3 7 5.3 10.2 4.6 12c.7 1.8 3.7 5 7.4 5s6.7-3.2 7.4-5C18.7 10.2 15.7 7 12 7Zm0 2.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Z"/></svg>'
                        +'<span data-sales-i18n="viewDetails">'+escapeHtml(salesTr('viewDetails'))+'</span>'
                    +'</button>'
                    +'<span class="sales-self-post-status'
                        +(normalizedStatus!=='unreviewed'?' '+escapeHtml(normalizedStatus):'')+'">'
                        +escapeHtml(statusLabel)
                    +'</span>'
                +'</div>'
            +'</article>'
        );
    }).join('');

    $salesPostSearchResults
        .removeClass('hidden')
        .html(
            '<div class="sales-post-card-grid sales-post-search-card-grid">'
            +cards
            +'</div>'
        );
}

/**
 * EN: Query the authenticated Sales user's saved Posts across all dates.
 * 中文：跨全部日期查询当前 Sales 自己保存的 Posts。
 */
function searchSalesPostsAcrossAllDates(query){
    query=String(query||'').trim();

    if(!$salesPostSearchPanel.length){
        return;
    }

    if(query.length<2){
        salesSelfPostSearchRows=[];
        salesSelfPostSearchQuery='';
        if(salesSelfPostSearchRequest&&salesSelfPostSearchRequest.readyState!==4){
            salesSelfPostSearchRequest.abort();
        }
        setSalesSelfPostSearchActive(false);
        return;
    }

    const endpoint=String(
        $('#salesPortalDashboard').attr('data-post-search-url')||''
    );

    if(!endpoint){
        return;
    }

    setSalesSelfPostSearchActive(true);
    $salesPostSearchResults
        .removeClass('hidden')
        .html(
            '<div class="sales-post-search-empty">'
            +escapeHtml(salesTr('salesPostSearching'))+'…'
            +'</div>'
        );

    if(salesSelfPostSearchRequest&&salesSelfPostSearchRequest.readyState!==4){
        salesSelfPostSearchRequest.abort();
    }

    const seq=++salesSelfPostSearchSeq;
    salesSelfPostSearchRequest=$.ajax({
        url:endpoint,
        method:'GET',
        dataType:'json',
        cache:false,
        timeout:15000,
        data:{q:query}
    })
    .done(function(resp){
        if(seq!==salesSelfPostSearchSeq){
            return;
        }
        if(!resp||!resp.ok){
            throw new Error('Sales Post Search returned an invalid response.');
        }
        renderSalesSelfPostSearchResults(resp.matches||[],query);
    })
    .fail(function(xhr,status){
        if(status==='abort'||seq!==salesSelfPostSearchSeq){
            return;
        }
        salesSelfPostSearchRows=[];
        salesSelfPostSearchQuery=query;
        $salesPostSearchResults
            .removeClass('hidden')
            .html(
                '<div class="sales-post-search-empty error">'
                +escapeHtml(
                    (xhr.responseJSON&&xhr.responseJSON.message)
                    ||salesTr('salesPostSearchFailed')
                )
                +'</div>'
            );
    });
}

if($salesPostSearchInput.length){
    $salesPostSearchInput.on('input',function(){
        const query=String($(this).val()||'');
        window.clearTimeout(salesSelfPostSearchTimer);
        salesSelfPostSearchTimer=window.setTimeout(function(){
            searchSalesPostsAcrossAllDates(query);
        },220);
    });
}

$salesPostSearchClear.on('click',function(){
    window.clearTimeout(salesSelfPostSearchTimer);
    $salesPostSearchInput.val('').trigger('focus');
    salesSelfPostSearchRows=[];
    salesSelfPostSearchQuery='';
    setSalesSelfPostSearchActive(false);
});

$(document).on('cdsp:language-changed.sales-post-search',function(){
    if(
        $salesPostSearchPanel.attr('data-search-active')==='1'
        &&salesSelfPostSearchQuery
    ){
        renderSalesSelfPostSearchResults(
            salesSelfPostSearchRows,
            salesSelfPostSearchQuery
        );
    }
});

/**
 * EN: Open or show the open sales image lightbox behavior used by the application UI.
 * 中文：打开或显示application UI 使用的“open sales image lightbox”行为。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function openSalesImageLightbox(){
    const src=String(
        $('#salesPostDetailImage').attr('src')||''
    );

    if(!src){
        return;
    }

    $('#salesImageLightboxImage')
        .attr('src',src);

    showSalesOverlay($salesImageLightbox);
}

/**
 * EN: Close or clear the close sales image lightbox behavior used by the application UI.
 * 中文：关闭或清理application UI 使用的“close sales image lightbox”行为。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function closeSalesImageLightbox(){
    hideSalesOverlay($salesImageLightbox);
}

$('#salesRangeFrom').on('change',function(){
    const range=syncSalesRangeConstraints('from');

    if(!range){
        return;
    }

    setSalesRangePeriod(
        'custom'
    );

    /*
     * X axis responds immediately to the newly selected range.
     * AJAX then replaces data, not the geometry/state.
     */
    renderSalesChart();

    loadSalesRange(
        range,
        'custom',
        salesPlatformFilter
    );
});

$('#salesRangeTo').on('change',function(){
    const range=syncSalesRangeConstraints('to');

    if(!range){
        return;
    }

    setSalesRangePeriod(
        'custom'
    );

    /*
     * X axis responds immediately to the newly selected range.
     * AJAX then replaces data, not the geometry/state.
     */
    renderSalesChart();

    loadSalesRange(
        range,
        'custom',
        salesPlatformFilter
    );
});

$('#salesBackToday').on('click',function(){
    const today=salesTodayValue();

    if(!today){
        return;
    }

    const period=(
        salesRangePeriod==='week'
        ||salesRangePeriod==='month'
        ||salesRangePeriod==='day'
        ||salesRangePeriod==='single'
    )
        ?salesRangePeriod
        :'day';

    const range=salesPresetRange(
        period,
        today
    );

    if(!range){
        return;
    }

    $('#salesRangeFrom').val(range.from);
    $('#salesRangeTo').val(range.to);

    syncSalesRangeConstraints('');
    setSalesRangePeriod(period);
    updateSalesBackToday(range);

    renderSalesChart();

    loadSalesRange(
        range,
        period,
        salesPlatformFilter
    );
});

$('#salesRangeForm').on(
    'submit',
    function(event){
        event.preventDefault();

        const range=
            syncSalesRangeConstraints('');

        if(!range){
            return;
        }

        setSalesRangePeriod(
            'custom'
        );

        loadSalesRange(
            range,
            'custom',
            salesPlatformFilter
        );
    }
);

$(document).on(
    'click',
    '[data-sales-period]',
    function(event){
        event.preventDefault();

        const period=String(
            $(this).attr('data-sales-period')
            ||'day'
        );

        const anchor=String(
            $('#salesRangeTo').val()
            ||salesTodayValue()
            ||''
        );

        if(period==='custom'){
            const customRange=
                syncSalesRangeConstraints('');

            if(!customRange){
                return;
            }

            setSalesRangePeriod(
                'custom'
            );

            renderSalesChart();

            loadSalesRange(
                customRange,
                'custom',
                salesPlatformFilter
            );

            return;
        }

        const range=salesPresetRange(
            period,
            anchor
        );

        if(!range){
            return;
        }

        $('#salesRangeFrom').val(range.from);
        $('#salesRangeTo').val(range.to);

        syncSalesRangeConstraints('');
        setSalesRangePeriod(period);

        /*
         * Immediately switch the X axis to the selected preset.
         * Example: 3 Days becomes exactly three date slots before AJAX.
         */
        renderSalesChart();

        loadSalesRange(
            range,
            period,
            salesPlatformFilter
        );
    }
);

$(document).on(
    'click',
    '[data-sales-platform-filter]',
    function(event){
        event.preventDefault();
        event.stopPropagation();

        const $clicked=$(this);

        const nextChannel=String(
            $clicked.attr(
                'data-sales-platform-filter'
            )||'all'
        ).trim().toLowerCase();

        if(
            nextChannel
            ===salesPlatformFilter
            &&!$('#salesPlatformFilter')
                .hasClass(
                    'sales-channel-loading'
                )
        ){
            return;
        }

        salesPlatformFilter=
            nextChannel;

        $('#salesPlatformFilter')
            .find(
                '[data-sales-platform-filter]'
            )
            .each(function(){
                const active=String(
                    $(this).attr(
                        'data-sales-platform-filter'
                    )||''
                ).trim().toLowerCase()
                    ===salesPlatformFilter;

                $(this)
                    .toggleClass(
                        'active',
                        active
                    )
                    .toggleClass(
                        'sales-channel-button-loading',
                        active
                    )
                    .attr(
                        'aria-pressed',
                        active
                            ?'true'
                            :'false'
                    );
            });

        /*
         * Immediate, guaranteed local feedback:
         * cards fade/reflow now. If zero match, Empty appears now.
         * Server AJAX then replaces it with authoritative filtered data.
         */
        applySalesPlatformFilterToCards(
            true
        );
        renderSalesChart();

        const range=
            syncSalesRangeConstraints('');

        if(!range){
            clearSalesRangeVisualState();
            return;
        }

        loadSalesRange(
            range,
            salesRangePeriod,
            salesPlatformFilter,
            'channel'
        );

        if(
            event.detail>0
            &&document.activeElement===this
        ){
            this.blur();
        }
    }
);

$(document).on(
    'click',
    '[data-sales-day-filter]',
    function(event){
        event.preventDefault();
        event.stopPropagation();

        const $button=$(this);
        const $section=$button.closest(
            '.sales-day-section'
        );
        const filter=String(
            $button.attr('data-sales-day-filter')
            ||'all'
        );

        applySalesDayFilter(
            $section,
            filter,
            true
        );

        if(
            event.detail>0
            &&document.activeElement===this
        ){
            this.blur();
        }
    }
);

$(document).on(
    'click',
    '.sales-self-post-card',
    function(event){
        if(
            $(event.target).closest(
                'a,button,input,form,label,select,textarea'
            ).length
        ){
            return;
        }

        openSalesPostDetail($(this));
    }
);

$(document).on(
    'click',
    '[data-view-sales-post]',
    function(event){
        event.preventDefault();
        event.stopPropagation();

        openSalesPostDetail(
            $(this).closest(
                '.sales-self-post-card'
            )
        );
    }
);

$(document).on(
    'keydown',
    '.sales-self-post-card',
    function(event){
        if(
            event.key!=='Enter'
            &&event.key!==' '
        ){
            return;
        }

        if(
            $(event.target).closest(
                'a,button,input,form,label,select,textarea'
            ).length
        ){
            return;
        }

        event.preventDefault();
        openSalesPostDetail($(this));
    }
);

$(document).on('click','[data-open-sales-submit]',function(event){
    if(!$salesSubmitModal.length){return;}
    event.preventDefault();
    openSalesSubmitModal();
});

$(document).on('click','[data-open-sales-bulk-submit]',function(event){
    if(!$salesBulkSubmitModal.length){return;}
    event.preventDefault();
    openSalesBulkSubmitModal();
});

$('#salesSubmitModalClose').on('click',function(){
    closeSalesSubmitModal();
});

$salesSubmitModal.on('click',function(event){
    if(event.target===this){closeSalesSubmitModal();}
});

$('#salesBulkSubmitModalClose').on('click',function(){
    closeSalesBulkSubmitModal();
});

$salesBulkSubmitModal.on('click',function(event){
    if(event.target===this){closeSalesBulkSubmitModal();}
});

$('#salesPostDeleteRequestOpen').on('click',function(){
    if($(this).prop('disabled'))return;
    const $form=$('#salesPostDeleteRequestForm');
    $('#salesPostDeleteRequestMessage').text('');
    if(salesPrefersReducedMotion()){
        $form.removeClass('hidden').show();
        $('#salesPostDeleteRequestReason').trigger('focus');
        return;
    }
    $form
        .stop(true,true)
        .removeClass('hidden')
        .hide()
        .addClass('sales-request-opening')
        .slideDown(160,function(){
            $(this).removeClass('sales-request-opening');
            $('#salesPostDeleteRequestReason').trigger('focus');
        });
});

$('#salesPostDeleteRequestCancel').on('click',function(){
    const $form=$('#salesPostDeleteRequestForm');
    $('#salesPostDeleteRequestMessage').text('');
    if(salesPrefersReducedMotion()){
        $form.addClass('hidden').removeAttr('style');
        return;
    }
    $form.stop(true,true).slideUp(130,function(){
        $(this).addClass('hidden').removeAttr('style');
    });
});

$('#salesPostDeleteRequestForm').on('submit',function(event){
    event.preventDefault();
    const $form=$(this);
    const $send=$('#salesPostDeleteRequestSend');
    const reason=$('#salesPostDeleteRequestReason').val().trim();
    if(!reason){
        $('#salesPostDeleteRequestMessage').text('Enter a reason.').addClass('error');
        return;
    }
    $send.prop('disabled',true).text('Sending…');
    $('#salesPostDeleteRequestMessage').removeClass('error ok').text('');
    $.ajax({
        url:$form.attr('action'),method:'POST',dataType:'json',data:$form.serialize(),
        headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}
    }).done(function(data){
        const postId=String($('#salesPostDeleteRequestId').val()||'');
        $('.sales-self-post-card[data-sales-post-id="'+postId+'"]')
            .attr('data-sales-post-delete-status','pending');
        $('#salesPostDeleteRequestOpen')
            .prop('disabled',true)
            .addClass('delete-requested')
            .text('Deletion requested ✓');
        $('#salesPostDeleteRequestReason').val('');
        $send.prop('disabled',true).text('Sent');

        // The request is already persisted. Collapse the reason editor
        // immediately so the remaining state is unambiguous to Sales.
        if(salesPrefersReducedMotion()){
            $form.addClass('hidden').removeAttr('style');
        }else{
            $form.stop(true,true).slideUp(150,function(){
                $(this).addClass('hidden').removeAttr('style');
            });
        }
        $('#salesPostDeleteRequestMessage')
            .removeClass('error ok')
            .text('');
    }).fail(function(xhr){
        $('#salesPostDeleteRequestMessage').addClass('error').text(
            (xhr.responseJSON&&xhr.responseJSON.message)||'Deletion request could not be sent.'
        );
        $send.prop('disabled',false).text('Send request');
    }).always(function(){
        if(!$('#salesPostDeleteRequestOpen').prop('disabled')){
            $send.prop('disabled',false).text('Send request');
        }
    });
});

$('#salesPostDetailClose,#salesPostDetailFooterClose')
    .on('click',function(){
        closeSalesPostDetail();
    });

$salesPostDetailModal.on(
    'click',
    function(event){
        if(event.target===this){
            closeSalesPostDetail();
        }
    }
);

$salesPostDetailImageButton.on(
    'click',
    function(){
        openSalesImageLightbox();
    }
);

$('#salesImageLightboxClose').on(
    'click',
    function(){
        closeSalesImageLightbox();
    }
);

$salesImageLightbox.on(
    'click',
    function(event){
        if(event.target===this){
            closeSalesImageLightbox();
        }
    }
);

/*
 * Desktop mouse: one native pointer controller for both server-rendered and
 * AJAX-rendered chart days. Start the 3-second timer only when the pointer
 * truly enters a new day; moving between children inside the same day never
 * resets it. This avoids delegated mouseenter edge cases that could leave the
 * tooltip permanently hidden after chart re-renders.
 */
/**
 * EN: Perform the sales chart day from pointer target behavior used by the application UI.
 * 中文：执行application UI 使用的“sales chart day from pointer target”行为。
 *
 * @param {Element|*} target Target DOM node or application object. / 目标 DOM 节点或应用对象。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function salesChartDayFromPointerTarget(target){
    if(!target||!target.closest){
        return null;
    }

    return target.closest('.sales-chart-day');
}

/**
 * EN: Perform the sales chart day owned by portal behavior used by the application UI.
 * 中文：执行application UI 使用的“sales chart day owned by portal”行为。
 *
 * @param {*} day Day value used by this function. / 本函数使用的“day”参数值。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function salesChartDayOwnedByPortal(day){
    return !!(
        day
        &&day.closest
        &&day.closest('#salesPortalDashboard')
    );
}

/**
 * EN: Schedule or start the start sales chart mouse hover behavior used by the application UI.
 * 中文：调度或启动application UI 使用的“start sales chart mouse hover”行为。
 *
 * @param {*} day Day value used by this function. / 本函数使用的“day”参数值。
 * @param {Event|*} event DOM event that triggered the operation. / 触发本操作的 DOM Event。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function startSalesChartMouseHover(day,event){
    cancelSalesChartHoverTimer();
    salesTouchChartDay=null;
    $salesChartTooltip.addClass('hidden');

    const raw=event||{};
    salesChartHoverDay=day;
    salesChartHoverPoint={
        clientX:Number(raw.clientX)||0,
        clientY:Number(raw.clientY)||0,
        pointerType:'mouse'
    };

    salesChartHoverTimer=window.setTimeout(
        function(){
            salesChartHoverTimer=null;

            if(
                salesChartHoverDay!==day
                ||!document.documentElement.contains(day)
            ){
                cancelSalesChartHoverTimer();
                return;
            }

            showSalesChartTooltip(
                $(day),
                salesChartHoverPoint,
                'pointer'
            );
        },
        3000
    );
}

document.addEventListener(
    'pointerover',
    function(event){
        if(String(event.pointerType||'mouse')!=='mouse'){
            return;
        }

        const day=salesChartDayFromPointerTarget(event.target);
        if(!day||salesChartDayOwnedByPortal(day)){
            return;
        }

        if(event.relatedTarget&&day.contains(event.relatedTarget)){
            return;
        }

        startSalesChartMouseHover(day,event);
    }
);

document.addEventListener(
    'pointermove',
    function(event){
        if(String(event.pointerType||'mouse')!=='mouse'){
            return;
        }

        const day=salesChartDayFromPointerTarget(event.target);
        if(!day||salesChartDayOwnedByPortal(day)){
            return;
        }

        if(salesChartHoverDay===day){
            salesChartHoverPoint={
                clientX:Number(event.clientX)||0,
                clientY:Number(event.clientY)||0,
                pointerType:'mouse'
            };
        }

        if(!$salesChartTooltip.hasClass('hidden')){
            moveSalesChartTooltipWithPointer($(day),event);
        }
    }
);

document.addEventListener(
    'pointerout',
    function(event){
        if(String(event.pointerType||'mouse')!=='mouse'){
            return;
        }

        const day=salesChartDayFromPointerTarget(event.target);
        if(!day||salesChartDayOwnedByPortal(day)){
            return;
        }

        if(event.relatedTarget&&day.contains(event.relatedTarget)){
            return;
        }

        if(salesTouchChartDay!==day){
            hideSalesChartTooltip();
        }
    }
);

/* Keyboard focus uses the same stable, collision-safe anchored card. */
$(document).on(
    'focus',
    '.sales-chart-day',
    function(event){
        if(salesChartDayOwnedByPortal(this)){
            return;
        }

        if(salesTouchChartDay===this){
            return;
        }

        if(this.matches&& !this.matches(':focus-visible')){
            return;
        }

        showSalesChartTooltip(
            $(this),
            event,
            'anchor'
        );
    }
);

$(document).on(
    'blur',
    '.sales-chart-day',
    function(){
        if(salesChartDayOwnedByPortal(this)){
            return;
        }

        if(salesTouchChartDay!==this){
            hideSalesChartTooltip();
        }
    }
);

/*
 * Touch/pen: tap once to pin the selected day; tap another day to switch.
 * A second tap on the same day closes it. No long-press or hover delay.
 */
$(document).on(
    'pointerup',
    '.sales-chart-day',
    function(event){
        if(salesChartDayOwnedByPortal(this)){
            return;
        }

        const raw=event.originalEvent||event;
        const pointerType=String(raw.pointerType||'');

        if(pointerType!=='touch'&&pointerType!=='pen'){
            return;
        }

        event.preventDefault();

        if(salesTouchChartDay===this){
            hideSalesChartTooltip();
            return;
        }

        salesTouchChartDay=this;
        showSalesChartTooltip(
            $(this),
            event,
            'anchor'
        );
    }
);

/* Tapping outside the chart selection closes a pinned touch tooltip. */
$(document).on(
    'pointerdown',
    function(event){
        if(!salesTouchChartDay){
            return;
        }

        if(salesChartDayOwnedByPortal(this)){
            return;
        }

        const raw=event.originalEvent||event;
        const pointerType=String(raw.pointerType||'');

        if(pointerType!=='touch'&&pointerType!=='pen'){
            return;
        }

        if($(event.target).closest('.sales-chart-day').length){
            return;
        }

        hideSalesChartTooltip();
    }
);

window.addEventListener(
    'resize',
    function(){
        if(
            salesTouchChartDay
            ||salesChartHoverTimer
            ||!$salesChartTooltip.hasClass('hidden')
        ){
            hideSalesChartTooltip();
        }
    },
    {passive:true}
);

window.addEventListener(
    'scroll',
    function(){
        if(
            salesTouchChartDay
            ||salesChartHoverTimer
            ||!$salesChartTooltip.hasClass('hidden')
        ){
            hideSalesChartTooltip();
        }
    },
    {passive:true,capture:true}
);

$(document).on('keydown',function(event){
    if(event.key!=='Escape'){
        return;
    }

    if(!$salesImageLightbox.hasClass('hidden')){
        closeSalesImageLightbox();
        return;
    }

    if($salesSubmitModal.length&&!$salesSubmitModal.hasClass('hidden')){
        closeSalesSubmitModal();
        return;
    }

    if(!$salesPostDetailModal.hasClass('hidden')){
        closeSalesPostDetail();
        return;
    }

    hideSalesChartTooltip();
});

parseSalesChartInitialData();

const initialSalesRange=
    syncSalesRangeConstraints('');

if(initialSalesRange){
    setSalesRangePeriod(
        salesRangePeriod
    );
}

updateSalesBackToday(
    initialSalesRange
);

let salesChartResizeTimer=null;

if(
    window.ResizeObserver
    &&document.getElementById('salesChartScroll')
){
    const salesChartResizeObserver=new ResizeObserver(
        function(){
            clearTimeout(salesChartResizeTimer);

            salesChartResizeTimer=setTimeout(
                function(){
                    renderSalesChart({animate:false});
                },
                70
            );
        }
    );

    salesChartResizeObserver.observe(
        document.getElementById('salesChartScroll')
    );
}else{
    $(window).on('resize',function(){
        clearTimeout(salesChartResizeTimer);

        salesChartResizeTimer=setTimeout(
            function(){
                renderSalesChart({animate:false});
            },
            100
        );
    });
}


syncSalesRangeConstraints('');
renderSalesChart();
applySalesPlatformFilterToCards();





    $('#postUrl').on('input paste change', function(){
        setTimeout(updateDetectedPlatform, 0);
    });

    if($('#inspectForm').length){
        setSalesInspectionBusyState(salesInspectionBusy,false);
        syncSalesInspectionProcessState(false);
    }

/**
 * EN: Update the set sales submit message behavior used by the application UI.
 * 中文：更新application UI 使用的“set sales submit message”行为。
 *
 * @param {string|*} message Human-readable message shown or recorded by the UI. / UI 显示或记录的可读消息。
 * @param {*} type Type value used by this function. / 本函数使用的“type”参数值。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function setSalesSubmitMessage(message,type){
    const $message=$('#salesSubmitMessage');
    $message.removeClass('duplicate-compact');

    if(!$message.length){
        return;
    }

    if(!message){
        $message
            .addClass('hidden')
            .removeClass('ok error warning')
            .text('');
        return;
    }

    const messageClass=type==='ok'
        ?'ok'
        :(type==='warning'?'warning':'error');

    $message
        .removeClass('hidden ok error warning')
        .addClass(messageClass)
        .text(message);
}

/**
 * EN: Show the exact duplicate URL returned by the server.
 * 中文：显示服务器返回的实际重复记录 URL。
 *
 * @param {string|*} url Duplicate source URL. / 重复来源 URL。
 * @param {string|*} kind Duplicate reason kind used to label the matched link accurately. / 用于准确标注命中链接的重复类型。
 * @returns {void} No value is returned. / 无返回值。
 */
function setSalesDuplicateSource(url,kind){
    const $source=$('#salesDuplicateSource');
    const raw=String(url||'').trim();
    if(!raw){
        $source.addClass('hidden').attr('href','#').text('');
        return;
    }
    const duplicateKind=String(kind||'').trim();
    const duplicateLabel={
        exact_title:'Title duplicate',
        external_id:'Post ID duplicate',
        url:'URL duplicate',
        image:'Image duplicate',
        exact_image:'Image duplicate',
        same_platform_image:'Image duplicate',
        same_account_title:'Account title duplicate',
        same_account_image:'Account image duplicate',
        website_exact_image:'Website image duplicate',
        website_exact_title:'Website title duplicate'
    }[duplicateKind]||'Duplicate match';
    try{
        const parsed=new URL(raw);
        if(parsed.protocol!=='https:'&&parsed.protocol!=='http:')throw new Error('Unsupported protocol');
        $source
            .attr({href:parsed.href,title:parsed.href})
            .removeClass('hidden')
            .text(duplicateLabel+' — open matching post ↗');
    }catch(error){
        $source.addClass('hidden').attr('href','#').text('');
    }
}

/**
 * EN: Make duplicate notices compact so the matched URL remains the focus.
 * 中文：重复提示使用紧凑字号，让具体命中的 URL 更醒目。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function markSalesDuplicateMessageCompact(){
    $('#salesSubmitMessage').addClass('duplicate-compact');
    $('#verificationBanner').addClass('duplicate-compact');
}

/**
 * Render provider/API marketplace account metadata only when it exists.
 * Missing account data is normal and must never be treated as an empty account.
 */
function renderMarketplaceAccount($fact,$value,account){
    if(!$fact||!$fact.length||!$value||!$value.length){return;}
    const data=account&&typeof account==='object'?account:{};
    const name=String(data.name||'').trim();
    const id=String(data.id||'').trim();
    const url=String(data.url||'').trim();
    const label=name||id;
    if(!label){
        $value.empty().text('—');
        $fact.addClass('hidden');
        return;
    }
    $value.empty();
    if(url){
        try{
            const parsed=new URL(url);
            if(parsed.protocol==='http:'||parsed.protocol==='https:'){
                $('<a>')
                    .attr({href:parsed.href,target:'_blank',rel:'noopener noreferrer',title:parsed.href})
                    .text(label)
                    .appendTo($value);
            }else{
                $value.text(label);
            }
        }catch(error){$value.text(label);}
    }else{
        $value.text(label);
    }
    $fact.removeClass('hidden');
}


/**
 * EN: Apply the local UI state for a running Marketplace verification.
 * 中文：应用 Marketplace 验证运行期间的本地 UI 状态。
 *
 * @param {boolean} busy Whether verification is currently active. / 当前是否正在验证。
 * @param {boolean} showMessage Whether to show the in-progress notice. / 是否显示处理中提示。
 * @returns {void} No value is returned. / 无返回值。
 */
function setSalesInspectionBusyState(busy,showMessage){
    salesInspectionBusy=Boolean(busy);

    try{
        if(salesInspectionBusy){
            sessionStorage.setItem(SALES_INSPECTION_BUSY_KEY,'1');
        }else{
            sessionStorage.removeItem(SALES_INSPECTION_BUSY_KEY);
        }
    }catch(error){/* Session storage is only a UI hint; server lock remains authoritative. */}

    const platform=detectPlatform($('#postUrl').val()||'');
    $('#inspectButton')
        .prop('disabled',salesInspectionBusy||!platform)
        .text(salesInspectionBusy?salesTr('checking'):salesTr('checkPost'));
    $('#saveWaitButton').prop('disabled',salesInspectionBusy||!platform||$('#salesPreflightActions').hasClass('hidden'));
    $('#postUrl')
        .prop('readonly',salesInspectionBusy)
        .attr('aria-busy',salesInspectionBusy?'true':'false');

    if(salesInspectionBusy&&showMessage){
        setSalesSubmitMessage(salesTr('verificationInProgress'),'warning');
    }
}

/**
 * EN: Stop polling the server-side inspection process lock.
 * 中文：停止轮询服务器端 Inspection 进程锁。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function stopSalesInspectionStatusTimer(){
    if(salesInspectionStatusTimer){
        window.clearTimeout(salesInspectionStatusTimer);
        salesInspectionStatusTimer=null;
    }
}

/**
 * EN: Return whether the verification UI is currently visible and should poll.
 * 中文：返回验证 UI 当前是否可见、是否需要继续轮询。
 *
 * @returns {boolean} True when the submit UI is visible. / Submit UI 可见时返回 true。
 */
function salesInspectionUiVisible(){
    return !$salesSubmitModal.length||!$salesSubmitModal.hasClass('hidden');
}

/**
 * EN: Schedule a short status poll while the server still owns the process lock.
 * 中文：服务器仍持有进程锁时安排下一次短间隔状态轮询。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function scheduleSalesInspectionStatusPoll(){
    stopSalesInspectionStatusTimer();
    if(!salesInspectionBusy||!salesInspectionUiVisible()){return;}
    salesInspectionStatusTimer=window.setTimeout(function(){
        syncSalesInspectionProcessState(false);
    },1200);
}

/**
 * EN: Synchronize Check Post with the authoritative server-side process lock.
 * 中文：使用服务器端权威进程锁同步 Check Post 的可点击状态。
 *
 * This protects modal reopen, lost AJAX responses, page reloads, and a second
 * browser tab. A local button state is never trusted as the process lock.
 * 该检查可覆盖重新打开弹窗、AJAX 响应丢失、页面刷新以及第二个浏览器标签页；
 * 本地按钮状态永远不能替代服务器端进程锁。
 *
 * @param {boolean} showMessage Whether a busy state should show a notice. / Busy 时是否显示提示。
 * @returns {void} No value is returned. / 无返回值。
 */
function syncSalesInspectionProcessState(showMessage){
    if(!$('#inspectForm').length){return;}

    $.ajax({
        url:window.CD_BASE_PATH+'/api/inspect/status',
        method:'GET',
        dataType:'json',
        cache:false,
        timeout:5000,
        headers:{'Accept':'application/json'}
    })
    .done(function(data){
        const serverBusy=Boolean(data&&data.in_progress);
        const localRequestBusy=Boolean(
            salesInspectionRequest
            &&salesInspectionRequest.readyState!==4
        );

        if(serverBusy||localRequestBusy){
            setSalesInspectionBusyState(true,Boolean(showMessage));
            scheduleSalesInspectionStatusPoll();
            return;
        }

        stopSalesInspectionStatusTimer();
        setSalesInspectionBusyState(false,false);
        updateDetectedPlatform();
    })
    .fail(function(){
        // EN: Fail closed when this page already knows a verification is active.
        // Never unlock the button merely because the status request failed.
        // 中文：如果当前页面已知验证正在运行，状态接口失败时保持锁定；不能因为
        // 状态检查失败就错误地重新启用 Check Post。
        if(salesInspectionBusy){
            setSalesInspectionBusyState(true,Boolean(showMessage));
            scheduleSalesInspectionStatusPoll();
        }
    });
}


/**
 * EN: Update the set inspection step behavior used by the application UI.
 * 中文：更新application UI 使用的“set inspection step”行为。
 *
 * @param {*} step Step value used by this function. / 本函数使用的“step”参数值。
 * @param {Object|*} state State value used by this function. / 本函数使用的“state”参数值。
 * @param {*} label Label value used by this function. / 本函数使用的“label”参数值。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function setInspectionStep(step,state,label){
    const $step=$('#inspectionProgress [data-inspection-step="'+step+'"]');
    if(!$step.length)return;
    $step.removeClass('active done failed skipped limited');
    if(state){$step.addClass(state);}
    $step.find('.inspection-step-state').text(label||'Waiting');
}

$('#inspectForm').on('submit',function(e){
    e.preventDefault();

    if(salesInspectionBusy){
        setSalesInspectionBusyState(true,true);
        syncSalesInspectionProcessState(true);
        return;
    }

    const platform=updateDetectedPlatform();
    const continueImmediately=Boolean(salesContinueAfterPreflight);
    salesContinueAfterPreflight=false;
    if(!continueImmediately){
        $('#salesPreflightActions').addClass('hidden');
        $('#saveWaitButton').prop('disabled',true);
    }

    if(!platform){
        const $p=$('#inspectionProgress');
        $p.removeClass('hidden');
        $p.find('div').removeClass('active done failed skipped limited');
        $p.find('.inspection-step-state').text('Waiting');
        setInspectionStep('platform','failed','Issue');
        ['duplicate','fetch','date','final'].forEach(function(step){
            setInspectionStep(step,'skipped','Skipped');
        });
        $('#inspectionResult').addClass('hidden');
        $('#salesVerifiedSaveForm').addClass('hidden');
        $('#saveButton').prop('disabled',true);
        setSalesSubmitMessage(
            salesTr('useSupported'),
            'error'
        );
        $('#postUrl').addClass('field-error').trigger('focus');
        return;
    }

    $('#postUrl').removeClass('field-error');
    setSalesSubmitMessage('',null);

    $('#salesPostSaveComplete').addClass('hidden');
    const craigslistManualForm=$('#craigslistManualVerification').addClass('hidden').get(0);
    if(craigslistManualForm){craigslistManualForm.reset();}
    $('#craigslistManualInspectionToken').val('');
    setSalesDuplicateSource('');
    $('#resultTitle').removeClass('duplicate-title');
    $('#salesVerifiedSaveForm').addClass('hidden');
    $('#saveButton')
        .removeClass('saved')
        .prop('disabled',true)
        .find('span')
        .text(salesTr('saveVerified'));

    const $b=$('#inspectButton');
    const $p=$('#inspectionProgress');
    const $r=$('#inspectionResult');

    setSalesInspectionBusyState(true,false);

    $p.removeClass('hidden');
    $p.find('div').removeClass('active done failed skipped limited');
    $p.find('.inspection-step-state').text('Waiting');
    setInspectionStep('platform','done','OK');
    setInspectionStep('duplicate','active',salesTr('checking'));
    setInspectionStep('fetch',null,'Waiting');

    $('#inspectionEmpty').addClass('hidden');
    $('#duplicateComparisonWarnings').empty().addClass('hidden');
    renderMarketplaceAccount($('#resultPlatformAccountFact'),$('#resultPlatformAccount'),null);
    $('#resultImages').empty();
    $('#resultImagesWrap').addClass('hidden');
    $r.addClass('hidden');
    $('#saveButton').prop('disabled',true);
    $('#salesVerifiedSaveForm').addClass('hidden');

    const inspectPayload=$(this).serialize();

    // EN: Platform detection and initial duplicate checking are hard prerequisites.
    // The expensive remote/provider verification is never started unless both pass.
    // 中文：平台识别与初次查重是硬前置条件；任一失败都不会启动后续远程/Provider 验证。
    const preflightRequest=continueImmediately
        ?$.Deferred().resolve({ok:true,continued:true}).promise()
        :$.post(
            window.CD_BASE_PATH+'/api/inspect/preflight',
            inspectPayload
        );
    salesInspectionRequest=preflightRequest;

    preflightRequest
    .done(function(preflight){
        if(!(preflight&&preflight.ok)){
            setInspectionStep('duplicate','failed','Issue');
            ['fetch','date','final'].forEach(function(step){
                setInspectionStep(step,'skipped','Skipped');
            });
            $('#inspectionResult').addClass('hidden');
            $('#salesVerifiedSaveForm').addClass('hidden');
            $('#saveButton').prop('disabled',true);
            if(preflight&&preflight.duplicate_url){
                setSalesDuplicateSource(preflight.duplicate_url,preflight.duplicate_kind);
            }
            setSalesSubmitMessage(
                (preflight&&preflight.message)||'Duplicate check failed. Verification was not started.',
                'error'
            );
            if(preflight&&preflight.duplicate_url){
                markSalesDuplicateMessageCompact();
            }
            salesInspectionRequest=null;
            setSalesInspectionBusyState(false,false);
            updateDetectedPlatform();
            return;
        }

        setInspectionStep('duplicate','done','OK');

        // v0.2.97: this is the intentional decision point. Save & Wait belongs
        // after Platform + hard Duplicate preflight and BEFORE provider/fetch (step 3).
        if(!continueImmediately){
            salesInspectionRequest=null;
            setSalesInspectionBusyState(false,false);
            $('#postUrl').prop('readonly',true).attr('aria-busy','false');
            $('#inspectButton').prop('disabled',true);
            $('#salesPreflightActions').removeClass('hidden');
            $('#saveWaitButton').prop('disabled',false);
            setInspectionStep('fetch',null,'Waiting');
            setInspectionStep('date',null,'Waiting');
            setInspectionStep('final',null,'Waiting');
            setSalesSubmitMessage(salesTr('preflightPassedMessage'),'ok');
            return;
        }

        $('#salesPreflightActions').addClass('hidden');
        setInspectionStep('fetch','active',salesTr('checking'));

    salesInspectionRequest=$.post(
        window.CD_BASE_PATH+'/api/inspect',
        inspectPayload
    )
    .done(function(d){
        const $warnings=$('#duplicateComparisonWarnings').empty();
        (d.duplicate_warnings||[]).forEach(function(message){
            $('<p>').text(message).appendTo($warnings);
        });
        (d.duplicate_matches||[]).forEach(function(match){
            try{
                const link=new URL(match.url);
                if(link.protocol!=='https:')return;
                $('<a>').attr({href:link.href,target:'_blank',rel:'noopener noreferrer'})
                    .text('Duplicate: '+link.href).appendTo($warnings);
            }catch(error){/* Ignore malformed source URLs. */}
        });
        $warnings.toggleClass('hidden',!$warnings.children().length);
        $('#resultPlatform').text(
            platformLabel(d.platform)||d.platform||'—'
        );
        $('#resultTitle')
            .text(d.title||d.duplicate_title||'—')
            .toggleClass('duplicate-title',d.failure_code==='DUPLICATE'&&['exact_title','same_account_title'].includes(String(d.duplicate_kind||'')));
        if(d.duplicate_url){
            setSalesDuplicateSource(d.duplicate_url,d.duplicate_kind);
        }else{
            setSalesDuplicateSource('');
        }
        $('#resultDate').text(d.published_at||'—');
        $('#resultExternalId').text(
            d.external_post_id||'—'
        );
        renderMarketplaceAccount(
            $('#resultPlatformAccountFact'),
            $('#resultPlatformAccount'),
            d.platform_account
        );
        $('#resultDescription').text(
            d.description||'—'
        );

        const resultImages=Array.isArray(d.images)?d.images:[];
        const $resultImages=$('#resultImages').empty();
        resultImages.slice(0,1).forEach(function(imageUrl,index){
            try{
                const parsed=new URL(String(imageUrl||''));
                if(parsed.protocol!=='https:')return;
                $('<a>')
                    .attr({href:parsed.href,target:'_blank',rel:'noopener noreferrer'})
                    .append(
                        $('<img>')
                            .attr({src:parsed.href,loading:'lazy',alt:'Listing image '+(index+1)})
                    )
                    .appendTo($resultImages);
            }catch(error){/* Ignore malformed provider image URLs. */}
        });
        $('#resultImagesWrap').toggleClass('hidden',!$resultImages.children().length);

        const u=d.canonical_url||d.resolved_url||'—';

        $('#resultCanonical').html(
            u==='—'
                ?'—'
                :'<a target="_blank" rel="noopener" href="'
                    +$('<div>').text(u).html()
                    +'">'
                    +$('<div>').text(u).html()
                    +'</a>'
        );

        $('#inspectionToken').val(
            d.inspection_token||''
        );

        const manualRequired=Boolean(d.manual_required);
        const manualPending=Boolean(d.manual_pending);
        const rawResultMessage=manualRequired
            ?salesTr('manualVerificationHelp')
            :(manualPending?salesTr('manualAccepted'):(d.message||salesTr('inspectionFailed')));
        const resultMessage=(d.failure_code==='DUPLICATE'&&['exact_title','same_account_title'].includes(String(d.duplicate_kind||'')))
            ?'TITLE DUPLICATE — '+rawResultMessage
            :rawResultMessage;

        $('#verificationBanner')
            .attr(
                'class',
                'banner '+(manualRequired||manualPending?'warning':(d.ok?'ok':'bad'))
            )
            .text(
                manualRequired||manualPending
                    ?salesTr('limited')+' — '+resultMessage
                    :(d.ok
                        ?salesTr('verified')
                        :salesTr('blocked')
                            +' — '
                            +(d.message||salesTr('inspectionFailed')))
            );

        if(manualRequired){
            $('#craigslistManualInspectionToken').val(d.inspection_token||'');
            $('#craigslistManualVerification').removeClass('hidden');
        }else if(!manualPending){
            $('#craigslistManualVerification').addClass('hidden');
        }

        $('#saveButton')
            .prop('disabled',!d.ok)
            .find('span')
            .text(manualPending?salesTr('saveForAdminReview'):salesTr('saveVerified'));
        $('#salesVerifiedSaveForm').toggleClass('hidden',manualRequired||!d.ok);

        $r.removeClass('hidden');

        const code=String(d.failure_code||'');
        const fetchFailed=[
            'FETCH_FAILED','FACEBOOK_PROVIDER_FAILED','LISTING_UNAVAILABLE','TITLE_NOT_VERIFIABLE'
        ].indexOf(code)!==-1;
        const earlyBlocked=[
            'PLATFORM_INVALID','URL_INVALID'
        ].indexOf(code)!==-1;

        if(manualRequired){
            setInspectionStep('fetch','limited','HTTP 403');
            setInspectionStep('date','skipped','Manual');
            setInspectionStep('final','skipped','Waiting');
        }else if(code==='DUPLICATE' && !d.title){
            setInspectionStep('fetch','skipped','Skipped');
            setInspectionStep('date','skipped','Skipped');
            setInspectionStep('final','skipped','Skipped');
        }else{
            setInspectionStep('fetch',fetchFailed?'failed':'done',fetchFailed?'Issue':'OK');
            if(code==='DATE_NOT_VERIFIABLE'||code==='FUTURE_DATE'){
                setInspectionStep('date','failed','Issue');
            }else if(earlyBlocked||fetchFailed){
                setInspectionStep('date','skipped','Skipped');
            }else{
                setInspectionStep('date','done','OK');
            }

            if(code==='DUPLICATE'||code==='DUPLICATE_IMAGE'||code==='COMPARISON_UNAVAILABLE'){
                setInspectionStep('final','failed','Issue');
            }else if(earlyBlocked||fetchFailed||code==='DATE_NOT_VERIFIABLE'||code==='FUTURE_DATE'){
                setInspectionStep('final','skipped','Skipped');
            }else{
                setInspectionStep('final','done','OK');
            }
        }

        if(code==='DUPLICATE'){
            const duplicateState=['exact_title','same_account_title'].includes(String(d.duplicate_kind||''))?'Title duplicate':'Issue';
            setInspectionStep('duplicate','failed',duplicateState);
            if(['exact_title','same_account_title'].includes(String(d.duplicate_kind||''))){
                setInspectionStep('final','failed','Title duplicate');
            }
        }else{
            setInspectionStep('duplicate','done','OK');
        }

        if(manualRequired||manualPending){
            setSalesSubmitMessage(
                resultMessage,
                'warning'
            );
        }else if(!d.ok){
            setSalesSubmitMessage(
                d.message||salesTr('inspectionFailed'),
                'error'
            );
        }else{
            setSalesSubmitMessage(
                d.message||salesTr('verified'),
                'ok'
            );
        }
        if(code==='DUPLICATE'||code==='DUPLICATE_IMAGE'){
            markSalesDuplicateMessageCompact();
        }
    })
    .fail(function(x){
        $('#salesVerifiedSaveForm').addClass('hidden');
        $('#saveButton').prop('disabled',true);
        const rawMessage=
            (x.responseJSON&&x.responseJSON.message)
            ||salesTr('inspectionFailed');
        const failureCode=String(
            (x.responseJSON&&x.responseJSON.failure_code)||''
        );
        const duplicateKind=String(
            (x.responseJSON&&x.responseJSON.duplicate_kind)||''
        );
        const message=(failureCode==='DUPLICATE'&&duplicateKind==='exact_title')
            ?'TITLE DUPLICATE — '+rawMessage
            :rawMessage;

        if(failureCode==='INSPECTION_IN_PROGRESS'){
            setSalesInspectionBusyState(true,true);
            setInspectionStep('fetch','active',salesTr('checking'));
            setInspectionStep('date','skipped','Waiting');
            setInspectionStep('final','skipped','Waiting');
            scheduleSalesInspectionStatusPoll();
            return;
        }

        setSalesSubmitMessage(message,'error');
        if(x.responseJSON&&x.responseJSON.duplicate_url){
            setSalesDuplicateSource(x.responseJSON.duplicate_url,x.responseJSON.duplicate_kind);
            markSalesDuplicateMessageCompact();
        }

        $('#verificationBanner')
            .attr('class','banner bad')
            .text(
                salesTr('blocked')
                +' — '
                +message
            );

        $r.removeClass('hidden');
        ['fetch','date','final'].forEach(function(step){
            setInspectionStep(step,'failed','Issue');
        });
        if($('#inspectionProgress [data-inspection-step="duplicate"]').hasClass('active')){
            setInspectionStep('duplicate','failed','Issue');
        }
    })
    .always(function(){
        salesInspectionRequest=null;
        // EN: Do not blindly unlock here. The authoritative server-side lock decides
        // whether a reopened modal may start another verification.
        // 中文：这里不能盲目解锁；是否允许重新验证由服务器端权威锁状态决定。
        syncSalesInspectionProcessState(false);
    });
    })
    .fail(function(xhr){
        const data=xhr.responseJSON||{};
        setInspectionStep('duplicate','failed','Issue');
        ['fetch','date','final'].forEach(function(step){
            setInspectionStep(step,'skipped','Skipped');
        });
        $('#inspectionResult').addClass('hidden');
        $('#salesVerifiedSaveForm').addClass('hidden');
        $('#saveButton').prop('disabled',true);
        setSalesSubmitMessage(
            data.message||'Duplicate check failed. Verification was not started.',
            'error'
        );
        salesInspectionRequest=null;
        setSalesInspectionBusyState(false,false);
        updateDetectedPlatform();
    });
});

$('#craigslistManualVerification').on('submit',function(event){
    event.preventDefault();

    const $form=$(this);
    const $button=$('#craigslistManualContinue');
    const title=String($('#craigslistManualTitle').val()||'').trim();
    const publishedDate=String($('#craigslistManualPublishedDate').val()||'').trim();

    if(!title){
        $('#craigslistManualTitle').addClass('field-error').trigger('focus');
        setSalesSubmitMessage(salesTr('manualTitleRequired'),'error');
        return;
    }
    $('#craigslistManualTitle').removeClass('field-error');

    if(!publishedDate){
        $('#craigslistManualPublishedDate').addClass('field-error').trigger('focus');
        setSalesSubmitMessage(salesTr('manualDateRequired'),'error');
        return;
    }
    $('#craigslistManualPublishedDate').removeClass('field-error');

    $button.prop('disabled',true).find('span').text(salesTr('manualChecking'));
    setSalesSubmitMessage(salesTr('manualChecking'),'warning');

    $.ajax({
        url:window.CD_BASE_PATH+'/api/inspect',
        method:'POST',
        dataType:'json',
        data:$form.serialize(),
        headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}
    })
    .done(function(d){
        $('#resultTitle').text(d.title||'—').removeClass('duplicate-title');
        $('#resultDescription').text(d.description||'—');
        $('#resultDate').text(d.published_at||d.published_date||'—');
        $('#resultExternalId').text(d.external_post_id||'—');
        renderMarketplaceAccount(
            $('#resultPlatformAccountFact'),
            $('#resultPlatformAccount'),
            d.platform_account
        );
        const u=d.canonical_url||d.resolved_url||'—';
        $('#resultCanonical').html(
            u==='—'
                ?'—'
                :'<a target="_blank" rel="noopener" href="'+escapeHtml(u)+'">'+escapeHtml(u)+'</a>'
        );

        $('#inspectionToken').val(d.inspection_token||'');
        $('#craigslistManualVerification').addClass('hidden');
        $('#verificationBanner')
            .attr('class','banner warning')
            .text(salesTr('limited')+' — '+salesTr('manualAccepted'));
        $('#saveButton')
            .prop('disabled',!d.ok)
            .find('span')
            .text(salesTr('saveForAdminReview'));
        $('#salesVerifiedSaveForm').toggleClass('hidden',!d.ok);
        setInspectionStep('fetch','limited','HTTP 403');
        setInspectionStep('date','done','Manual');
        setInspectionStep('final','done','OK');
        setInspectionStep('duplicate','done','OK');
        setSalesSubmitMessage(salesTr('manualAccepted'),'warning');
    })
    .fail(function(xhr){
        const d=xhr.responseJSON||{};
        const message=d.message||salesTr('inspectionFailed');
        setSalesSubmitMessage(message,'error');
        if(d.duplicate_url){
            setSalesDuplicateSource(d.duplicate_url,d.duplicate_kind);
        }
        if(d.failure_code==='DUPLICATE'||d.failure_code==='DUPLICATE_IMAGE'){
            markSalesDuplicateMessageCompact();
            setInspectionStep('final','failed','Issue');
        }
    })
    .always(function(){
        $button.prop('disabled',false).find('span').text(salesTr('continueManualVerification'));
    });
});

$('#salesVerifiedSaveForm').on('submit',function(event){
    event.preventDefault();
    const $form=$(this);
    const $button=$('#saveButton');
    if($button.prop('disabled'))return;

    $button.prop('disabled',true).find('span').text(salesTr('savingPost'));
    setSalesSubmitMessage('',null);

    $.ajax({
        url:$form.attr('action'),
        method:'POST',
        dataType:'json',
        data:$form.serialize(),
        headers:{
            'X-Requested-With':'XMLHttpRequest',
            'Accept':'application/json'
        }
    })
    .done(function(data){
        if(!data||!data.ok){
            setSalesSubmitMessage((data&&data.message)||salesTr('inspectionFailed'),'error');
            $button.prop('disabled',false).find('span').text(salesTr('saveVerified'));
            return;
        }

        setSalesSubmitMessage(data.message||salesTr('postSaved'),'ok');
        $button.addClass('saved').find('span').text('Saved ✓');
        $('#inspectionToken').val('');
        // EN: After a successful save, always follow the server-provided dashboard URL first.
        // The saved listing belongs to its verified published_date, which may be outside the
        // date range currently open on the Sales dashboard. Reloading the current range first
        // made a successfully saved post look as if it had disappeared.
        // 中文：保存成功后优先跳转到后端返回的 Dashboard URL。帖子应显示在其已验证的
        // published_date 日期下；如果先刷新当前日期范围，发布日期不在当前范围内的帖子
        // 会看起来像“保存后消失”。
        if(data.dashboard_url){
            window.location.href=data.dashboard_url;
            return;
        }
        window.location.reload();
    })
    .fail(function(xhr){
        setSalesSubmitMessage((xhr.responseJSON&&xhr.responseJSON.message)||salesTr('inspectionFailed'),'error');
        if(xhr.responseJSON&&xhr.responseJSON.duplicate_url){
            setSalesDuplicateSource(xhr.responseJSON.duplicate_url,xhr.responseJSON.duplicate_kind);
            markSalesDuplicateMessageCompact();
            $('#resultTitle').toggleClass('duplicate-title',xhr.responseJSON.duplicate_kind==='exact_title');
        }
        $button.prop('disabled',false).find('span').text(salesTr('saveVerified'));
    });
});

    const savedView=localStorage.getItem('cdsp-sales-post-view')||'grid';

    /**
     * EN: Update the set post view behavior used by the application UI.
     * 中文：更新application UI 使用的“set post view”行为。
     *
     * @param {*} v V value used by this function. / 本函数使用的“v”参数值。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function setPostView(v){
        $('[data-view]').removeClass('active');
        $('[data-view="'+v+'"]').addClass('active');
        $('#postCollection')
            .toggleClass('post-grid',v==='grid')
            .toggleClass('post-list',v==='list');
    }

    setPostView(savedView);

    $('[data-view]').on('click',function(){
        const v=$(this).data('view');
        localStorage.setItem('cdsp-sales-post-view',v);
        setPostView(v);
    });

    updateDetectedPlatform();


    /**
     * EN: Read or load the load more daily posts behavior used by the application UI.
     * 中文：读取或加载application UI 使用的“load more daily posts”行为。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function loadMoreDailyPosts(){
        const $wrap = $('#dailyPosts');
        const $btn = $('#loadMoreDailyPosts');

        if(!$wrap.length || !$btn.length || $btn.prop('disabled')){
            return;
        }

        const from = String($wrap.attr('data-from') || '');
        const to = String($wrap.attr('data-to') || '');
        const offset = parseInt($wrap.attr('data-offset') || '0', 10);
        const limit = parseInt($wrap.data('limit') || '3', 10);

        $btn.prop('disabled', true).text(salesTr('loading'));
        $('#dailyLoadStatus').text(salesTr('loadingEarlier'));

        $.get(window.CD_BASE_PATH + '/sales/daily-posts', {
            from: from,
            to: to,
            offset: offset,
            limit: limit,
            channel: salesPlatformFilter
        })
        .done(function(d){
            if(!d || !d.ok){
                $('#dailyLoadStatus').text((d && d.message) || 'Could not load earlier days.');
                return;
            }

            if(d.html){
                $wrap.append(d.html);
                applySalesLanguage();
                mergeSalesChartRowsFromDom();
                renderSalesChart();
                applySalesPlatformFilterToCards();
            }

            $wrap.attr('data-offset', d.next_offset || offset);

            if(d.has_more){
                $btn.prop('disabled', false).text(salesTr('loadEarlier'));
                $('#dailyLoadStatus').text('');
            }else{
                $btn.prop('disabled', true).hide();
                $('#dailyLoadStatus').text(salesTr('allDaysLoaded'));
            }
        })
        .fail(function(){
            $btn.prop('disabled', false).text(salesTr('loadEarlier'));
            $('#dailyLoadStatus').text(salesTr('loadEarlierFailed'));
        });
    }

    $('#loadMoreDailyPosts').on('click', loadMoreDailyPosts);

    // Progressive loading: when the button approaches the viewport, fetch the next date batch.
    if($('#loadMoreDailyPosts').length && 'IntersectionObserver' in window){
        const observer = new IntersectionObserver(function(entries){
            if(entries.some(function(entry){ return entry.isIntersecting; })){
                loadMoreDailyPosts();
            }
        }, {rootMargin:'240px 0px'});

        observer.observe(document.getElementById('loadMoreDailyPosts'));
    }




/**
 * EN: Update the sync html note behavior used by the application UI.
 * 中文：更新application UI 使用的“sync html note”行为。
 *
 * @param {*} $root $root value used by this function. / 本函数使用的“$root”参数值。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function syncHtmlNote($root){
    const $editor=$root.find('[data-html-editor]');
    const $source=$root.find('[data-html-source]');
    if(!$editor.hasClass('hidden')){$source.val($editor.html());}
}

/**
 * EN: Format or normalize the normalize editor block behavior used by the application UI.
 * 中文：格式化或规范化application UI 使用的“normalize editor block”行为。
 *
 * @param {string|*} value Value read, transformed, or applied by this function. / 本函数读取、转换或应用的值。
 *
 * @returns {Array} Array result produced by this UI helper. / 本 UI 辅助函数生成的数组结果。
 */
function normalizeEditorBlock(value){
    value=String(value||'p').toLowerCase();
    return ['p','h3','h4','blockquote'].includes(value)?value:'p';
}

/**
 * EN: Format or normalize the escape code html behavior used by the application UI.
 * 中文：格式化或规范化application UI 使用的“escape code html”行为。
 *
 * @param {string|*} value Value read, transformed, or applied by this function. / 本函数读取、转换或应用的值。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function escapeCodeHtml(value){
    return String(value||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

/**
 * EN: Perform the highlight html source behavior used by the application UI.
 * 中文：执行application UI 使用的“highlight html source”行为。
 *
 * @param {*} source Source value used by this function. / 本函数使用的“source”参数值。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function highlightHtmlSource(source){
    return escapeCodeHtml(source)
        .replace(/(&lt;!--[\s\S]*?--&gt;)/g,'<span class="code-comment">$1</span>')
        .replace(/(&lt;\/?)([a-zA-Z][\w:-]*)([\s\S]*?)(&gt;)/g,function(_,open,tag,attrs,close){
            return '<span class="code-punct">'+open+'</span>'+'<span class="code-tag">'+tag+'</span>'+attrs+'<span class="code-punct">'+close+'</span>';
        });
}

/**
 * EN: Perform the line number text behavior used by the application UI.
 * 中文：执行application UI 使用的“line number text”行为。
 *
 * @param {*} source Source value used by this function. / 本函数使用的“source”参数值。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function lineNumberText(source){
    const count=Math.max(1,String(source||'').split('\n').length);
    return Array.from({length:count},(_,i)=>String(i+1)).join('\n');
}

$('[data-html-note]').each(function(){
    const $root=$(this),$editor=$root.find('[data-html-editor]'),$source=$root.find('[data-html-source]'),$toolbar=$root.find('[data-html-toolbar]'),$tabs=$root.find('[data-note-mode]'),$format=$root.find('[data-note-format]'),$status=$root.find('[data-note-status]'),$cursor=$root.find('[data-note-cursor]'),$linkbar=$root.find('[data-note-linkbar]'),$linkInput=$root.find('[data-note-link-input]'),$linkNewTab=$root.find('[data-note-link-newtab]'),$imagePanel=$root.find('[data-note-image-panel]'),$imageUrl=$root.find('[data-note-image-url]'),$listingPhoto=$root.find('[data-note-listing-photo]'),$imageFile=$root.find('[data-note-image-file]'),$imageMessage=$root.find('[data-note-image-message]'),$codeEditor=$root.find('[data-code-editor]'),$codeHighlight=$root.find('[data-code-highlight]'),$codeGutter=$root.find('[data-code-gutter]');
    let mode='visual',savedRange=null;

    /**
     * EN: Render the render source behavior used by the application UI.
     * 中文：渲染application UI 使用的“render source”行为。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function renderSource(){
        const value=String($source.val()||'');
        $codeHighlight.html(highlightHtmlSource(value)+'\n');
        $codeGutter.text(lineNumberText(value));
        const el=$source.get(0);
        if(el){$codeHighlight.scrollTop(el.scrollTop);$codeHighlight.scrollLeft(el.scrollLeft);$codeGutter.scrollTop(el.scrollTop);}
    }

    /**
     * EN: Perform the cursor status behavior used by the application UI.
     * 中文：执行application UI 使用的“cursor status”行为。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function cursorStatus(){
        const el=$source.get(0);if(!el||mode!=='html')return;
        const before=el.value.slice(0,el.selectionStart),lines=before.split('\n');
        $cursor.text('Ln '+lines.length+', Col '+(lines[lines.length-1].length+1));
    }

    /**
     * EN: Perform the remember selection behavior used by the application UI.
     * 中文：执行application UI 使用的“remember selection”行为。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function rememberSelection(){
        const selection=window.getSelection();if(!selection||!selection.rangeCount)return;
        const range=selection.getRangeAt(0),node=range.commonAncestorContainer,editorNode=$editor.get(0);
        if(editorNode&&(node===editorNode||$.contains(editorNode,node.nodeType===1?node:node.parentNode))){savedRange=range.cloneRange();}
    }

    /**
     * EN: Perform the restore selection behavior used by the application UI.
     * 中文：执行application UI 使用的“restore selection”行为。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function restoreSelection(){
        if(!savedRange){$editor.trigger('focus');return;}
        const selection=window.getSelection();if(selection){selection.removeAllRanges();selection.addRange(savedRange);}
    }

    /**
     * EN: Update the set mode behavior used by the application UI.
     * 中文：更新application UI 使用的“set mode”行为。
     *
     * @param {*} next Next value used by this function. / 本函数使用的“next”参数值。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function setMode(next){
        mode=next==='html'?'html':'visual';
        $tabs.each(function(){const active=$(this).data('note-mode')===mode;$(this).toggleClass('active',active).attr('aria-selected',active?'true':'false');});
        $linkbar.addClass('hidden');$imagePanel.addClass('hidden');
        if(mode==='html'){
            syncHtmlNote($root);renderSource();$toolbar.addClass('hidden');$editor.addClass('hidden');$codeEditor.removeClass('hidden');$status.text('HTML source');cursorStatus();setTimeout(()=>$source.trigger('focus'),0);
        }else{
            $editor.html($source.val());$codeEditor.addClass('hidden');$editor.removeClass('hidden');$toolbar.removeClass('hidden');$status.text('Rich text');$cursor.text('');setTimeout(()=>$editor.trigger('focus'),0);
        }
    }

    /**
     * EN: Perform the command behavior used by the application UI.
     * 中文：执行application UI 使用的“command”行为。
     *
     * @param {*} name Name value used by this function. / 本函数使用的“name”参数值。
     * @param {string|*} value Value read, transformed, or applied by this function. / 本函数读取、转换或应用的值。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function command(name,value){restoreSelection();$editor.trigger('focus');document.execCommand(name,false,value||null);rememberSelection();$source.val($editor.html());}

    /**
     * EN: Perform the insert html at cursor behavior used by the application UI.
     * 中文：执行application UI 使用的“insert html at cursor”行为。
     *
     * @param {string|*} html HTML content rendered or sanitized by this function. / 本函数渲染或清理的 HTML 内容。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function insertHtmlAtCursor(html){
        if(mode==='html'){
            const el=$source.get(0),start=el.selectionStart,end=el.selectionEnd;
            el.value=el.value.slice(0,start)+html+el.value.slice(end);el.selectionStart=el.selectionEnd=start+html.length;$source.trigger('input');return;
        }
        restoreSelection();$editor.trigger('focus');document.execCommand('insertHTML',false,html);$source.val($editor.html());rememberSelection();
    }

    /**
     * EN: Perform the safe image html behavior used by the application UI.
     * 中文：执行application UI 使用的“safe image html”行为。
     *
     * @param {string|*} url URL read, generated, or requested by this function. / 本函数读取、生成或请求的 URL。
     *
     * @returns {string} String result produced by this UI helper. / 本 UI 辅助函数生成的字符串结果。
     */
    function safeImageHtml(url){return '<p><img src="'+String(url).replace(/"/g,'&quot;')+'" alt=""></p>';}

    /**
     * EN: Open or show the open image panel behavior used by the application UI.
     * 中文：打开或显示application UI 使用的“open image panel”行为。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function openImagePanel(){
        rememberSelection();$linkbar.addClass('hidden');$imagePanel.removeClass('hidden');$imageMessage.removeClass('error').text('');
        const photos=window.cdspReviewListingPhotos||[];$listingPhoto.toggleClass('hidden',!photos.length);
    }

    $tabs.on('click',function(){setMode($(this).data('note-mode'));});
    $format.on('change',function(){command('formatBlock','<'+normalizeEditorBlock($(this).val())+'>');});
    $toolbar.on('mousedown','[data-cmd],[data-note-link],[data-note-image]',rememberSelection);
    $toolbar.on('click','[data-cmd]',function(){command(String($(this).data('cmd')||''),$(this).data('value')||null);});
    $toolbar.on('click','[data-note-link]',function(){rememberSelection();$imagePanel.addClass('hidden');$linkbar.removeClass('hidden');setTimeout(()=>$linkInput.trigger('focus'),0);});
    $toolbar.on('click','[data-note-image]',openImagePanel);
    $root.on('click','[data-note-link-cancel]',()=> $linkbar.addClass('hidden'));
    $root.on('click','[data-note-link-apply]',function(){
        const href=String($linkInput.val()||'').trim();if(!href){$linkInput.addClass('field-error');return;}
        restoreSelection();document.execCommand('createLink',false,href);
        if($linkNewTab.is(':checked')){const selection=window.getSelection();let node=selection&&selection.anchorNode;if(node&&node.nodeType===3)node=node.parentNode;if(node&&String(node.tagName).toLowerCase()==='a'){node.setAttribute('target','_blank');node.setAttribute('rel','noopener noreferrer');}}
        $source.val($editor.html());$linkbar.addClass('hidden');
    });
    $root.on('click','[data-note-image-cancel]',()=> $imagePanel.addClass('hidden'));
    $root.on('click','[data-note-image-url-insert]',function(){
        const url=String($imageUrl.val()||'').trim();
        if(!/^https:\/\//i.test(url)&&!url.startsWith('/')){$imageMessage.addClass('error').text('Use an HTTPS or local image URL.');return;}
        insertHtmlAtCursor(safeImageHtml(url));$imageUrl.val('');$imagePanel.addClass('hidden');
    });
    $root.on('click','[data-note-listing-photo]',function(){const photos=window.cdspReviewListingPhotos||[];if(photos.length){insertHtmlAtCursor(safeImageHtml(photos[0]));$imagePanel.addClass('hidden');}});

    $imageFile.on('change',function(){
        const file=this.files&&this.files[0];if(!file)return;
        const postId=parseInt($('#dashboardReviewPostId').val(),10)||0,uploadUrl=$('#adminDashboardLive').data('editor-image-url');
        if(!postId||!uploadUrl){$imageMessage.addClass('error').text('Open a post review before uploading.');return;}
        const fd=new FormData();fd.append('_csrf',$('#adminDashboardCsrf').val());fd.append('post_id',postId);fd.append('editor_image',file);
        $imageMessage.removeClass('error').text('Uploading…');
        $.ajax({url:uploadUrl,method:'POST',dataType:'json',data:fd,processData:false,contentType:false,headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}})
        .done(function(data){if(!data||!data.ok){$imageMessage.addClass('error').text((data&&data.message)||'Image upload failed.');return;}insertHtmlAtCursor(safeImageHtml(data.image.url));$imageMessage.removeClass('error').text('Inserted');setTimeout(()=>$imagePanel.addClass('hidden'),350);})
        .fail(function(xhr){$imageMessage.addClass('error').text((xhr.responseJSON&&xhr.responseJSON.message)||String(xhr.responseText||'').trim()||'Image upload failed.');})
        .always(()=>{$imageFile.val('');});
    });

    $editor.on('keyup mouseup input blur',function(){rememberSelection();$source.val($editor.html());});
    $source.on('input',function(){renderSource();cursorStatus();});
    $source.on('scroll',function(){$codeHighlight.scrollTop(this.scrollTop);$codeHighlight.scrollLeft(this.scrollLeft);$codeGutter.scrollTop(this.scrollTop);});
    $source.on('click keyup select',cursorStatus);
    $source.on('keydown',function(event){if(event.key!=='Tab')return;event.preventDefault();const start=this.selectionStart,end=this.selectionEnd,indent='  ';this.value=this.value.slice(0,start)+indent+this.value.slice(end);this.selectionStart=this.selectionEnd=start+indent.length;$source.trigger('input');});
    $root.closest('form').on('submit',function(){if(mode==='html'){$editor.html($source.val());}else{syncHtmlNote($root);}});
    setMode('visual');
});



    // v0.2.10 Paginated / filtered live Provider Jobs
    (function(){
        const $monitor = $('#providerJobsMonitor');
        const $body = $('#providerJobsBody');
        const $live = $('#providerJobsLive');
        const $liveText = $('#providerJobsLiveText');
        const $timeFilter = $('#providerJobsTimeFilter');
        const $prev = $('#providerJobsPrev');
        const $next = $('#providerJobsNext');
        const $page = $('#providerJobsPage');
        const $pages = $('#providerJobsPages');
        const $total = $('#providerJobsTotal');

        if(!$monitor.length || !$body.length){
            return;
        }

        let timer = null;
        let request = null;
        let lastSignature = '';
        let currentPage = Math.max(1, Number($monitor.attr('data-page')) || 1);
        let totalPages = Math.max(1, Number($monitor.attr('data-pages')) || 1);
        const perPage = Math.max(1, Number($monitor.attr('data-per-page')) || 8);
        let currentTime = String($monitor.attr('data-time-filter') || '24h');

        /**
         * EN: Escape a Provider Job value before inserting it into generated table markup.
         * 中文：将 Provider Job 值写入动态表格 HTML 前进行转义。
         *
         * @param {string|number|null|undefined} value Value rendered into the Provider Jobs table. / 要写入 Provider Jobs 表格的值。
         *
         * @returns {string} HTML-safe text value. / 可安全写入 HTML 的文本值。
         */
        function esc(value){
            return $('<div>').text(
                value == null || value === '' ? '—' : String(value)
            ).html();
        }

        /**
         * EN: Normalize a Provider Job status into one of the supported CSS status classes.
         * 中文：将 Provider Job 状态标准化为受支持的 CSS 状态类。
         *
         * @param {string|*} value Raw Provider Job status. / 原始 Provider Job 状态。
         *
         * @returns {string} Safe Provider Job status class. / 安全的 Provider Job 状态类。
         */
        function safeStatus(value){
            const status = String(value || '').toLowerCase();
            return ['starting','running','ready','failed'].includes(status)
                ? status
                : 'starting';
        }

        /**
         * EN: Convert a normalized Provider Job status into the visible label used by the table.
         * 中文：将标准化的 Provider Job 状态转换为表格中显示的文本。
         *
         * @param {string} status Normalized Provider Job status. / 标准化后的 Provider Job 状态。
         *
         * @returns {string} Human-readable status label. / 人类可读的状态文本。
         */
        function statusLabel(status){
            if(status === 'ready') return 'Ready';
            if(status === 'failed') return 'Failed';
            if(status === 'running') return 'Running';
            return 'Starting';
        }

        /**
         * EN: Render one page of Provider Jobs returned by the polling endpoint.
         * 中文：渲染轮询接口返回的一页 Provider Jobs。
         *
         * @param {Array<Object>} jobs Provider Job records for the current page. / 当前页的 Provider Job 记录。
         *
         * @returns {void} No value is returned. / 无返回值。
         */
        function renderJobs(jobs){
            jobs = Array.isArray(jobs) ? jobs : [];

            if(!jobs.length){
                $body.html(
                    '<tr class="provider-jobs-empty">'+
                        '<td colspan="7">No provider jobs in this time range.</td>'+
                    '</tr>'
                );
                return;
            }

            const html = jobs.map(function(job){
                const status = safeStatus(job.status);

                return '<tr data-job-id="'+esc(job.id)+'">'+
                    '<td>'+esc(job.created_at)+'</td>'+
                    '<td>'+esc(job.user)+'</td>'+
                    '<td>'+esc(job.provider)+'</td>'+
                    '<td>'+esc(job.item)+'</td>'+
                    '<td><span class="provider-job '+status+'">'+
                        statusLabel(status)+
                    '</span></td>'+
                    '<td>'+esc(job.http)+'</td>'+
                    '<td class="job-error">'+esc(job.error)+'</td>'+
                '</tr>';
            }).join('');

            $body.html(html);
        }

        /**
         * EN: Update Provider Jobs pagination controls from server pagination metadata.
         * 中文：根据服务器返回的分页元数据更新 Provider Jobs 分页控件。
         *
         * @param {Object} pagination Pagination metadata returned by the Provider Jobs endpoint. / Provider Jobs 接口返回的分页元数据。
         *
         * @returns {void} No value is returned. / 无返回值。
         */
        function renderPagination(pagination){
            pagination = pagination || {};
            currentPage = Math.max(1, Number(pagination.page) || 1);
            totalPages = Math.max(1, Number(pagination.pages) || 1);
            currentTime = String(pagination.time_filter || currentTime || '24h');

            $page.text(currentPage);
            $pages.text(totalPages);
            $total.text(Math.max(0, Number(pagination.total) || 0));
            $prev.prop('disabled', currentPage <= 1);
            $next.prop('disabled', currentPage >= totalPages);
            $timeFilter.val(currentTime);
            $monitor
                .attr('data-page', currentPage)
                .attr('data-pages', totalPages)
                .attr('data-time-filter', currentTime);
        }

        /**
         * EN: Set the Provider Jobs live indicator to live, paused, or reconnect state.
         * 中文：将 Provider Jobs 实时状态指示器设置为 Live、Paused 或 Reconnect。
         *
         * @param {string} state CSS state class applied to the live indicator. / 应用于实时状态指示器的 CSS 状态类。
         *
         * @returns {void} No value is returned. / 无返回值。
         */
        function setLiveState(state){
            $live
                .removeClass('is-live is-paused is-error')
                .addClass(state);

            if(state === 'is-error'){
                $liveText.text('Reconnect');
            }else if(state === 'is-paused'){
                $liveText.text('Paused');
            }else{
                $liveText.text('Live');
            }
        }

        /**
         * EN: Request the current filtered Provider Jobs page and refresh table/pagination state.
         * 中文：请求当前筛选条件下的 Provider Jobs 页面，并刷新表格与分页状态。
         *
         * @param {boolean} force Allow a manual request while viewing a non-first page. / 在非第一页时是否允许手动请求。
         *
         * @returns {void} No value is returned. / 无返回值。
         */
        function refreshProviderJobs(force){
            if(document.hidden){
                setLiveState('is-paused');
                return;
            }

            // Automatic polling is kept on page 1 only so older pages do not
            // jump while new jobs are inserted at the top of the log.
            if(!force && currentPage !== 1){
                setLiveState('is-paused');
                return;
            }

            if(request && request.readyState !== 4){
                if(force){
                    request.abort();
                    request = null;
                }else{
                    return;
                }
            }

            request = $.ajax({
                url: $monitor.data('jobs-url'),
                method: 'GET',
                dataType: 'json',
                cache: false,
                data: {
                    page: currentPage,
                    per_page: perPage,
                    time: currentTime
                }
            })
            .done(function(d){
                if(!d || !d.ok){
                    setLiveState('is-error');
                    return;
                }

                const signature = JSON.stringify({
                    page: d.pagination && d.pagination.page,
                    pages: d.pagination && d.pagination.pages,
                    total: d.pagination && d.pagination.total,
                    jobs: (d.jobs || []).map(function(job){
                        return [job.id, job.updated_at, job.status, job.http, job.error];
                    })
                });

                if(signature !== lastSignature){
                    lastSignature = signature;
                    renderJobs(d.jobs);
                    renderPagination(d.pagination);
                }

                setLiveState(currentPage === 1 ? 'is-live' : 'is-paused');
            })
            .fail(function(_xhr, statusText){
                if(statusText === 'abort'){
                    return;
                }
                setLiveState('is-error');
            });
        }

        /**
         * EN: Start or restart Provider Jobs polling while preserving the selected filter/page state.
         * 中文：在保留当前时间筛选与页码状态的同时启动或重新启动 Provider Jobs 轮询。
         *
         * @returns {void} No value is returned. / 无返回值。
         */
        function startProviderJobsPolling(){
            if(timer){
                clearInterval(timer);
            }

            refreshProviderJobs(true);
            timer = setInterval(function(){
                refreshProviderJobs(false);
            }, 2000);
        }

        $timeFilter.on('change', function(){
            currentTime = String($(this).val() || '24h');
            currentPage = 1;
            lastSignature = '';
            refreshProviderJobs(true);
        });

        $prev.on('click', function(){
            if(currentPage <= 1){
                return;
            }
            currentPage -= 1;
            lastSignature = '';
            refreshProviderJobs(true);
        });

        $next.on('click', function(){
            if(currentPage >= totalPages){
                return;
            }
            currentPage += 1;
            lastSignature = '';
            refreshProviderJobs(true);
        });

        window.refreshProviderJobs = function(){
            currentPage = 1;
            lastSignature = '';
            refreshProviderJobs(true);
        };

        document.addEventListener('visibilitychange', function(){
            if(document.hidden){
                setLiveState('is-paused');
                return;
            }

            refreshProviderJobs(currentPage === 1);
        });

        startProviderJobsPolling();
    })();

    // v0.1.12 Provider Manager
    (function(){
        const $composer = $('#providerComposer');
        const $form = $('#providerDraftForm');

        if(!$composer.length || !$form.length){
            return;
        }

        $form.on('submit', function(e){
            e.preventDefault();
        });

        const defaults = {
            brightdata: {
                name: 'Bright Data',
                website: 'https://brightdata.com/'
            },
            apify: {
                name: 'Apify',
                website: 'https://apify.com/'
            },
            scrapecreators: {
                name: 'ScrapeCreators',
                website: 'https://scrapecreators.com/'
            },
            generic_json: {
                name: 'Custom API',
                website: ''
            }
        };

        /**
         * EN: Close or clear the clear provider field error behavior used by the application UI.
         * 中文：关闭或清理application UI 使用的“clear provider field error”行为。
         *
         * @param {Element|*} target Target DOM node or application object. / 目标 DOM 节点或应用对象。
         *
         * @returns {void} No value is returned. / 无返回值。
         */
        function clearProviderFieldError(target){
            const $field = typeof target === 'string'
                ? $form.find('[name="'+target+'"]:enabled').first()
                : $(target);

            if(!$field.length){
                return;
            }

            const $wrap = $field.closest('label');
            $field.removeAttr('aria-invalid');
            $wrap.removeClass('provider-field-has-error');
            $wrap.children('.provider-field-error').remove();
        }

        /**
         * EN: Close or clear the clear all provider field errors behavior used by the application UI.
         * 中文：关闭或清理application UI 使用的“clear all provider field errors”行为。
         *
         * @returns {void} No value is returned. / 无返回值。
         */
        function clearAllProviderFieldErrors(){
            $form
                .find('.provider-field-has-error')
                .removeClass('provider-field-has-error')
                .children('.provider-field-error')
                .remove();

            $form.find('[aria-invalid="true"]').removeAttr('aria-invalid');
        }

        /**
         * EN: Open or show the show provider field error behavior used by the application UI.
         * 中文：打开或显示application UI 使用的“show provider field error”行为。
         *
         * @param {*} field Field value used by this function. / 本函数使用的“field”参数值。
         * @param {string|*} message Human-readable message shown or recorded by the UI. / UI 显示或记录的可读消息。
         *
         * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
         */
        function showProviderFieldError(field, message){
            const $field = $form.find('[name="'+field+'"]:enabled').first();

            if(!$field.length){
                return false;
            }

            clearProviderFieldError($field);

            const $wrap = $field.closest('label');
            $field.attr('aria-invalid', 'true');
            $wrap.addClass('provider-field-has-error');

            $('<small class="provider-field-error" role="alert"></small>')
                .text(message)
                .appendTo($wrap);

            const el = $field.get(0);
            if(el){
                el.scrollIntoView({
                    behavior:'smooth',
                    block:'center'
                });

                setTimeout(function(){
                    try{
                        el.focus({preventScroll:true});
                    }catch(e){
                        el.focus();
                    }
                }, 250);
            }

            return true;
        }

        /**
         * EN: Perform the validate provider test url behavior used by the application UI.
         * 中文：执行application UI 使用的“validate provider test url”行为。
         *
         * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
         */
        function validateProviderTestUrl(){
            const value = String($('#providerTestUrl').val() || '').trim();
            const match = value.match(
                /^https?:\/\/(?:[a-z0-9-]+\.)?facebook\.com\/marketplace\/item\/(\d+)(?:[/?#].*)?$/i
            );

            if(!match){
                showProviderFieldError(
                    'test_url',
                    'Enter a complete Facebook Marketplace item URL with its numeric Item ID.'
                );
                return false;
            }

            clearProviderFieldError('test_url');
            return true;
        }

        /**
         * EN: Perform the page notice behavior used by the application UI.
         * 中文：执行application UI 使用的“page notice”行为。
         *
         * @param {string|*} message Human-readable message shown or recorded by the UI. / UI 显示或记录的可读消息。
         * @param {*} ok Ok value used by this function. / 本函数使用的“ok”参数值。
         *
         * @returns {void} No value is returned. / 无返回值。
         */
        function pageNotice(message, ok){
            const $n = $('#providerPageNotice');
            $n
                .removeClass('hidden ok bad')
                .addClass(ok ? 'ok' : 'bad')
                .text(message);
        }

        /**
         * EN: Perform the invalidate provider test behavior used by the application UI.
         * 中文：执行application UI 使用的“invalidate provider test”行为。
         *
         * @returns {void} No value is returned. / 无返回值。
         */
        function invalidateProviderTest(){
            $('#providerTestTicket').val('');
            $('#providerAddButton').prop('disabled', true);
            $('#providerDraftResult')
                .addClass('hidden')
                .removeClass('ok bad')
                .empty();
        }

        /**
         * EN: Update the sync provider type behavior used by the application UI.
         * 中文：更新application UI 使用的“sync provider type”行为。
         *
         * @returns {void} No value is returned. / 无返回值。
         */
        function syncProviderType(){
            const type = $('#providerType').val();
            const d = defaults[type] || defaults.generic_json;

            $('[data-provider-settings]')
                .addClass('hidden')
                .find(':input')
                .prop('disabled', true);

            $('[data-provider-settings="'+type+'"]')
                .removeClass('hidden')
                .find(':input')
                .prop('disabled', false);

            $('.provider-custom-only')
                .toggleClass('hidden', type !== 'generic_json')
                .find(':input')
                .prop('disabled', type !== 'generic_json');

            if(!$('#providerName').data('user-edited')){
                $('#providerName').val(d.name);
            }

            if(!$('#providerWebsite').data('user-edited')){
                $('#providerWebsite').val(d.website);
            }

            invalidateProviderTest();
        }

        $('#providerAddOpen').on('click', function(){
            $composer.removeClass('hidden');
            $composer.get(0).scrollIntoView({behavior:'smooth', block:'start'});
        });

        $('#providerAddClose').on('click', function(){
            $composer.addClass('hidden');
        });

        $('#providerType').on('change', function(){
            $('#providerName').data('user-edited', false);
            $('#providerWebsite').data('user-edited', false);
            syncProviderType();
        });

        $('#providerName, #providerWebsite').on('input', function(){
            $(this).data('user-edited', true);
        });

        $('#providerAuthMode').on('change', function(){
            const v = $(this).val();
            $('#providerAuthNameWrap').toggleClass(
                'hidden',
                !(v === 'header' || v === 'query')
            );
        }).trigger('change');

        $form.on('input change', 'input,select,textarea', function(e){
            if(e.target.id !== 'providerTestTicket'){
                clearProviderFieldError(e.target);
                invalidateProviderTest();
            }
        });

        $('#providerTestButton').on('click', function(){
            const $button = $(this);
            const $result = $('#providerDraftResult');

            clearAllProviderFieldErrors();

            if(!validateProviderTestUrl()){
                $result
                    .addClass('hidden')
                    .removeClass('ok bad')
                    .empty();
                return;
            }

            $button.prop('disabled', true).text('Testing...');
            $('#providerAddButton').prop('disabled', true);

            if(typeof window.refreshProviderJobs === 'function'){
                window.refreshProviderJobs();
                setTimeout(window.refreshProviderJobs, 250);
            }

            $.ajax({
                url: $button.data('test-url'),
                method: 'POST',
                data: $form.serialize(),
                dataType: 'json'
            })
            .done(function(d){
                if(!d || !d.ok){
                    const message =
                        (d && d.message) || 'Provider test failed.';

                    if(d && d.field && showProviderFieldError(d.field, message)){
                        $result
                            .addClass('hidden')
                            .removeClass('ok bad')
                            .empty();
                    }else{
                        $result
                            .removeClass('hidden ok')
                            .addClass('bad')
                            .text(message);
                    }
                    return;
                }

                $('#providerTestTicket').val(d.ticket || '');
                $('#providerAddButton').prop('disabled', !d.ticket);

                const r = d.result || {};
                const esc = function(v){
                    return $('<div>').text(v == null ? '—' : String(v)).html();
                };

                $result
                    .removeClass('hidden bad')
                    .addClass('ok')
                    .html(
                        '<strong>'+esc(d.message || 'Test passed.')+'</strong>'+
                        '<dl>'+
                            '<dt>Provider</dt><dd>'+esc(r.provider)+'</dd>'+
                            '<dt>Item ID</dt><dd>'+esc(r.item_id)+'</dd>'+
                            '<dt>Title</dt><dd>'+esc(r.title)+'</dd>'+
                            '<dt>Listing date</dt><dd>'+esc(r.listing_date)+'</dd>'+
                            '<dt>Description</dt><dd>'+esc(r.description)+'</dd>'+
                        '</dl>'
                    );
            })
            .fail(function(x){
                const data = x.responseJSON || {};
                const message =
                    data.message || 'Provider test failed.';

                if(data.field && showProviderFieldError(data.field, message)){
                    $result
                        .addClass('hidden')
                        .removeClass('ok bad')
                        .empty();
                }else{
                    $result
                        .removeClass('hidden ok')
                        .addClass('bad')
                        .text(message);
                }
            })
            .always(function(){
                $button.prop('disabled', false).text('Test Provider');

                if(typeof window.refreshProviderJobs === 'function'){
                    window.refreshProviderJobs();
                    setTimeout(window.refreshProviderJobs, 500);
                }
            });
        });

        $('#providerAddButton').on('click', function(){
            const $button = $(this);

            if(!$('#providerTestTicket').val()){
                return;
            }

            $button.prop('disabled', true).text('Adding...');

            $.ajax({
                url: $button.data('add-url'),
                method: 'POST',
                data: $form.serialize(),
                dataType: 'json'
            })
            .done(function(d){
                if(d && d.ok){
                    window.location.reload();
                    return;
                }
                pageNotice((d && d.message) || 'Could not add provider.', false);
            })
            .fail(function(x){
                pageNotice(
                    (x.responseJSON && x.responseJSON.message)
                    || 'Could not add provider.',
                    false
                );
            })
            .always(function(){
                $button.text('Add Provider');
                if($('#providerTestTicket').val()){
                    $button.prop('disabled', false);
                }
            });
        });

        $(document).on('change', '.provider-toggle', function(){
            const $toggle = $(this);
            const $card = $toggle.closest('.provider-card');
            const enabled = $toggle.is(':checked');

            $toggle.prop('disabled', true);

            $.post($toggle.data('toggle-url'), {
                _csrf: $form.find('[name="_csrf"]').val(),
                id: $card.data('provider-id'),
                enabled: enabled ? '1' : '0'
            })
            .done(function(d){
                if(!d || !d.ok){
                    $toggle.prop('checked', !enabled);
                    pageNotice((d && d.message) || 'Could not update provider.', false);
                    return;
                }

                $card
                    .toggleClass('is-enabled', enabled)
                    .toggleClass('is-disabled', !enabled);

                $toggle.next('span').text(enabled ? 'Enabled' : 'Disabled');
                pageNotice(d.message, true);
            })
            .fail(function(x){
                $toggle.prop('checked', !enabled);
                pageNotice(
                    (x.responseJSON && x.responseJSON.message)
                    || 'Could not update provider.',
                    false
                );
            })
            .always(function(){
                $toggle.prop('disabled', false);
            });
        });

        $(document).on('click', '.provider-delete', function(){
            const $button = $(this);
            const $card = $button.closest('.provider-card');

            if(!$button.hasClass('confirming')){
                $button
                    .addClass('confirming')
                    .text('Remove?');
                setTimeout(function(){
                    $button.removeClass('confirming').text('Remove');
                }, 3500);
                return;
            }

            $button.prop('disabled', true).text('Removing...');

            $.post($button.data('delete-url'), {
                _csrf: $form.find('[name="_csrf"]').val(),
                id: $card.data('provider-id')
            })
            .done(function(d){
                if(d && d.ok){
                    $card.remove();
                    refreshPriorityNumbers();
                    pageNotice(d.message, true);
                    return;
                }

                pageNotice((d && d.message) || 'Could not remove provider.', false);
            })
            .fail(function(x){
                pageNotice(
                    (x.responseJSON && x.responseJSON.message)
                    || 'Could not remove provider.',
                    false
                );
            })
            .always(function(){
                $button.prop('disabled', false).removeClass('confirming').text('Remove');
            });
        });

        /**
         * EN: Perform the refresh priority numbers behavior used by the application UI.
         * 中文：执行application UI 使用的“refresh priority numbers”行为。
         *
         * @returns {void} No value is returned. / 无返回值。
         */
        function refreshPriorityNumbers(){
            $('#providerSortable .provider-card').each(function(index){
                $(this).find('[data-provider-priority]').text(index + 1);
            });
        }

        /**
         * EN: Submit or persist the save provider order behavior used by the application UI.
         * 中文：提交或保存application UI 使用的“save provider order”行为。
         *
         * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
         */
        function saveProviderOrder(){
            const ids = $('#providerSortable .provider-card').map(function(){
                return $(this).data('provider-id');
            }).get();

            if(!ids.length){
                return;
            }

            $.post($('#providerSortable').data('reorder-url'), {
                _csrf: $form.find('[name="_csrf"]').val(),
                ids: JSON.stringify(ids)
            })
            .done(function(d){
                if(d && d.ok){
                    pageNotice(d.message, true);
                }else{
                    pageNotice((d && d.message) || 'Could not save provider order.', false);
                }
            })
            .fail(function(x){
                pageNotice(
                    (x.responseJSON && x.responseJSON.message)
                    || 'Could not save provider order.',
                    false
                );
            });
        }

        let dragging = null;
        let dragPlaceholder = null;
        let dragOriginMarker = null;
        let dragPointerId = null;
        let dragOffsetX = 0;
        let dragOffsetY = 0;
        let dragStartX = 0;
        let dragStartY = 0;
        let dragMoved = false;

        /**
         * EN: Remove temporary provider drag UI while preserving the provider card itself.
         * 中文：移除 Provider 拖拽期间的临时 UI，同时保留 Provider 卡片本身。
         *
         * @returns {void} No value is returned. / 无返回值。
         */
        function clearProviderDragUi(){
            $('.provider-card').removeClass(
                'drag-over drag-over-before drag-over-after provider-pointer-dragging'
            );
            $('.provider-drag').attr('aria-grabbed', 'false');

            if(dragPlaceholder && dragPlaceholder.parentNode){
                dragPlaceholder.parentNode.removeChild(dragPlaceholder);
            }

            if(dragOriginMarker && dragOriginMarker.parentNode){
                dragOriginMarker.parentNode.removeChild(dragOriginMarker);
            }

            dragPlaceholder = null;
            dragOriginMarker = null;
        }

        /**
         * EN: Reset inline geometry applied while a provider row follows the pointer.
         * 中文：重置 Provider 整行跟随鼠标拖动时使用的内联几何样式。
         *
         * @param {HTMLElement|null} card Provider card being restored. / 要恢复的 Provider 卡片。
         *
         * @returns {void} No value is returned. / 无返回值。
         */
        function resetProviderDragGeometry(card){
            if(!card){
                return;
            }

            card.style.position = '';
            card.style.left = '';
            card.style.top = '';
            card.style.width = '';
            card.style.height = '';
            card.style.zIndex = '';
            card.style.pointerEvents = '';
            card.style.margin = '';
        }

        /**
         * EN: Find and move the provider drop placeholder to the pointer's current list position.
         * 中文：根据当前鼠标位置寻找目标 Provider，并移动真实 Drop 占位块。
         *
         * @param {number} clientX Pointer X coordinate in the viewport. / 鼠标在视口中的 X 坐标。
         * @param {number} clientY Pointer Y coordinate in the viewport. / 鼠标在视口中的 Y 坐标。
         *
         * @returns {void} No value is returned. / 无返回值。
         */
        function moveProviderPlaceholder(clientX, clientY){
            if(!dragging || !dragPlaceholder){
                return;
            }

            const sortable = document.getElementById('providerSortable');
            if(!sortable){
                return;
            }

            const hit = document.elementFromPoint(clientX, clientY);
            const target = hit && hit.closest
                ? hit.closest('#providerSortable .provider-card')
                : null;

            $('.provider-card').removeClass(
                'drag-over drag-over-before drag-over-after'
            );

            if(target && target !== dragging){
                const rect = target.getBoundingClientRect();
                const before = clientY < rect.top + (rect.height / 2);

                $(target)
                    .addClass('drag-over')
                    .toggleClass('drag-over-before', before)
                    .toggleClass('drag-over-after', !before);

                if(before){
                    sortable.insertBefore(dragPlaceholder, target);
                }else{
                    sortable.insertBefore(dragPlaceholder, target.nextSibling);
                }
                return;
            }

            const listRect = sortable.getBoundingClientRect();
            if(
                clientX >= listRect.left
                && clientX <= listRect.right
                && clientY >= listRect.top
                && clientY <= listRect.bottom
            ){
                const cards = Array.from(
                    sortable.querySelectorAll('.provider-card')
                ).filter(function(card){
                    return card !== dragging;
                });

                if(!cards.length){
                    sortable.appendChild(dragPlaceholder);
                    return;
                }

                if(clientY < cards[0].getBoundingClientRect().top){
                    sortable.insertBefore(dragPlaceholder, cards[0]);
                    return;
                }

                const last = cards[cards.length - 1];
                if(clientY > last.getBoundingClientRect().bottom){
                    sortable.appendChild(dragPlaceholder);
                }
            }
        }

        /**
         * EN: Finish pointer sorting, committing the placeholder position or restoring the original position.
         * 中文：结束 Pointer 排序；提交占位块位置，或恢复 Provider 原始位置。
         *
         * @param {boolean} commit Whether the current placeholder position should be saved. / 是否保存当前占位块位置。
         *
         * @returns {void} No value is returned. / 无返回值。
         */
        function finishProviderPointerDrag(commit){
            if(!dragging){
                clearProviderDragUi();
                return;
            }

            const card = dragging;

            if(commit && dragPlaceholder && dragPlaceholder.parentNode){
                dragPlaceholder.parentNode.insertBefore(card, dragPlaceholder);
            }else if(dragOriginMarker && dragOriginMarker.parentNode){
                dragOriginMarker.parentNode.insertBefore(card, dragOriginMarker);
            }

            resetProviderDragGeometry(card);
            clearProviderDragUi();
            refreshPriorityNumbers();

            if(commit && dragMoved){
                saveProviderOrder();
            }

            dragging = null;
            dragPointerId = null;
            dragMoved = false;
            document.body.classList.remove('provider-sort-active');
        }

        $(document).on('pointerdown', '.provider-drag', function(e){
            if(e.pointerType === 'mouse' && e.button !== 0){
                return;
            }

            const card = $(this).closest('.provider-card').get(0);
            const sortable = document.getElementById('providerSortable');

            if(!card || !sortable || dragging){
                return;
            }

            e.preventDefault();

            const rect = card.getBoundingClientRect();
            dragging = card;
            dragPointerId = e.pointerId;
            dragStartX = e.clientX;
            dragStartY = e.clientY;
            dragOffsetX = e.clientX - rect.left;
            dragOffsetY = e.clientY - rect.top;
            dragMoved = false;

            dragOriginMarker = document.createComment('provider-drag-origin');
            sortable.insertBefore(dragOriginMarker, card);

            dragPlaceholder = document.createElement('div');
            dragPlaceholder.className = 'provider-drop-placeholder';
            dragPlaceholder.style.height = Math.max(64, Math.round(rect.height)) + 'px';
            sortable.insertBefore(dragPlaceholder, card);

            document.body.appendChild(card);
            card.classList.add('provider-pointer-dragging');
            card.style.position = 'fixed';
            card.style.left = rect.left + 'px';
            card.style.top = rect.top + 'px';
            card.style.width = rect.width + 'px';
            card.style.height = rect.height + 'px';
            card.style.margin = '0';
            card.style.zIndex = '10000';
            card.style.pointerEvents = 'none';

            $(this).attr('aria-grabbed', 'true');
            document.body.classList.add('provider-sort-active');

            if(this.setPointerCapture){
                try{
                    this.setPointerCapture(e.pointerId);
                }catch(_error){
                    // Pointer capture is an enhancement; document handlers still work.
                }
            }
        });

        $(document).on('pointermove', function(e){
            if(!dragging || e.pointerId !== dragPointerId){
                return;
            }

            e.preventDefault();

            const deltaX = Math.abs(e.clientX - dragStartX);
            const deltaY = Math.abs(e.clientY - dragStartY);
            if(deltaX > 3 || deltaY > 3){
                dragMoved = true;
            }

            dragging.style.left = (e.clientX - dragOffsetX) + 'px';
            dragging.style.top = (e.clientY - dragOffsetY) + 'px';
            moveProviderPlaceholder(e.clientX, e.clientY);
        });

        $(document).on('pointerup', function(e){
            if(!dragging || e.pointerId !== dragPointerId){
                return;
            }

            e.preventDefault();
            finishProviderPointerDrag(true);
        });

        $(document).on('pointercancel', function(e){
            if(!dragging || e.pointerId !== dragPointerId){
                return;
            }

            finishProviderPointerDrag(false);
        });

        $(document).on('keydown', function(e){
            if(e.key === 'Escape' && dragging){
                e.preventDefault();
                finishProviderPointerDrag(false);
            }
        });

        syncProviderType();
    })();


// v0.1.23 AJAX dashboard + Post Grid + Review Modal
(function(){
    const $grid = $('#salesProgressGrid');
    const $live = $('#adminDashboardLive');

    if(!$grid.length || !$live.length){
        return;
    }

    const targetUrl = $grid.data('target-url');
    const progressUrl = $live.data('progress-url');
    const updatesUrl = $live.data('updates-url');
    const salesPostsUrl = $live.data('sales-posts-url');
    const postSearchUrl = $live.data('post-search-url');
    const postReviewUrl = $live.data('post-review-url');
    const salesReviewSaveUrl = $live.data('sales-review-save-url');
    const salesReviewHistoryDeleteUrl = $live.data('sales-review-history-delete-url');
    const dailyStatusUrl = $live.data('daily-status-url');
    const dailyCompleteUrl = $live.data('daily-complete-url');
    const reviewSaveUrl = $live.data('review-save-url');
    const getContentUrl = $live.data('get-content-url');
    const editorImageUrl = $live.data('editor-image-url');
    const commentAddUrl = $live.data('comment-add-url');
    const commentUpdateUrl = $live.data('comment-update-url');
    const commentDeleteUrl = $live.data('comment-delete-url');
    const attachmentDeleteUrl = $live.data('attachment-delete-url');
    const today = String($live.data('today') || '');
    const csrf = $('#adminDashboardCsrf').val();

    const $salesDirectorySearch=$('#salesCardSearch');
    const $salesPostSearchResults=$('#adminSalesPostSearchResults');
    const $salesLocationFilter=$('#salesLocationFilter');
    const $salesDirectoryEmpty=$('#salesDirectoryFilterEmpty');
    const selectedLocationFilters=new Set();
    let salesDirectoryExpandedControlsReady=false;
    let salesPostSearchRequest=null;
    let salesPostSearchTimer=null;
    let salesPostSearchSalesIds=new Set();
    let salesPostSearchMatches=[];

    /**
     * EN: Normalize Sales directory text for case-insensitive search matching.
     * 中文：标准化 Sales Directory 文本，用于不区分大小写的搜索匹配。
     *
     * @param {*} value Value to normalize. / 需要标准化的值。
     * @returns {string} Normalized searchable text. / 标准化后的可搜索文本。
     */
    function normalizeSalesDirectoryText(value){
        return String(value||'')
            .trim()
            .toLocaleLowerCase();
    }

    /**
     * EN: Apply the Sales search text and every selected Location button to the Sales card grid.
     * 中文：把 Sales 搜索文字以及所有已选 Location Button 同时应用到 Sales Card Grid。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function salesDirectoryFilteringActive(){
        return normalizeSalesDirectoryText(
            $salesDirectorySearch.val()
        )!=='' || selectedLocationFilters.size>0;
    }

    function applySalesDirectoryFilters(){
        const query=normalizeSalesDirectoryText(
            $salesDirectorySearch.val()
        );
        let visibleCount=0;
        let visiblePostCount=0;

        $grid.find('.sales-progress-card').each(function(){
            const $card=$(this);
            const locationId=String(
                parseInt($card.attr('data-location-id'),10)||0
            );
            const salesId=String(
                parseInt($card.attr('data-sales-id'),10)||0
            );
            const haystack=normalizeSalesDirectoryText([
                $card.attr('data-sales-name'),
                $card.attr('data-sales-number')
            ].filter(Boolean).join(' '));
            const matchesSearch=!query
                ||haystack.includes(query)
                ||salesPostSearchSalesIds.has(salesId);
            const matchesLocation=selectedLocationFilters.size===0
                ||selectedLocationFilters.has(locationId);
            const show=matchesSearch&&matchesLocation;

            $card
                .toggleClass('sales-directory-hidden',!show)
                .attr('aria-hidden',show?'false':'true');

            if(show){
                visibleCount+=1;
                visiblePostCount+=Math.max(
                    0,
                    parseInt($card.attr('data-post-count'),10)||0
                );
            }
        });

        // v0.2.96 — Directory summary follows the cards that are actually
        // visible after Sales Search / Location filtering. This prevents an
        // all-company Post total from being shown beside a one-Sales filter.
        $('#dashboardSalesCount').text(visibleCount);
        $('#dashboardPostCount').text(visiblePostCount);
        $salesDirectoryEmpty.toggleClass('hidden',visibleCount>0);

        // Filtering no longer disables View Posts. Keep an already-open Sales
        // panel only when its card still matches the active filters; otherwise
        // close it so details can never remain attached to a hidden Sales card.
        if(salesDirectoryExpandedControlsReady&&expandedSalesId){
            const $expandedCard=$grid.find(
                '.sales-progress-card[data-sales-id="'
                +expandedSalesId
                +'"]'
            );

            if(
                !$expandedCard.length
                ||$expandedCard.hasClass('sales-directory-hidden')
            ){
                closeExpandedPosts();
            }else if(!$expanded.hasClass('hidden')){
                placeExpandedAfterCardRow($expandedCard);
                $expanded.removeClass('hidden');
            }
        }
    }

    /**
     * EN: Synchronize multi-select Location button visual and aria-pressed state.
     * 中文：同步多选 Location Button 的视觉状态与 aria-pressed 状态。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function syncSalesLocationButtons(){
        const hasSpecific=selectedLocationFilters.size>0;
        $salesLocationFilter
            .find('[data-location-filter="all"]')
            .toggleClass('active',!hasSpecific)
            .attr('aria-pressed',hasSpecific?'false':'true');

        $salesLocationFilter
            .find('[data-location-filter]')
            .not('[data-location-filter="all"]')
            .each(function(){
                const key=String($(this).attr('data-location-filter'));
                const active=selectedLocationFilters.has(key);
                $(this)
                    .toggleClass('active',active)
                    .attr('aria-pressed',active?'true':'false');
            });
    }

    /**
     * EN: Refresh Location button Sales counts after an Admin changes a Sales assignment.
     * 中文：Admin 修改 Sales Location 分配后，刷新每个 Location Button 后面的 Sales 人数。
     *
     * @param {Object|*} data Save response containing per-location counts. / 包含各 Location 人数的保存响应。
     * @returns {void} No value is returned. / 无返回值。
     */
    function updateSalesLocationFilterCounts(data){
        const counts={};
        (Array.isArray(data&&data.location_counts)
            ?data.location_counts
            :[]
        ).forEach(function(row){
            counts[String(parseInt(row.id,10)||0)]=
                Math.max(0,parseInt(row.count,10)||0);
        });
        counts['0']=Math.max(
            0,
            parseInt(data&&data.unassigned_count,10)||0
        );

        let total=0;
        Object.keys(counts).forEach(function(key){
            total+=counts[key];
            $salesLocationFilter
                .find('[data-location-filter="'+key+'"] [data-location-count]')
                .text(counts[key]);
        });
        $salesLocationFilter
            .find('[data-location-filter="all"] [data-location-count]')
            .text(total);
    }

    /**
     * EN: Render Post-origin matches beneath Sales/Post Search. Each row identifies
     * the Sales owner and opens the exact existing Post Review popup when clicked.
     * 中文：在 Sales/Post Search 下方渲染原帖匹配；每行显示 Sales 归属，点击后
     * 直接打开系统现有的该条精确 Post Review 弹窗。
     *
     * @param {Array|*} matches Post search matches. / Post 搜索匹配结果。
     * @param {string} query Current normalized query. / 当前标准化搜索文字。
     * @returns {void} No value is returned. / 无返回值。
     */
    function renderSalesPostSearchResults(matches,query){
        matches=Array.isArray(matches)?matches:[];
        salesPostSearchMatches=matches.slice();
        salesPostSearchSalesIds=new Set(
            matches.map(function(row){
                return String(parseInt(row.sales_user_id,10)||0);
            }).filter(function(id){return id!=='0';})
        );
        applySalesDirectoryFilters();

        if(!query||query.length<2){
            $salesPostSearchResults.addClass('hidden').empty();
            return;
        }

        if(!matches.length){
            let directSalesMatch=false;
            $grid.find('.sales-progress-card').each(function(){
                const haystack=normalizeSalesDirectoryText([
                    $(this).attr('data-sales-name'),
                    $(this).attr('data-sales-number')
                ].filter(Boolean).join(' '));
                if(haystack.includes(normalizeSalesDirectoryText(query))){
                    directSalesMatch=true;
                    return false;
                }
            });

            if(directSalesMatch){
                $salesPostSearchResults.addClass('hidden').empty();
                return;
            }

            $salesPostSearchResults
                .removeClass('hidden')
                .html(
                    '<div class="admin-sales-post-search-empty">'
                    +escapeHtml(tr('noPostSearchMatch'))
                    +'</div>'
                );
            return;
        }

        const rows=matches.map(function(row){
            const owner=String(row.sales_name||'Sales');
            const salesNumber=String(row.sales_id||'');
            const platform=String(row.platform||'Post');
            const title=String(row.title||'').trim()||tr('noTitle');
            const date=String(row.published_date||'');
            const url=String(row.original_url||'').trim();
            const externalId=String(row.external_post_id||'').trim();
            const original=url||externalId||platform;

            return (
                '<button type="button" class="admin-sales-post-search-result"'
                +' data-post-search-post-id="'+escapeHtml(row.post_id)+'"'
                +' data-post-search-sales-id="'+escapeHtml(row.sales_user_id)+'">'
                    +'<span class="admin-sales-post-search-owner">'
                        +'<b>'+escapeHtml(owner)+'</b>'
                        +(salesNumber?'<small>#'+escapeHtml(salesNumber)+'</small>':'')
                    +'</span>'
                    +'<span class="admin-sales-post-search-post">'
                        +'<span><b>'+escapeHtml(platform)+'</b>'
                        +(date?' · '+escapeHtml(date):'')+'</span>'
                        +'<strong>'+escapeHtml(title)+'</strong>'
                        +'<small title="'+escapeHtml(original)+'">'
                            +escapeHtml(original)
                        +'</small>'
                    +'</span>'
                +'</button>'
            );
        }).join('');

        $salesPostSearchResults
            .removeClass('hidden')
            .html(
                '<div class="admin-sales-post-search-head">'
                    +escapeHtml(tr('postSearchMatches'))
                    +' · '+matches.length
                +'</div>'
                +rows
            );
    }


    /**
     * EN: Convert one Sales/Post Search response row into the existing Post Grid
     * card shape. Search expansion intentionally keeps only matched posts.
     * 中文：把一条 Sales/Post Search 返回记录转换成现有 Post Grid 卡片数据；
     * 搜索展开时只保留本次真正命中的 Post。
     *
     * @param {Object|*} row Search result row. / 搜索结果记录。
     * @returns {Object} Post Grid item. / Post Grid 项目。
     */
    function salesPostSearchMatchToPost(row){
        row=row&&typeof row==='object'?row:{};
        return {
            id:parseInt(row.post_id,10)||0,
            platform:String(row.platform||'Post'),
            title:String(row.title||''),
            description:String(row.description||''),
            thumbnail_url:row.thumbnail_url||null,
            published_at:String(row.published_at||''),
            published_date:String(row.published_date||''),
            status:['good','bad'].includes(String(row.status||'').toLowerCase())
                ?String(row.status).toLowerCase()
                :null
        };
    }

    /**
     * EN: Expand only the posts matched by the active Sales/Post Search for one
     * Sales owner. This never falls back to the owner's full date-range Post Grid.
     * 中文：只展开当前 Sales/Post Search 对某位 Sales 真正命中的 Post；
     * 不再回退成该 Sales 当前日期范围内的全部 Post。
     *
     * @param {*} $card Matching Sales card. / 匹配的 Sales 卡片。
     * @param {Array|*} matches Search matches to display. / 要显示的搜索匹配。
     * @returns {void} No value is returned. / 无返回值。
     */
    function openSalesPostSearchMatches($card,matches){
        const salesId=parseInt($card&&$card.attr?$card.attr('data-sales-id'):0,10)||0;
        const rows=(Array.isArray(matches)?matches:[]).filter(function(row){
            return (parseInt(row.sales_user_id,10)||0)===salesId
                &&(parseInt(row.post_id,10)||0)>0;
        });

        if(!salesId||!rows.length||$card.hasClass('sales-directory-hidden')){
            return;
        }

        if(expandedRequest&&expandedRequest.readyState!==4){
            expandedRequest.abort();
        }

        dailyReviewOnlyMode=false;
        expandedSalesId=salesId;

        $grid
            .find('.sales-progress-card')
            .removeClass('expanded')
            .attr('aria-expanded','false');

        $card
            .addClass('expanded')
            .attr('aria-expanded','true');

        placeExpandedAfterCardRow($card);
        $expanded.removeClass('hidden');
        $expandedList.empty();
        $expandedLoading.addClass('hidden');
        $expandedReview.addClass('hidden');
        currentSalesPeriodReview=null;
        adminExpandedChannel='all';
        $('#adminSalesPlatformFilter [data-admin-sales-platform]')
            .removeClass('active')
            .attr('aria-pressed','false')
            .filter('[data-admin-sales-platform="all"]')
            .addClass('active')
            .attr('aria-pressed','true');

        const first=rows[0]||{};
        const postItems=rows.map(salesPostSearchMatchToPost).filter(function(post){
            return post.id>0;
        });
        currentExpandedData={
            search_only:true,
            sales:{
                id:salesId,
                name:String($card.attr('data-sales-name')||first.sales_name||'Sales'),
                sales_id:String($card.attr('data-sales-number')||first.sales_id||'')
            },
            period:'search',
            preset:'search',
            period_label:tr('postSearchMatches'),
            review:null,
            posts:postItems,
            count:postItems.length,
            chart_rows:[],
            daily_target:Math.max(1,parseInt($card.attr('data-daily-target'),10)||10),
            daily_targets:{}
        };

        renderPostGrid(currentExpandedData);
        $salesPostSearchResults.addClass('hidden');

        const panel=$expanded.get(0);
        if(panel&&typeof panel.scrollIntoView==='function'){
            panel.scrollIntoView({behavior:'smooth',block:'nearest'});
        }
    }

    /**
     * EN: Query the server for original marketplace Post information without
     * changing the existing Sales-name / Sales-ID filtering behavior.
     * 中文：向服务器搜索原始平台 Post 信息，同时保留现有姓名 / Sales ID 筛选行为。
     *
     * @param {string} query Raw Sales/Post Search text. / Sales/Post Search 原始搜索文字。
     * @returns {void} No value is returned. / 无返回值。
     */
    function searchOriginalSalesPosts(query){
        query=String(query||'').trim();

        if(salesPostSearchRequest&&salesPostSearchRequest.readyState!==4){
            salesPostSearchRequest.abort();
        }

        if(!postSearchUrl||query.length<2){
            salesPostSearchSalesIds=new Set();
            salesPostSearchMatches=[];
            renderSalesPostSearchResults([],query);
            return;
        }

        $salesPostSearchResults
            .removeClass('hidden')
            .html(
                '<div class="admin-sales-post-search-empty">'
                +escapeHtml(tr('searchingPosts'))+'…</div>'
            );

        salesPostSearchRequest=$.ajax({
            url:postSearchUrl,
            method:'GET',
            dataType:'json',
            cache:false,
            data:{q:query,_:Date.now()}
        })
        .done(function(data){
            const activeQuery=String($salesDirectorySearch.val()||'').trim();
            if(activeQuery!==query){
                return;
            }
            renderSalesPostSearchResults(
                data&&data.ok&&Array.isArray(data.matches)?data.matches:[],
                query
            );
        })
        .fail(function(xhr,status){
            if(status==='abort'){
                return;
            }
            salesPostSearchSalesIds=new Set();
            salesPostSearchMatches=[];
            applySalesDirectoryFilters();
            $salesPostSearchResults
                .removeClass('hidden')
                .html(
                    '<div class="admin-sales-post-search-empty">'
                    +escapeHtml(tr('postSearchFailed'))
                    +'</div>'
                );
        });
    }

    $salesDirectorySearch.on('input',function(){
        const query=String($(this).val()||'').trim();
        salesPostSearchSalesIds=new Set();
        salesPostSearchMatches=[];
        if(currentExpandedData&&currentExpandedData.search_only){
            closeExpandedPosts();
        }
        applySalesDirectoryFilters();

        if(salesPostSearchTimer){
            window.clearTimeout(salesPostSearchTimer);
        }
        salesPostSearchTimer=window.setTimeout(function(){
            searchOriginalSalesPosts(query);
        },220);
    });

    $salesPostSearchResults.on('click','[data-post-search-post-id]',function(event){
        event.preventDefault();
        event.stopPropagation();
        const postId=parseInt($(this).attr('data-post-search-post-id'),10)||0;
        const salesId=parseInt($(this).attr('data-post-search-sales-id'),10)||0;
        if(!postId||!salesId){
            return;
        }

        const match=salesPostSearchMatches.find(function(row){
            return (parseInt(row.post_id,10)||0)===postId;
        });
        const $card=$grid.find(
            '.sales-progress-card[data-sales-id="'+salesId+'"]'
        ).first();
        if(!match||!$card.length){
            return;
        }

        openSalesPostSearchMatches($card,[match]);
    });

    $(document).on('click.adminSalesPostSearch',function(event){
        if($(event.target).closest('#salesCardSearch,#adminSalesPostSearchResults').length){
            return;
        }
        $salesPostSearchResults.addClass('hidden');
    });

    $salesDirectorySearch.on('focus',function(){
        if($salesPostSearchResults.children().length){
            $salesPostSearchResults.removeClass('hidden');
        }
    });

    $salesLocationFilter.on('click','[data-location-filter]',function(){
        const key=String($(this).attr('data-location-filter')||'all');

        if(key==='all'){
            selectedLocationFilters.clear();
        }else if(selectedLocationFilters.has(key)){
            selectedLocationFilters.delete(key);
        }else{
            selectedLocationFilters.add(key);
        }

        syncSalesLocationButtons();
        applySalesDirectoryFilters();
    });

    applySalesDirectoryFilters();

const dashboardI18n={
    en:{
        greeting:'Hi, {name}',
        pageTitle:'My Sales Activity',
        dashboardSubtitle:'Review Sales posting activity, Post Review, and Sales Review status.',
        view:'View',
        from:'From',
        to:'To',
        range:'Range',
        backToday:'Back to today',
        daily:'Daily',
        oneDay:'1 Day',
        threeDays:'3 Days',
        weekly:'Weekly',
        monthly:'Monthly',
        custom:'Custom',
        sales:'Sales',
        posts:'Posts',
        postingProgress:'{period} Posting Progress',
        targetFormula:'Daily target × {days} = {target}.',
        targetMet:'Target met',
        postsLower:'posts',
        perDay:'/day',
        day:'day',
        days:'days',
        good:'Good',
        issues:'Bad',
        issue:'Bad',
        unreviewed:'Unreviewed',
        dailyReview:'Daily Review',
        markComplete:'Mark as Complete',
        unmarkComplete:'Unmark Complete',
        completed:'Completed',
        dailyActivityCalendar:'Daily Activity Calendar',
        selectDailyReviewDate:'Select a date to jump to that day and open Daily Review.',
        selectDailyCompleteDate:'Select a date to jump to that day, then mark it complete.',
        complete:'Complete',
        incomplete:'Incomplete',
        weeklyReview:'Weekly Sales Review',
        monthlyReview:'Monthly Sales Review',
        dailyTarget:'Daily Target',
        settings:'Settings',
        salesSettings:'Sales Settings',
        targetChartHelp:'This target is the horizontal line on the Sales activity chart.',
        saveSettings:'Save Settings',
        save:'Save',
        saved:'Saved',
        saveReview:'Save Review',
        addReview:'Add Review',
        editReview:'Edit Review',
        noReviewYet:'No review yet',
        rating:'Rating',
        salesRating:'Sales Rating',
        required:'Required',
        notRated:'Not rated',
        reviewHistory:'Review History',
        saves:'saves',
        addManagementReview:'Add a Sales / Behavior Review for this period.',
        reviewed:'Reviewed',
        reviewedBy:'Reviewed by {name}',
        viewPosts:'View posts',
        noActiveSales:'No active Sales users.',
        newPosts:'New posts are available',
        newPostBadge:'New',
        salesChanged:'Sales activity changed since this view was loaded.',
        refresh:'Refresh',
        refreshing:'Refreshing…',
        newPostAvailable:'1 new post available',
        newPostsAvailable:'{count} new posts available',
        refreshLatestProgress:'Refresh to load the latest {period} progress.',
        postList:'POST LIST',
        chronological:'chronological order',
        loading:'Loading',
        noPostsPeriod:'No verified posts in this period.',
        couldNotLoadPosts:'Could not load Sales posts.',
        noDescription:'No description available.',
        review:'Review',
        comment:'Comment',
        history:'History',
        seeFullComments:'See full comments',
        hideDeletedComments:'Hide deleted comments',
        deletedCount:'See full comments ({count} deleted)',
        decision:'Decision',
        required:'Required',
        passReview:'Pass review',
        needsAttention:'Needs attention',
        addNote:'Add Note',
        cancel:'Cancel',
        close:'Close',
        noPostsSelected:'No posts for the selected period.',
        periodTarget:'period target',
        listed:'Listed',
        noTitle:'No title returned',
        noDescriptionReturned:'No description returned.',
        contentFetched:'Content fetched.',
        addReviewForPeriod:'Add a management review for this Sales period.',
        salesSearch:'Sales / Post Search',
        salesSearchPlaceholder:'Search name, Sales ID or original Post link',
        postSearchMatches:'Post matches',
        searchingPosts:'Searching Posts',
        noPostSearchMatch:'No original Post matches found.',
        postSearchFailed:'Could not search original Posts.',
        location:'Location',
        allLocations:'All',
        unassigned:'Unassigned',
        noSalesMatch:'No Sales users match this search and location filter.',
        locationAssignmentHelp:'Used by the Admin Sales location filter.'
    },
    'zh-CN':{
        greeting:'你好，{name}',
        pageTitle:'我的销售活动',
        view:'查看',
        from:'开始',
        to:'结束',
        range:'日期范围',
        backToday:'返回今天',
        daily:'每日',
        oneDay:'1天',
        threeDays:'3天',
        weekly:'每周',
        monthly:'每月',
        custom:'自定义',
        sales:'销售',
        posts:'帖子',
        postingProgress:'{period}发帖进度',
        targetFormula:'每日目标 × {days} = {target}。',
        targetMet:'已达目标',
        postsLower:'帖',
        perDay:'/天',
        day:'天',
        days:'天',
        good:'通过',
        issues:'不合格',
        issue:'不合格',
        unreviewed:'未审核',
        dailyReview:'每日评估',
        markComplete:'标记完成',
        unmarkComplete:'取消完成标记',
        completed:'已完成',
        dailyActivityCalendar:'每日活动日历',
        selectDailyReviewDate:'选择日期跳转到当天并打开每日评估。',
        selectDailyCompleteDate:'选择日期跳转到当天，然后标记完成。',
        complete:'完成',
        incomplete:'未完成',
        weeklyReview:'每周销售评估',
        monthlyReview:'每月销售评估',
        dailyTarget:'每日目标',
        settings:'设置',
        salesSettings:'销售设置',
        targetChartHelp:'这个目标会显示为销售活动图上的横线。',
        saveSettings:'保存设置',
        save:'保存',
        saved:'已保存',
        saveReview:'保存评语',
        addReview:'添加评语',
        editReview:'修改评语',
        noReviewYet:'暂无评语',
        rating:'评分',
        salesRating:'销售评分',
        required:'必选',
        notRated:'未评分',
        reviewHistory:'评语历史',
        saves:'次保存',
        addManagementReview:'为该销售人员添加工作表现评估。',
        reviewed:'已评阅',
        reviewedBy:'评阅人：{name}',
        viewPosts:'查看帖子',
        noActiveSales:'没有启用的销售人员。',
        newPosts:'有新的帖子',
        newPostBadge:'新帖',
        salesChanged:'自本页面加载后，销售活动已有变化。',
        refresh:'刷新',
        refreshing:'刷新中…',
        newPostAvailable:'有 1 个新帖子',
        newPostsAvailable:'有 {count} 个新帖子',
        refreshLatestProgress:'刷新以加载最新的{period}进度。',
        postList:'帖子列表',
        chronological:'按时间顺序',
        loading:'加载中',
        noPostsPeriod:'该周期没有已验证的帖子。',
        couldNotLoadPosts:'无法加载销售帖子。',
        noDescription:'暂无描述。',
        review:'审核',
        comment:'评论',
        history:'历史记录',
        seeFullComments:'查看完整评论',
        hideDeletedComments:'隐藏已删除评论',
        deletedCount:'查看完整评论（{count} 条已删除）',
        decision:'审核结果',
        required:'必选',
        passReview:'审核通过',
        needsAttention:'需要处理',
        addNote:'添加备注',
        cancel:'取消',
        close:'关闭',
        noPostsSelected:'所选周期没有帖子。',
        periodTarget:'周期目标',
        listed:'发布于',
        noTitle:'未返回标题',
        noDescriptionReturned:'未返回描述。',
        contentFetched:'内容已获取。',
        addReviewForPeriod:'为该销售周期添加管理评语。',
        salesSearch:'Sales / Post 搜索',
        salesSearchPlaceholder:'搜索姓名、Sales ID 或原帖链接',
        postSearchMatches:'Post 匹配',
        searchingPosts:'正在搜索 Post',
        noPostSearchMatch:'没有找到匹配的原帖。',
        postSearchFailed:'无法搜索原帖。',
        location:'地点',
        allLocations:'全部',
        unassigned:'未分配',
        noSalesMatch:'没有符合搜索和地点筛选条件的 Sales。',
        locationAssignmentHelp:'用于 Admin Sales Dashboard 的地点筛选。'
    },
    'zh-TW':{
        greeting:'你好，{name}',
        pageTitle:'我的銷售活動',
        view:'查看',
        from:'開始',
        to:'結束',
        range:'日期範圍',
        backToday:'返回今天',
        daily:'每日',
        oneDay:'1天',
        threeDays:'3天',
        weekly:'每週',
        monthly:'每月',
        custom:'自訂',
        sales:'銷售',
        posts:'貼文',
        postingProgress:'{period}發文進度',
        targetFormula:'每日目標 × {days} = {target}。',
        targetMet:'已達目標',
        postsLower:'篇',
        perDay:'/天',
        day:'天',
        days:'天',
        good:'通過',
        issues:'不合格',
        issue:'不合格',
        unreviewed:'未審核',
        dailyReview:'每日評估',
        markComplete:'標記完成',
        unmarkComplete:'取消完成標記',
        completed:'已完成',
        dailyActivityCalendar:'每日活動日曆',
        selectDailyReviewDate:'選擇日期跳轉到當天並打開每日評估。',
        selectDailyCompleteDate:'選擇日期跳轉到當天，然後標記完成。',
        complete:'完成',
        incomplete:'未完成',
        weeklyReview:'每週銷售評估',
        monthlyReview:'每月銷售評估',
        dailyTarget:'每日目標',
        settings:'設定',
        salesSettings:'銷售設定',
        targetChartHelp:'此目標會顯示為銷售活動圖上的橫線。',
        saveSettings:'儲存設定',
        save:'儲存',
        saved:'已儲存',
        saveReview:'儲存評語',
        addReview:'新增評語',
        editReview:'修改評語',
        noReviewYet:'尚無評語',
        rating:'評分',
        salesRating:'銷售評分',
        required:'必選',
        notRated:'未評分',
        reviewHistory:'評語歷史',
        saves:'次儲存',
        addManagementReview:'為該銷售人員新增工作表現評估。',
        reviewed:'已評閱',
        reviewedBy:'評閱人：{name}',
        viewPosts:'查看貼文',
        noActiveSales:'沒有啟用的銷售人員。',
        newPosts:'有新的貼文',
        newPostBadge:'新貼文',
        salesChanged:'自本頁載入後，銷售活動已有變化。',
        refresh:'重新整理',
        refreshing:'重新整理中…',
        newPostAvailable:'有 1 則新貼文',
        newPostsAvailable:'有 {count} 則新貼文',
        refreshLatestProgress:'重新整理以載入最新的{period}進度。',
        postList:'貼文列表',
        chronological:'依時間順序',
        loading:'載入中',
        noPostsPeriod:'此週期沒有已驗證的貼文。',
        couldNotLoadPosts:'無法載入銷售貼文。',
        noDescription:'暫無描述。',
        review:'審核',
        comment:'評論',
        history:'歷史記錄',
        seeFullComments:'查看完整評論',
        hideDeletedComments:'隱藏已刪除評論',
        deletedCount:'查看完整評論（{count} 筆已刪除）',
        decision:'審核結果',
        required:'必選',
        passReview:'審核通過',
        needsAttention:'需要處理',
        addNote:'新增備註',
        cancel:'取消',
        close:'關閉',
        noPostsSelected:'所選週期沒有貼文。',
        periodTarget:'週期目標',
        listed:'發布於',
        noTitle:'未回傳標題',
        noDescriptionReturned:'未回傳描述。',
        contentFetched:'內容已取得。',
        addReviewForPeriod:'為此銷售週期新增管理評語。',
        salesSearch:'Sales / Post 搜尋',
        salesSearchPlaceholder:'搜尋姓名、Sales ID 或原貼連結',
        postSearchMatches:'Post 匹配',
        searchingPosts:'正在搜尋 Post',
        noPostSearchMatch:'沒有找到匹配的原貼。',
        postSearchFailed:'無法搜尋原貼。',
        location:'地點',
        allLocations:'全部',
        unassigned:'未分配',
        noSalesMatch:'沒有符合搜尋與地點篩選條件的 Sales。',
        locationAssignmentHelp:'用於 Admin Sales Dashboard 的地點篩選。'
    },
    es:{
        greeting:'Hola, {name}',
        pageTitle:'Mi actividad de ventas',
        view:'Ver',
        from:'Desde',
        to:'Hasta',
        range:'Rango',
        backToday:'Volver a hoy',
        daily:'Diario',
        oneDay:'1 Día',
        threeDays:'3 Días',
        weekly:'Semanal',
        monthly:'Mensual',
        custom:'Personal.',
        sales:'Ventas',
        posts:'Publicaciones',
        postingProgress:'Progreso de publicaciones · {period}',
        targetFormula:'Meta diaria × {days} = {target}.',
        targetMet:'Meta alcanzada',
        postsLower:'publicaciones',
        perDay:'/día',
        day:'día',
        days:'días',
        good:'Aprobado',
        issues:'Malo',
        issue:'Malo',
        unreviewed:'Sin revisar',
        dailyReview:'Revisión diaria',
        markComplete:'Marcar como completado',
        unmarkComplete:'Quitar completado',
        completed:'Completado',
        dailyActivityCalendar:'Calendario de actividad diaria',
        selectDailyReviewDate:'Selecciona una fecha para ir a ese día y abrir la revisión diaria.',
        selectDailyCompleteDate:'Selecciona una fecha para ir a ese día y marcarlo como completado.',
        complete:'Completo',
        incomplete:'Incompleto',
        weeklyReview:'Evaluación semanal de ventas',
        monthlyReview:'Evaluación mensual de ventas',
        dailyTarget:'Meta diaria',
        settings:'Configuración',
        salesSettings:'Configuración de ventas',
        targetChartHelp:'Esta meta aparece como la línea horizontal del gráfico de actividad.',
        saveSettings:'Guardar configuración',
        save:'Guardar',
        saved:'Guardado',
        saveReview:'Guardar revisión',
        addReview:'Añadir revisión',
        editReview:'Editar revisión',
        noReviewYet:'Sin revisión todavía',
        rating:'Calificación',
        salesRating:'Calificación de ventas',
        required:'Obligatorio',
        notRated:'Sin calificar',
        reviewHistory:'Historial de revisión',
        saves:'guardados',
        addManagementReview:'Añade una evaluación de desempeño para esta persona de ventas.',
        reviewed:'Revisado',
        reviewedBy:'Revisado por {name}',
        viewPosts:'Ver publicaciones',
        noActiveSales:'No hay vendedores activos.',
        newPosts:'Hay nuevas publicaciones',
        newPostBadge:'Nuevo',
        salesChanged:'La actividad de ventas cambió desde que se cargó esta vista.',
        refresh:'Actualizar',
        refreshing:'Actualizando…',
        newPostAvailable:'1 publicación nueva disponible',
        newPostsAvailable:'{count} publicaciones nuevas disponibles',
        refreshLatestProgress:'Actualiza para cargar el progreso más reciente de {period}.',
        postList:'LISTA DE PUBLICACIONES',
        chronological:'orden cronológico',
        loading:'Cargando',
        noPostsPeriod:'No hay publicaciones verificadas en este período.',
        couldNotLoadPosts:'No se pudieron cargar las publicaciones.',
        noDescription:'Sin descripción.',
        review:'Revisión',
        comment:'Comentario',
        history:'Historial',
        seeFullComments:'Ver comentarios completos',
        hideDeletedComments:'Ocultar comentarios eliminados',
        deletedCount:'Ver comentarios completos ({count} eliminados)',
        decision:'Decisión',
        required:'Obligatorio',
        passReview:'Aprobar revisión',
        needsAttention:'Requiere atención',
        addNote:'Añadir nota',
        cancel:'Cancelar',
        close:'Cerrar',
        noPostsSelected:'No hay publicaciones en el período seleccionado.',
        periodTarget:'meta del período',
        listed:'Publicado',
        noTitle:'No se devolvió título',
        noDescriptionReturned:'No se devolvió descripción.',
        contentFetched:'Contenido obtenido.',
        addReviewForPeriod:'Añade una revisión de gestión para este período de ventas.',
        salesSearch:'Buscar ventas / publicaciones',
        salesSearchPlaceholder:'Buscar nombre, ID de ventas o enlace original',
        postSearchMatches:'Coincidencias de publicaciones',
        searchingPosts:'Buscando publicaciones',
        noPostSearchMatch:'No se encontraron publicaciones originales.',
        postSearchFailed:'No se pudieron buscar publicaciones originales.',
        location:'Ubicación',
        allLocations:'Todas',
        unassigned:'Sin asignar',
        noSalesMatch:'Ningún vendedor coincide con la búsqueda y el filtro de ubicación.',
        locationAssignmentHelp:'Se usa en el filtro de ubicación del panel Admin.'
    }
};

let dashboardLanguage=localStorage.getItem('cdsp-admin-language')||'en';

if(!dashboardI18n[dashboardLanguage]){
    dashboardLanguage='en';
}

/**
 * EN: Perform the dashboard locale behavior used by the application UI.
 * 中文：执行application UI 使用的“dashboard locale”行为。
 *
 * @returns {string} String result produced by this UI helper. / 本 UI 辅助函数生成的字符串结果。
 */
function dashboardLocale(){
    if(dashboardLanguage==='zh-CN')return 'zh-CN';
    if(dashboardLanguage==='zh-TW')return 'zh-TW';
    if(dashboardLanguage==='es')return 'es-US';
    return 'en-US';
}

/**
 * EN: Perform the tr behavior used by the application UI.
 * 中文：执行application UI 使用的“tr”行为。
 *
 * @param {string|*} key Key used to identify the requested value. / 用于标识目标值的键。
 * @param {*} vars Vars value used by this function. / 本函数使用的“vars”参数值。
 *
 * @returns {boolean} Boolean result produced by this UI helper. / 本 UI 辅助函数生成的布尔结果。
 */
function tr(key,vars){
    const dict=dashboardI18n[dashboardLanguage]||dashboardI18n.en;
    let value=String(dict[key]??dashboardI18n.en[key]??key);

    Object.entries(vars||{}).forEach(function(entry){
        value=value.replace(
            new RegExp('\\{'+entry[0]+'\\}','g'),
            String(entry[1])
        );
    });

    return value;
}

/**
 * EN: Perform the translated period name behavior used by the application UI.
 * 中文：执行application UI 使用的“translated period name”行为。
 *
 * @param {*} period Period value used by this function. / 本函数使用的“period”参数值。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function translatedPeriodName(period){
    if(period==='week')return tr('weekly');
    if(period==='month')return tr('monthly');
    if(period==='range')return tr('range');
    return tr('daily');
}

/**
 * EN: Perform the translate sales card behavior used by the application UI.
 * 中文：执行application UI 使用的“translate sales card”行为。
 *
 * @param {*} $card $card value used by this function. / 本函数使用的“$card”参数值。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function translateSalesCard($card){
    const days=parseInt(
        $card.find('[data-period-days]').text(),
        10
    )||1;

    $card.find('[data-target-badge] [data-dashboard-i18n]')
        .text(tr('targetMet'));
    $card.find('[data-card-posts-label]').text(tr('postsLower'));
    $card.find('[data-card-per-day]').text(tr('perDay'));
    $card.find('[data-card-days-label]').text(
        days===1?tr('day'):tr('days')
    );
    $card.find('[data-card-good-label]').text(tr('good'));
    $card.find('[data-card-issues-label]').text(tr('issues'));
    $card.find('[data-card-unreviewed-label]').text(tr('unreviewed'));
    $card.find('[data-new-posts-label]').text(tr('newPostBadge'));
    $card.find('[data-card-daily-review-label]').text(tr('dailyReview'));
    $card.find('[data-card-daily-complete-label]').text(
        $card.attr('data-effective-complete')==='1'
            ?tr('complete')
            :tr('incomplete')
    );
    $card.find('[data-card-daily-target-label]').text(tr('dailyTarget'));
    $card.find('[data-card-save-label]').text(tr('save'));
    $card.find('[data-card-view-posts-label]').text(tr('viewPosts'));
    const locationName=String($card.attr('data-location-name')||'').trim();
    $card.find('[data-sales-location-label]').text(
        locationName||tr('unassigned')
    );
}

/**
 * EN: Perform the translate top nav behavior used by the application UI.
 * 中文：执行application UI 使用的“translate top nav”行为。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function translateTopNav(){
    // Header/footer are universal layout partials. Keep one menu translator
    // authoritative so Dashboard cannot rename the shared Dashboard link.
    applyGlobalMenuLanguage();
}

/**
 * EN: Update the apply dashboard language behavior used by the application UI.
 * 中文：更新application UI 使用的“apply dashboard language”行为。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function applyDashboardLanguage(){
    const adminName=String(
        $('#dashboardGreeting').attr('data-admin-name')
        ||'Administrator'
    );

    $('#dashboardGreeting').text(
        tr('greeting',{name:adminName})
    );
    $('#dashboardPageTitle').text(tr('pageTitle'));

    $('[data-dashboard-i18n]').each(function(){
        const key=String($(this).data('dashboard-i18n')||'');

        if(key){
            $(this).text(tr(key));
        }
    });

    $('[data-dashboard-i18n-placeholder]').each(function(){
        const key=String($(this).data('dashboard-i18n-placeholder')||'');
        if(key){
            $(this).attr('placeholder',tr(key));
        }
    });

    const adminPresetLabels={
        single:tr('oneDay'),
        day:tr('threeDays'),
        week:tr('weekly'),
        month:tr('monthly'),
        custom:tr('custom')
    };
    $('#dashboardPeriodSwitch [data-admin-preset]').each(function(){
        const preset=String($(this).attr('data-admin-preset')||'single');
        $(this).text(adminPresetLabels[preset]||preset);
    });
    $('#dashboardStickyPeriodSwitch [data-admin-sticky-preset]').each(function(){
        const preset=String($(this).attr('data-admin-sticky-preset')||'single');
        $(this).text(adminPresetLabels[preset]||preset);
    });

    $('#dashboardProgressTitle').text(
        tr('postingProgress',{
            period:currentPreset==='single'
                ?tr('oneDay')
                :currentPreset==='day'
                    ?tr('threeDays')
                    :currentPreset==='week'
                        ?tr('weekly')
                        :currentPreset==='month'
                            ?tr('monthly')
                            :tr('range')
        })
    );

    $('#dashboardProgressSubtitle').text(
        tr('targetFormula',{
            days:currentPeriodDays,
            target:
                String(
                    $('#dashboardProgressSubtitle')
                        .attr('data-period-target-label')
                    ||tr('periodTarget')
                )
        })
    );

    $grid.find('.sales-progress-card').each(function(){
        translateSalesCard($(this));
    });

    $('.sales-period-review-label').each(function(){
        if(currentSalesPeriodReview){
            $(this).text(
                currentSalesPeriodReview.period==='week'
                    ?tr('weeklyReview')
                    :currentSalesPeriodReview.period==='month'
                        ?tr('monthlyReview')
                        :tr('dailyReview')
            );
        }
    });

    $('#dashboardHistoryDeletedLabel').text(
        showDeletedComments
            ?tr('hideDeletedComments')
            :tr('seeFullComments')
    );

    $('.review-comment-kicker').text(tr('history'));

    $('.review-decision-modern legend')
        .contents()
        .filter(function(){
            return this.nodeType===3;
        })
        .first()
        .replaceWith(tr('decision')+' ');

    $('.review-required').text(tr('required'));
    $('#salesPeriodReviewRatingField .sales-review-rating-label strong').text(tr('salesRating'));
    $('#salesPeriodReviewRatingField .sales-review-rating-label span').text(tr('required'));
    $('.sales-review-save-history-head > span').text(tr('reviewHistory'));

    $('.review-decision-option.good strong').text(tr('good'));
    $('.review-decision-option.bad strong').text(
        dashboardLanguage==='es'?'Problema':tr('issues')
    );
    $('.review-decision-option.good small').text(tr('passReview'));
    $('.review-decision-option.bad small').text(tr('needsAttention'));

    $('.prose-editor-label label').each(function(){
        const text=String($(this).text()||'').trim();

        if(/Add Note|添加备注|新增備註|Añadir nota/i.test(text)){
            $(this).text(tr('addNote'));
        }
    });

    $('#dashboardCommentSave').text(tr('addNote'));
    $('#dashboardReviewCancel').text(tr('cancel'));
    $('#dashboardReviewSave').text(tr('saveReview'));

    translateTopNav();

    $('#appLanguageSwitch [data-app-lang]').each(function(){
        const active=String($(this).data('app-lang'))===dashboardLanguage;

        $(this)
            .toggleClass('active',active)
            .attr('aria-pressed',active?'true':'false');
    });

    document.documentElement.lang=dashboardLanguage;

    if(
        typeof renderDailyWorkflowCalendar==='function'
        &&$dailyWorkflowCalendarBackdrop
        &&$dailyWorkflowCalendarBackdrop.length
        &&!$dailyWorkflowCalendarBackdrop.hasClass('hidden')
    ){
        renderDailyWorkflowCalendar();
    }
}


    let currentDate = String($live.data('date') || '');
    let currentFrom = String($live.attr('data-from') || currentDate);
    let currentTo = String($live.attr('data-to') || currentDate);
    let currentPeriod = String($live.attr('data-period') || 'day');
    let currentPreset = String($live.attr('data-preset') || (currentPeriod==='day'?'single':currentPeriod));
    let currentPeriodDays = parseInt(
        $live.attr('data-period-days'),
        10
    ) || 1;
    let baselineCount = parseInt(
        $live.attr('data-post-count'),
        10
    ) || 0;
    let baselineMaxId = parseInt(
        $live.attr('data-max-post-id'),
        10
    ) || 0;

    let periodRequest = null;
    let activityRequest = null;
    let activityTimer = null;
    let noticeShown = false;
    let expandedSalesId = 0;
    let expandedRequest = null;
    let currentExpandedData = null;
    // v0.2.73: Daily Review can load its review data without opening
    // the expanded Post Grid. Keep this state separate from normal card expansion.
    let dailyReviewOnlyMode = false;
    let adminExpandedChannel = 'all';
    let reviewRequest = null;
    let activePostId = 0;

    const $notice = $('#dashboardRefreshNotice');
    const $noticeTitle = $('#dashboardRefreshTitle');
    const $noticeText = $('#dashboardRefreshText');
    const $expanded = $('#salesExpandedPosts');
    const $expandedTitle = $('#salesExpandedTitle');
    const $expandedSubtitle = $('#salesExpandedSubtitle');
    const $expandedList = $('#salesExpandedList');
    const $expandedLoading = $('#salesExpandedLoading');
    const $expandedReview = $('#salesExpandedReview');
    const $expandedReviewLabel = $('#salesExpandedReviewLabel');
    const $expandedReviewState = $('#salesExpandedReviewState');
    const $expandedReviewNote = $('#salesExpandedReviewNote');
    const $expandedReviewMeta = $('#salesExpandedReviewMeta');
    const $expandedReviewEdit = $('#salesExpandedReviewEdit');
    const $expandedReviewRating = $('#salesExpandedReviewRating');
    salesDirectoryExpandedControlsReady=true;
    const $adminSalesActivity = $('#adminSalesActivityChartPanel');
    const $adminSalesChartBars = $('#adminSalesChartBars');
    const $adminSalesChartCanvas = $('#adminSalesChartCanvas');
    const $adminSalesChartReviewLine = $('#adminSalesChartReviewLine');
    const $adminSalesChartScroll = $('#adminSalesChartScroll');
    const $adminSalesChartYAxis = $('#adminSalesChartYAxis');
    const $adminSalesChartRatingAxis = $('#adminSalesChartRatingAxis');
    const $adminSalesChartRatingAxisTicks = $('#adminSalesChartRatingAxisTicks');
    const $adminRangeBar = $('#adminDashboardRangeBar');
    const $adminRangeAnchor = $('#adminDashboardRangeAnchor');
    const $adminStickyRange = $('#adminDashboardStickyRange');
    const $adminStickyFrom = $('#dashboardStickyFrom');
    const $adminStickyTo = $('#dashboardStickyTo');
    const $adminStickyBackToday = $('#dashboardStickyBackToday');
    const $dailyWorkflowCalendarBackdrop = $('#dailyWorkflowCalendarBackdrop');
    const $dailyWorkflowCalendarSales = $('#dailyWorkflowCalendarSales');
    const $dailyWorkflowCalendarTitle = $('#dailyWorkflowCalendarTitle');
    const $dailyWorkflowCalendarMonth = $('#dailyWorkflowCalendarMonth');
    const $dailyWorkflowCalendarGrid = $('#dailyWorkflowCalendarGrid');
    const $dailyWorkflowCalendarPrev = $('#dailyWorkflowCalendarPrev');
    const $dailyWorkflowCalendarNext = $('#dailyWorkflowCalendarNext');
    const $dailyWorkflowCalendarHelp = $('#dailyWorkflowCalendarHelp');
    let dailyWorkflowCalendarRequest = null;
    let dailyWorkflowCalendarSalesId = 0;
    let dailyWorkflowCalendarMode = 'review';
    let dailyWorkflowCalendarSelectedDate = '';
    let dailyWorkflowCalendarMonth = '';
    let dailyWorkflowCalendarDays = {};

    /*
     * v0.1.95: the normal Admin activity header never becomes fixed. A separate
     * compact sticky strip mirrors the controls after the normal range deck
     * scrolls behind the universal header. This keeps legacy page-head/flex
     * rules completely out of the fixed layer and prevents the tall blank box.
     */
    let adminRangeStickyFrame = 0;

    /**
     * EN: Update the sync admin sticky range controls behavior used by the application UI.
     * 中文：更新application UI 使用的“sync admin sticky range controls”行为。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function syncAdminStickyRangeControls(){
        if(!$adminStickyRange.length){
            return;
        }

        const $from=$('#dashboardFromInput');
        const $to=$('#dashboardToInput');

        $adminStickyFrom
            .val(String($from.val()||currentFrom||''))
            .attr('max',String($from.attr('max')||''));

        $adminStickyTo
            .val(String($to.val()||currentTo||''))
            .removeAttr('min')
            .attr('max',String($to.attr('max')||''));

        $('#dashboardStickyPeriodSwitch [data-admin-sticky-preset]').each(function(){
            const active=String($(this).attr('data-admin-sticky-preset')||'')===currentPreset;
            $(this)
                .toggleClass('active',active)
                .attr('aria-pressed',active?'true':'false');
        });

        $adminStickyBackToday.toggleClass(
            'hidden',
            $('#dashboardBackToday').hasClass('hidden')
        );
    }

    /**
     * EN: Update the sync admin range sticky state behavior used by the application UI.
     * 中文：更新application UI 使用的“sync admin range sticky state”行为。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function syncAdminRangeStickyState(){
        if(!$adminRangeBar.length||!$adminRangeAnchor.length||!$adminStickyRange.length){
            return;
        }

        const topbar=document.querySelector('.topbar');
        const topbarHeight=topbar
            ?Math.ceil(topbar.getBoundingClientRect().height)
            :0;
        const rangeRect=$adminRangeBar.get(0).getBoundingClientRect();
        const stuck=window.scrollY>0 && rangeRect.bottom<=topbarHeight+2;

        $adminStickyRange
            .toggleClass('is-visible',stuck)
            .attr('aria-hidden',stuck?'false':'true')
            .css('top',topbarHeight+'px');

        if(stuck){
            syncAdminStickyRangeControls();
        }
    }

    /**
     * EN: Perform the request admin range sticky sync behavior used by the application UI.
     * 中文：执行application UI 使用的“request admin range sticky sync”行为。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function requestAdminRangeStickySync(){
        if(adminRangeStickyFrame){
            return;
        }

        adminRangeStickyFrame=window.requestAnimationFrame(function(){
            adminRangeStickyFrame=0;
            syncAdminRangeStickyState();
        });
    }

    $(window).on(
        'scroll.cdspAdminRangeSticky resize.cdspAdminRangeSticky',
        requestAdminRangeStickySync
    );
    syncAdminRangeStickyState();

    const $periodReviewModal = $('#salesPeriodReviewModal');
    const $periodReviewForm = $('#salesPeriodReviewForm');
    const $periodReviewSave = $('#salesPeriodReviewSave');
    const $periodReviewMessage = $('#salesPeriodReviewMessage');
    const $periodReviewRating = $('#salesPeriodReviewRating');
    const $periodReviewStars = $('#salesPeriodReviewStars');
    const $periodReviewRatingText = $('#salesPeriodReviewRatingText');
    const $periodReviewRatingError = $('#salesPeriodReviewRatingError');
    const $periodReviewHistory = $('#salesPeriodReviewHistory');
    const $periodReviewHistoryCount = $('#salesPeriodReviewHistoryCount');
    const $periodReviewDeletedSwitch = $('#salesPeriodReviewDeletedSwitch');
    const $periodReviewDeletedLabel = $('#salesPeriodReviewDeletedLabel');
    const $periodReviewImages = $('#salesPeriodReviewImages');
    const $periodReviewFileSelection = $('#salesPeriodReviewFileSelection');
    const $periodReviewAttachments = $('#salesPeriodReviewAttachments');

    let currentSalesPeriodReview = null;
    let showDeletedSalesReviewHistory = false;
    let armedSalesReviewHistoryDeleteId = 0;
    let armedSalesReviewHistoryDeleteTimer = null;
    let openReviewAfterExpand = false;
    const initialSalesId=parseInt($live.attr('data-initial-sales-id')||'0',10)||0;
    const initialOpenReview=String($live.attr('data-initial-open-review')||'0')==='1';

    const $modal = $('#dashboardReviewModal');
    const $modalForm = $('#dashboardReviewForm');
    const $modalLoading = $('#dashboardReviewLoading');
    const $modalMessage = $('#dashboardReviewMessage');
    const $reviewSaveState = $('#dashboardReviewSaveState');
    const $reviewCancel = $('#dashboardReviewCancel');
    const $modalAttachments = $('#dashboardReviewAttachments');
    const $commentList = $('#dashboardCommentList');
    const $commentEmpty = $('#dashboardCommentEmpty');
    const $commentCount = $('#dashboardCommentCount');
    const $commentSave = $('#dashboardCommentSave');
    const $commentCancelEdit = $('#dashboardCommentCancelEdit');
    const $commentMessage = $('#dashboardCommentMessage');
    const $commentImages = $('#dashboardCommentImages');
    const $commentFileSelection = $('#dashboardCommentFileSelection');
    let editingCommentId = 0;
    let currentComments = [];
    let currentReviewHistory = [];
    let currentLegacyAttachments = [];
    let showDeletedComments = false;

    const $historyDeletedSwitch = $('#dashboardHistoryDeletedSwitch');
    const $historyDeletedLabel = $('#dashboardHistoryDeletedLabel');
    let deleteCommentId = 0;
    let deleteAnchorButton = null;
    const $deletePopover = $('#commentDeletePopover');
    const $deleteConfirm = $('#commentDeleteConfirm');
    const $deleteCancel = $('#commentDeleteCancel');
    const $contentPreview = $('#dashboardContentPreview');
    const $contentProvider = $('#dashboardContentProvider');
    const $contentFetched = $('#dashboardContentFetched');
    const $contentTitle = $('#dashboardContentTitle');
    const $contentDate = $('#dashboardContentDate');
    const $contentDescription = $('#dashboardContentDescription');
    const $contentFacts = $('#dashboardContentFacts');
    const $productDetail = $('#dashboardProductDetail');
    const $contentPhotos = $('#dashboardContentPhotos');
    const $getContent = $('#dashboardGetContent');

    /**
     * EN: Escape text before inserting it into HTML output.
     * 中文：在将文本插入 HTML 输出前进行转义。
     *
     * @param {string|*} value Value read, transformed, or applied by this function. / 本函数读取、转换或应用的值。
     *
     * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
     */
    function escapeHtml(value){
        return $('<div>').text(
            value == null ? '' : String(value)
        ).html();
    }

/**
 * EN: Perform the platform logo html behavior used by the application UI.
 * 中文：执行application UI 使用的“platform logo html”行为。
 *
 * @param {*} platform Platform value used by this function. / 本函数使用的“platform”参数值。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function platformLogoHtml(platform){
    const key = String(platform || '').toLowerCase();

    if(key === 'facebook'){
        return (
            '<span class="platform-logo platform-logo-facebook"'
            +' title="Facebook" aria-label="Facebook">'
            +'<svg viewBox="0 0 24 24" aria-hidden="true">'
            +'<path d="M13.8 21v-8h2.7l.4-3.1h-3.1v-2c0-.9.3-1.5 1.6-1.5H17V3.6c-.3 0-1.3-.1-2.5-.1-2.5 0-4.2 1.5-4.2 4.3v2.1H7.5V13h2.8v8h3.5Z"/>'
            +'</svg></span>'
        );
    }

    if(key === 'offerup'){
        return (
            '<span class="platform-logo platform-logo-offerup"'
            +' title="OfferUp" aria-label="OfferUp">'
            +'<svg viewBox="0 0 24 24" aria-hidden="true">'
            +'<circle cx="8" cy="12" r="5.2"/>'
            +'<circle cx="16" cy="12" r="5.2"/>'
            +'<path d="M7.8 8.7v6.6M16.2 8.7v6.6"/>'
            +'</svg></span>'
        );
    }

    if(key === 'craigslist'){
        return (
            '<span class="platform-logo platform-logo-craigslist"'
            +' title="Craigslist" aria-label="Craigslist">'
            +'<svg viewBox="0 0 24 24" aria-hidden="true">'
            +'<circle cx="12" cy="12" r="8"/>'
            +'<path d="M12 4v16M12 12l-5.2 4M12 12l5.2 4"/>'
            +'</svg></span>'
        );
    }

    return (
        '<span class="platform-logo platform-logo-generic"'
        +' title="'+escapeHtml(platform)+'">'
        +'<svg viewBox="0 0 24 24" aria-hidden="true">'
        +'<path d="M4 5h16v14H4V5Zm2 2v10h12V7H6Z"/>'
        +'</svg></span>'
    );
}

    /**
     * EN: Perform the admin sales activity aggregate behavior used by the application UI.
     * 中文：执行application UI 使用的“admin sales activity aggregate”行为。
     *
     * @param {Object|*} data Structured data consumed by this function. / 本函数使用的结构化数据。
     * @param {string|*} date Date value used by the calculation or filter. / 计算或筛选使用的日期值。
     * @param {*} channel Channel value used by this function. / 本函数使用的“channel”参数值。
     *
     * @returns {boolean} Boolean result produced by this UI helper. / 本 UI 辅助函数生成的布尔结果。
     */
    function adminSalesActivityAggregate(data,date,channel){
        const result={
            date:date,
            post_count:0,
            good_count:0,
            bad_count:0,
            unreviewed_count:0
        };
        const rows=Array.isArray(data&&data.chart_rows)
            ?data.chart_rows
            :[];

        rows.forEach(function(row){
            if(String(row.date||'')!==date){
                return;
            }

            const platform=String(row.platform||'').toLowerCase();
            if(channel!=='all'&&platform!==channel){
                return;
            }

            result.post_count+=parseInt(row.post_count,10)||0;
            result.good_count+=parseInt(row.good_count,10)||0;
            result.bad_count+=parseInt(row.bad_count,10)||0;
            result.unreviewed_count+=parseInt(row.unreviewed_count,10)||0;
        });

        return result;
    }

    /**
     * EN: Render the render admin sales chart axis behavior used by the application UI.
     * 中文：渲染application UI 使用的“render admin sales chart axis”行为。
     *
     * @param {*} cap Cap value used by this function. / 本函数使用的“cap”参数值。
     * @param {Element|*} target Target DOM node or application object. / 目标 DOM 节点或应用对象。
     * @param {*} plotHeight Plot height value used by this function. / 本函数使用的“plot height”参数值。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function renderAdminSalesChartAxis(cap,targets,plotHeight){
        const step=salesChartTickStep(cap);
        const targetSet=new Set(
            (Array.isArray(targets)?targets:[targets])
                .map(function(value){
                    return String(Math.max(1,parseInt(value,10)||0));
                })
        );
        const values=[];
        for(let value=0;value<=cap+0.0001;value+=step){
            values.push(Number(value.toFixed(4)));
        }
        if(!values.length||Math.abs(values[values.length-1]-cap)>0.0001){
            values.push(cap);
        }

        const seen=new Set();
        let ticks='';
        let grid='';
        values.forEach(function(value){
            const key=String(value);
            if(seen.has(key)){return;}
            seen.add(key);
            const top=plotHeight*(1-(value/cap));
            const label=Number.isInteger(value)
                ?String(value)
                :String(Number(value.toFixed(1)));
            const cls=targetSet.has(String(Math.round(value)))?' target':'';
            ticks+='<span class="sales-chart-y-tick'+cls+'" style="top:'+top+'px">'+escapeHtml(label)+'</span>';
            grid+='<span class="sales-chart-grid-line'+cls+'" style="top:'+top+'px"></span>';
        });
        $('#adminSalesChartYAxisTicks').html(ticks);
        $('#adminSalesChartGridLines').html(grid);
    }

    /**
     * EN: Render the independent 1–5 Daily Review rating axis on the right.
     * 中文：在右侧渲染独立的 1–5 星 Daily Review 评分坐标轴。
     *
     * @param {number} plotHeight Height of the plotting area. / 绘图区高度。
     * @returns {void} No value is returned. / 无返回值。
     */
    function renderAdminSalesRatingAxis(plotHeight){
        let html='';
        for(let rating=5;rating>=1;rating-=1){
            const top=plotHeight*(1-((rating-1)/4));
            html+='<span class="sales-chart-rating-tick" style="top:'+top+'px">'
                +rating+'<i aria-hidden="true">★</i></span>';
        }
        $adminSalesChartRatingAxisTicks.html(html);
    }

    /**
     * EN: Render the render admin sales activity behavior used by the application UI.
     * 中文：渲染application UI 使用的“render admin sales activity”行为。
     *
     * @param {Object|*} data Structured data consumed by this function. / 本函数使用的结构化数据。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function renderAdminSalesActivity(data){
        if(!$adminSalesActivity.length||!data){
            return;
        }

        currentExpandedData=data;
        const from=String(data.from||currentFrom||currentDate);
        const to=String(data.to||currentTo||currentDate);
        const dates=salesDateRange(from,to);
        const fallbackTarget=Math.max(1,parseInt(data.daily_target,10)||10);
        const dailyTargets=(data.daily_targets&&typeof data.daily_targets==='object')
            ?data.daily_targets
            :{};
        const dailyRatings=(data.daily_ratings&&typeof data.daily_ratings==='object')
            ?data.daily_ratings
            :{};
        const targetForDate=function(date){
            return Math.max(1,parseInt(dailyTargets[date],10)||fallbackTarget);
        };
        const dayTargets=dates.map(targetForDate);
        const maxTarget=dayTargets.length
            ?Math.max.apply(null,dayTargets)
            :fallbackTarget;
        const minTarget=dayTargets.length
            ?Math.min.apply(null,dayTargets)
            :fallbackTarget;
        let maxPosts=0;
        dates.forEach(function(date){
            maxPosts=Math.max(
                maxPosts,
                Math.max(
                    0,
                    parseInt(
                        adminSalesActivityAggregate(data,date,adminExpandedChannel).post_count,
                        10
                    )||0
                )
            );
        });
        // Left axis is intentionally independent from Daily Review ratings.
        // Its ceiling is exactly the higher of: highest target × 120%, or
        // highest visible post count + 1. The 1–5 rating uses its own right axis.
        const cap=Math.max(1,maxTarget*1.2,maxPosts+1);
        const chartHeight=280;
        const xAxisHeight=32;
        const plotHeight=chartHeight-xAxisHeight;

        // The shared tooltip reads this target. Admin and Sales dashboards are
        // separate pages, so this safely keeps the displayed Missing value exact.
        salesChartDailyTarget=fallbackTarget;

        const targetSummary=minTarget===maxTarget
            ?String(maxTarget)
            :String(minTarget)+'–'+String(maxTarget);
        $('#adminSalesChartTargetCopy,#adminSalesChartTargetLineValue').text(targetSummary);
        $('#adminSalesChartPeriodTitle').text(
            currentPreset==='single'
                ?'1 Day Posting Activity'
                :currentPreset==='day'
                    ?'3 Days Posting Activity'
                    :currentPreset==='week'
                        ?'Weekly Posting Activity'
                        :currentPreset==='month'
                            ?'Monthly Posting Activity'
                            :'Custom Range Posting Activity'
        );

        $adminSalesChartCanvas.css({
            height:chartHeight+'px',
            '--sales-chart-height':chartHeight+'px',
            '--sales-plot-height':plotHeight+'px',
            '--sales-x-axis-height':xAxisHeight+'px'
        });
        $adminSalesChartYAxis.css('height',chartHeight+'px');
        $adminSalesChartRatingAxis.css('height',chartHeight+'px');
        renderAdminSalesChartAxis(
            cap,
            Array.from(new Set(dayTargets)),
            plotHeight
        );
        renderAdminSalesRatingAxis(plotHeight);
        $('#adminSalesChartTargetLine')
            .addClass('hidden')
            .removeAttr('style');

        const availableWidth=Math.max(
            320,
            Math.floor(
                ($adminSalesChartScroll.innerWidth()
                    ||$adminSalesActivity.innerWidth()
                    ||720)-2
            )
        );
        const dayCount=Math.max(1,dates.length);
        const coarse=Boolean(
            window.matchMedia
            &&window.matchMedia('(pointer:coarse)').matches
        );
        let minimumSlot;
        if(dayCount<=3){minimumSlot=coarse?96:82;}
        else if(dayCount<=7){minimumSlot=coarse?64:52;}
        else{minimumSlot=coarse?40:34;}

        const naturalSlot=availableWidth/dayCount;
        const needsScroll=naturalSlot<minimumSlot;
        const canvasWidth=needsScroll
            ?Math.max(availableWidth,dayCount*minimumSlot)
            :availableWidth;
        const slotWidth=canvasWidth/dayCount;
        let barWidth;
        if(dayCount<=3){barWidth=Math.min(74,Math.max(46,slotWidth*.46));}
        else if(dayCount<=7){barWidth=Math.min(48,Math.max(24,slotWidth*.45));}
        else{barWidth=Math.min(34,Math.max(12,slotWidth*.58));}

        let html='';
        const reviewTrendPoints=[];
        dates.forEach(function(date,dateIndex){
            const raw=adminSalesActivityAggregate(
                data,
                date,
                adminExpandedChannel
            );
            const actual=Math.max(0,parseInt(raw.post_count,10)||0);
            const dayTarget=targetForDate(date);
            const dailyRating=Math.max(0,Math.min(5,parseInt(dailyRatings[date],10)||0));
            if(dailyRating>0){
                const plotPadding=5;
                const reviewX=plotPadding+((dateIndex+.5)*Math.max(1,canvasWidth-(plotPadding*2))/dayCount);
                const rawReviewY=plotHeight*(1-((dailyRating-1)/4));
                reviewTrendPoints.push({
                    date:date,
                    rating:dailyRating,
                    x:reviewX,
                    y:Math.max(3,Math.min(plotHeight-3,rawReviewY))
                });
            }
            const targetTop=plotHeight*(1-(dayTarget/cap));
            const targetIndex=dateIndex;
            const previousTarget=targetIndex>0
                ?targetForDate(dates[targetIndex-1])
                :null;
            const showTargetLabel=
                targetIndex===0
                ||targetIndex===dates.length-1
                ||previousTarget!==dayTarget;
            const good=Math.min(actual,Math.max(0,parseInt(raw.good_count,10)||0));
            const bad=Math.min(Math.max(0,actual-good),Math.max(0,parseInt(raw.bad_count,10)||0));
            const unreviewed=Math.max(0,actual-good-bad);
            const visibleTotal=Math.min(actual,cap);
            const scale=actual>0?visibleTotal/actual:0;
            const goodH=(good*scale/cap)*100;
            const badH=(bad*scale/cap)*100;
            const unreviewedH=(unreviewed*scale/cap)*100;
            const missing=Math.max(0,dayTarget-actual);

            html+='<div class="sales-chart-day" tabindex="0"'
                +' data-chart-date="'+escapeHtml(date)+'"'
                +' data-chart-total="'+actual+'"'
                +' data-chart-good="'+good+'"'
                +' data-chart-bad="'+bad+'"'
                +' data-chart-unreviewed="'+unreviewed+'"'
                +' data-chart-missing="'+missing+'"'
                +' data-chart-target="'+dayTarget+'"'
                +' data-chart-rating="'+dailyRating+'">'
                +'<div class="sales-chart-day-plot">'
                    +'<span class="sales-chart-day-target" style="top:'+targetTop+'px">'
                        +(showTargetLabel?'<span>Daily target '+dayTarget+'</span>':'')
                    +'</span>'
                    +'<div class="sales-chart-stack">'
                        +'<span class="sales-chart-segment good" style="height:'+goodH+'%"></span>'
                        +'<span class="sales-chart-segment bad" style="height:'+badH+'%"></span>'
                        +'<span class="sales-chart-segment unreviewed" style="height:'+unreviewedH+'%"></span>'
                    +'</div>'
                +'</div>'
                +'<span class="sales-chart-x-label">'+escapeHtml(salesShortDate(date))+'</span>'
            +'</div>';
        });

        $adminSalesChartBars.html(html).css({
            'grid-template-columns':'repeat('+dayCount+',minmax(0,1fr))',
            'grid-auto-flow':'row',
            'grid-auto-columns':'unset',
            '--sales-chart-bar-width':Math.round(barWidth)+'px'
        });

        let reviewTrendSvg='';
        if(reviewTrendPoints.length>1){
            const path=reviewTrendPoints.map(function(point,index){
                return (index===0?'M':'L')+point.x.toFixed(2)+' '+point.y.toFixed(2);
            }).join(' ');
            reviewTrendSvg+='<path class="sales-chart-review-trend-path" d="'+path+'"></path>';
        }
        reviewTrendPoints.forEach(function(point){
            reviewTrendSvg+=
                '<g class="sales-chart-review-trend-point">'
                +'<text class="sales-chart-review-trend-star" x="'+point.x.toFixed(2)+'" y="'+point.y.toFixed(2)+'" text-anchor="middle" dominant-baseline="central">★</text>'
                +'<title>'+escapeHtml(salesShortDate(point.date))+' · Daily Review '+point.rating+'/5</title>'
                +'</g>';
        });
        $adminSalesChartReviewLine
            .attr('viewBox','0 0 '+Math.round(canvasWidth)+' '+plotHeight)
            .attr('width',Math.round(canvasWidth))
            .attr('height',plotHeight)
            .html(reviewTrendSvg);

        $adminSalesChartCanvas.css('width',Math.round(canvasWidth)+'px');
        $adminSalesActivity
            .attr('data-daily-target',fallbackTarget)
            .attr('data-range-days',dayCount)
            .toggleClass('sales-chart-single-day',dayCount===1)
            .toggleClass('sales-chart-short-range',dayCount<=7)
            .toggleClass('sales-chart-scrollable',needsScroll)
            .removeClass('hidden');
    }

    /**
     * EN: Perform the period name behavior used by the application UI.
     * 中文：执行application UI 使用的“period name”行为。
     *
     * @param {*} period Period value used by this function. / 本函数使用的“period”参数值。
     *
     * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
     */
    function periodName(period){
        return translatedPeriodName(period);
    }

    /**
     * EN: Update the set target message behavior used by the application UI.
     * 中文：更新application UI 使用的“set target message”行为。
     *
     * @param {*} $card $card value used by this function. / 本函数使用的“$card”参数值。
     * @param {string|*} message Human-readable message shown or recorded by the UI. / UI 显示或记录的可读消息。
     * @param {*} error Error value used by this function. / 本函数使用的“error”参数值。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function setTargetMessage($card, message, error){
        $card
            .find('[data-target-message]')
            .toggleClass('error', !!error)
            .text(message || '');
    }

    /**
     * EN: Perform the animate number behavior used by the application UI.
     * 中文：执行application UI 使用的“animate number”行为。
     *
     * @param {*} $element $element value used by this function. / 本函数使用的“$element”参数值。
     * @param {*} from From value used by this function. / 本函数使用的“from”参数值。
     * @param {*} to To value used by this function. / 本函数使用的“to”参数值。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function animateNumber($element, from, to){
        from = parseInt(from, 10) || 0;
        to = parseInt(to, 10) || 0;

        if(from === to){
            $element.text(to);
            return;
        }

        const start = performance.now();
        const duration = 300;

        /**
         * EN: Perform the frame behavior used by the application UI.
         * 中文：执行application UI 使用的“frame”行为。
         *
         * @param {*} now Now value used by this function. / 本函数使用的“now”参数值。
         *
         * @returns {void} No value is returned. / 无返回值。
         */
        function frame(now){
            const raw = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - raw, 3);
            const value = Math.round(from + (to - from) * eased);

            $element.text(value);

            if(raw < 1){
                requestAnimationFrame(frame);
            }
        }

        requestAnimationFrame(frame);
    }

    /**
     * EN: Update the update history behavior used by the application UI.
     * 中文：更新application UI 使用的“update history”行为。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function updateHistory(){
        if(!window.history || !window.history.replaceState){
            return;
        }

        const url=new URL(window.location.href);

        url.searchParams.set('preset',currentPreset);
        if(currentPeriod==='range'){
            url.searchParams.delete('date');
            url.searchParams.set('period','range');
            url.searchParams.set('from',currentFrom);
            url.searchParams.set('to',currentTo);
        }else{
            url.searchParams.set('date',currentDate);
            url.searchParams.set('period',currentPeriod);
            url.searchParams.delete('from');
            url.searchParams.delete('to');
        }

        url.searchParams.delete('sales_id');
        window.history.replaceState({},'',url.toString());
    }

    /**
     * EN: Update the update back today behavior used by the application UI.
     * 中文：更新application UI 使用的“update back today”行为。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function updateBackToday(){
        const pickerMax=String(
            $('#dashboardToInput').attr('max')
            ||''
        );

        const atLatest=Boolean(
            (today&&currentTo===today)
            ||(
                pickerMax
                &&currentTo===pickerMax
            )
        );

        $('#dashboardBackToday').toggleClass(
            'hidden',
            atLatest
        );
        if(typeof syncAdminStickyRangeControls==='function'){
            syncAdminStickyRangeControls();
        }
    }

    /**
     * EN: Update the sync admin range inputs behavior used by the application UI.
     * 中文：更新application UI 使用的“sync admin range inputs”行为。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function syncAdminRangeInputs(){
        const $from=$('#dashboardFromInput');
        const $to=$('#dashboardToInput');

        const maxFrom=(
            today
            &&currentTo>today
        )
            ?today
            :currentTo;

        $from
            .val(currentFrom)
            .attr('max',maxFrom);

        $to
            .val(currentTo)
            .removeAttr('min')
            .attr('max',today||'');
        if(typeof syncAdminStickyRangeControls==='function'){
            syncAdminStickyRangeControls();
        }
    }

    /**
     * EN: Perform the admin ajax range data behavior used by the application UI.
     * 中文：执行application UI 使用的“admin ajax range data”行为。
     *
     * @param {*} extra Extra value used by this function. / 本函数使用的“extra”参数值。
     *
     * @returns {boolean} Boolean result produced by this UI helper. / 本 UI 辅助函数生成的布尔结果。
     */
    function adminAjaxRangeData(extra){
        const data=Object.assign({},extra||{});
        data.preset=currentPreset;
        if(currentPeriod==='range'){
            data.from=currentFrom;
            data.to=currentTo;
            data.period='range';
        }else{
            data.date=currentDate;
            data.period=currentPeriod;
        }
        return data;
    }

    /**
     * EN: Update the update period buttons behavior used by the application UI.
     * 中文：更新application UI 使用的“update period buttons”行为。
     *
     * @param {*} preset Preset value used by this function. / 本函数使用的“preset”参数值。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function updatePeriodButtons(preset){
        currentPreset=String(preset||'custom');
        $('#dashboardPeriodSwitch [data-admin-preset]').each(function(){
            const active=String($(this).attr('data-admin-preset'))===currentPreset;
            $(this)
                .toggleClass('active',active)
                .attr('aria-pressed',active?'true':'false');
        });
        $live.attr('data-preset',currentPreset);
        if(typeof syncAdminStickyRangeControls==='function'){
            syncAdminStickyRangeControls();
        }
    }

    /**
     * EN: Perform the admin preset range behavior used by the application UI.
     * 中文：执行application UI 使用的“admin preset range”行为。
     *
     * @param {*} preset Preset value used by this function. / 本函数使用的“preset”参数值。
     * @param {*} anchorValue Anchor value value used by this function. / 本函数使用的“anchor value”参数值。
     *
     * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
     */
    function adminPresetRange(preset,anchorValue){
        const parse=function(value){
            const m=String(value||'').match(/^(\d{4})-(\d{2})-(\d{2})$/);
            if(!m)return null;
            const d=new Date(+m[1],+m[2]-1,+m[3],12,0,0);
            return Number.isNaN(d.getTime())?null:d;
        };
        const iso=function(d){
            return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');
        };
        let anchor=parse(anchorValue)||parse(today);
        const todayDate=parse(today);
        if(!anchor)return null;
        if(todayDate&&anchor>todayDate)anchor=new Date(todayDate);
        const toDate=new Date(anchor);
        let fromDate=new Date(anchor);

        if(preset==='day'){
            fromDate.setDate(fromDate.getDate()-2);
        }else if(preset==='week'){
            fromDate.setDate(fromDate.getDate()-6);
        }else if(preset==='month'){
            const anchorDay=toDate.getDate();
            const prevStart=new Date(toDate.getFullYear(),toDate.getMonth()-1,1,12,0,0);
            const prevLastDay=new Date(toDate.getFullYear(),toDate.getMonth(),0,12,0,0).getDate();
            fromDate=new Date(prevStart.getFullYear(),prevStart.getMonth(),Math.min(anchorDay,prevLastDay),12,0,0);
            fromDate.setDate(fromDate.getDate()+1);
        }

        return {from:iso(fromDate),to:iso(toDate)};
    }

    /**
     * EN: Parse one dashboard ISO date at local noon so timezone offsets cannot shift the day.
     * 中文：把 Dashboard 的 ISO 日期按本地中午解析，避免时区偏移把日期挪到前一天/后一天。
     *
     * @param {*} value ISO date value. / ISO 日期值。
     * @returns {Date|null} Parsed local date or null. / 本地日期对象，失败时返回 null。
     */
    function dailyWorkflowParseDate(value){
        const match=String(value||'').match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if(!match){
            return null;
        }
        const date=new Date(
            Number(match[1]),
            Number(match[2])-1,
            Number(match[3]),
            12,0,0
        );
        return Number.isNaN(date.getTime())?null:date;
    }

    /**
     * EN: Format a compact date shown beside the Daily Review / Complete action.
     * 中文：格式化 Daily Review / Complete 旁边显示的短日期。
     *
     * @param {*} value ISO date value. / ISO 日期值。
     * @returns {string} Localized short date label. / 本地化短日期标签。
     */
    function dailyActionDateLabel(value){
        const date=dailyWorkflowParseDate(value);
        if(!date){
            return String(value||'');
        }
        return date.toLocaleDateString(dashboardLocale(),{
            month:'short',
            day:'numeric'
        });
    }

    /**
     * EN: Convert a date to the YYYY-MM month key used by the Daily Activity Calendar endpoint.
     * 中文：把日期转换成 Daily Activity Calendar 接口使用的 YYYY-MM 月份键。
     *
     * @param {*} value ISO date value. / ISO 日期值。
     * @returns {string} Month key or empty string. / 月份键，失败时为空。
     */
    function dailyWorkflowMonthKey(value){
        const match=String(value||'').match(/^(\d{4})-(\d{2})/);
        return match?match[1]+'-'+match[2]:'';
    }

    /**
     * EN: Shift one YYYY-MM month key by the requested number of months.
     * 中文：把 YYYY-MM 月份键前后移动指定月数。
     *
     * @param {string} month Month key. / 月份键。
     * @param {number} delta Month offset. / 月份偏移量。
     * @returns {string} Shifted month key. / 移动后的月份键。
     */
    function dailyWorkflowShiftMonth(month,delta){
        const match=String(month||'').match(/^(\d{4})-(\d{2})$/);
        if(!match){
            return '';
        }
        const date=new Date(Number(match[1]),Number(match[2])-1+delta,1,12,0,0);
        return date.getFullYear()+'-'+String(date.getMonth()+1).padStart(2,'0');
    }

    /**
     * EN: Close the shared Daily Review / Complete history calendar.
     * 中文：关闭 Daily Review / Complete 共用的历史日历。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function closeDailyWorkflowCalendar(){
        if(dailyWorkflowCalendarRequest&&dailyWorkflowCalendarRequest.readyState!==4){
            dailyWorkflowCalendarRequest.abort();
        }
        $dailyWorkflowCalendarBackdrop
            .addClass('hidden')
            .attr('aria-hidden','true');
    }

    /**
     * EN: Render one month with separate Reviewed and Complete dots.
     * 中文：渲染一个月，并用独立圆点显示 Reviewed 与 Complete 状态。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function renderDailyWorkflowCalendar(){
        const match=String(dailyWorkflowCalendarMonth||'').match(/^(\d{4})-(\d{2})$/);
        if(!match){
            return;
        }
        const year=Number(match[1]);
        const monthIndex=Number(match[2])-1;
        const first=new Date(year,monthIndex,1,12,0,0);
        const lastDay=new Date(year,monthIndex+1,0,12,0,0).getDate();
        const todayDate=dailyWorkflowParseDate(today)||new Date();
        const todayIso=today
            ||todayDate.getFullYear()+'-'+String(todayDate.getMonth()+1).padStart(2,'0')+'-'+String(todayDate.getDate()).padStart(2,'0');
        const todayMonth=dailyWorkflowMonthKey(todayIso);
        const html=[];

        for(let blank=0;blank<first.getDay();blank+=1){
            html.push('<span class="daily-workflow-calendar-blank" aria-hidden="true"></span>');
        }

        for(let day=1;day<=lastDay;day+=1){
            const date=dailyWorkflowCalendarMonth+'-'+String(day).padStart(2,'0');
            const status=dailyWorkflowCalendarDays[date]||{};
            const future=Boolean(todayIso&&date>todayIso);
            const selected=date===dailyWorkflowCalendarSelectedDate;
            const dots=[];
            const reviewRating=Math.max(0,Math.min(5,parseInt(status.rating,10)||0));
            const dayAria=[date];
            if(status.reviewed){
                const reviewedTitle=tr('reviewed')+(reviewRating>0?' · '+reviewRating+'/5':'');
                dots.push('<i class="reviewed" title="'+escapeHtml(reviewedTitle)+'"></i>');
                dayAria.push(reviewedTitle);
            }
            if(status.completed){
                dots.push('<i class="completed" title="'+escapeHtml(tr('complete'))+'"></i>');
                dayAria.push(tr('complete'));
            }
            html.push(
                '<button type="button" class="daily-workflow-calendar-day'
                +(selected?' is-selected':'')
                +(future?' is-future':'')
                +'" data-daily-workflow-date="'+escapeHtml(date)+'"'
                +(future?' disabled':'')
                +' aria-label="'+escapeHtml(dayAria.join(' · '))+'">'
                +'<span>'+day+'</span>'
                +(dots.length?'<span class="daily-workflow-calendar-day-dots">'+dots.join('')+'</span>':'')
                +'</button>'
            );
        }

        $dailyWorkflowCalendarGrid.html(html.join(''));
        $dailyWorkflowCalendarMonth.text(
            first.toLocaleDateString(dashboardLocale(),{
                month:'long',
                year:'numeric'
            })
        );
        $('.daily-workflow-weekdays span').each(function(index){
            const reference=new Date(2026,7,2+index,12,0,0);
            $(this).text(
                reference.toLocaleDateString(dashboardLocale(),{weekday:'short'})
            );
        });
        $dailyWorkflowCalendarTitle.text(tr('dailyActivityCalendar'));
        $dailyWorkflowCalendarHelp.text(
            dailyWorkflowCalendarMode==='complete'
                ?tr('selectDailyCompleteDate')
                :tr('selectDailyReviewDate')
        );
        $dailyWorkflowCalendarNext.prop(
            'disabled',
            Boolean(todayMonth&&dailyWorkflowCalendarMonth>=todayMonth)
        );
    }

    /**
     * EN: Load Reviewed / Complete status for the currently visible calendar month.
     * 中文：加载当前日历月份的 Reviewed / Complete 状态。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function loadDailyWorkflowCalendarMonth(){
        if(!dailyStatusUrl||!dailyWorkflowCalendarSalesId||!dailyWorkflowCalendarMonth){
            return;
        }
        if(dailyWorkflowCalendarRequest&&dailyWorkflowCalendarRequest.readyState!==4){
            dailyWorkflowCalendarRequest.abort();
        }

        $dailyWorkflowCalendarGrid.html(
            '<div class="daily-workflow-calendar-loading">'+escapeHtml(tr('loading'))+'…</div>'
        );
        dailyWorkflowCalendarRequest=$.ajax({
            url:dailyStatusUrl,
            method:'GET',
            dataType:'json',
            cache:false,
            data:{
                sales_id:dailyWorkflowCalendarSalesId,
                month:dailyWorkflowCalendarMonth,
                _:Date.now()
            }
        })
        .done(function(data){
            if(!data||!data.ok){
                return;
            }
            dailyWorkflowCalendarDays=(
                data.days&&typeof data.days==='object'
            )?data.days:{};
            renderDailyWorkflowCalendar();
        })
        .fail(function(xhr,status){
            if(status==='abort'){
                return;
            }
            const data=xhr.responseJSON||{};
            $dailyWorkflowCalendarGrid.html(
                '<div class="daily-workflow-calendar-loading">'
                +escapeHtml(data.message||'Could not load daily activity.')
                +'</div>'
            );
        });
    }

    /**
     * EN: Open the shared history calendar from a Sales card action date.
     * 中文：从 Sales Card 的日期按钮打开共用历史日历。
     *
     * @param {*} $card Sales card. / Sales 卡片。
     * @param {string} mode review or complete. / review 或 complete 模式。
     * @param {string} date Selected ISO date. / 当前选择的 ISO 日期。
     * @returns {void} No value is returned. / 无返回值。
     */
    function openDailyWorkflowCalendar($card,mode,date){
        const salesId=parseInt($card.attr('data-sales-id'),10)||0;
        const selected=String(date||$card.attr('data-completion-date')||currentTo||currentDate||today||'');
        const month=dailyWorkflowMonthKey(selected)||dailyWorkflowMonthKey(today);
        if(!salesId||!month){
            return;
        }

        dailyWorkflowCalendarSalesId=salesId;
        dailyWorkflowCalendarMode=mode==='complete'?'complete':'review';
        dailyWorkflowCalendarSelectedDate=selected;
        dailyWorkflowCalendarMonth=month;
        dailyWorkflowCalendarDays={};
        $dailyWorkflowCalendarSales.text(
            String($card.attr('data-sales-name')||'Sales')
        );
        $dailyWorkflowCalendarBackdrop
            .removeClass('hidden')
            .attr('aria-hidden','false');
        renderDailyWorkflowCalendar();
        loadDailyWorkflowCalendarMonth();
    }

/**
 * EN: Update the update review progress segments behavior used by the application UI.
 * 中文：更新application UI 使用的“update review progress segments”行为。
 *
 * @param {*} $card $card value used by this function. / 本函数使用的“$card”参数值。
 * @param {*} postCount Post count value used by this function. / 本函数使用的“post count”参数值。
 * @param {*} periodTarget Period target value used by this function. / 本函数使用的“period target”参数值。
 * @param {*} goodCount Good count value used by this function. / 本函数使用的“good count”参数值。
 * @param {*} badCount Bad count value used by this function. / 本函数使用的“bad count”参数值。
 * @param {*} unreviewedCount Unreviewed count value used by this function. / 本函数使用的“unreviewed count”参数值。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function updateReviewProgressSegments(
    $card,
    postCount,
    periodTarget,
    goodCount,
    badCount,
    unreviewedCount
){
    postCount=Math.max(0,parseInt(postCount,10)||0);
    periodTarget=Math.max(1,parseInt(periodTarget,10)||1);
    goodCount=Math.max(0,parseInt(goodCount,10)||0);
    badCount=Math.max(0,parseInt(badCount,10)||0);
    unreviewedCount=Math.max(
        0,
        parseInt(unreviewedCount,10)||0
    );

    const denominator=Math.max(
        1,
        periodTarget,
        postCount
    );

    $card.find('[data-progress-good]').css(
        'width',
        ((goodCount/denominator)*100)+'%'
    );
    $card.find('[data-progress-bad]').css(
        'width',
        ((badCount/denominator)*100)+'%'
    );
    $card.find('[data-progress-unreviewed]').css(
        'width',
        ((unreviewedCount/denominator)*100)+'%'
    );
}

/**
 * EN: Update the sync expanded sales card from tiles behavior used by the application UI.
 * 中文：更新application UI 使用的“sync expanded sales card from tiles”行为。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function syncExpandedSalesCardFromTiles(){
    if(!expandedSalesId){
        return;
    }

    // Search-only expansion is a filtered view, not the Sales period total.
    // Never overwrite the Sales card's real Good/Bad/Unreviewed counts from it.
    if(currentExpandedData&&currentExpandedData.search_only){
        return;
    }

    const $card=$grid.find(
        '.sales-progress-card[data-sales-id="'
        +expandedSalesId
        +'"]'
    );

    if(!$card.length){
        return;
    }

    const $tiles=$expandedList.find('.sales-post-tile');
    const goodCount=$tiles.filter('.review-good').length;
    const badCount=$tiles.filter('.review-bad').length;
    const postCount=$tiles.length;
    const unreviewedCount=Math.max(
        0,
        postCount-goodCount-badCount
    );
    const periodTarget=Math.max(
        1,
        parseInt(
            $card.find('[data-progress-target]').text(),
            10
        )||1
    );

    $card
        .attr('data-good-count',goodCount)
        .attr('data-bad-count',badCount)
        .attr('data-unreviewed-count',unreviewedCount);

    $card.find('[data-good-count]').text(goodCount);
    $card.find('[data-bad-count]').text(badCount);
    $card.find('[data-unreviewed-count]').text(
        unreviewedCount
    );

    updateReviewProgressSegments(
        $card,
        postCount,
        periodTarget,
        goodCount,
        badCount,
        unreviewedCount
    );
}

    /**
     * EN: Update the update card behavior used by the application UI.
     * 中文：更新application UI 使用的“update card”行为。
     *
     * @param {*} $card $card value used by this function. / 本函数使用的“$card”参数值。
     * @param {Object|*} row Current record or row being rendered or processed. / 当前正在渲染或处理的记录/行。
     * @param {*} days Days value used by this function. / 本函数使用的“days”参数值。
     * @param {*} period Period value used by this function. / 本函数使用的“period”参数值。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function updateCard($card, row, days, period){
        const oldCount = parseInt(
            $card.attr('data-post-count'),
            10
        ) || 0;
        const count = parseInt(row.post_count, 10) || 0;
        const dailyTarget = Math.max(
            1,
            parseInt(row.daily_target, 10) || 10
        );
        const periodTarget = Math.max(
            1,
            parseInt(row.period_target, 10)
            || dailyTarget * Math.max(1, days)
        );
        const percent = Math.min(
            100,
            parseInt(row.percent, 10) || 0
        );
        const manualCompleted=Boolean(row.completed);
        const completionTargetMet=Boolean(row.completion_target_met);
        const effectiveComplete=manualCompleted||completionTargetMet;
        const actualPeriodTargetMet=Object.prototype.hasOwnProperty.call(
            row,
            'actual_target_met'
        )
            ?Boolean(row.actual_target_met)
            :Boolean(row.target_met)&&!manualCompleted;
        const met=Boolean(row.target_met);

        $card
            .attr('data-post-count', count)
            .attr('data-daily-target', dailyTarget)
            .attr('data-actual-target-met',actualPeriodTargetMet?'1':'0')
            .attr('data-completion-target-met',completionTargetMet?'1':'0')
            .attr('data-effective-complete',effectiveComplete?'1':'0')
            .toggleClass('target-met', met);

        if(Object.prototype.hasOwnProperty.call(row,'location_id')){
            const rowLocationId=Math.max(0,parseInt(row.location_id,10)||0);
            const rowLocationName=String(row.location_name||'');
            $card
                .attr('data-location-id',rowLocationId)
                .attr('data-location-name',rowLocationName);
            $card.find('[data-sales-location-label]').text(
                rowLocationName||tr('unassigned')
            );
        }

        animateNumber(
            $card.find('[data-progress-count]'),
            oldCount,
            count
        );

        $card.find('[data-progress-target]').text(periodTarget);
        $card.find('[data-daily-target-label]').text(dailyTarget);
        $card.find('[data-period-days]').text(days);
        $card.find('[data-target-input]').val(dailyTarget);

        const rowGoodCount=parseInt(row.good_count,10)||0;
        const rowBadCount=parseInt(row.bad_count,10)||0;
        const rowUnreviewedCount=parseInt(row.unreviewed_count,10)||0;

        $card
            .attr('data-good-count',rowGoodCount)
            .attr('data-bad-count',rowBadCount)
            .attr('data-unreviewed-count',rowUnreviewedCount);

        $card.find('[data-good-count]').text(rowGoodCount);
        $card.find('[data-bad-count]').text(rowBadCount);
        $card.find('[data-unreviewed-count]').text(
            rowUnreviewedCount
        );
        $card.find('[data-new-posts-count]').text(
            rowUnreviewedCount
        );
        $card.find('[data-new-posts-badge]')
            .toggleClass('hidden',rowUnreviewedCount<1)
            .attr(
                'title',
                rowUnreviewedCount>0
                    ?rowUnreviewedCount+' '+tr('unreviewed')
                    :''
            );

        const goodCount=rowGoodCount;
        const badCount=rowBadCount;
        const unreviewedCount=rowUnreviewedCount;

        updateReviewProgressSegments(
            $card,
            count,
            periodTarget,
            goodCount,
            badCount,
            unreviewedCount
        );

        $card
            .find('.sales-progress-track')
            .attr('aria-valuemax', periodTarget)
            .attr('aria-valuenow', count);

        $card
            .find('[data-target-badge]')
            .toggleClass('hidden', !met);

        const actionDate=String(
            row.completion_date
            ||currentTo
            ||currentDate
            ||today
            ||''
        );
        const $dailyReviewGroup=$card.find('[data-daily-review-group]');
        const $dailyCompleteGroup=$card.find('[data-daily-complete-group]');
        const $dailyComplete=$card.find('[data-daily-complete]');

        // EN: Daily Review is a one-day action. Mark as Complete always targets
        // the selected range's last day, so multi-day/date-range views stay actionable.
        // 中文：Daily Review 只属于单日；Mark as Complete 始终对应当前范围最后一天，
        // 因此 3-Day、Week、Month、Custom Range 都可以标记最后一天。
        $card
            .attr('data-completion-date',actionDate)
            // Manual completion is reversible until the real target is met.
            .attr('data-completed',manualCompleted?'1':'0')
            .attr('data-completion-target-met',completionTargetMet?'1':'0')
            .attr('data-effective-complete',effectiveComplete?'1':'0');
        // Daily Review stays available in every range. It opens a one-day
        // review for the range's last day, and the date inside the review editor
        // can be changed to any historical day.
        $dailyReviewGroup.removeClass('hidden');
        $dailyCompleteGroup.removeClass('hidden');
        $dailyComplete
            .toggleClass('is-completed',effectiveComplete)
            .prop('disabled',completionTargetMet)
            .attr('aria-disabled',completionTargetMet?'true':'false')
            .attr(
                'title',
                completionTargetMet
                    ?'Target met; cannot set Incomplete'
                    :''
            );
        $card.find('[data-card-daily-complete-label]').text(
            effectiveComplete?tr('complete'):tr('incomplete')
        );

        // Translate only after the new completion attributes are in place.
        translateSalesCard($card);

        $card.removeClass('period-updated');
        void $card.get(0).offsetWidth;
        $card.addClass('period-updated');

        setTimeout(function(){
            $card.removeClass('period-updated');
        }, 650);
    }

    /**
     * EN: Close or clear the close expanded posts behavior used by the application UI.
     * 中文：关闭或清理application UI 使用的“close expanded posts”行为。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function closeExpandedPosts(){
        expandedSalesId = 0;
        dailyReviewOnlyMode = false;

        $grid
            .find('.sales-progress-card.expanded')
            .removeClass('expanded')
            .attr('aria-expanded', 'false');

        $expanded.addClass('hidden');
        $expandedList.empty();
        $adminSalesActivity.addClass('hidden');
        $adminSalesChartBars.empty();
        currentExpandedData=null;
        adminExpandedChannel='all';
        $('#adminSalesPlatformFilter [data-admin-sales-platform]')
            .removeClass('active')
            .attr('aria-pressed','false')
            .filter('[data-admin-sales-platform="all"]')
            .addClass('active')
            .attr('aria-pressed','true');
        $expandedReview.addClass('hidden');
        currentSalesPeriodReview=null;
        $expandedLoading.addClass('hidden');

        if(expandedRequest && expandedRequest.readyState !== 4){
            expandedRequest.abort();
        }
    }

/**
 * EN: Perform the post date group label behavior used by the application UI.
 * 中文：执行application UI 使用的“post date group label”行为。
 *
 * @param {string|*} value Value read, transformed, or applied by this function. / 本函数读取、转换或应用的值。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function postDateGroupLabel(value){
    const raw=String(value||'').trim();
    const match=raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);

    if(!match){
        return raw;
    }

    const d=new Date(
        parseInt(match[1],10),
        parseInt(match[2],10)-1,
        parseInt(match[3],10),
        12,0,0
    );

    return d.toLocaleDateString(
        dashboardLocale(),
        {
            month:'short',
            day:'numeric',
            year:'numeric'
        }
    );
}

/**
 * EN: Perform the post date time label behavior used by the application UI.
 * 中文：执行application UI 使用的“post date time label”行为。
 *
 * @param {string|*} value Value read, transformed, or applied by this function. / 本函数读取、转换或应用的值。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function postDateTimeLabel(value){
    const raw=String(value||'').trim();
    const match=raw.match(
        /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/
    );

    if(!match){
        return raw;
    }

    const d=new Date(
        parseInt(match[1],10),
        parseInt(match[2],10)-1,
        parseInt(match[3],10),
        parseInt(match[4],10),
        parseInt(match[5],10)
    );

    return d.toLocaleString(
        dashboardLocale(),
        {
            month:'short',
            day:'numeric',
            hour:'numeric',
            minute:'2-digit'
        }
    ).replace(',',' ·');
}

/**
 * EN: Perform the post thumbnail html behavior used by the application UI.
 * 中文：执行application UI 使用的“post thumbnail html”行为。
 *
 * @param {*} post Post value used by this function. / 本函数使用的“post”参数值。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function postThumbnailHtml(post){
    const url=String(post.thumbnail_url||'').trim();

    if(url){
        return (
            '<img class="sales-post-card-image"'
            +' src="'+escapeHtml(url)+'"'
            +' loading="lazy"'
            +' alt="">'
        );
    }

    return (
        '<div class="sales-post-card-placeholder">'
            +platformLogoHtml(post.platform)
            +'<span>'+escapeHtml(post.platform)+'</span>'
        +'</div>'
    );
}

/**
 * EN: Perform the period review date label behavior used by the application UI.
 * 中文：执行application UI 使用的“period review date label”行为。
 *
 * @param {*} review Review value used by this function. / 本函数使用的“review”参数值。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function periodReviewDateLabel(review){
    if(!review){
        return '';
    }

    if(review.period==='day'){
        const parts=String(review.from||'').split('-');

        if(parts.length===3){
            const d=new Date(
                parseInt(parts[0],10),
                parseInt(parts[1],10)-1,
                parseInt(parts[2],10)
            );

            return d.toLocaleDateString(dashboardLocale(),{
                year:'numeric',
                month:'long',
                day:'numeric'
            });
        }
    }

    return String(review.period_label||'');
}

/**
 * EN: Update the set html note value behavior used by the application UI.
 * 中文：更新application UI 使用的“set html note value”行为。
 *
 * @param {*} $root $root value used by this function. / 本函数使用的“$root”参数值。
 * @param {string|*} html HTML content rendered or sanitized by this function. / 本函数渲染或清理的 HTML 内容。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function setHtmlNoteValue($root,html){
    if(!$root||!$root.length){
        return;
    }

    const value=String(html||'');

    $root.find('[data-html-source]').val(value);
    $root.find('[data-html-editor]').html(value);
    $root.find('[data-note-linkbar]').addClass('hidden');
    $root.find('[data-note-image-panel]').addClass('hidden');
    $root.find('[data-note-image-message]')
        .removeClass('error')
        .text('');

    $root
        .find('[data-note-mode="visual"]')
        .trigger('click');
}

/**
 * EN: Perform the sales rating stars behavior used by the application UI.
 * 中文：执行application UI 使用的“sales rating stars”行为。
 *
 * @param {*} rating Rating value used by this function. / 本函数使用的“rating”参数值。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function salesRatingStars(rating){
    rating=parseInt(rating,10)||0;
    return Array.from({length:5},function(_,index){return index<rating?'★':'☆';}).join('');
}

/**
 * EN: Update the set sales period rating behavior used by the application UI.
 * 中文：更新application UI 使用的“set sales period rating”行为。
 *
 * @param {*} rating Rating value used by this function. / 本函数使用的“rating”参数值。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function setSalesPeriodRating(rating){
    rating=parseInt(rating,10)||0;
    $periodReviewRating.val(rating>=1&&rating<=5?rating:'');
    $periodReviewStars.find('[data-rating-star]').each(function(){
        const value=parseInt($(this).data('rating-star'),10)||0;
        $(this).toggleClass('active',rating>=value).attr('aria-checked',rating===value?'true':'false');
    });
    $periodReviewRatingText.text(rating?salesRatingStars(rating)+' '+rating+'/5':'Not rated');
    $periodReviewRatingError.addClass('hidden');
    $('#salesPeriodReviewRatingField').removeClass('has-error');
}

/**
 * EN: Render the render person review attachments behavior used by the application UI.
 * 中文：渲染application UI 使用的“render person review attachments”行为。
 *
 * @param {Array} items Collection of items processed by this function. / 本函数处理的数据项集合。
 * @param {*} readOnly Read only value used by this function. / 本函数使用的“read only”参数值。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function renderPersonReviewAttachments(items,readOnly){
    items=(Array.isArray(items)?items:[]).filter(function(item){
        // v0.1.86 briefly used attachment tombstones for Person Reviews.
        // Person Review attachments now match Post Review attachments:
        // deletion is permanent, so tombstones are never rendered.
        return !item.deleted;
    });
    if(!items.length){
        return '';
    }
    return '<div class="review-comment-attachments">'
        +items.map(function(item){
            const image=String(item.mime||'').startsWith('image/');
            const meta=[
                item.uploaded_by_name?'Uploaded by '+item.uploaded_by_name:'Uploaded',
                item.uploaded_at?commentDateLabel(item.uploaded_at):''
            ].filter(Boolean).join(' · ');
            return '<div class="review-comment-attachment" data-person-attachment-id="'+escapeHtml(item.id)+'">'
                +'<div class="review-comment-attachment-media">'
                    +(image
                        ?'<button type="button" class="review-comment-image" data-comment-image="'+escapeHtml(item.url)+'" aria-label="Open image"><img loading="lazy" src="'+escapeHtml(item.url)+'" alt="'+escapeHtml(item.name||'Attachment')+'"></button>'
                        :'<a target="_blank" rel="noopener" href="'+escapeHtml(item.url)+'">'+escapeHtml(item.name||'Attachment')+'</a>')
                +'</div>'
                +'<div class="review-comment-attachment-audit"><span>'+escapeHtml(item.name||'Attachment')+'</span><small>'+escapeHtml(meta)+'</small></div>'
                +(!readOnly?'<button type="button" class="attachment-remove" data-person-attachment-delete="'+escapeHtml(item.id)+'" aria-label="Delete attachment permanently" title="Delete attachment permanently">×</button>':'')
            +'</div>';
        }).join('')
    +'</div>';
}

/**
 * EN: Render the render current person review attachments behavior used by the application UI.
 * 中文：渲染application UI 使用的“render current person review attachments”行为。
 *
 * @param {Array} items Collection of items processed by this function. / 本函数处理的数据项集合。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function renderCurrentPersonReviewAttachments(items){
    $periodReviewAttachments.html(renderPersonReviewAttachments(items,false));
}

/**
 * EN: Update the update person review file selection behavior used by the application UI.
 * 中文：更新application UI 使用的“update person review file selection”行为。
 *
 * @returns {string} String result produced by this UI helper. / 本 UI 辅助函数生成的字符串结果。
 */
function updatePersonReviewFileSelection(){
    const input=$periodReviewImages.get(0);
    const files=input?Array.from(input.files||[]):[];
    $periodReviewFileSelection.html(
        files.map(function(file){return '<span>'+escapeHtml(file.name)+'</span>';}).join('')
    );
}

/**
 * EN: Close or clear the reset sales review history delete arm behavior used by the application UI.
 * 中文：关闭或清理application UI 使用的“reset sales review history delete arm”行为。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function resetSalesReviewHistoryDeleteArm(){
    armedSalesReviewHistoryDeleteId=0;
    if(armedSalesReviewHistoryDeleteTimer){
        window.clearTimeout(armedSalesReviewHistoryDeleteTimer);
        armedSalesReviewHistoryDeleteTimer=null;
    }
    $periodReviewHistory.find('[data-person-review-history-delete]')
        .removeClass('confirm-delete')
        .attr('title','Mark review as deleted')
        .attr('aria-label','Mark review as deleted');
}

/**
 * EN: Update the update sales review history meta behavior used by the application UI.
 * 中文：更新application UI 使用的“update sales review history meta”行为。
 *
 * @param {Array} items Collection of items processed by this function. / 本函数处理的数据项集合。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function updateSalesReviewHistoryMeta(items){
    items=Array.isArray(items)?items:[];
    const deletedCount=items.filter(function(item){return Boolean(item.deleted);}).length;
    const activeCount=items.length-deletedCount;

    $periodReviewHistoryCount.text(activeCount+' '+tr('saves'));
    $periodReviewDeletedSwitch
        .toggleClass('hidden',deletedCount<1)
        .toggleClass('active',showDeletedSalesReviewHistory)
        .attr('aria-checked',showDeletedSalesReviewHistory?'true':'false');
    $periodReviewDeletedLabel.text(
        showDeletedSalesReviewHistory
            ?'Hide deleted reviews'
            :'See deleted reviews ('+deletedCount+')'
    );
}

/**
 * EN: Render the render sales review history behavior used by the application UI.
 * 中文：渲染application UI 使用的“render sales review history”行为。
 *
 * @param {Array} items Collection of items processed by this function. / 本函数处理的数据项集合。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function renderSalesReviewHistory(items){
    items=Array.isArray(items)?items:[];
    updateSalesReviewHistoryMeta(items);

    const visible=items.filter(function(item){
        return showDeletedSalesReviewHistory||!item.deleted;
    });

    if(!visible.length){
        $periodReviewHistory.html('<div class="sales-review-history-empty">'+escapeHtml(tr('notRated'))+'</div>');
        return;
    }

    $periodReviewHistory.html(visible.map(function(item){
        const rating=parseInt(item.rating,10)||0;
        const note=String(item.note||'').trim();
        const deleted=Boolean(item.deleted);
        const deletedAudit=deleted
            ?'<div class="sales-review-history-deleted-audit"><strong>Marked as deleted</strong>'
                +(item.deleted_by_name?' by '+escapeHtml(item.deleted_by_name):'')
                +(item.deleted_at?' · '+escapeHtml(commentDateLabel(item.deleted_at)):'')
            +'</div>'
            :'';
        return '<article class="sales-review-history-item'+(deleted?' is-deleted':'')+'" data-person-review-history-id="'+escapeHtml(item.id)+'">'
            +'<div class="sales-review-history-meta"><strong>'+escapeHtml(item.admin_name||'Administrator')+'</strong><div class="sales-review-history-meta-actions"><span>'+escapeHtml(commentDateLabel(item.created_at))+'</span>'
                +(!deleted?'<button type="button" class="sales-review-history-delete" data-person-review-history-delete="'+escapeHtml(item.id)+'" title="Mark review as deleted" aria-label="Mark review as deleted"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 4h8l1 2h4v2H3V6h4l1-2Zm1 6h2v7H9v-7Zm4 0h2v7h-2v-7ZM6 9h12l-1 11H7L6 9Z"/></svg></button>':'')
            +'</div></div>'
            +'<div class="sales-review-history-rating">'+(rating?escapeHtml(salesRatingStars(rating)+' '+rating+'/5'):escapeHtml(tr('notRated')))+'</div>'
            +(note?'<div class="sales-review-history-note">'+note+'</div>':'')
            +renderPersonReviewAttachments(item.attachments||[],deleted)
            +deletedAudit
            +'</article>';
    }).join(''));
}

$periodReviewDeletedSwitch.on('click',function(){
    showDeletedSalesReviewHistory=!showDeletedSalesReviewHistory;
    resetSalesReviewHistoryDeleteArm();
    renderSalesReviewHistory(
        currentSalesPeriodReview&&Array.isArray(currentSalesPeriodReview.history)
            ?currentSalesPeriodReview.history
            :[]
    );
});

$periodReviewHistory.on('click','[data-person-review-history-delete]',function(){
    const $button=$(this);
    const $row=$button.closest('.sales-review-history-item');
    const historyId=parseInt($button.attr('data-person-review-history-delete'),10)||0;
    if(!historyId||!salesReviewHistoryDeleteUrl||!currentSalesPeriodReview||!$row.length){
        return;
    }

    // The delete action is saved immediately. Keep the current history viewport
    // stable and animate only the affected row; do not rebuild the whole list.
    resetSalesReviewHistoryDeleteArm();
    $button.prop('disabled',true);

    const previousHistory=(currentSalesPeriodReview.history||[]).map(function(item){
        return Object.assign({},item);
    });
    const optimisticDeletedAt=new Date().toISOString();
    const historyScrollTop=$periodReviewHistory.scrollTop();
    const rowHeight=Math.max(1,Math.ceil($row.outerHeight()));

    currentSalesPeriodReview.history=previousHistory.map(function(item){
        if(parseInt(item.id,10)!==historyId){
            return Object.assign({},item);
        }
        return Object.assign({},item,{
            deleted:true,
            deleted_at:optimisticDeletedAt,
            deleted_by_name:item.deleted_by_name||'Administrator'
        });
    });
    updateSalesReviewHistoryMeta(currentSalesPeriodReview.history);

    // In the normal history view the row fades, then collapses smoothly. When
    // deleted reviews are already visible, keep the row in place and simply
    // refresh that audit view after the server confirms the delete.
    if(!showDeletedSalesReviewHistory){
        $row
            .stop(true,true)
            .css({height:rowHeight+'px',overflow:'hidden'})
            .addClass('is-person-review-deleting');
        window.requestAnimationFrame(function(){
            $row.addClass('is-person-review-deleting-active');
        });
    }else{
        $row.addClass('is-person-review-delete-pending');
    }

    $periodReviewMessage
        .removeClass('error')
        .text('Review marked as deleted. Saving…');

    $.ajax({
        url:salesReviewHistoryDeleteUrl,
        method:'POST',
        dataType:'json',
        data:{_csrf:csrf,history_id:historyId},
        headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}
    }).done(function(data){
        if(!data||!data.ok){
            currentSalesPeriodReview.history=previousHistory;
            renderSalesReviewHistory(previousHistory);
            $periodReviewHistory.scrollTop(historyScrollTop);
            $periodReviewMessage
                .addClass('error')
                .text((data&&data.message)||'Review history could not be marked as deleted.');
            return;
        }

        if(data.review){
            currentSalesPeriodReview=data.review;
            // Keep the current editor in sync with the server's surviving
            // Sales Review without rebuilding the history list.
            setSalesPeriodRating(data.review.rating||0);
            setHtmlNoteValue(
                $periodReviewModal.find('[data-html-note]').first(),
                data.review.note||''
            );
            renderCurrentPersonReviewAttachments(data.review.attachments||[]);
        }else{
            currentSalesPeriodReview.history=(currentSalesPeriodReview.history||[]).map(function(item){
                if(parseInt(item.id,10)!==historyId){
                    return item;
                }
                return Object.assign({},item,{
                    deleted:true,
                    deleted_at:data.deleted_at||item.deleted_at||optimisticDeletedAt,
                    deleted_by_name:data.deleted_by_name||item.deleted_by_name||'Administrator'
                });
            });
        }

        updateSalesReviewHistoryMeta(currentSalesPeriodReview.history||[]);

        if(showDeletedSalesReviewHistory){
            // Preserve the user's scroll position while converting the row to
            // its deleted-audit state.
            const preservedScroll=$periodReviewHistory.scrollTop();
            renderSalesReviewHistory(currentSalesPeriodReview.history||[]);
            $periodReviewHistory.scrollTop(preservedScroll);
        }else{
            // The CSS transition handles fade + collapse. Remove only after it
            // finishes so neighboring review cards never jump back and forth.
            window.setTimeout(function(){
                $row.remove();
                if(!$periodReviewHistory.children('.sales-review-history-item').length){
                    $periodReviewHistory.html('<div class="sales-review-history-empty">'+escapeHtml(tr('notRated'))+'</div>');
                }
            },300);
        }

        $periodReviewSave
            .prop('disabled',false)
            .removeClass('saved')
            .text('Save Review');
        $periodReviewMessage
            .removeClass('error')
            .text((data.message||'Sales Review history entry marked as deleted.')+' No Save Review is required.');
    }).fail(function(xhr){
        currentSalesPeriodReview.history=previousHistory;
        renderSalesReviewHistory(previousHistory);
        $periodReviewHistory.scrollTop(historyScrollTop);
        $periodReviewMessage
            .addClass('error')
            .text((xhr.responseJSON&&xhr.responseJSON.message)||'Review history could not be marked as deleted.');
    });
});

/**
 * EN: Render the render sales period review behavior used by the application UI.
 * 中文：渲染application UI 使用的“render sales period review”行为。
 *
 * @param {*} review Review value used by this function. / 本函数使用的“review”参数值。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function renderSalesPeriodReview(review){
    // v0.2.117: Daily Review is modal-only. Keep the loaded review object for
    // editor workflows, but never render a persistent review panel under the
    // activity chart / Post Grid.
    currentSalesPeriodReview=review||null;
    $expandedReview.addClass('hidden');
}

/**
 * EN: Open or show the open sales period review editor behavior used by the application UI.
 * 中文：打开或显示application UI 使用的“open sales period review editor”行为。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function openSalesPeriodReviewEditor(){
    const review=currentSalesPeriodReview;

    if(!review||!expandedSalesId){
        return;
    }

    const salesName=String(
        $grid
            .find(
                '.sales-progress-card[data-sales-id="'
                +expandedSalesId
                +'"]'
            )
            .attr('data-sales-name')
        ||'Sales'
    );

    $('#salesPeriodReviewSalesId').val(expandedSalesId);
    $('#salesPeriodReviewDate').val(review.from||currentDate);
    $('#salesPeriodReviewPeriod').val(review.period||'day');

    $('#salesPeriodReviewModalEyebrow').text(
        review.period==='week'
            ?tr('weeklyReview')
            :review.period==='month'
                ?tr('monthlyReview')
                :tr('dailyReview')
    );
    $('#salesPeriodReviewModalTitle').text(
        salesName+' · '+(
            review.period==='week'
                ?tr('weeklyReview')
                :review.period==='month'
                    ?tr('monthlyReview')
                    :tr('dailyReview')
        )
    );
    $('#salesPeriodReviewModalSubtitle').text(
        review.period_label||''
    );
    $('#salesPeriodReviewDateTrigger')
        .toggleClass('hidden',review.period!=='day')
        .attr('aria-label',review.period==='day'
            ?'Change Daily Review date: '+String(review.period_label||review.from||'')
            :'');

    showDeletedSalesReviewHistory=false;
    resetSalesReviewHistoryDeleteArm();
    setSalesPeriodRating(review.rating||0);
    renderSalesReviewHistory(review.history||[]);
    $periodReviewImages.val('');
    updatePersonReviewFileSelection();
    renderCurrentPersonReviewAttachments(review.attachments||[]);

    setHtmlNoteValue(
        $periodReviewModal.find('[data-html-note]').first(),
        review.note||''
    );

    $periodReviewMessage
        .removeClass('error')
        .text('');

    $periodReviewSave
        .prop('disabled',false)
        .removeClass('saved')
        .text(tr('saveReview'));

    $periodReviewModal
        .removeClass('hidden')
        .attr('aria-hidden','false');
}

/**
 * EN: Close or clear the close sales period review editor behavior used by the application UI.
 * 中文：关闭或清理application UI 使用的“close sales period review editor”行为。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function closeSalesPeriodReviewEditor(){
    $periodReviewModal
        .addClass('hidden')
        .attr('aria-hidden','true');

    $periodReviewMessage
        .removeClass('error')
        .text('');

    // A review opened directly from the card must leave no fake expanded-card
    // state behind. The next normal card click should open Posts on the first try.
    if(dailyReviewOnlyMode){
        dailyReviewOnlyMode=false;
        expandedSalesId=0;
        currentSalesPeriodReview=null;
        currentExpandedData=null;
    }
}

/**
 * EN: Render the render post grid behavior used by the application UI.
 * 中文：渲染application UI 使用的“render post grid”行为。
 *
 * @param {Object|*} data Structured data consumed by this function. / 本函数使用的结构化数据。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function renderPostGrid(data){
    const searchOnly=Boolean(data&&data.search_only);
    const allPosts=Array.isArray(data.posts)
        ?data.posts
        :[];
    const posts=adminExpandedChannel==='all'
        ?allPosts
        :allPosts.filter(function(post){
            return String(post.platform||'').toLowerCase()===adminExpandedChannel;
        });

    if(searchOnly){
        currentSalesPeriodReview=null;
        $expandedReview.addClass('hidden');
        $adminSalesActivity.addClass('hidden');
        currentExpandedData=data;
    }else{
        renderSalesPeriodReview(data.review||null);
        renderAdminSalesActivity(data);
    }

    $expandedTitle.text(
        data.sales.name
        +' · '
        +posts.length
        +' '
        +tr('postsLower')
    );

    $expandedSubtitle.text(
        searchOnly
            ?tr('postSearchMatches')+' · #'+data.sales.sales_id
            :data.period_label
                +' · #'
                +data.sales.sales_id
                +' · '
                +tr('chronological')
    );

    $expandedList.addClass(
        'admin-grouped-posts'
    );

    if(!posts.length){
        $expandedList.html(
            '<div class="sales-expanded-empty">'
            +escapeHtml(tr('noPostsPeriod'))
            +'</div>'
        );
        return;
    }

    const groups=[];
    const byDate={};

    posts.forEach(function(post){
        const published=String(
            post.published_date
            ||post.published_at
            ||''
        );
        const dateKey=(
            published.match(/^\d{4}-\d{2}-\d{2}/)
            ||['Unknown date']
        )[0];

        if(!byDate[dateKey]){
            byDate[dateKey]={
                date:dateKey,
                posts:[]
            };
            groups.push(byDate[dateKey]);
        }

        byDate[dateKey].posts.push(post);
    });

    const cardHtml=function(post){
        const status=String(
            post.status||''
        ).toLowerCase();

        const rowClass=
            status==='good'
                ?' review-good'
                :(
                    status==='bad'
                        ?' review-bad'
                        :''
                );

        const statusText=
            status==='good'
                ?tr('good')
                :(
                    status==='bad'
                        ?tr('issue')
                        :tr('unreviewed')
                );

        const title=String(post.title||'').trim()
            ||post.platform+' Marketplace post';

        const description=String(
            post.description||''
        ).trim();

        return (
            '<article class="sales-post-tile'
            +rowClass
            +'" data-post-id="'
            +escapeHtml(post.id)
            +'" data-review-status="'
            +escapeHtml(status)
            +'" data-status-source="history"'
            +'" role="button" tabindex="0"'
            +' aria-label="Review '
            +escapeHtml(title)
            +'">'+

                '<div class="sales-post-card-media">'
                    +postThumbnailHtml(post)+
                    '<span class="sales-post-card-platform">'
                        +platformLogoHtml(post.platform)
                    +'</span>'+
                '</div>'+

                '<div class="sales-post-card-body">'+
                    '<h3 title="'+escapeHtml(title)+'">'
                        +escapeHtml(title)
                    +'</h3>'+
                    '<p>'
                        +escapeHtml(
                            description
                            ||tr('noDescription')
                        )
                    +'</p>'+
                '</div>'+

                '<div class="sales-post-card-footer">'+
                    '<span class="sales-post-card-time">'
                        +escapeHtml(
                            postDateTimeLabel(
                                post.published_at
                            )
                        )
                    +'</span>'+
                    '<span class="sales-post-tile-status '
                        +escapeHtml(status)
                        +'">'
                        +escapeHtml(statusText)
                    +'</span>'+
                '</div>'+
            '</article>'
        );
    };

    const html=groups.map(function(group){
        const count=group.posts.length;

        return (
            '<section class="sales-expanded-date-group"'
                +' data-expanded-date="'
                +escapeHtml(group.date)
                +'">'
                +'<div class="sales-expanded-date-head">'
                    +'<strong>'
                        +escapeHtml(
                            postDateGroupLabel(group.date)
                        )
                    +'</strong>'
                    +'<span>'
                        +count
                        +' '
                        +escapeHtml(tr('postsLower'))
                    +'</span>'
                +'</div>'
                +'<div class="sales-expanded-date-grid">'
                    +group.posts.map(cardHtml).join('')
                +'</div>'
            +'</section>'
        );
    }).join('');

    $expandedList.html(html);
}
    /**
     * EN: Place the expanded Sales post panel directly below the visual grid row
     * that contains the selected Sales card instead of leaving the panel at the
     * bottom of the entire Sales grid.
     * 中文：将展开的 Sales Post 面板放到所选 Sales 卡片所在的可视行正下方，
     * 而不是固定显示在整个 Sales Grid 的最底部。
     *
     * @param {*} $card Selected Sales progress card. / 当前选中的 Sales 进度卡片。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function placeExpandedAfterCardRow($card){
        const card=$card&&$card.get?$card.get(0):null;

        if(!card||!$grid.length||!$expanded.length){
            return;
        }

        // Detach first so a previous expanded position cannot affect Grid layout
        // while we detect which cards are in the same visual row.
        // 先临时移出展开面板，避免旧位置影响当前 Grid 行位置判断。
        $expanded.addClass('hidden').detach();

        const selectedTop=Math.round(
            card.getBoundingClientRect().top
        );
        let rowEnd=card;

        $grid.find('.sales-progress-card').each(function(){
            const $candidate=$(this);

            if($candidate.hasClass('sales-directory-hidden')){
                return;
            }

            const top=Math.round(
                this.getBoundingClientRect().top
            );

            if(Math.abs(top-selectedTop)<=3){
                rowEnd=this;
            }
        });

        $(rowEnd).after($expanded);
    }

    /**
     * EN: Load only the one-day Sales Review for a card. This intentionally does
     * not expand/render the employee Post Grid, so the review button behaves as
     * an isolated modal action.
     * 中文：仅加载单日 Sales Review；不会展开或渲染员工 Post Grid，避免点击
     * Daily Review 时同时打开 Post 详细区域。
     *
     * @param {*} $card Selected Sales progress card. / 当前 Sales 进度卡片。
     * @returns {void} No value is returned. / 无返回值。
     */
    function openDailyReviewOnly($card,requestedDate){
        const salesId=parseInt($card.attr('data-sales-id'),10)||0;
        const reviewDate=String(
            requestedDate
            ||$card.attr('data-completion-date')
            ||currentTo
            ||currentDate
            ||today
            ||''
        );

        if(!salesId){
            return;
        }

        // Daily Review is a separate action. Any previously expanded Post Grid is
        // closed first, but no new Post Grid is opened for this review request.
        closeExpandedPosts();
        dailyReviewOnlyMode=true;
        expandedSalesId=salesId;
        currentExpandedData=null;
        currentSalesPeriodReview=null;
        setTargetMessage($card,'',false);

        const $button=$card.find('[data-daily-review]').first();
        $button.prop('disabled',true).attr('aria-busy','true');

        expandedRequest=$.ajax({
            url:salesPostsUrl,
            method:'GET',
            dataType:'json',
            cache:false,
            data:{
                sales_id:salesId,
                date:reviewDate,
                period:'day',
                preset:'single',
                _:Date.now()
            }
        })
        .done(function(data){
            if(
                !dailyReviewOnlyMode
                ||expandedSalesId!==salesId
            ){
                return;
            }

            if(!data||!data.ok||!data.review){
                setTargetMessage(
                    $card,
                    (data&&data.message)||'Could not load Sales Review.',
                    true
                );
                dailyReviewOnlyMode=false;
                expandedSalesId=0;
                return;
            }

            currentSalesPeriodReview=data.review;
            openSalesPeriodReviewEditor();
        })
        .fail(function(xhr,status){
            if(status==='abort'){
                return;
            }

            const data=xhr.responseJSON||{};
            setTargetMessage(
                $card,
                data.message||'Could not load Sales Review.',
                true
            );
            dailyReviewOnlyMode=false;
            expandedSalesId=0;
        })
        .always(function(){
            $button.prop('disabled',false).removeAttr('aria-busy');
        });
    }

    /**
     * EN: Open or show the open expanded posts behavior used by the application UI.
     * 中文：打开或显示application UI 使用的“open expanded posts”行为。
     *
     * @param {*} $card $card value used by this function. / 本函数使用的“$card”参数值。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function openExpandedPosts($card){
        // v0.2.96 — Search/location filters narrow the Sales directory only;
        // they must not disable View Posts for a Sales card that remains visible.
        if($card.hasClass('sales-directory-hidden')){
            return;
        }

        const salesId = parseInt(
            $card.attr('data-sales-id'),
            10
        ) || 0;

        if(!salesId){
            return;
        }

        const activePostSearchQuery=String($salesDirectorySearch.val()||'').trim();
        if(activePostSearchQuery&&salesPostSearchMatches.length){
            const matchedRows=salesPostSearchMatches.filter(function(row){
                return (parseInt(row.sales_user_id,10)||0)===salesId;
            });
            if(matchedRows.length){
                openSalesPostSearchMatches($card,matchedRows);
                return;
            }
        }

        if(expandedSalesId === salesId){
            closeExpandedPosts();
            return;
        }

        if(expandedRequest && expandedRequest.readyState !== 4){
            expandedRequest.abort();
        }

        expandedSalesId = salesId;

        $grid
            .find('.sales-progress-card')
            .removeClass('expanded')
            .attr('aria-expanded', 'false');

        $card
            .addClass('expanded')
            .attr('aria-expanded', 'true');

        placeExpandedAfterCardRow($card);
        $expanded.removeClass('hidden');
        $expandedTitle.text(
            String($card.attr('data-sales-name') || 'Sales')
            + ' · '+tr('loading')
        );
        $expandedSubtitle.text(
            (currentPreset==='single'?tr('oneDay'):currentPreset==='day'?tr('threeDays'):currentPreset==='week'?tr('weekly'):currentPreset==='month'?tr('monthly'):tr('range')) + ' · ' + tr('posts')
        );
        $expandedList.empty();
        $adminSalesActivity.addClass('hidden');
        currentExpandedData=null;
        adminExpandedChannel='all';
        $('#adminSalesPlatformFilter [data-admin-sales-platform]')
            .removeClass('active')
            .attr('aria-pressed','false')
            .filter('[data-admin-sales-platform="all"]')
            .addClass('active')
            .attr('aria-pressed','true');
        $expandedReview.addClass('hidden');
        currentSalesPeriodReview=null;
        $expandedLoading.removeClass('hidden');

        expandedRequest=$.ajax({
            url:salesPostsUrl,
            method:'GET',
            dataType:'json',
            cache:false,
            data:adminAjaxRangeData({
                sales_id:salesId,
                _:Date.now()
            })
        })
        .done(function(data){
            if(
                data
                &&data.ok
                &&expandedSalesId===salesId
            ){
                currentExpandedData=data;
                renderPostGrid(data);

                if(openReviewAfterExpand){
                    openReviewAfterExpand=false;
                    setTimeout(function(){
                        openSalesPeriodReviewEditor();
                    },0);
                }
            }
        })
        .fail(function(xhr, status){
            if(status === 'abort' || expandedSalesId !== salesId){
                return;
            }

            const data = xhr.responseJSON || {};

            $expandedList.html(
                '<div class="sales-expanded-error">'+
                    escapeHtml(
                        data.message || tr('couldNotLoadPosts')
                    )+
                '</div>'
            );
        })
        .always(function(){
            if(expandedSalesId === salesId){
                $expandedLoading.addClass('hidden');
            }
        });
    }

    /**
     * EN: Update the apply progress behavior used by the application UI.
     * 中文：更新application UI 使用的“apply progress”行为。
     *
     * @param {Object|*} data Structured data consumed by this function. / 本函数使用的结构化数据。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function applyProgress(data){
        currentPeriod=data.period||'day';
        currentPreset=String(data.preset||currentPreset||(currentPeriod==='day'?'single':'custom'));
        currentFrom=String(data.from||currentFrom||currentDate);
        currentTo=String(data.to||currentTo||currentDate);
        currentDate=String(data.date||currentTo||currentDate);
        currentPeriodDays=parseInt(data.days,10)||1;
        baselineCount=parseInt(data.post_count,10)||0;
        baselineMaxId = parseInt(data.max_post_id, 10) || 0;
        noticeShown = false;
        $notice.addClass('hidden');

        $live
            .attr('data-date',currentDate)
            .attr('data-from',currentFrom)
            .attr('data-to',currentTo)
            .attr('data-period',currentPeriod)
            .attr('data-period-days', currentPeriodDays)
            .attr('data-post-count', baselineCount)
            .attr('data-max-post-id', baselineMaxId);

        syncAdminRangeInputs();
        updatePeriodButtons(currentPreset);
        updateBackToday();
        updateHistory();

        $('#dashboardProgressSubtitle')
            .attr(
                'data-period-target-label',
                data.period_short_label||tr('periodTarget')
            );

        $('#dashboardProgressTitle').text(
            tr('postingProgress',{
                period:currentPreset==='single'
                    ?tr('oneDay')
                    :currentPreset==='day'
                        ?tr('threeDays')
                        :currentPreset==='week'
                            ?tr('weekly')
                            :currentPreset==='month'
                                ?tr('monthly')
                                :tr('range')
            })
        );
        $('#dashboardProgressSubtitle').text(
            tr('targetFormula',{
                days:currentPeriodDays,
                target:data.period_short_label||tr('periodTarget')
            })
        );
        $('#dashboardPostCount').text(baselineCount);
        applyDashboardLanguage();

        const rows = Array.isArray(data.rows) ? data.rows : [];
        const byId = {};

        rows.forEach(function(row){
            byId[String(row.sales_user_id)] = row;
        });

        $grid.find('.sales-progress-card').each(function(){
            const $card = $(this);
            const id = String($card.data('sales-id'));

            if(byId[id]){
                updateCard(
                    $card,
                    byId[id],
                    currentPeriodDays,
                    currentPeriod
                );
            }
        });
        applySalesDirectoryFilters();
    }

    /**
     * EN: Read or load the load progress behavior used by the application UI.
     * 中文：读取或加载application UI 使用的“load progress”行为。
     *
     * @param {Object|*} options Optional settings that control this function. / 控制本函数行为的可选设置。
     *
     * @returns {boolean} Boolean result produced by this UI helper. / 本 UI 辅助函数生成的布尔结果。
     */
    function loadProgress(options){
        options=options||{};
        const initial=!!options.initial;
        let requestData={};

        if(options.from&&options.to){
            requestData={
                from:String(options.from),
                to:String(options.to),
                period:'range',
                preset:String(options.preset||'custom')
            };
        }else{
            requestData={
                date:String(options.date||currentDate),
                period:String(options.period||currentPeriod),
                preset:String(options.preset||currentPreset||'single')
            };
        }

        closeExpandedPosts();

        if(periodRequest&&periodRequest.readyState!==4){
            periodRequest.abort();
        }

        $('#dashboardPeriodSwitch [data-admin-preset]').prop('disabled',true);
        $('body').addClass('dashboard-ajax-loading');
        $grid.addClass(initial?'dashboard-date-syncing':'period-loading');

        periodRequest=$.ajax({
            url:progressUrl,
            method:'GET',
            dataType:'json',
            cache:false,
            data:Object.assign({},requestData,{_:Date.now()})
        })
        .done(function(data){
            if(data&&data.ok){
                applyProgress(data);
            }
        })
        .always(function(){
            $('body').removeClass('dashboard-ajax-loading');
            $grid.removeClass('dashboard-date-syncing period-loading');
            $('#dashboardPeriodSwitch [data-admin-preset]').prop('disabled',false);
        });

        return periodRequest;
    }

    /**
     * EN: Perform the reload current progress behavior used by the application UI.
     * 中文：执行application UI 使用的“reload current progress”行为。
     *
     * @param {Object|*} options Optional settings that control this function. / 控制本函数行为的可选设置。
     *
     * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
     */
    function reloadCurrentProgress(options){
        options=Object.assign({},options||{});
        options.preset=currentPreset;
        if(currentPeriod==='range'){
            options.from=currentFrom;
            options.to=currentTo;
        }else{
            options.date=currentDate;
            options.period=currentPeriod;
        }
        return loadProgress(options);
    }

$('#appLanguageSwitch').on(
    'click',
    '[data-app-lang]',
    function(){
        const lang=String(
            $(this).data('app-lang')||'en'
        );

        if(!dashboardI18n[lang]){
            return;
        }

        dashboardLanguage=lang;
        localStorage.setItem(
            'cdsp-admin-language',
            dashboardLanguage
        );

        applyDashboardLanguage();

        if(currentSalesPeriodReview){
            renderSalesPeriodReview(
                currentSalesPeriodReview
            );
        }

        if(expandedSalesId){
            const activeSalesId=expandedSalesId;
            const $card=$grid.find(
                '.sales-progress-card[data-sales-id="'
                +activeSalesId
                +'"]'
            );

            if($card.length){
                expandedSalesId=0;
                openExpandedPosts($card);
            }
        }
    }
);

    // v0.2.120: Settings/header language changes must immediately translate
    // the Dashboard range controls as well, including preset buttons and From/To.
    $(document).on('cdsp:language-changed.cdspAdminDashboard',function(_event,lang){
        lang=String(lang||currentAppLanguage()||'en');
        if(!dashboardI18n[lang]){
            lang='en';
        }
        dashboardLanguage=lang;
        applyDashboardLanguage();
    });

    $('#dashboardPeriodSwitch').on(
        'click',
        '[data-admin-preset]',
        function(){
            const preset=String($(this).attr('data-admin-preset')||'single');
            const anchor=String($('#dashboardToInput').val()||today||currentTo);

            if(preset==='custom'){
                applyAdminRangeChange('');
                return;
            }

            const range=adminPresetRange(preset,anchor);
            if(!range)return;

            $('#dashboardFromInput').val(range.from);
            $('#dashboardToInput').val(range.to);
            currentPreset=preset;

            if(preset==='single'){
                loadProgress({date:range.to,period:'day',preset:'single'});
            }else{
                loadProgress({from:range.from,to:range.to,preset:preset});
            }
        }
    );

    /**
     * EN: Update the apply admin range change behavior used by the application UI.
     * 中文：更新application UI 使用的“apply admin range change”行为。
     *
     * @param {*} changed Changed value used by this function. / 本函数使用的“changed”参数值。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function applyAdminRangeChange(changed){
        const $from=$('#dashboardFromInput');
        const $to=$('#dashboardToInput');

        let from=String($from.val()||'');
        let to=String($to.val()||'');

        if(
            !/^\d{4}-\d{2}-\d{2}$/.test(from)
            ||!/^\d{4}-\d{2}-\d{2}$/.test(to)
        ){
            return;
        }

        if(today&&to>today){
            to=today;
            $to.val(to);
        }

        if(today&&from>today){
            from=today;
            $from.val(from);
        }

        /*
         * v0.2.120 — To is a movable historical anchor.
         * EN: When To is picked inside the currently loaded range, keep From
         *     where it is and turn the selection into Custom. When To is picked
         *     outside the current range, keep the active preset and move the
         *     whole preset window so it ends on the newly selected To date.
         *     From intentionally keeps the legacy behavior: editing it always
         *     creates a Custom range.
         * 中文：To 是可以自由选择历史日期的锚点。若新 To 落在当前范围内，
         *     保持 From 不动并切换为 Custom；若新 To 落在当前范围外，则保留
         *     当前 1 Day / 3 Days / Weekly / Monthly 预设，并把整个范围平移到
         *     新 To。From 保持原逻辑：手动修改 From 一律进入 Custom。
         */
        if(changed==='to'){
            const loadedFrom=String(currentFrom||from);
            const loadedTo=String(currentTo||to);
            const preset=String(currentPreset||'custom');
            const insideLoadedRange=(
                /^\d{4}-\d{2}-\d{2}$/.test(loadedFrom)
                &&/^\d{4}-\d{2}-\d{2}$/.test(loadedTo)
                &&to>=loadedFrom
                &&to<=loadedTo
            );

            if(insideLoadedRange){
                from=loadedFrom;
                $from.val(from);
                currentPreset='custom';
                $from.attr('max',to);
                $to.removeAttr('min').attr('max',today||'');
                loadProgress({from:from,to:to,preset:'custom'});
                return;
            }

            if(preset!=='custom'){
                const shifted=adminPresetRange(preset,to);
                if(!shifted){
                    return;
                }
                $from.val(shifted.from).attr('max',shifted.to);
                $to.val(shifted.to).removeAttr('min').attr('max',today||'');
                currentPreset=preset;

                if(preset==='single'){
                    loadProgress({date:shifted.to,period:'day',preset:'single'});
                }else{
                    loadProgress({from:shifted.from,to:shifted.to,preset:preset});
                }
                return;
            }

            // Custom has no fixed named length. Preserve its current day span
            // when To moves outside the loaded Custom range.
            const oldFrom=dailyWorkflowParseDate(loadedFrom);
            const oldTo=dailyWorkflowParseDate(loadedTo);
            const newTo=dailyWorkflowParseDate(to);
            if(oldFrom&&oldTo&&newTo){
                const spanDays=Math.max(0,Math.round((oldTo-oldFrom)/86400000));
                const newFrom=new Date(newTo);
                newFrom.setDate(newFrom.getDate()-spanDays);
                const iso=function(date){
                    return date.getFullYear()+'-'+String(date.getMonth()+1).padStart(2,'0')+'-'+String(date.getDate()).padStart(2,'0');
                };
                from=iso(newFrom);
                $from.val(from);
            }else if(to<from){
                from=to;
                $from.val(from);
            }

            currentPreset='custom';
            $from.attr('max',to);
            $to.removeAttr('min').attr('max',today||'');
            loadProgress({from:from,to:to,preset:'custom'});
            return;
        }

        // Existing From behavior: editing From always creates Custom.
        if(changed==='from'&&from>to){
            to=from;
            $to.val(to);
        }else if(from>to){
            from=to;
            $from.val(from);
        }

        $from.attr('max',to);
        $to.removeAttr('min').attr('max',today||'');

        currentPreset='custom';
        loadProgress({
            from:from,
            to:to,
            preset:'custom'
        });
    }

    $('#dashboardDateForm').on('submit',function(event){event.preventDefault();});
    $('#dashboardFromInput').on('change',function(){applyAdminRangeChange('from');});
    $('#dashboardToInput').on('change',function(){applyAdminRangeChange('to');});

    $('#dashboardBackToday').on('click',function(){
        if(!today)return;
        currentFrom=today;
        currentTo=today;
        currentDate=today;
        loadProgress({date:today,period:'day',preset:'single'});
    });

    $('#dashboardStickyPeriodSwitch').on(
        'click',
        '[data-admin-sticky-preset]',
        function(){
            const preset=String($(this).attr('data-admin-sticky-preset')||'single');
            $('#dashboardPeriodSwitch [data-admin-preset="'+preset+'"]').trigger('click');
        }
    );

    $adminStickyFrom.on('change',function(){
        $('#dashboardFromInput').val(String($(this).val()||''));
        applyAdminRangeChange('from');
    });

    $adminStickyTo.on('change',function(){
        $('#dashboardToInput').val(String($(this).val()||''));
        applyAdminRangeChange('to');
    });

    $adminStickyBackToday.on('click',function(){
        $('#dashboardBackToday').trigger('click');
    });

    $grid.on('click','[data-daily-complete]',function(event){
        event.preventDefault();
        event.stopImmediatePropagation();

        const $button=$(this);
        const $card=$button.closest('.sales-progress-card');
        const salesId=parseInt($card.attr('data-sales-id'),10)||0;
        const date=String(
            $card.attr('data-completion-date')
            ||currentTo
            ||currentDate
            ||today
            ||''
        );

        if(!dailyCompleteUrl||!salesId||!date||$button.attr('aria-busy')==='true'){
            return;
        }

        // EN: A day that objectively met its real Daily Target is locked Complete.
        // 中文：真实达到当日 Daily Target 的日期锁定为 Complete，不能改回 Incomplete。
        if($card.attr('data-completion-target-met')==='1'){
            return;
        }

        const nextCompleted=$card.attr('data-completed')!=='1';
        $button.prop('disabled',true).attr('aria-busy','true');
        setTargetMessage($card,'',false);

        $.ajax({
            url:dailyCompleteUrl,
            method:'POST',
            dataType:'json',
            data:{
                _csrf:csrf,
                sales_user_id:salesId,
                date:date,
                completed:nextCompleted?'1':'0'
            }
        })
        .done(function(data){
            if(!data||!data.ok){
                $button.prop('disabled',false).removeAttr('aria-busy');
                setTargetMessage(
                    $card,
                    (data&&data.message)||'Could not mark the daily task complete.',
                    true
                );
                return;
            }

            const manualCompleted=Boolean(data.completed);
            const targetLocked=Boolean(data.actual_target_met||data.locked);
            const effectiveComplete=Object.prototype.hasOwnProperty.call(
                data,
                'effective_completed'
            )
                ?Boolean(data.effective_completed)
                :(manualCompleted||targetLocked);

            if(targetLocked&&currentFrom===date&&currentTo===date){
                $card.attr('data-actual-target-met','1');
            }
            const actualPeriodTargetMet=$card.attr('data-actual-target-met')==='1';
            const targetMet=actualPeriodTargetMet||manualCompleted;

            $card
                .attr('data-completed',manualCompleted?'1':'0')
                .attr('data-completion-target-met',targetLocked?'1':'0')
                .attr('data-effective-complete',effectiveComplete?'1':'0')
                .toggleClass('target-met',targetMet);
            $card.find('[data-target-badge]').toggleClass('hidden',!targetMet);

            $button
                .toggleClass('is-completed',effectiveComplete)
                .prop('disabled',targetLocked)
                .attr('aria-disabled',targetLocked?'true':'false')
                .attr(
                    'title',
                    targetLocked
                        ?'Target met; cannot set Incomplete'
                        :''
                )
                .removeAttr('aria-busy');
            $card.find('[data-card-daily-complete-label]').text(
                effectiveComplete?tr('complete'):tr('incomplete')
            );
            setTargetMessage(
                $card,
                data.message||(effectiveComplete?tr('complete'):tr('incomplete')),
                false
            );

            if(dailyWorkflowMonthKey(date)===dailyWorkflowCalendarMonth){
                if(!dailyWorkflowCalendarDays[date]){
                    dailyWorkflowCalendarDays[date]={
                        reviewed:false,
                        completed:false
                    };
                }
                dailyWorkflowCalendarDays[date].completed=effectiveComplete;
            }
        })
        .fail(function(xhr){
            const data=xhr.responseJSON||{};
            const targetLocked=Boolean(data.actual_target_met||data.locked);

            if(targetLocked){
                if(currentFrom===date&&currentTo===date){
                    $card.attr('data-actual-target-met','1');
                }
                const targetMet=
                    $card.attr('data-actual-target-met')==='1'
                    ||$card.attr('data-completed')==='1';
                $card
                    .attr('data-completion-target-met','1')
                    .attr('data-effective-complete','1')
                    .toggleClass('target-met',targetMet);
                $card.find('[data-target-badge]').toggleClass('hidden',!targetMet);
                $button
                    .addClass('is-completed')
                    .prop('disabled',true)
                    .attr('aria-disabled','true')
                    .attr('title','Target met; cannot set Incomplete')
                    .removeAttr('aria-busy');
                $card.find('[data-card-daily-complete-label]').text(tr('complete'));
                setTargetMessage(
                    $card,
                    data.message||'Target met; this day is Complete.',
                    false
                );
                return;
            }

            $button.prop('disabled',false).removeAttr('aria-busy');
            setTargetMessage(
                $card,
                data.message||'Could not update the daily completion status.',
                true
            );
        });
    });

    $grid.on('click','[data-daily-review]',function(event){
        event.preventDefault();
        // This button owns the click completely. Do not allow the Sales card or
        // any other delegated click handler to treat it as a Post/Grid click.
        event.stopImmediatePropagation();
        const $card=$(this).closest('.sales-progress-card');
        openDailyReviewOnly(
            $card,
            String($card.attr('data-completion-date')||currentTo||currentDate||'')
        );
    });

    $('#salesPeriodReviewDateTrigger').on('click',function(event){
        event.preventDefault();
        if(!currentSalesPeriodReview||currentSalesPeriodReview.period!=='day'||!expandedSalesId){
            return;
        }
        const $card=$grid.find(
            '.sales-progress-card[data-sales-id="'+expandedSalesId+'"]'
        ).first();
        if(!$card.length){
            return;
        }
        openDailyWorkflowCalendar(
            $card,
            'review',
            String(currentSalesPeriodReview.from||currentDate||today||'')
        );
    });

    $('#dailyWorkflowCalendarClose').on('click',function(){
        closeDailyWorkflowCalendar();
    });

    $dailyWorkflowCalendarBackdrop.on('click',function(event){
        if(event.target===this){
            closeDailyWorkflowCalendar();
        }
    });

    $dailyWorkflowCalendarPrev.on('click',function(){
        const month=dailyWorkflowShiftMonth(dailyWorkflowCalendarMonth,-1);
        if(!month){
            return;
        }
        dailyWorkflowCalendarMonth=month;
        dailyWorkflowCalendarDays={};
        renderDailyWorkflowCalendar();
        loadDailyWorkflowCalendarMonth();
    });

    $dailyWorkflowCalendarNext.on('click',function(){
        if($(this).prop('disabled')){
            return;
        }
        const month=dailyWorkflowShiftMonth(dailyWorkflowCalendarMonth,1);
        if(!month){
            return;
        }
        dailyWorkflowCalendarMonth=month;
        dailyWorkflowCalendarDays={};
        renderDailyWorkflowCalendar();
        loadDailyWorkflowCalendarMonth();
    });

    $dailyWorkflowCalendarGrid.on('click','[data-daily-workflow-date]',function(){
        const date=String($(this).attr('data-daily-workflow-date')||'');
        const salesId=dailyWorkflowCalendarSalesId;
        const mode=dailyWorkflowCalendarMode;
        if(!date||!salesId||(today&&date>today)){
            return;
        }

        dailyWorkflowCalendarSelectedDate=date;
        closeDailyWorkflowCalendar();

        // If the calendar was opened from the Daily Review editor, hide the
        // current editor while the requested day is loading. loadProgress()
        // will clear stale card/review state before reopening the selected date.
        if(mode==='review'&&!$periodReviewModal.hasClass('hidden')){
            $periodReviewModal
                .addClass('hidden')
                .attr('aria-hidden','true');
        }

        // EN: Calendar selection changes the dashboard itself to 1-Day first;
        // the requested action is performed only after that day's card is current.
        // 中文：从日历选日期时，先把 Dashboard 切到该日的 1-Day；
        // 等当天卡片加载完成以后才进入对应 Review / Complete 操作。
        loadProgress({
            date:date,
            period:'day',
            preset:'single'
        }).done(function(data){
            if(!data||!data.ok){
                return;
            }
            const $card=$grid.find(
                '.sales-progress-card[data-sales-id="'+salesId+'"]'
            ).first();
            if(!$card.length){
                return;
            }
            const card=$card.get(0);
            if(card&&typeof card.scrollIntoView==='function'){
                card.scrollIntoView({behavior:'smooth',block:'center'});
            }
            if(mode==='review'){
                openDailyReviewOnly($card,date);
            }else{
                $card.find('[data-daily-complete]').first().trigger('focus');
            }
        });
    });

    $(document).on('keydown.dailyWorkflowCalendar',function(event){
        if(event.key==='Escape'&&!$dailyWorkflowCalendarBackdrop.hasClass('hidden')){
            closeDailyWorkflowCalendar();
        }
    });

    $grid.on('click', '[data-card-toggle]', function(event){
        if(
            $(event.target).closest(
                '[data-card-control],a,button,input,select,textarea'
            ).length
        ){
            return;
        }

        openExpandedPosts($(this));
    });

    $grid.on('keydown', '[data-card-toggle]', function(event){
        if(event.key !== 'Enter' && event.key !== ' '){
            return;
        }

        if(
            $(event.target).closest(
                '[data-card-control],input,button,a'
            ).length
        ){
            return;
        }

        event.preventDefault();
        openExpandedPosts($(this));
    });

    $('#salesExpandedClose').on('click', function(){
        closeExpandedPosts();
    });

    $('#adminSalesPlatformFilter').on(
        'click',
        '[data-admin-sales-platform]',
        function(event){
            event.preventDefault();
            event.stopPropagation();
            if(!currentExpandedData){return;}

            adminExpandedChannel=String(
                $(this).attr('data-admin-sales-platform')||'all'
            ).toLowerCase();

            $('#adminSalesPlatformFilter [data-admin-sales-platform]')
                .each(function(){
                    const active=String(
                        $(this).attr('data-admin-sales-platform')||''
                    ).toLowerCase()===adminExpandedChannel;
                    $(this)
                        .toggleClass('active',active)
                        .attr('aria-pressed',active?'true':'false');
                });

            renderPostGrid(currentExpandedData);
        }
    );

    let adminSalesChartResizeTimer=null;
    $(window).on('resize',function(){
        if(!currentExpandedData||$adminSalesActivity.hasClass('hidden')){
            return;
        }
        if(adminSalesChartResizeTimer){
            window.clearTimeout(adminSalesChartResizeTimer);
        }
        adminSalesChartResizeTimer=window.setTimeout(function(){
            renderAdminSalesActivity(currentExpandedData);
        },120);
    });

$periodReviewStars.on('click','[data-rating-star]',function(){
    setSalesPeriodRating(parseInt($(this).data('rating-star'),10)||0);
});

$periodReviewStars.on('mouseenter','[data-rating-star]',function(){
    const hover=parseInt($(this).data('rating-star'),10)||0;
    $periodReviewStars.find('[data-rating-star]').each(function(){
        $(this).toggleClass('hover',(parseInt($(this).data('rating-star'),10)||0)<=hover);
    });
}).on('mouseleave',function(){
    $(this).find('[data-rating-star]').removeClass('hover');
});

$periodReviewImages.on('change',function(){
    updatePersonReviewFileSelection();
});

$(document).on('click','[data-person-attachment-delete]',function(){
    if(!attachmentDeleteUrl||!currentSalesPeriodReview){
        return;
    }
    const attachmentId=parseInt($(this).attr('data-person-attachment-delete'),10)||0;
    if(!attachmentId){
        return;
    }
    const $button=$(this);
    $button.prop('disabled',true);
    $.ajax({
        url:attachmentDeleteUrl,
        method:'POST',
        dataType:'json',
        data:{_csrf:csrf,attachment_id:attachmentId},
        headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}
    }).done(function(data){
        if(!data||!data.ok){
            $periodReviewMessage.addClass('error').text((data&&data.message)||'Attachment could not be removed.');
            $button.prop('disabled',false);
            return;
        }
        currentSalesPeriodReview.attachments=(currentSalesPeriodReview.attachments||[]).filter(function(item){
            return parseInt(item.id,10)!==attachmentId;
        });
        (currentSalesPeriodReview.history||[]).forEach(function(history){
            history.attachments=(history.attachments||[]).filter(function(item){
                return parseInt(item.id,10)!==attachmentId;
            });
        });
        renderCurrentPersonReviewAttachments(currentSalesPeriodReview.attachments||[]);
        renderSalesReviewHistory(currentSalesPeriodReview.history||[]);
        $periodReviewMessage.removeClass('error').text(data.message||'Attachment permanently deleted.');
    }).fail(function(xhr){
        $periodReviewMessage.addClass('error').text((xhr.responseJSON&&xhr.responseJSON.message)||'Attachment could not be removed.');
        $button.prop('disabled',false);
    });
});

$expandedReviewEdit.on('click',function(){
    openSalesPeriodReviewEditor();
});

$('#salesPeriodReviewClose,#salesPeriodReviewCancel').on(
    'click',
    function(){
        closeSalesPeriodReviewEditor();
    }
);

$periodReviewModal.on('click',function(event){
    if(event.target===this){
        closeSalesPeriodReviewEditor();
    }
});

$periodReviewForm.on('submit',function(event){
    event.preventDefault();

    if(!salesReviewSaveUrl||!expandedSalesId){
        return;
    }

    const rating=parseInt($periodReviewRating.val(),10)||0;

    if(rating<1||rating>5){
        $('#salesPeriodReviewRatingField').addClass('has-error');
        $periodReviewRatingError.removeClass('hidden');
        $periodReviewStars.find('[data-rating-star]').first().trigger('focus');
        return;
    }

    const $note=$periodReviewModal
        .find('[data-html-note]')
        .first();

    syncHtmlNote($note);

    $periodReviewSave
        .prop('disabled',true)
        .removeClass('saved')
        .text('Saving…');

    $periodReviewMessage
        .removeClass('error')
        .text('');

    const personReviewFormData=new FormData($periodReviewForm.get(0));

    $.ajax({
        url:salesReviewSaveUrl,
        method:'POST',
        dataType:'json',
        data:personReviewFormData,
        processData:false,
        contentType:false,
        headers:{
            'X-Requested-With':'XMLHttpRequest',
            'Accept':'application/json'
        }
    })
    .done(function(data){
        if(!data||!data.ok){
            $periodReviewMessage
                .addClass('error')
                .text(
                    (data&&data.message)
                    ||'Could not save review.'
                );
            return;
        }

        renderSalesPeriodReview(data.review);
        renderSalesReviewHistory((data.review&&data.review.history)||[]);
        renderCurrentPersonReviewAttachments((data.review&&data.review.attachments)||[]);

        if(
            data.review
            &&data.review.period==='day'
            &&currentExpandedData
            &&!currentExpandedData.search_only
        ){
            const reviewDate=String(data.review.from||'');
            if(reviewDate){
                currentExpandedData.daily_ratings=
                    (currentExpandedData.daily_ratings&&typeof currentExpandedData.daily_ratings==='object')
                        ?currentExpandedData.daily_ratings
                        :{};
                currentExpandedData.daily_ratings[reviewDate]=parseInt(data.review.rating,10)||0;
                renderAdminSalesActivity(currentExpandedData);
            }
        }
        $periodReviewImages.val('');
        updatePersonReviewFileSelection();

        $periodReviewSave
            .addClass('saved')
            .text(data.unchanged?'No changes':'Saved ✓');

        $periodReviewMessage.text(data.message||'Sales Review saved.');

        setTimeout(function(){
            closeSalesPeriodReviewEditor();
        },data.unchanged?450:600);
    })
    .fail(function(xhr){
        const data=xhr.responseJSON||{};

        if(data.field==='rating'){
            $('#salesPeriodReviewRatingField').addClass('has-error');
            $periodReviewRatingError.removeClass('hidden').text(data.message||'Choose 1–5 stars.');
        }

        $periodReviewMessage
            .addClass('error')
            .text(data.message||'Could not save review.');
    })
    .always(function(){
        if(!$periodReviewSave.hasClass('saved')){
            $periodReviewSave
                .prop('disabled',false)
                .text('Save Review');
        }
    });
});

    /**
     * EN: Update the set modal editor html behavior used by the application UI.
     * 中文：更新application UI 使用的“set modal editor html”行为。
     *
     * @param {string|*} html HTML content rendered or sanitized by this function. / 本函数渲染或清理的 HTML 内容。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function setModalEditorHtml(html){
        const $note = $modal.find('[data-html-note]').first();
        const $editor = $note.find('[data-html-editor]');
        const $source = $note.find('[data-html-source]');

        $source.val(html || '');
        $editor.html(html || '');

        $note.find('[data-note-linkbar]').addClass('hidden');
        $note.find('[data-note-image-panel]').addClass('hidden');
        $note.find('[data-note-image-message]')
            .removeClass('error')
            .text('');

        $note
            .find('[data-note-mode="visual"]')
            .trigger('click');
    }

/**
 * EN: Render the render content preview behavior used by the application UI.
 * 中文：渲染application UI 使用的“render content preview”行为。
 *
 * @param {*} content Content value used by this function. / 本函数使用的“content”参数值。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function renderContentPreview(content){
    content=content||{};
    $contentProvider.text(content.provider||'Saved post');
    $contentFetched.text(content.fetched_at?'Fetched '+content.fetched_at:'');

    const listingDate=String(content.listing_date||'').trim();

    if(listingDate){
        $contentDate
            .removeClass('hidden')
            .text(
                tr('listed')+' · '
                +commentDateLabel(listingDate)
            );
    }else{
        $contentDate
            .addClass('hidden')
            .text('');
    }

    $contentTitle.text(content.title||tr('noTitle'));
    $contentDescription.text(
        content.description||tr('noDescriptionReturned')
    );

    const facts=[];
    if(content.price)facts.push('<span><b>Price</b>'+escapeHtml(content.price)+'</span>');
    if(content.location)facts.push('<span><b>Location</b>'+escapeHtml(content.location)+'</span>');
    $contentFacts.html(facts.join(''));
    const photos=Array.isArray(content.photos)?content.photos.filter(Boolean):[];
    window.cdspReviewListingPhotos=photos;
    if(!photos.length){
        $contentPhotos.addClass('hidden').empty();
        $productDetail.addClass('no-photo');
        return;
    }
    $productDetail.removeClass('no-photo');
    $contentPhotos.html(
        '<button type="button" class="listing-photo-thumb" data-listing-photo="'+escapeHtml(photos[0])+'" aria-label="Open listing photo">'
        +'<img loading="lazy" src="'+escapeHtml(photos[0])+'" alt="Marketplace listing">'
        +'<span class="listing-photo-zoom"><svg viewBox="0 0 24 24"><path d="M10 4a6 6 0 1 0 3.7 10.7L19 20l1-1-5.3-5.3A6 6 0 0 0 10 4Zm0 2a4 4 0 1 1 0 8 4 4 0 0 1 0-8Zm-.8 1.5h1.6v1.7h1.7v1.6h-1.7v1.7H9.2v-1.7H7.5V9.2h1.7V7.5Z"/></svg></span></button>'
    ).removeClass('hidden');
}

    /**
     * EN: Open or show the open listing image behavior used by the application UI.
     * 中文：打开或显示application UI 使用的“open listing image”行为。
     *
     * @param {string|*} url URL read, generated, or requested by this function. / 本函数读取、生成或请求的 URL。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function openListingImage(url){
        if(!url)return;
        $('#listingImageLarge').attr('src',url);
        $('#listingImageLightbox').removeClass('hidden').attr('aria-hidden','false');
    }
    /**
     * EN: Close or clear the close listing image behavior used by the application UI.
     * 中文：关闭或清理application UI 使用的“close listing image”行为。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function closeListingImage(){
        $('#listingImageLightbox').addClass('hidden').attr('aria-hidden','true');
        $('#listingImageLarge').attr('src','');
    }
    $contentPhotos.on('click','[data-listing-photo]',function(){openListingImage(String($(this).data('listing-photo')||''));});
    $('#listingImageClose').on('click',closeListingImage);
    $('#listingImageLightbox').on('click',function(event){if(event.target===this)closeListingImage();});

    // Admin-only explicit refresh. This reuses the existing server-side Get Content
    // path, which forces a fresh provider request rather than the provider cache.
    $getContent.on('click',function(){
        const postId=parseInt($('#dashboardReviewPostId').val(),10)||0;
        if(!postId||!getContentUrl){
            return;
        }

        const $button=$(this);
        $button
            .prop('disabled',true)
            .addClass('is-loading')
            .text('Refreshing…');
        $modalMessage
            .removeClass('error warning')
            .text('Refreshing listing content…');

        $.ajax({
            url:getContentUrl,
            method:'POST',
            dataType:'json',
            cache:false,
            data:{_csrf:csrf,post_id:postId},
            headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}
        })
        .done(function(data){
            if(!data||!data.ok){
                $modalMessage
                    .addClass('error')
                    .text((data&&data.message)||'Content could not be refreshed.');
                return;
            }

            renderContentPreview(data.content||{});
            if(data.content&&data.content.platform_account){
                renderMarketplaceAccount(
                    $('#dashboardReviewAccountFact'),
                    $('#dashboardReviewAccount'),
                    data.content.platform_account
                );
            }

            if(currentExpandedData&&Array.isArray(currentExpandedData.posts)){
                currentExpandedData.posts.forEach(function(post){
                    if(parseInt(post.id,10)!==postId){
                        return;
                    }
                    post.title=String((data.content&&data.content.title)||post.title||'');
                    post.description=String((data.content&&data.content.description)||post.description||'');
                    const photos=Array.isArray(data.content&&data.content.photos)
                        ?data.content.photos.filter(Boolean)
                        :[];
                    if(photos.length){
                        post.thumbnail_url=photos[0];
                    }
                });
                renderPostGrid(currentExpandedData);
            }

            $modalMessage
                .removeClass('error warning')
                .text(data.message||'Content refreshed.');
            if(data.verification_status==='verified'){
                $('#dashboardReviewModalSubtitle').text(
                    $('#dashboardReviewModalSubtitle').text().replace(' · Manual verification','')
                );
            }
        })
        .fail(function(xhr){
            const data=xhr.responseJSON||{};
            $modalMessage
                .addClass('error')
                .text(data.message||String(xhr.responseText||'').trim()||'Content could not be refreshed.');
        })
        .always(function(){
            if(parseInt($('#dashboardReviewPostId').val(),10)===postId){
                $button
                    .prop('disabled',false)
                    .removeClass('is-loading')
                    .text('Refresh Content');
            }
        });
    });


/**
 * EN: Close or clear the close comment delete popover behavior used by the application UI.
 * 中文：关闭或清理application UI 使用的“close comment delete popover”行为。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function closeCommentDeletePopover(){
    deleteCommentId=0;
    deleteAnchorButton=null;

    $deletePopover
        .addClass('hidden')
        .attr('aria-hidden','true')
        .removeClass('place-left place-right place-below place-above')
        .css({left:'',top:''});

    $deleteConfirm
        .prop('disabled',false)
        .text('Mark Deleted');
}

/**
 * EN: Position the position comment delete popover behavior used by the application UI.
 * 中文：定位application UI 使用的“position comment delete popover”行为。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function positionCommentDeletePopover(){
    if(!deleteAnchorButton||$deletePopover.hasClass('hidden')){
        return;
    }

    const anchorRect=deleteAnchorButton.getBoundingClientRect();
    const popoverEl=$deletePopover.get(0);
    if(!popoverEl)return;

    const margin=10;
    const edge=10;
    const vw=window.innerWidth;
    const vh=window.innerHeight;
    const popRect=popoverEl.getBoundingClientRect();
    const width=popRect.width;
    const height=popRect.height;

    let placement='left';
    let left=anchorRect.left-width-margin;
    let top=anchorRect.top+(anchorRect.height-height)/2;

    if(left<edge){
        placement='right';
        left=anchorRect.right+margin;
    }

    if(left+width>vw-edge){
        placement='below';
        left=Math.min(Math.max(edge,anchorRect.right-width),vw-width-edge);
        top=anchorRect.bottom+margin;
    }

    if(top<edge)top=edge;

    if(top+height>vh-edge){
        const above=anchorRect.top-height-margin;
        if(above>=edge){
            placement='above';
            top=above;
            left=Math.min(Math.max(edge,anchorRect.right-width),vw-width-edge);
        }else{
            top=Math.max(edge,vh-height-edge);
        }
    }

    $deletePopover
        .removeClass('place-left place-right place-below place-above')
        .addClass('place-'+placement)
        .css({left:Math.round(left)+'px',top:Math.round(top)+'px'});
}

/**
 * EN: Open or show the open comment delete popover behavior used by the application UI.
 * 中文：打开或显示application UI 使用的“open comment delete popover”行为。
 *
 * @param {Element|*} button Button value used by this function. / 本函数使用的“button”参数值。
 * @param {string|number} commentId Identifier associated with the target record or entity. / 与目标记录或实体关联的 ID。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function openCommentDeletePopover(button,commentId){
    deleteCommentId=parseInt(commentId,10)||0;
    deleteAnchorButton=button||null;

    if(!deleteCommentId||!deleteAnchorButton)return;

    $deletePopover
        .removeClass('hidden')
        .attr('aria-hidden','false');

    requestAnimationFrame(function(){
        positionCommentDeletePopover();
        $deleteCancel.trigger('focus');
    });
}

/**
 * EN: Perform the comment date label behavior used by the application UI.
 * 中文：执行application UI 使用的“comment date label”行为。
 *
 * @param {string|*} value Value read, transformed, or applied by this function. / 本函数读取、转换或应用的值。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function commentDateLabel(value){
    const raw=String(value||'');

    if(!raw){
        return '';
    }

    const normalized=raw.replace(' ','T');
    const dateObj=new Date(normalized);

    if(Number.isNaN(dateObj.getTime())){
        return raw;
    }

    return dateObj.toLocaleString([],{
        year:'numeric',
        month:'short',
        day:'numeric',
        hour:'numeric',
        minute:'2-digit'
    });
}

/**
 * EN: Render the render comment attachments behavior used by the application UI.
 * 中文：渲染application UI 使用的“render comment attachments”行为。
 *
 * @param {Array} items Collection of items processed by this function. / 本函数处理的数据项集合。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function renderCommentAttachments(items){
    items=Array.isArray(items)?items:[];

    if(!items.length){
        return '';
    }

    return '<div class="review-comment-attachments">'
        +items.map(function(item){
            const image=String(item.mime||'').startsWith('image/');
            const deleted=Boolean(item.deleted);
            const uploadedMeta=[
                item.uploaded_by_name
                    ?'Uploaded by '+item.uploaded_by_name
                    :'Uploaded',
                item.uploaded_at
                    ?commentDateLabel(item.uploaded_at)
                    :''
            ].filter(Boolean).join(' · ');

            const deletedMeta=deleted
                ?'<div class="attachment-deleted-audit">'
                    +'<strong>Marked as deleted</strong>'
                    +(item.deleted_by_name
                        ?' by '+escapeHtml(item.deleted_by_name)
                        :'')
                    +(item.deleted_at
                        ?' · '+escapeHtml(
                            commentDateLabel(item.deleted_at)
                        )
                        :'')
                +'</div>'
                :'';

            return (
                '<div class="review-comment-attachment'
                +(deleted?' is-deleted':'')
                +'" data-attachment-id="'
                +escapeHtml(item.id)
                +'">'+

                    '<div class="review-comment-attachment-media">'
                        +(image
                            ?'<button type="button" class="review-comment-image"'
                                +' data-comment-image="'
                                +escapeHtml(item.url)
                                +'" aria-label="Open image">'
                                +'<img loading="lazy" src="'
                                +escapeHtml(item.url)
                                +'" alt="'
                                +escapeHtml(item.name)
                                +'">'
                            +'</button>'
                            :'<a target="_blank" rel="noopener" href="'
                                +escapeHtml(item.url)
                                +'">'
                                +escapeHtml(item.name)
                            +'</a>')
                        +(deleted
                            ?'<span class="attachment-deleted-overlay">'
                                +'Marked as deleted'
                            +'</span>'
                            :'')
                    +'</div>'+

                    '<div class="review-comment-attachment-audit">'
                        +'<span>'+escapeHtml(item.name||'Image')+'</span>'
                        +'<small>'+escapeHtml(uploadedMeta)+'</small>'
                        +deletedMeta+
                    '</div>'+

                    '<button type="button" class="attachment-remove"'
                        +' data-attachment-delete="'
                        +escapeHtml(item.id)
                        +'" aria-label="Delete image permanently"'
                        +' title="Delete image permanently">×</button>'
                +'</div>'
            );
        }).join('')
        +'</div>';
}

/**
 * EN: Update the update comment file selection behavior used by the application UI.
 * 中文：更新application UI 使用的“update comment file selection”行为。
 *
 * @returns {string} String result produced by this UI helper. / 本 UI 辅助函数生成的字符串结果。
 */
function updateCommentFileSelection(){
    const input=$commentImages.get(0);
    const files=input?Array.from(input.files||[]):[];
    $commentFileSelection.html(
        files.map(function(file){return '<span>'+escapeHtml(file.name)+'</span>';}).join('')
    );
}

/**
 * EN: Render the render comments behavior used by the application UI.
 * 中文：渲染application UI 使用的“render comments”行为。
 *
 * @param {Array} items Collection of items processed by this function. / 本函数处理的数据项集合。
 * @param {*} reviewItems Review items value used by this function. / 本函数使用的“review items”参数值。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function renderComments(items,reviewItems){
    currentComments=Array.isArray(items)
        ?items.slice()
        :[];

    if(Array.isArray(reviewItems)){
        currentReviewHistory=reviewItems.slice();
    }

    const activities=[];

    currentReviewHistory.forEach(function(review){
        activities.push({
            activity_type:'review',
            id:review.id,
            author_name:review.author_name,
            created_at:review.created_at,
            decision:review.decision,
            decision_only:true
        });
    });

    const deletedCommentCount=currentComments.filter(
        function(comment){
            return Boolean(comment.deleted);
        }
    ).length;

    $historyDeletedSwitch
        .toggleClass('hidden',deletedCommentCount<1)
        .toggleClass('active',showDeletedComments)
        .attr(
            'aria-checked',
            showDeletedComments?'true':'false'
        );

    $historyDeletedLabel.text(
        showDeletedComments
            ?tr('hideDeletedComments')
            :(
                deletedCommentCount
                    ?tr('deletedCount',{count:deletedCommentCount})
                    :tr('seeFullComments')
            )
    );

    currentComments.forEach(function(comment){
        if(comment.deleted&&!showDeletedComments){
            return;
        }

        activities.push({
            activity_type:'comment',
            id:comment.id,
            author_name:comment.author_name,
            created_at:comment.created_at,
            comment:comment
        });
    });

    activities.sort(function(a,b){
        const av=String(a.created_at||'');
        const bv=String(b.created_at||'');

        if(av===bv){
            return String(a.activity_type)
                .localeCompare(String(b.activity_type));
        }

        return av.localeCompare(bv);
    });

    $commentCount.text(
        activities.length
        +' activit'
        +(activities.length===1?'y':'ies')
    );

    if(!activities.length){
        $commentList.empty();
        $commentEmpty.removeClass('hidden');
        return;
    }

    $commentEmpty.addClass('hidden');

    const html=activities.map(function(activity){
        const initial=escapeHtml(
            String(activity.author_name||'A')
                .trim()
                .charAt(0)
                .toUpperCase()
        );

        if(activity.activity_type==='review'){
            const decision=String(
                activity.decision||''
            ).toLowerCase();
            const good=decision==='good';

            return (
                '<article class="review-history-event '
                +(good?'good':'bad')
                +'">'+
                    '<div class="review-comment-head">'+
                        '<div class="review-comment-author">'+
                            '<span class="review-comment-avatar">'
                                +initial
                            +'</span>'+
                            '<div>'+
                                '<strong>'
                                    +escapeHtml(
                                        activity.author_name
                                        ||'Administrator'
                                    )
                                +'</strong>'+
                                '<span>'
                                    +escapeHtml(
                                        commentDateLabel(
                                            activity.created_at
                                        )
                                    )
                                +'</span>'+
                            '</div>'+
                        '</div>'+
                        '<span class="review-history-decision '
                            +(good?'good':'bad')
                            +'">'
                            +(good?'Good':'Bad')
                        +'</span>'+
                    '</div>'+
                    '<div class="review-history-copy">'
                        +'<strong>Review saved</strong>'
                        +'<span>Decision only · '
                        +(good?'Good':'Bad')
                        +'</span>'
                    +'</div>'+
                '</article>'
            );
        }

        const comment=activity.comment;
        const attachments=Array.isArray(comment.attachments)
            ?comment.attachments
            :[];
        const activePhotos=attachments.filter(function(item){
            return !item.deleted;
        });
        const bodyText=$('<div>')
            .html(comment.body_html||'')
            .text()
            .trim();

        let actionLabel='Comment';

        if(bodyText&&activePhotos.length){
            actionLabel='Comment + '
                +activePhotos.length
                +' photo'
                +(activePhotos.length===1?'':'s');
        }else if(activePhotos.length){
            actionLabel=activePhotos.length
                +' photo'
                +(activePhotos.length===1?'':'s');
        }

        const edited=comment.edited
            ?'<span class="review-comment-edited">Edited</span>'
            :'';

        const deleted=Boolean(comment.deleted);
        const deletedAudit=deleted
            ?'<div class="review-comment-deleted-audit">'
                +'<strong>Marked as deleted</strong>'
                +(comment.deleted_by_name
                    ?' by '+escapeHtml(comment.deleted_by_name)
                    :'')
                +(comment.deleted_at
                    ?' · '+escapeHtml(
                        commentDateLabel(comment.deleted_at)
                    )
                    :'')
            +'</div>'
            :'';

        const editAudit=(
            comment.edited
            &&comment.updated_by_name
            &&!deleted
        )
            ?'<div class="review-comment-edit-audit">'
                +'Last edited by '
                +escapeHtml(comment.updated_by_name)
                +(comment.updated_at
                    ?' · '+escapeHtml(
                        commentDateLabel(comment.updated_at)
                    )
                    :'')
            +'</div>'
            :'';

        return (
            '<article class="review-comment'
            +(deleted?' is-deleted':'')
            +'" data-comment-id="'
            +escapeHtml(comment.id)
            +'">'+
                '<div class="review-comment-head">'+
                    '<div class="review-comment-author">'+
                        '<span class="review-comment-avatar">'
                            +initial
                        +'</span>'+
                        '<div>'+
                            '<strong>'
                                +escapeHtml(
                                    comment.author_name
                                    ||'Administrator'
                                )
                            +'</strong>'+
                            '<span>'
                                +escapeHtml(
                                    commentDateLabel(
                                        comment.created_at
                                    )
                                )
                                +edited+
                            '</span>'+
                        '</div>'+
                    '</div>'+
                    '<div class="review-comment-head-right">'+
                        '<span class="review-comment-action-label">'
                            +escapeHtml(actionLabel)
                        +'</span>'+
                        '<div class="review-comment-actions">'+
                            '<button type="button"'
                            +' class="review-comment-edit-button"'
                            +' data-comment-edit'
                            +' title="Edit comment"'
                            +' aria-label="Edit comment">'
                            +'Edit'
                            +'</button>'+
                            (!deleted
                                ?'<button type="button"'
                                    +' class="review-comment-icon danger"'
                                    +' data-comment-delete'
                                    +' title="Mark note as deleted"'
                                    +' aria-label="Mark note as deleted">'+
                                        '<svg viewBox="0 0 24 24" aria-hidden="true">'
                                        +'<path d="M8 4h8l1 2h4v2H3V6h4l1-2Zm1 6h2v7H9v-7Zm4 0h2v7h-2v-7ZM6 9h12l-1 11H7L6 9Z"/>'
                                        +'</svg>'+
                                    '</button>'
                                :'')
                        +'</div>'
                    +'</div>'+
                '</div>'+
                '<div class="review-comment-body">'
                    +(comment.body_html||'')
                +'</div>'+
                renderCommentAttachments(attachments)+
                editAudit+
                deletedAudit+
            '</article>'
        );
    }).join('');

    $commentList.html(html);
}

$historyDeletedSwitch.on('click',function(){
    showDeletedComments=!showDeletedComments;

    renderComments(
        currentComments,
        currentReviewHistory
    );
});

/**
 * EN: Read or load the get comment editor html behavior used by the application UI.
 * 中文：读取或加载application UI 使用的“get comment editor html”行为。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function getCommentEditorHtml(){
    const $note=$modal.find('[data-html-note]').first();

    if(!$note.length){
        return '';
    }

    syncHtmlNote($note);

    return String(
        $note.find('[data-html-source]').val()||''
    );
}

/**
 * EN: Close or clear the clear comment composer behavior used by the application UI.
 * 中文：关闭或清理application UI 使用的“clear comment composer”行为。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function clearCommentComposer(){
    editingCommentId=0;
    setModalEditorHtml('');
    $commentSave
        .prop('disabled',false)
        .text('Add Note');
    $commentCancelEdit.addClass('hidden');
    $commentImages.val('');
    $commentFileSelection.empty();
    $commentMessage
        .removeClass('error warning')
        .text('');
}

/**
 * EN: Schedule or start the start comment edit behavior used by the application UI.
 * 中文：调度或启动application UI 使用的“start comment edit”行为。
 *
 * @param {string|number} commentId Identifier associated with the target record or entity. / 与目标记录或实体关联的 ID。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function startCommentEdit(commentId){
    const comment=currentComments.find(function(item){
        return parseInt(item.id,10)===parseInt(commentId,10);
    });

    if(!comment){
        return;
    }

    editingCommentId=parseInt(comment.id,10)||0;
    setModalEditorHtml(comment.body_html||'');

    $commentSave.text('Update Note');
    $commentCancelEdit.removeClass('hidden');
    $commentMessage
        .removeClass('error warning')
        .text(
            comment.deleted
                ?'Editing a comment that remains marked as deleted.'
                :'Editing existing note.'
        );

    const editorEl=$modal
        .find('[data-html-note]')
        .first()
        .get(0);

    if(editorEl){
        editorEl.scrollIntoView({
            behavior:'smooth',
            block:'center'
        });
    }
}

/**
 * EN: Render the render attachments behavior used by the application UI.
 * 中文：渲染application UI 使用的“render attachments”行为。
 *
 * @param {Array} items Collection of items processed by this function. / 本函数处理的数据项集合。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function renderAttachments(items){
    currentLegacyAttachments=Array.isArray(items)
        ?items.slice()
        :[];

    const $list=$modalAttachments.find(
        '[data-review-attachment-list]'
    );

    if(!currentLegacyAttachments.length){
        $list.empty();
        $modalAttachments.addClass('hidden');
        return;
    }

    $list.html(
        currentLegacyAttachments.map(function(item){
            const deleted=Boolean(item.deleted);
            const audit=deleted
                ?'<small>Marked as deleted'
                    +(item.deleted_by_name
                        ?' by '+escapeHtml(item.deleted_by_name)
                        :'')
                    +(item.deleted_at
                        ?' · '+escapeHtml(
                            commentDateLabel(item.deleted_at)
                        )
                        :'')
                +'</small>'
                :'<small>'
                    +escapeHtml(
                        [
                            item.uploaded_by_name
                                ?'Uploaded by '+item.uploaded_by_name
                                :'Uploaded',
                            item.uploaded_at
                                ?commentDateLabel(item.uploaded_at)
                                :''
                        ].filter(Boolean).join(' · ')
                    )
                +'</small>';

            return (
                '<div class="legacy-attachment-chip'
                +(deleted?' is-deleted':'')
                +'" data-attachment-id="'
                +escapeHtml(item.id)
                +'">'+
                    '<a target="_blank" rel="noopener" href="'
                        +escapeHtml(item.url)
                        +'">'
                        +escapeHtml(item.name)
                    +'</a>'+
                    audit+
                    '<button type="button" class="attachment-remove"'
                        +' data-attachment-delete="'
                        +escapeHtml(item.id)
                        +'" aria-label="Delete image permanently"'
                        +' title="Delete image permanently">×</button>'
                +'</div>'
            );
        }).join('')
    );

    $modalAttachments.removeClass('hidden');
}

/**
 * EN: Update the sync decision visual state behavior used by the application UI.
 * 中文：更新application UI 使用的“sync decision visual state”行为。
 *
 * @param {*} decision Decision value used by this function. / 本函数使用的“decision”参数值。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function syncDecisionVisualState(decision){
    const normalized=['good','bad'].includes(
        String(decision||'')
    )
        ?String(decision)
        :'';

    const $options=$modalForm.find(
        '.review-decision-option'
    );

    $options.removeClass('is-selected');

    if(normalized){
        $modalForm
            .find(
                '.review-decision-option.'
                +normalized
            )
            .addClass('is-selected');
    }
}

    /**
     * EN: Close or clear the reset review modal behavior used by the application UI.
     * 中文：关闭或清理application UI 使用的“reset review modal”行为。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function resetReviewModalViewport(focusDialog){
        const scrollEl=$modal.find('.review-modal-scroll').get(0);
        if(scrollEl){
            scrollEl.scrollTop=0;
            scrollEl.scrollLeft=0;
        }

        if(focusDialog){
            const dialog=$modal.find('.review-modal').get(0);
            if(dialog){
                try{
                    dialog.focus({preventScroll:true});
                }catch(_error){
                    dialog.focus();
                }
            }
        }
    }

    function resetReviewModal(){
        $modalMessage
            .removeClass('error warning')
            .text('');
        $reviewSaveState
            .addClass('hidden')
            .removeClass('warning')
            .find('span')
            .text('Review saved');
        $reviewCancel.text('Cancel');
        $('#dashboardReviewSave')
            .prop('disabled',false)
            .removeClass('saved')
            .text('Save Review');
        $modalForm.get(0).reset();
        $modalForm
            .find('.review-decision-modern')
            .removeClass('is-invalid')
            .attr('aria-invalid','false');
        syncDecisionVisualState('');
        $('#dashboardDecisionSaved')
            .addClass('hidden')
            .text('');
        $modalForm
            .find('[data-decision-error]')
            .addClass('hidden');
        $('#dashboardReviewPostId').val('');
        $('#dashboardReviewModalTitle').text('Review Post');
        $('#dashboardReviewModalSubtitle').text('');
        $('#dashboardReviewPublished').text('—');
        $('#dashboardReviewPlatform').text('—');
        $('#dashboardReviewItemId').text('—');
        $('#dashboardReviewOriginal')
            .addClass('hidden')
            .attr('href', '#');
        $getContent
            .prop('disabled',true)
            .removeClass('is-loading')
            .text('Refresh Content');
        window.cdspReviewListingPhotos=[];
        editingCommentId=0;
        currentComments=[];
        currentReviewHistory=[];
        currentLegacyAttachments=[];
        showDeletedComments=false;

        $historyDeletedSwitch
            .attr('aria-checked','false')
            .removeClass('active hidden');
        $historyDeletedLabel.text('See full comments');

        closeCommentDeletePopover();
        renderComments([],[]);
        clearCommentComposer();
        renderAttachments([]);
        renderContentPreview({
            provider:'Saved post',
            title:'No content loaded',
            description:'',
            photos:[]
        });
        setModalEditorHtml('');
        resetReviewModalViewport(false);
    }

    /**
     * EN: Close or clear the close review modal behavior used by the application UI.
     * 中文：关闭或清理application UI 使用的“close review modal”行为。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function closeReviewModal(){
        if(reviewRequest && reviewRequest.readyState !== 4){
            reviewRequest.abort();
        }

        activePostId = 0;
        $modal.addClass('hidden').attr('aria-hidden', 'true');
        $('body').removeClass('review-modal-open');
        resetReviewModal();
    }

    /**
     * EN: Open or show the open review modal behavior used by the application UI.
     * 中文：打开或显示application UI 使用的“open review modal”行为。
     *
     * @param {string|number} postId Identifier associated with the target record or entity. / 与目标记录或实体关联的 ID。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function openReviewModal(postId){
        postId = parseInt(postId, 10) || 0;

        if(!postId){
            return;
        }

        if(reviewRequest && reviewRequest.readyState !== 4){
            reviewRequest.abort();
        }

        activePostId = postId;
        resetReviewModal();

        $modal
            .removeClass('hidden')
            .attr('aria-hidden', 'false');
        $('body').addClass('review-modal-open');
        resetReviewModalViewport(true);
        $modalForm.addClass('hidden');
        $modalLoading.removeClass('hidden');

        reviewRequest = $.ajax({
            url: postReviewUrl,
            method: 'GET',
            dataType: 'json',
            cache: false,
            data: {
                id: postId,
                _: Date.now()
            }
        })
        .done(function(data){
            if(
                !data
                || !data.ok
                || activePostId !== postId
            ){
                return;
            }

            $('#dashboardReviewPostId').val(data.post.id);
            $('#dashboardReviewModalTitle').text(
                data.post.sales_name + ' · Post Review'
            );
            const manualVerification=data.post.verification_status==='manual_pending';
            $('#dashboardReviewModalSubtitle').text(
                '#'
                + data.post.sales_id
                + ' · '
                + data.post.platform
                +(manualVerification?' · Manual verification':'')
            );
            if(manualVerification){
                $modalMessage
                    .addClass('warning')
                    .text('Craigslist blocked automated verification. Sales entered the title/date manually; review the original listing before marking Good.');
            }
            $('#dashboardReviewPublished').text(
                data.post.published_at || '—'
            );
            $('#dashboardReviewPlatform').text(
                data.post.platform || '—'
            );
            $('#dashboardReviewItemId').text(
                data.post.external_post_id || '—'
            );
            renderMarketplaceAccount(
                $('#dashboardReviewAccountFact'),
                $('#dashboardReviewAccount'),
                {
                    id:data.post.platform_account_id||'',
                    name:data.post.platform_account_name||'',
                    url:data.post.platform_account_url||''
                }
            );

            if(data.post.canonical_url){
                $('#dashboardReviewOriginal')
                    .removeClass('hidden')
                    .attr('href', data.post.canonical_url);
            }

            $getContent.prop('disabled',false);

            const historyItems=Array.isArray(
                data.review_history
            )
                ?data.review_history
                :[];

            const latestHistory=historyItems.length
                ?historyItems[historyItems.length-1]
                :null;

            const historyDecision=latestHistory
                &&['good','bad'].includes(
                    String(latestHistory.decision||'').toLowerCase()
                )
                    ?String(latestHistory.decision).toLowerCase()
                    :'';

            const responseDecision=data.review
                &&['good','bad'].includes(
                    String(data.review.decision||'').toLowerCase()
                )
                    ?String(data.review.decision).toLowerCase()
                    :'';

            // History is the immutable record of each Save Review,
            // therefore its latest entry wins when the popup reopens.
            const savedDecision=historyDecision||responseDecision;

            const $decisionInputs=$modalForm.find(
                'input[name="decision"]'
            );

            $decisionInputs.prop('checked',false);

            if(savedDecision){
                const $savedInput=$decisionInputs.filter(
                    '[value="'+savedDecision+'"]'
                );

                $savedInput.prop('checked',true);
                syncDecisionVisualState(savedDecision);

                const savedAt=latestHistory
                    ?latestHistory.created_at
                    :(data.review&&data.review.last_saved_at);
                const savedBy=latestHistory
                    ?latestHistory.author_name
                    :(data.review&&data.review.last_saved_by);

                $('#dashboardDecisionSaved')
                    .removeClass('hidden')
                    .text(
                        'Last saved: '
                        +(savedDecision==='good'?'Good':'Bad')
                        +(savedBy?' · '+savedBy:'')
                        +(savedAt
                            ?' · '+commentDateLabel(savedAt)
                            :'')
                    );
            }else{
                syncDecisionVisualState('');
                $('#dashboardDecisionSaved')
                    .addClass('hidden')
                    .text('');
            }

            clearCommentComposer();
            renderComments(
                data.comments || [],
                data.review_history || []
            );
            renderContentPreview(data.content);
            renderAttachments(data.attachments);
        })
        .fail(function(xhr, status){
            if(status === 'abort'){
                return;
            }

            const data = xhr.responseJSON || {};
            $modalMessage
                .addClass('error')
                .text(
                    data.message || 'Could not load review.'
                );
        })
        .always(function(){
            if(activePostId === postId){
                $modalLoading.addClass('hidden');
                $modalForm.removeClass('hidden');
                window.requestAnimationFrame(function(){
                    if(activePostId===postId){
                        resetReviewModalViewport(true);
                    }
                });
            }
        });
    }

    $expandedList.on('click', '.sales-post-tile', function(){
        openReviewModal($(this).data('post-id'));
    });

    $expandedList.on('keydown', '.sales-post-tile', function(event){
        if(event.key !== 'Enter' && event.key !== ' '){
            return;
        }

        event.preventDefault();
        openReviewModal($(this).data('post-id'));
    });

    $('#dashboardReviewClose,#dashboardReviewCancel').on(
        'click',
        closeReviewModal
    );

    $modal.on('click', function(event){
        if(event.target === this){
            closeReviewModal();
        }
    });

    $(document).on('keydown', function(event){
        if(event.key!=='Escape')return;

        if(!$periodReviewModal.hasClass('hidden')){
            closeSalesPeriodReviewEditor();
            return;
        }

        if(!$deletePopover.hasClass('hidden')){
            closeCommentDeletePopover();
            return;
        }

        if(!$('#listingImageLightbox').hasClass('hidden')){
            closeListingImage();
            return;
        }

        if(!$modal.hasClass('hidden'))closeReviewModal();
    });



/**
 * EN: Open or show the show decision error behavior used by the application UI.
 * 中文：打开或显示application UI 使用的“show decision error”行为。
 *
 * @param {string|*} message Human-readable message shown or recorded by the UI. / UI 显示或记录的可读消息。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function showDecisionError(message){
    const $decisionBlock=$modalForm.find(
        '.review-decision-modern'
    );

    $decisionBlock
        .addClass('is-invalid')
        .attr('aria-invalid','true');

    $decisionBlock
        .find('[data-decision-error]')
        .removeClass('hidden')
        .text(
            message || 'Select Good or Bad before saving.'
        );

    $modalMessage
        .removeClass('warning')
        .addClass('error')
        .text('Choose Good or Bad.');

    const decisionEl=$decisionBlock.get(0);

    if(decisionEl){
        decisionEl.scrollIntoView({
            behavior:'smooth',
            block:'center'
        });
    }

    // Make the first choice the keyboard focus target without selecting it.
    const firstChoice=$decisionBlock
        .find('input[name="decision"]')
        .get(0);

    if(firstChoice){
        setTimeout(function(){
            firstChoice.focus({
                preventScroll:true
            });
        },220);
    }
}

    /**
     * EN: Perform the mark review dirty behavior used by the application UI.
     * 中文：执行application UI 使用的“mark review dirty”行为。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function markReviewDirty(){
        $reviewSaveState
            .addClass('hidden')
            .removeClass('warning');

        $reviewCancel.text('Cancel');

        $('#dashboardReviewSave')
            .prop('disabled',false)
            .removeClass('saved')
            .text('Save Review');
    }

    $modalForm.on(
        'change',
        'input[name="decision"]',
        function(){
            const $decisionBlock=$modalForm.find(
                '.review-decision-modern'
            );

            syncDecisionVisualState(
                String($(this).val()||'')
            );

            $decisionBlock
                .removeClass('is-invalid')
                .attr('aria-invalid','false');

            $decisionBlock
                .find('[data-decision-error]')
                .addClass('hidden');

            if(
                $modalMessage.text().trim()==='Choose Good or Bad.'
            ){
                $modalMessage
                    .removeClass('error')
                    .text('');
            }

            markReviewDirty();
        }
    );

$commentSave.on('click',function(){
    const postId=parseInt($('#dashboardReviewPostId').val(),10)||0;
    const body=getCommentEditorHtml();
    if(!postId) return;

    const isEditing=editingCommentId>0;
    const url=isEditing?commentUpdateUrl:commentAddUrl;
    const formData=new FormData();
    formData.append('_csrf',csrf);
    formData.append('post_id',postId);
    formData.append('comment_body',body);
    if(isEditing) formData.append('comment_id',editingCommentId);

    const input=$commentImages.get(0);
    const files=input?Array.from(input.files||[]):[];
    files.forEach(function(file){formData.append('comment_images[]',file);});

    $commentMessage.removeClass('error warning').text(isEditing?'Updating note…':'Adding note…');
    $commentSave.prop('disabled',true).text(isEditing?'Updating…':'Adding…');

    $.ajax({
        url:url,method:'POST',dataType:'json',data:formData,
        processData:false,contentType:false,
        headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}
    })
    .done(function(data){
        if(!data||!data.ok){
            $commentMessage.addClass('error').text((data&&data.message)||'Could not save note.');
            return;
        }
        if(isEditing){
            currentComments=currentComments.map(function(item){
                return parseInt(item.id,10)===parseInt(data.comment.id,10)?data.comment:item;
            });
        }else{
            currentComments.push(data.comment);
        }
        renderComments(currentComments,currentReviewHistory);
        clearCommentComposer();
        if(data.upload_warning){
            $commentMessage.addClass('warning').text('Note saved. Image warning: '+data.upload_warning);
        }else{
            $commentMessage.removeClass('error warning').text(isEditing?'Note updated.':'Note added.');
        }
    })
    .fail(function(xhr){
        const data=xhr.responseJSON||{};
        $commentMessage.addClass('error').text(data.message||String(xhr.responseText||'').trim()||'Could not save note.');
    })
    .always(function(){
        $commentSave.prop('disabled',false).text(editingCommentId>0?'Update Note':'Add Note');
    });
});

$commentImages.on('change',updateCommentFileSelection);

$commentCancelEdit.on('click',function(){
    clearCommentComposer();
});

$commentList.on('click','[data-comment-edit]',function(){
    const commentId=parseInt(
        $(this)
            .closest('[data-comment-id]')
            .data('comment-id'),
        10
    )||0;

    startCommentEdit(commentId);
});


$commentList.on('click','[data-comment-image]',function(){
    openListingImage(String($(this).data('comment-image')||''));
});

/**
 * EN: Delete or remove the delete attachment behavior used by the application UI.
 * 中文：删除或移除application UI 使用的“delete attachment”行为。
 *
 * @param {string|number} attachmentId Identifier associated with the target record or entity. / 与目标记录或实体关联的 ID。
 * @param {*} $source $source value used by this function. / 本函数使用的“$source”参数值。
 *
 * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
 */
function deleteAttachment(attachmentId,$source){
    attachmentId=parseInt(attachmentId,10)||0;

    if(!attachmentId){
        return;
    }

    $source
        .prop('disabled',true)
        .text('…');

    $.ajax({
        url:attachmentDeleteUrl,
        method:'POST',
        dataType:'json',
        data:{
            _csrf:csrf,
            attachment_id:attachmentId
        },
        headers:{
            'X-Requested-With':'XMLHttpRequest',
            'Accept':'application/json'
        }
    })
    .done(function(data){
        if(!data||!data.ok){
            $commentMessage
                .addClass('error')
                .text(
                    (data&&data.message)
                    ||'Could not delete image.'
                );

            $source
                .prop('disabled',false)
                .text('×');
            return;
        }

        if(data.entity_type==='post_comment'){
            currentComments=currentComments.map(function(comment){
                if(
                    parseInt(comment.id,10)
                    ===parseInt(data.entity_id,10)
                ){
                    comment.attachments=(comment.attachments||[])
                        .filter(function(item){
                            return parseInt(item.id,10)!==attachmentId;
                        });

                    comment.active_attachment_count=(
                        comment.attachments||[]
                    ).length;
                }

                return comment;
            });

            renderComments(
                currentComments,
                currentReviewHistory
            );
        }else{
            currentLegacyAttachments=currentLegacyAttachments
                .filter(function(item){
                    return parseInt(item.id,10)!==attachmentId;
                });

            renderAttachments(
                currentLegacyAttachments
            );
        }

        $commentMessage
            .removeClass('error warning')
            .text('Image permanently deleted.');

        setTimeout(function(){
            if(
                $commentMessage.text()
                ==='Image permanently deleted.'
            ){
                $commentMessage.text('');
            }
        },1800);
    })
    .fail(function(xhr){
        const data=xhr.responseJSON||{};

        $commentMessage
            .addClass('error')
            .text(
                data.message
                ||'Could not delete image.'
            );

        $source
            .prop('disabled',false)
            .text('×');
    });
}

$modal.on('click','[data-attachment-delete]',function(event){
    event.preventDefault();event.stopPropagation();
    deleteAttachment($(this).data('attachment-delete'),$(this));
});

$commentList.on('click','[data-comment-delete]',function(event){
    event.preventDefault();
    event.stopPropagation();

    const button=this;
    const commentId=parseInt(
        $(button).closest('[data-comment-id]').data('comment-id'),
        10
    )||0;

    if(deleteCommentId===commentId&&!$deletePopover.hasClass('hidden')){
        closeCommentDeletePopover();
        return;
    }

    openCommentDeletePopover(button,commentId);
});

$deleteCancel.on('click',function(){
    closeCommentDeletePopover();
});

$deleteConfirm.on('click',function(){
    const commentId=deleteCommentId;
    if(!commentId){
        closeCommentDeletePopover();
        return;
    }

    const $button=$(this);
    $button.prop('disabled',true).text('Deleting…');

    $.ajax({
        url:commentDeleteUrl,
        method:'POST',
        dataType:'json',
        data:{_csrf:csrf,comment_id:commentId},
        headers:{
            'X-Requested-With':'XMLHttpRequest',
            'Accept':'application/json'
        }
    })
    .done(function(data){
        if(!data||!data.ok){
            $commentMessage.addClass('error').text(
                (data&&data.message)||'Could not delete note.'
            );
            $button.prop('disabled',false).text('Mark Deleted');
            return;
        }

        if(data.comment){
            currentComments=currentComments.map(function(item){
                return parseInt(item.id,10)===commentId
                    ?data.comment
                    :item;
            });
        }

        if(editingCommentId===commentId){
            clearCommentComposer();
        }

        closeCommentDeletePopover();
        renderComments(
            currentComments,
            currentReviewHistory
        );

        $commentMessage
            .removeClass('error warning')
            .text('Comment marked as deleted.');

        setTimeout(function(){
            if($commentMessage.text()==='Note deleted.')$commentMessage.text('');
        },1800);
    })
    .fail(function(xhr){
        const data=xhr.responseJSON||{};
        $commentMessage.addClass('error').text(
            data.message||'Could not delete note.'
        );
        $button.prop('disabled',false).text('Mark Deleted');
    });
});

$(document).on('mousedown.commentDeletePopover',function(event){
    if($deletePopover.hasClass('hidden'))return;

    if(
        $(event.target).closest('#commentDeletePopover').length
        ||$(event.target).closest('[data-comment-delete]').length
    )return;

    closeCommentDeletePopover();
});

$(window).on(
    'resize.commentDeletePopover scroll.commentDeletePopover',
    function(){
        if(!$deletePopover.hasClass('hidden'))positionCommentDeletePopover();
    }
);

$modalForm.on('submit', function(event){
        event.preventDefault();

        const decision = String(
            $modalForm
                .find('input[name="decision"]:checked')
                .val() || ''
        );

        if(!['good','bad'].includes(decision)){
            showDecisionError(
                'Select Good or Bad before saving.'
            );
            return;
        }

        const form = $modalForm.get(0);
        const formData = new FormData(form);
        const $save = $('#dashboardReviewSave');

        $modalMessage
            .removeClass('error')
            .text('');
        $save.prop('disabled', true).text('Saving...');

        $.ajax({
            url: reviewSaveUrl,
            method: 'POST',
            dataType: 'json',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .done(function(data){
            if(!data || !data.ok){
                $modalMessage
                    .addClass('error')
                    .text(
                        (data && data.message)
                        || 'Could not save review.'
                    );
                return;
            }

            renderAttachments(data.attachments);

            const status = String(data.decision || '');
            const $tile = $expandedList.find(
                '.sales-post-tile[data-post-id="'
                +data.post_id
                +'"]'
            );

            $tile
                .removeClass('review-good review-bad')
                .addClass(
                    status === 'good'
                        ? 'review-good'
                        : 'review-bad'
                )
                .attr('data-review-status', status);

            $tile
                .find('.sales-post-tile-status')
                .removeClass('good bad')
                .addClass(status)
                .text(
                    status === 'good' ? 'Good' : 'Bad'
                );

            syncDecisionVisualState(status);
            syncExpandedSalesCardFromTiles();

            if(data.history_event){
                $('#dashboardDecisionSaved')
                    .removeClass('hidden')
                    .text(
                        'Last saved: '
                        +(status==='good'?'Good':'Bad')
                        +(data.history_event.author_name
                            ?' · '+data.history_event.author_name
                            :'')
                        +(data.history_event.created_at
                            ?' · '+commentDateLabel(
                                data.history_event.created_at
                            )
                            :'')
                    );
                currentReviewHistory.push(
                    data.history_event
                );
                renderComments(
                    currentComments,
                    currentReviewHistory
                );
            }

            if(data.upload_warning){
                $modalMessage
                    .removeClass('error')
                    .addClass('warning')
                    .text('Image warning: '+data.upload_warning);

                $reviewSaveState
                    .removeClass('hidden')
                    .addClass('warning')
                    .find('span')
                    .text('Review saved with image warning');
            }else{
                $modalMessage
                    .removeClass('error warning')
                    .text('');

                $reviewSaveState
                    .removeClass('hidden warning')
                    .find('span')
                    .text('Review saved');
            }

            $save
                .prop('disabled',true)
                .addClass('saved')
                .text('Saved ✓');

            $reviewCancel.text('Close');

            // Update the Sales card metrics without closing the popup/grid.
            $.ajax({
                url: progressUrl,
                method: 'GET',
                dataType: 'json',
                cache: false,
                data: {
                    date: currentDate,
                    period: currentPeriod,
                    _: Date.now()
                }
            }).done(function(progress){
                if(progress && progress.ok){
                    applyProgress(progress);
                }
            });

            if(!data.upload_warning){
                const savedPostId=parseInt(data.post_id,10)||0;

                setTimeout(function(){
                    if(
                        activePostId===savedPostId
                        &&$save.hasClass('saved')
                    ){
                        closeReviewModal();
                    }
                },650);
            }
        })
        .fail(function(xhr){
            const data=xhr.responseJSON||{};
            const raw=String(xhr.responseText||'').trim();

            if(data.field==='decision'){
                showDecisionError(
                    data.message || 'Select Good or Bad before saving.'
                );
            }

            $modalMessage
                .removeClass('warning')
                .addClass('error')
                .text(
                    data.message
                    ||raw
                    ||'Could not save review.'
                );
        })
        .always(function(){
            if(!$save.hasClass('saved')){
                $save
                    .prop('disabled',false)
                    .text('Save Review');
            }
        });
    });

    /**
     * EN: Render the redraw after daily target save behavior used by the application UI.
     * 中文：渲染application UI 使用的“redraw after daily target save”行为。
     *
     * @param {*} $card $card value used by this function. / 本函数使用的“$card”参数值。
     * @param {*} dailyTarget Daily target value used by this function. / 本函数使用的“daily target”参数值。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function redrawAfterDailyTargetSave($card, dailyTarget){
        // EN: Target history is date-specific, so never recalculate a range as
        // current target × days in the browser. Reload the authoritative range.
        // 中文：Target 已按日期保存历史，前端不能再用“当前 Target × 天数”重算；
        // 保存后重新读取当前范围，才能正确处理历史日期与中途改 Target。
        if($card&&$card.length){
            $card.find('[data-target-input]').val(dailyTarget);
        }
        reloadCurrentProgress();
    }


let salesSettingsCard=null;
const $salesSettingsModal=$('#salesPersonSettingsModal');
const $salesSettingsInput=$('#salesPersonDailyTarget');
const $salesSettingsLocation=$('#salesPersonLocation');
const $salesSettingsMessage=$('#salesPersonSettingsMessage');

/**
 * EN: Close or clear the close sales person settings behavior used by the application UI.
 * 中文：关闭或清理application UI 使用的“close sales person settings”行为。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function closeSalesPersonSettings(){
    $salesSettingsModal
        .addClass('hidden')
        .attr('aria-hidden','true');
    $salesSettingsMessage
        .removeClass('error ok')
        .text('');
    salesSettingsCard=null;
}

$grid.on('click','[data-sales-settings]',function(event){
    event.preventDefault();
    event.stopPropagation();

    const $card=$(this).closest('.sales-progress-card');
    const target=Math.max(
        1,
        parseInt(
            $card.attr('data-daily-target'),
            10
        )||10
    );

    salesSettingsCard=$card;

    $('#salesPersonSettingsName').text(
        String(
            $card.attr('data-sales-name')||''
        )
    );

    $salesSettingsInput
        .val(target)
        .removeClass('field-error');
    $salesSettingsLocation
        .val(String(parseInt($card.attr('data-location-id'),10)||0))
        .removeClass('field-error');

    $salesSettingsMessage
        .removeClass('error ok')
        .text('');

    $salesSettingsModal
        .removeClass('hidden')
        .attr('aria-hidden','false');

    setTimeout(function(){
        $salesSettingsInput
            .trigger('focus')
            .trigger('select');
    },0);
});

$('#salesPersonSettingsClose,#salesPersonSettingsCancel')
    .on('click',function(){
        closeSalesPersonSettings();
    });

$salesSettingsModal.on('click',function(event){
    if(event.target===this){
        closeSalesPersonSettings();
    }
});

$('#salesPersonSettingsSave').on('click',function(){
    if(!salesSettingsCard||!salesSettingsCard.length){
        return;
    }

    const salesId=parseInt(
        salesSettingsCard.attr('data-sales-id'),
        10
    )||0;
    const target=parseInt(
        $salesSettingsInput.val(),
        10
    )||0;
    const locationId=Math.max(
        0,
        parseInt($salesSettingsLocation.val(),10)||0
    );
    const $button=$(this);

    $salesSettingsInput.removeClass('field-error');
    $salesSettingsLocation.removeClass('field-error');
    $salesSettingsMessage
        .removeClass('error ok')
        .text('');

    if(target<1||target>999){
        $salesSettingsInput
            .addClass('field-error')
            .trigger('focus');

        $salesSettingsMessage
            .addClass('error')
            .text('Target must be 1–999.');
        return;
    }

    $button
        .prop('disabled',true)
        .text(tr('loading'));

    $.ajax({
        url:targetUrl,
        method:'POST',
        dataType:'json',
        data:{
            _csrf:csrf,
            sales_user_id:salesId,
            target:target,
            location_id:locationId
        }
    })
    .done(function(data){
        if(!data||!data.ok){
            $salesSettingsMessage
                .addClass('error')
                .text(
                    (data&&data.message)
                    ||'Could not save.'
                );
            return;
        }

        const dailyTarget=Math.max(
            1,
            parseInt(data.target,10)||10
        );

        redrawAfterDailyTargetSave(
            salesSettingsCard,
            dailyTarget
        );

        $salesSettingsInput.val(dailyTarget);

        const savedLocationId=Math.max(
            0,
            parseInt(data.location_id,10)||0
        );
        const savedLocationName=String(data.location_name||'');
        salesSettingsCard
            .attr('data-location-id',savedLocationId)
            .attr('data-location-name',savedLocationName);
        salesSettingsCard
            .find('[data-sales-location-label]')
            .text(savedLocationName||tr('unassigned'));
        $salesSettingsLocation.val(String(savedLocationId));
        updateSalesLocationFilterCounts(data);
        applySalesDirectoryFilters();

        $salesSettingsMessage
            .addClass('ok')
            .text(data.message||tr('saved'));

        setTimeout(function(){
            closeSalesPersonSettings();
        },450);
    })
    .fail(function(xhr){
        const data=xhr.responseJSON||{};

        $salesSettingsMessage
            .addClass('error')
            .text(
                data.message
                ||'Could not save.'
            );
    })
    .always(function(){
        $button
            .prop('disabled',false)
            .text(tr('saveSettings'));
    });
});

    $(document).on('click', '[data-target-save]', function(){
        const $button = $(this);
        const $card = $button.closest('.sales-progress-card');
        const $input = $card.find('[data-target-input]');
        const salesId = parseInt(
            $card.attr('data-sales-id'),
            10
        ) || 0;
        const target = parseInt($input.val(), 10) || 0;

        setTargetMessage($card, '', false);
        $input.removeClass('field-error');

        if(target < 1 || target > 999){
            $input.addClass('field-error').focus();
            setTargetMessage(
                $card,
                'Target must be 1–999.',
                true
            );
            return;
        }

        $button.prop('disabled', true).text('Saving...');

        $.ajax({
            url: targetUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                _csrf: csrf,
                sales_user_id: salesId,
                target: target
            }
        })
        .done(function(data){
            if(!data || !data.ok){
                setTargetMessage(
                    $card,
                    (data && data.message) || 'Could not save.',
                    true
                );
                return;
            }

            const dailyTarget = parseInt(data.target, 10) || 10;
            $input.val(dailyTarget);
            redrawAfterDailyTargetSave($card, dailyTarget);
            setTargetMessage($card, 'Saved', false);
        })
        .fail(function(xhr){
            const data = xhr.responseJSON || {};
            setTargetMessage(
                $card,
                data.message || 'Could not save.',
                true
            );
        })
        .always(function(){
            $button.prop('disabled', false).text('Save');
        });
    });

    $grid.on('input', '[data-target-input]', function(){
        $(this).removeClass('field-error');
        setTargetMessage(
            $(this).closest('.sales-progress-card'),
            '',
            false
        );
    });

    /**
     * EN: Open or show the show refresh notice behavior used by the application UI.
     * 中文：打开或显示application UI 使用的“show refresh notice”行为。
     *
     * @param {Object|*} data Structured data consumed by this function. / 本函数使用的结构化数据。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function showRefreshNotice(data){
        if(noticeShown){
            return;
        }

        noticeShown = true;

        const count = parseInt(data.post_count, 10) || 0;
        const delta = Math.max(0, count - baselineCount);

        $noticeTitle.text(
            delta > 0
                ? tr(delta===1?'newPostAvailable':'newPostsAvailable',{count:delta})
                : tr('salesChanged')
        );

        $noticeText.text(
            tr('refreshLatestProgress',{
                period:periodName(currentPeriod).toLowerCase()
            })
        );

        $notice.removeClass('hidden');
    }

    /**
     * EN: Check the check dashboard activity behavior used by the application UI.
     * 中文：检查application UI 使用的“check dashboard activity”行为。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function checkDashboardActivity(){
        if(document.hidden || noticeShown){
            return;
        }

        if(activityRequest && activityRequest.readyState !== 4){
            return;
        }

        activityRequest = $.ajax({
            url: updatesUrl,
            method: 'GET',
            dataType: 'json',
            cache: false,
            data:adminAjaxRangeData({
                _:Date.now()
            })
        })
        .done(function(data){
            if(!data || !data.ok){
                return;
            }

            const postCount = parseInt(data.post_count, 10) || 0;
            const maxPostId = parseInt(data.max_post_id, 10) || 0;

            if(
                postCount > baselineCount
                || maxPostId > baselineMaxId
            ){
                showRefreshNotice(data);
            }
        });
    }

    $('#dashboardRefreshButton').on('click',function(){
        // v0.2.40: once Admin explicitly refreshes, clear the stale-activity
        // banner immediately and prevent an older in-flight poll from putting
        // the same banner back on screen while the fresh progress request runs.
        if(activityRequest && activityRequest.readyState !== 4){
            activityRequest.abort();
        }
        noticeShown = true;
        $notice.addClass('hidden');
        const $button=$(this).prop('disabled',true).text(tr('refreshing'));
        const request=reloadCurrentProgress();
        if(request&&typeof request.always==='function'){
            request.always(function(){
                noticeShown=false;
                $button.prop('disabled',false).text(tr('refresh'));
                checkDashboardActivity();
            });
        }else{
            noticeShown=false;
            $button.prop('disabled',false).text(tr('refresh'));
        }
    });

    document.addEventListener('visibilitychange', function(){
        if(!document.hidden){
            checkDashboardActivity();
        }
    });

    // Apply persisted Settings language immediately; do not wait for the
    // first AJAX progress response before translating the range toolbar.
    dashboardLanguage=currentAppLanguage();
    if(!dashboardI18n[dashboardLanguage]){
        dashboardLanguage='en';
    }
    applyDashboardLanguage();
    syncAdminRangeInputs();
    updateBackToday();
    const initialProgressRequest=reloadCurrentProgress({initial:true});

    if(initialProgressRequest&&initialSalesId){
        initialProgressRequest.done(function(){
            const $card=$grid.find(
                '.sales-progress-card[data-sales-id="'
                +initialSalesId
                +'"]'
            );
            if(!$card.length)return;
            openReviewAfterExpand=initialOpenReview;
            openExpandedPosts($card);
        });
    }

    checkDashboardActivity();
    activityTimer = setInterval(checkDashboardActivity, 5000);
})();


;

/* v0.1.71 — permanent post delete + website reference browser */
let adminPostDeleteArmed=false;
let adminPostDeleteTimer=null;

/**
 * EN: Close or clear the reset admin post delete behavior used by the application UI.
 * 中文：关闭或清理application UI 使用的“reset admin post delete”行为。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function resetAdminPostDelete(){
    adminPostDeleteArmed=false;
    clearTimeout(adminPostDeleteTimer);
    $('#dashboardPostDelete').prop('disabled',false).removeClass('danger-confirm').text('Delete Post');
    $('#dashboardPostDeleteHint').text('');
}

$(document).on('click','.sales-post-tile,[data-post-id],#dashboardReviewClose,#dashboardReviewCancel',function(){
    resetAdminPostDelete();
});

$('#dashboardPostDelete').on('click',function(){
    const $button=$(this);
    const postId=parseInt($('#dashboardReviewPostId').val()||'0',10);
    if(!postId)return;

    if(!adminPostDeleteArmed){
        adminPostDeleteArmed=true;
        $button.addClass('danger-confirm').text('Confirm permanent delete');
        $('#dashboardPostDeleteHint').text('This removes the post from the database. Click again to confirm.');
        adminPostDeleteTimer=setTimeout(resetAdminPostDelete,6000);
        return;
    }

    clearTimeout(adminPostDeleteTimer);
    $button.prop('disabled',true).text('Deleting…');
    $('#dashboardPostDeleteHint').text('');
    $.ajax({
        url:$button.data('delete-url'),
        method:'POST',
        dataType:'json',
        data:{
            _csrf:$('#dashboardReviewForm input[name="_csrf"]').val(),
            post_id:postId
        },
        headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}
    }).done(function(data){
        if(!data||!data.ok){
            $('#dashboardReviewMessage').text((data&&data.message)||'Post could not be deleted.').addClass('error');
            resetAdminPostDelete();
            return;
        }
        window.location.reload();
    }).fail(function(xhr){
        $('#dashboardReviewMessage').text(
            (xhr.responseJSON&&xhr.responseJSON.message)||'Post could not be deleted.'
        ).addClass('error');
        resetAdminPostDelete();
    });
});

/**
 * EN: Perform the website reference row behavior used by the application UI.
 * 中文：执行application UI 使用的“website reference row”行为。
 *
 * @param {Object|*} row Current record or row being rendered or processed. / 当前正在渲染或处理的记录/行。
 *
 * @returns {string} String result produced by this UI helper. / 本 UI 辅助函数生成的字符串结果。
 */
function websiteReferenceRow(row,detailMode){
    const title=escapeHtml(String(row.title||''));
    const description=escapeHtml(String(row.description||''));
    const page=escapeHtml(String(row.page_url||''));
    const image=escapeHtml(String(row.image_url||''));
    const imported=escapeHtml(String(row.imported_at||'—'));
    const id=escapeHtml(row.id);
    if(detailMode){
        return '<tr data-website-reference-id="'+id+'">'
            +'<td><strong>'+title+'</strong>'+(description?'<small class="website-source-reference-description">'+description+'</small>':'')+'</td>'
            +'<td class="website-source-url-cell"><a href="'+page+'" target="_blank" rel="noopener noreferrer">'+page+'</a></td>'
            +'<td>'+(image?'<a href="'+image+'" target="_blank" rel="noopener noreferrer">Open image ↗</a>':'—')+'</td>'
            +'<td>'+(row.sha256?'SHA-256 ✓':'—')+'</td>'
            +'<td>'+imported+'</td>'
            +'<td><button type="button" class="tiny badbtn website-reference-delete" data-reference-id="'+id+'">Delete</button></td>'
            +'</tr>';
    }
    const indexed=row.sha256?'Yes':'Pending';
    return '<tr data-website-reference-id="'+id+'">'
        +'<td><strong>'+title+'</strong></td>'
        +'<td class="website-reference-description">'+description+'</td>'
        +'<td><a href="'+page+'" target="_blank" rel="noopener noreferrer">Open page ↗</a></td>'
        +'<td>'+(image?'<a href="'+image+'" target="_blank" rel="noopener noreferrer">Image ↗</a>':'—')+'</td>'
        +'<td>'+indexed+'</td>'
        +'<td><button type="button" class="tiny badbtn website-reference-delete" data-reference-id="'+id+'">Delete</button></td>'
        +'</tr>';
}

/**
 * EN: Update the set website reference message behavior used by the application UI.
 * 中文：更新application UI 使用的“set website reference message”行为。
 *
 * @param {string|*} message Human-readable message shown or recorded by the UI. / UI 显示或记录的可读消息。
 * @param {*} type Type value used by this function. / 本函数使用的“type”参数值。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function setWebsiteReferenceMessage(message,type){
    const $box=$('#websiteReferenceMessage');
    if(!$box.length)return;
    if(!message){$box.addClass('hidden').removeClass('ok error').text('');return;}
    $box.removeClass('hidden ok error').addClass(type==='ok'?'ok':'error').text(message);
}

/**
 * EN: Read or load the load website references behavior used by the application UI.
 * 中文：读取或加载application UI 使用的“load website references”行为。
 *
 * @returns {void} No value is returned. / 无返回值。
 */
function loadWebsiteReferences(){
    const $library=$('#website-source-detail').length?$('#website-source-detail'):$('#website-comparison');
    if(!$library.length)return;
    const q=$('#websiteReferenceSearch').val()||'';
    const host=String($library.data('source-host')||'');
    const detailMode=$library.is('#website-source-detail');
    const $button=$('#websiteReferenceSearchButton');
    $button.prop('disabled',true).text('Searching…');
    $.getJSON($library.data('search-url'),{q:q,host:host})
        .done(function(data){
            if(!data||!data.ok){setWebsiteReferenceMessage((data&&data.message)||'Search failed.','error');return;}
            const rows=Array.isArray(data.rows)?data.rows:[];
            $('#websiteReferenceRows').html(
                rows.length
                    ?rows.map(function(row){return websiteReferenceRow(row,detailMode);}).join('')
                    :'<tr class="website-reference-empty"><td colspan="6">No matching website references.</td></tr>'
            );
            setWebsiteReferenceMessage(rows.length+' reference'+(rows.length===1?'':'s')+' found.','ok');
        })
        .fail(function(xhr){
            setWebsiteReferenceMessage((xhr.responseJSON&&xhr.responseJSON.message)||'Search failed.','error');
        })
        .always(function(){$button.prop('disabled',false).text('Search');});
}

$('#websiteReferenceSearchButton').on('click',loadWebsiteReferences);
$('#websiteReferenceSearch').on('keydown',function(event){
    if(event.key==='Enter'){event.preventDefault();loadWebsiteReferences();}
});

$(document).on('click','.website-reference-delete',function(){
    const $button=$(this);
    const $library=$('#website-source-detail').length?$('#website-source-detail'):$('#website-comparison');
    const id=parseInt($button.data('reference-id')||'0',10);
    if(!id)return;

    if(!$button.hasClass('delete-armed')){
        $('.website-reference-delete').removeClass('delete-armed').text('Delete');
        $button.addClass('delete-armed').text('Confirm');
        setWebsiteReferenceMessage('Click Confirm to permanently remove this website reference.','error');
        return;
    }

    $button.prop('disabled',true).text('Deleting…');
    $.ajax({
        url:$library.data('delete-url'),
        method:'POST',
        dataType:'json',
        data:{_csrf:$library.data('csrf'),id:id},
        headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}
    }).done(function(data){
        if(!data||!data.ok){setWebsiteReferenceMessage((data&&data.message)||'Delete failed.','error');return;}
        $('tr[data-website-reference-id="'+id+'"]').remove();
        if(!$('#websiteReferenceRows tr').length){
            $('#websiteReferenceRows').html('<tr class="website-reference-empty"><td colspan="6">No matching website references.</td></tr>');
        }
        setWebsiteReferenceMessage('Website reference deleted.','ok');
    }).fail(function(xhr){
        setWebsiteReferenceMessage((xhr.responseJSON&&xhr.responseJSON.message)||'Delete failed.','error');
        $button.prop('disabled',false).removeClass('delete-armed').text('Delete');
    });
});


/* v0.2.32 — source delete cascades to all indexed URLs / 删除网站同时删除关联 URL */
$(document).on('click','.website-source-delete',function(event){
    const $button=$(this);
    if($button.hasClass('delete-armed')){return;}
    event.preventDefault();
    $('.website-source-delete').removeClass('delete-armed').text('Delete Website');
    const count=parseInt($button.data('reference-count')||'0',10);
    $button.addClass('delete-armed').text('Confirm Delete '+count+' URLs');
    window.setTimeout(function(){
        if($button.hasClass('delete-armed')){$button.removeClass('delete-armed').text('Delete Website');}
    },7000);
});

// v0.1.91 Universal logged-in header: measure its real responsive height so
// secondary sticky controls can sit directly below it without hard-coded heights.
(function(){
    const topbar=document.querySelector('.topbar[data-user-role="admin"],.topbar[data-user-role="sales"]');
    if(!topbar){
        return;
    }
    const syncTopbarHeight=function(){
        const height=Math.max(0,Math.ceil(topbar.getBoundingClientRect().height));
        document.documentElement.style.setProperty('--cdsp-topbar-height',height+'px');
    };
    syncTopbarHeight();
    window.addEventListener('resize',syncTopbarHeight,{passive:true});
    if('ResizeObserver' in window){
        const observer=new ResizeObserver(syncTopbarHeight);
        observer.observe(topbar);
    }
})();

// v0.1.81 Management Reports: in-panel shared date controls + live result refresh.
(function(){
    const $reports=$('#managementReports');
    if(!$reports.length)return;

    const today=String($reports.attr('data-today')||'');
    const $form=$('#reportRangeForm');
    const $from=$('#reportRangeFrom');
    const $to=$('#reportRangeTo');
    const $period=$('#reportPeriodValue');
    const $sales=$('#reportSalesSelect');
    let refreshTimer=null;
    let activeRequest=null;
    let refreshSeq=0;

    /**
     * EN: Parse an ISO date string into a local Date value used by the dashboard.
     * 中文：将 ISO 日期字符串解析为 Dashboard 使用的本地 Date 值。
     *
     * @param {string|*} value Value read, transformed, or applied by this function. / 本函数读取、转换或应用的值。
     *
     * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
     */
    function parseIso(value){
        const m=String(value||'').match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if(!m)return null;
        const d=new Date(+m[1],+m[2]-1,+m[3],12,0,0);
        return Number.isNaN(d.getTime())?null:d;
    }
    /**
     * EN: Format a Date value as the ISO-style date key used by API requests and filters.
     * 中文：将 Date 值格式化为 API 请求和筛选使用的 ISO 日期键。
     *
     * @param {*} d D value used by this function. / 本函数使用的“d”参数值。
     *
     * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
     */
    function iso(d){
        return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');
    }
    /**
     * EN: Build the range for behavior used by the application UI.
     * 中文：构建application UI 使用的“range for”行为。
     *
     * @param {*} preset Preset value used by this function. / 本函数使用的“preset”参数值。
     * @param {*} anchorValue Anchor value value used by this function. / 本函数使用的“anchor value”参数值。
     *
     * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
     */
    function rangeFor(preset,anchorValue){
        let anchor=parseIso(anchorValue)||parseIso(today);
        const todayDate=parseIso(today);
        if(!anchor)return null;
        if(todayDate&&anchor>todayDate)anchor=new Date(todayDate);
        const toDate=new Date(anchor);
        let fromDate=new Date(anchor);
        if(preset==='day')fromDate.setDate(fromDate.getDate()-2);
        else if(preset==='week')fromDate.setDate(fromDate.getDate()-6);
        else if(preset==='month'){
            const day=toDate.getDate();
            const prevStart=new Date(toDate.getFullYear(),toDate.getMonth()-1,1,12,0,0);
            const prevLast=new Date(toDate.getFullYear(),toDate.getMonth(),0,12,0,0).getDate();
            fromDate=new Date(prevStart.getFullYear(),prevStart.getMonth(),Math.min(day,prevLast),12,0,0);
            fromDate.setDate(fromDate.getDate()+1);
        }
        return {from:iso(fromDate),to:iso(toDate)};
    }
    /**
     * EN: Update the select preset behavior used by the application UI.
     * 中文：更新application UI 使用的“select preset”行为。
     *
     * @param {*} preset Preset value used by this function. / 本函数使用的“preset”参数值。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function selectPreset(preset){
        $period.val(preset);
        $('#reportPeriodSwitch [data-report-period]').each(function(){
            const active=String($(this).attr('data-report-period'))===preset;
            $(this).toggleClass('active',active).attr('aria-pressed',active?'true':'false');
        });
    }
    /**
     * EN: Update the sync behavior used by the application UI.
     * 中文：更新application UI 使用的“sync”行为。
     *
     * @param {*} changed Changed value used by this function. / 本函数使用的“changed”参数值。
     *
     * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
     */
    function sync(changed){
        let from=String($from.val()||'');
        let to=String($to.val()||'');
        if(!parseIso(from)||!parseIso(to))return false;
        if(today&&to>today){to=today;$to.val(to);}
        if(today&&from>today){from=today;$from.val(from);}
        if(changed==='from'&&from>to){to=from;$to.val(to);}
        else if(changed==='to'&&to<from){from=to;$from.val(from);}
        else if(from>to){from=to;$from.val(from);}
        $from.attr('max',to);
        $to.attr('min',from).attr('max',today);
        return true;
    }
    /**
     * EN: Perform the query string behavior used by the application UI.
     * 中文：执行application UI 使用的“query string”行为。
     *
     * @returns {*} Result produced by this function; the concrete type depends on the execution path. / 本函数生成的结果；具体类型取决于执行路径。
     */
    function queryString(){
        return $form.serialize();
    }
    /**
     * EN: Update the set loading behavior used by the application UI.
     * 中文：更新application UI 使用的“set loading”行为。
     *
     * @param {*} loading Loading value used by this function. / 本函数使用的“loading”参数值。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function setLoading(loading){
        $('#reportResultPanel').toggleClass('report-loading',loading).attr('aria-busy',loading?'true':'false');
    }
    /**
     * EN: Perform the refresh report behavior used by the application UI.
     * 中文：执行application UI 使用的“refresh report”行为。
     *
     * @param {string|*} pushUrl URL value used by this UI operation. / 本 UI 操作使用的 URL。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function refreshReport(pushUrl){
        if(!sync(''))return;
        if(refreshTimer){window.clearTimeout(refreshTimer);refreshTimer=null;}
        if(activeRequest){activeRequest.abort();activeRequest=null;}
        const seq=++refreshSeq;
        const qs=queryString();
        setLoading(true);
        activeRequest=$.ajax({
            url:$form.attr('action'),
            method:'GET',
            data:qs,
            dataType:'html',
            headers:{'X-Requested-With':'XMLHttpRequest'}
        }).done(function(html){
            if(seq!==refreshSeq)return;
            const $doc=$('<div>').append($.parseHTML(html,document,false));
            const $nextTable=$doc.find('#reportTableArea').first();
            const $nextTitle=$doc.find('#reportSelectedSalesTitle').first();
            const $nextDownload=$doc.find('#reportDownloadButton').first();
            if(!$nextTable.length)return;

            const $currentTable=$('#reportTableArea');
            const reduceMotion=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if(reduceMotion){
                $currentTable.replaceWith($nextTable);
            }else{
                $currentTable.stop(true,true).fadeOut(80,function(){
                    $(this).replaceWith($nextTable.hide());
                    $nextTable.fadeIn(120);
                });
            }

            if($nextTitle.length){
                $('#reportSelectedSalesTitle').text($nextTitle.text());
            }
            if($nextDownload.length){
                $('#reportDownloadButton').attr('href',$nextDownload.attr('href')||'#');
            }
            if(pushUrl&&window.history&&window.history.replaceState){
                window.history.replaceState(null,'',$form.attr('action')+'?'+qs);
            }
        }).fail(function(xhr,status){
            if(status==='abort'||seq!==refreshSeq)return;
            const $panel=$('#reportResultPanel');
            $panel.removeClass('report-loading');
            if(!$panel.find('.report-live-error').length){
                $panel.prepend('<div class="notice bad report-live-error">Report could not be refreshed. Change a filter to retry.</div>');
            }
        }).always(function(){
            if(seq!==refreshSeq)return;
            activeRequest=null;
            setLoading(false);
        });
    }
    /**
     * EN: Schedule or start the schedule refresh behavior used by the application UI.
     * 中文：调度或启动application UI 使用的“schedule refresh”行为。
     *
     * @param {*} delay Delay value used by this function. / 本函数使用的“delay”参数值。
     *
     * @returns {void} No value is returned. / 无返回值。
     */
    function scheduleRefresh(delay){
        if(refreshTimer)window.clearTimeout(refreshTimer);
        refreshTimer=window.setTimeout(function(){refreshReport(true);},typeof delay==='number'?delay:180);
    }

    $('#reportPeriodSwitch').on('click','[data-report-period]',function(){
        const preset=String($(this).attr('data-report-period')||'single');
        if(preset==='custom'){
            selectPreset('custom');
            if(sync(''))scheduleRefresh(120);
            return;
        }
        const range=rangeFor(preset,String($to.val()||today));
        if(!range)return;
        $from.val(range.from);
        $to.val(range.to);
        sync('');
        selectPreset(preset);
        scheduleRefresh(80);
    });

    $from.on('change',function(){
        if(sync('from')){
            selectPreset('custom');
            scheduleRefresh(120);
        }
    });
    $to.on('change',function(){
        if(sync('to')){
            selectPreset('custom');
            scheduleRefresh(120);
        }
    });
    $sales.on('change',function(){scheduleRefresh(80);});

    $form.on('submit',function(event){
        event.preventDefault();
        refreshReport(true);
    });

    sync('');
})();

/* V0.2.49 — Settings Location CRUD stays in-place with animated add/edit/delete. */
(function(){
    const $section=$('#sales-locations');
    if(!$section.length)return;

    const endpoints={
        add:String($section.data('location-add-url')||''),
        update:String($section.data('location-update-url')||''),
        remove:String($section.data('location-delete-url')||'')
    };
    const csrf=String($section.data('csrf')||'');
    const $list=$('#salesLocationList');
    const $notice=$('#salesLocationLiveNotice');

    /**
     * EN: Read one Settings Location translation from the active global app language.
     * 中文：从当前全局语言中读取一条 Settings Location 翻译。
     *
     * @param {string} key Translation key. / 翻译键。
     * @returns {string} Localized text. / 本地化文字。
     */
    function locationText(key){
        const dict=appLanguageDictionary[currentAppLanguage()]||appLanguageDictionary.en;
        return String(dict[key]||appLanguageDictionary.en[key]||key);
    }

    /**
     * EN: Show an inline Settings Location result without navigating or changing scroll position.
     * 中文：以内联方式显示 Location 操作结果，不刷新页面也不改变滚动位置。
     *
     * @param {string} key Translation key. / 翻译键。
     * @param {boolean} bad Whether to use error styling. / 是否使用错误样式。
     * @returns {void}
     */
    function showLocationNotice(key,bad){
        $notice
            .stop(true,true)
            .removeClass('hidden ok bad')
            .addClass(bad?'bad':'ok')
            .text(locationText(key))
            .hide()
            .fadeIn(140);
    }

    /**
     * EN: Convert a server failure code to the existing Settings Location translation key.
     * 中文：把服务端失败代码转换为现有 Settings Location 翻译键。
     *
     * @param {Object|*} data Response payload. / 响应数据。
     * @returns {string} Translation key. / 翻译键。
     */
    function locationErrorKey(data){
        const code=String((data&&data.code)||'error');
        return {
            duplicate:'locationDuplicate',
            invalid:'locationInvalid',
            'in-use':'locationInUse',
            missing:'locationMissing',
            error:'locationError'
        }[code]||'locationError';
    }

    /**
     * EN: Update live Location counts after CRUD without rebuilding or jumping the Settings page.
     * 中文：CRUD 后原地更新 Location 数量，不重建或跳动 Settings 页面。
     *
     * @param {Object|*} data Response payload. / 响应数据。
     * @returns {void}
     */
    function updateLocationSummary(data){
        if(data&&data.locations_count!=null){
            $('#salesLocationCount').text(String(data.locations_count));
        }
        if(data&&data.unassigned_sales_count!=null){
            const count=Math.max(0,parseInt(data.unassigned_sales_count,10)||0);
            const $row=$('#salesLocationUnassigned');
            $row.find('[data-unassigned-count]').text(String(count));
            $row.toggleClass('hidden',count<1);
        }
    }

    /**
     * EN: Build one editable Location card for a newly created row.
     * 中文：为新建记录生成一张可修改的 Location Card。
     *
     * @param {Object} row Location row. / Location 记录。
     * @returns {jQuery} Created card. / 新建的 Card。
     */
    function buildLocationCard(row){
        const id=Math.max(0,parseInt(row&&row.id,10)||0);
        const name=String((row&&row.name)||'');
        const count=Math.max(0,parseInt(row&&row.sales_count,10)||0);
        const updateUrl=endpoints.update||'';
        const deleteUrl=endpoints.remove||'';
        const html=[
            '<article class="sales-location-card location-card-enter" data-location-card data-location-id="',id,'" data-sales-count="',count,'">',
              '<div class="sales-location-card-main">',
                '<div class="sales-location-card-copy">',
                  '<strong data-location-name>',escapeHtml(name),'</strong>',
                  '<span><b data-location-sales-count>',count,'</b> <span data-app-i18n="sales">Sales</span></span>',
                '</div>',
                '<div class="sales-location-card-actions">',
                  '<button class="tiny" type="button" data-location-edit data-app-i18n="editLocation">Edit</button>',
                  '<form method="post" class="js-location-delete-form" action="',escapeHtml(deleteUrl),'">',
                    '<input type="hidden" name="_csrf" value="',escapeHtml(csrf),'">',
                    '<input type="hidden" name="location_id" value="',id,'">',
                    '<button class="tiny badbtn" type="submit" data-app-i18n="deleteLocation">Delete</button>',
                  '</form>',
                '</div>',
              '</div>',
              '<form method="post" class="sales-location-edit-form js-location-edit-form hidden" action="',escapeHtml(updateUrl),'">',
                '<input type="hidden" name="_csrf" value="',escapeHtml(csrf),'">',
                '<input type="hidden" name="location_id" value="',id,'">',
                '<input class="sales-location-edit-input" type="text" name="location_name" maxlength="120" required autocomplete="off" value="',escapeHtml(name),'" aria-label="Location name">',
                '<div class="sales-location-edit-actions">',
                  '<button class="tiny primary" type="submit" data-app-i18n="saveLocation">Save</button>',
                  '<button class="tiny" type="button" data-location-edit-cancel data-app-i18n="cancel">Cancel</button>',
                '</div>',
              '</form>',
            '</article>'
        ].join('');
        const $card=$(html);
        applyGlobalMenuLanguage();
        return $card;
    }

    $('.js-location-add-form').on('submit',function(event){
        event.preventDefault();
        if(!endpoints.add)return;
        const $form=$(this);
        const $input=$form.find('[name="location_name"]');
        const $button=$form.find('[type="submit"]');
        if(!$input.val().trim()){showLocationNotice('locationInvalid',true);return;}

        $button.prop('disabled',true);
        $.ajax({
            url:endpoints.add,
            method:'POST',
            data:$form.serialize()+'&ajax=1',
            dataType:'json',
            headers:{'X-Requested-With':'XMLHttpRequest'}
        }).done(function(data){
            if(!data||!data.ok){showLocationNotice(locationErrorKey(data),true);return;}
            $('#salesLocationEmpty').stop(true,true).fadeOut(100,function(){$(this).remove();});
            const $card=buildLocationCard(data.location||{});
            $list.append($card);
            applyGlobalMenuLanguage();
            window.requestAnimationFrame(function(){
                window.requestAnimationFrame(function(){$card.removeClass('location-card-enter');});
            });
            $input.val('');
            updateLocationSummary(data);
            showLocationNotice('locationAdded',false);
        }).fail(function(xhr){
            showLocationNotice(locationErrorKey(xhr.responseJSON||{}),true);
        }).always(function(){
            $button.prop('disabled',false);
        });
    });

    $list.on('click','[data-location-edit]',function(){
        const $card=$(this).closest('[data-location-card]');
        $list.find('.js-location-edit-form:visible').not($card.find('.js-location-edit-form')).stop(true,true).slideUp(120,function(){$(this).addClass('hidden').removeAttr('style');});
        const $form=$card.find('.js-location-edit-form');
        $form.stop(true,true).removeClass('hidden').hide().slideDown(160,function(){
            $(this).removeAttr('style');
            $(this).find('[name="location_name"]').trigger('focus').select();
        });
    });

    $list.on('click','[data-location-edit-cancel]',function(){
        const $card=$(this).closest('[data-location-card]');
        const currentName=String($card.find('[data-location-name]').text()||'');
        const $form=$(this).closest('.js-location-edit-form');
        $form.find('[name="location_name"]').val(currentName);
        $form.stop(true,true).slideUp(140,function(){$(this).addClass('hidden').removeAttr('style');});
    });

    $list.on('submit','.js-location-edit-form',function(event){
        event.preventDefault();
        if(!endpoints.update)return;
        const $form=$(this);
        const $card=$form.closest('[data-location-card]');
        const $button=$form.find('[type="submit"]');
        $button.prop('disabled',true);
        $.ajax({
            url:endpoints.update,
            method:'POST',
            data:$form.serialize()+'&ajax=1',
            dataType:'json',
            headers:{'X-Requested-With':'XMLHttpRequest'}
        }).done(function(data){
            if(!data||!data.ok){showLocationNotice(locationErrorKey(data),true);return;}
            const row=data.location||{};
            const name=String(row.name||'');
            $card.find('[data-location-name]').text(name);
            $form.find('[name="location_name"]').val(name);
            $form.stop(true,true).slideUp(130,function(){$(this).addClass('hidden').removeAttr('style');});
            $card.removeClass('location-card-updated');
            window.requestAnimationFrame(function(){$card.addClass('location-card-updated');});
            window.setTimeout(function(){$card.removeClass('location-card-updated');},520);
            updateLocationSummary(data);
            showLocationNotice('locationUpdated',false);
        }).fail(function(xhr){
            showLocationNotice(locationErrorKey(xhr.responseJSON||{}),true);
        }).always(function(){
            $button.prop('disabled',false);
        });
    });

    $list.on('submit','.js-location-delete-form',function(event){
        event.preventDefault();
        if(!endpoints.remove)return;
        const $form=$(this);
        const $card=$form.closest('[data-location-card]');
        const $button=$form.find('[type="submit"]');
        if($button.prop('disabled'))return;
        $button.prop('disabled',true);
        $.ajax({
            url:endpoints.remove,
            method:'POST',
            data:$form.serialize()+'&ajax=1',
            dataType:'json',
            headers:{'X-Requested-With':'XMLHttpRequest'}
        }).done(function(data){
            if(!data||!data.ok){showLocationNotice(locationErrorKey(data),true);return;}
            $card.addClass('location-card-leave');
            window.setTimeout(function(){
                $card.remove();
                if(!$list.find('[data-location-card]').length){
                    const $empty=$('<div class="sales-location-empty" id="salesLocationEmpty" data-app-i18n="noLocations"></div>');
                    $list.append($empty);
                    applyGlobalMenuLanguage();
                    $empty.hide().fadeIn(140);
                }
            },180);
            updateLocationSummary(data);
            showLocationNotice('locationDeleted',false);
        }).fail(function(xhr){
            showLocationNotice(locationErrorKey(xhr.responseJSON||{}),true);
            $button.prop('disabled',false);
        });
    });
})();


/* v0.2.95 — Save & Wait / Bulk Verification Queue. */
(function(){
    let vqPollTimer=null;

    function vqCsrf(){
        return String($('#inspectForm input[name="_csrf"]').first().val()||$('input[name="_csrf"]').first().val()||'');
    }
    function vqValidUrl(raw){
        try{
            const u=new URL(String(raw||''));
            return (u.protocol==='https:'||u.protocol==='http:')?u:null;
        }catch(error){return null;}
    }
    function vqMessage($panel,message,type){
        const $m=$panel.find('[data-vq-message]');
        if(!message){$m.addClass('hidden').removeClass('ok error').text('');return;}
        $m.removeClass('hidden ok error').addClass(type==='ok'?'ok':'error').text(message);
    }
    const vqCollapseStorageKey='cdspSalesVerificationQueueCollapsed';
    function vqSetCollapsed($panel,collapsed,persist){
        collapsed=!!collapsed;
        $panel.toggleClass('is-collapsed',collapsed);
        const $toggle=$panel.find('[data-vq-collapse-toggle]').first();
        const $titleToggle=$panel.find('[data-vq-title-toggle]').first();
        const expanded=collapsed?'false':'true';
        const actionLabel=collapsed?'Open Verification Queue':'Close Verification Queue';
        $toggle.attr('aria-expanded',expanded);
        $toggle.attr('aria-label',actionLabel);
        $toggle.attr('title',actionLabel);
        $titleToggle.attr('aria-expanded',expanded).attr('aria-label',actionLabel).attr('title',actionLabel);
        if(persist){
            try{window.localStorage.setItem(vqCollapseStorageKey,collapsed?'1':'0');}catch(error){}
        }
    }
    function vqInitCollapseState(){
        let collapsed=true;
        try{
            const saved=window.localStorage.getItem(vqCollapseStorageKey);
            if(saved==='0')collapsed=false;
            if(saved==='1')collapsed=true;
        }catch(error){}
        $('[data-verification-queue-panel]').each(function(){vqSetCollapsed($(this),collapsed,false);});
    }
    // v0.2.135: A newly submitted background verification must be visible.
    // If Sales had intentionally collapsed the Queue, reopen it after a new
    // submission is accepted and switch that reopened panel to All so the new
    // Waiting / Verifying row cannot be hidden behind an old status filter.
    // Panels that were already open keep their current filter unchanged.
    function vqRevealAfterSubmission(){
        $('[data-verification-queue-panel]').each(function(){
            const $panel=$(this);
            if(!$panel.hasClass('is-collapsed'))return;
            $panel.attr('data-vq-current-filter','all');
            $panel.find('[data-vq-filter]').removeClass('active');
            $panel.find('[data-vq-filter="all"]').addClass('active');
            vqSetCollapsed($panel,false,true);
        });
    }
    function vqStatusLabel(status){
        const map={waiting:'queueWaiting',verifying:'queueVerifying',passed:'queuePassed',failed:'queueFailed',duplicate:'queueDuplicate',invalid:'queueInvalid'};
        return salesTr(map[status]||map.waiting);
    }
    function vqIsErrorStatus(status){
        status=String(status||'').toLowerCase();
        return status==='failed'||status==='duplicate'||status==='invalid';
    }
    function vqErrorType(item){
        item=item||{};
        const status=String(item.status||'').toLowerCase();
        const code=String(item.failure_code||'').toUpperCase();
        const kind=String(item.duplicate_kind||'').toLowerCase();
        const message=String(item.failure_message||'').toLowerCase();
        const result=function(slug,key){return {slug:slug,label:salesTr(key)};};

        if(kind==='external_id'||kind==='queue_external_id')return result('duplicate-id','queueErrorDuplicateId');
        if(kind==='same_account_title'||kind==='exact_title'||kind==='website_exact_title')return result('duplicate-title','queueErrorDuplicateTitle');
        if(kind==='same_account_image'||kind==='same_platform_image'||kind==='website_exact_image'||code==='DUPLICATE_IMAGE')return result('duplicate-photo','queueErrorDuplicatePhoto');
        if(kind==='url'||kind==='queue_url')return result('duplicate-url','queueErrorDuplicateUrl');
        if(status==='duplicate'||code==='DUPLICATE'||code==='DUPLICATE_PREFLIGHT'){
            if(String(item.external_post_id||'').trim()!=='')return result('duplicate-id','queueErrorDuplicateId');
            return result('duplicate','queueDuplicate');
        }
        if(status==='invalid'||code==='INVALID_URL'||code==='URL_INVALID'||code==='PLATFORM_INVALID')return result('invalid-url','queueErrorInvalidUrl');
        if(code.includes('TIMEOUT')||message.includes('timeout')||message.includes('timed out'))return result('timeout','queueErrorTimeout');
        if(code==='LISTING_UNAVAILABLE')return result('unavailable','queueErrorUnavailable');
        if(code==='FACEBOOK_PROVIDER_FAILED'||code==='FETCH_FAILED')return result('provider','queueErrorProvider');
        if(code==='DATE_NOT_VERIFIABLE'||code==='FUTURE_DATE')return result('date','queueErrorDate');
        if(code==='TITLE_NOT_VERIFIABLE')return result('title-check','queueErrorTitleCheck');
        if(code==='MANUAL_VERIFICATION_REQUIRED')return result('manual','queueErrorManual');
        if(code==='QUEUE_WORKER_ERROR'||code==='SAVE_BLOCKED'||code==='COMPARISON_UNAVAILABLE')return result('system','queueErrorSystem');
        return result('generic','queueErrorGeneric');
    }
    function vqDisplayStatus(item){
        const status=String((item&&item.status)||'waiting').toLowerCase();
        return vqIsErrorStatus(status)?vqErrorType(item).label:vqStatusLabel(status);
    }
    function vqShortUrl(raw){
        try{
            const u=new URL(String(raw||''));
            let text=u.hostname.replace(/^www\./,'')+u.pathname;
            if(text.length>74)text=text.slice(0,70)+'…';
            return text;
        }catch(error){return String(raw||'').slice(0,74);}
    }
    function vqExternalIdFromUrl(raw,platform){
        const url=String(raw||'').trim();
        let match=null;
        if(platform==='facebook'){
            match=url.match(/facebook\.com\/marketplace\/item\/(\d+)/i);
            return match&&match[1]?match[1]:'';
        }
        if(platform==='offerup'){
            match=url.match(/offerup\.(?:com|co)\/item\/detail\/([a-z0-9-]+)/i);
            return match&&match[1]?match[1]:'';
        }
        if(platform==='craigslist'){
            match=url.match(/\/(\d{8,})\.html(?:[?#]|$)/i);
            return match&&match[1]?match[1]:'';
        }
        return '';
    }
    function vqRefreshEditPreview($edit){
        if(!$edit||!$edit.length)return;
        const raw=String($edit.find('[data-vq-edit-url]').val()||'').trim();
        const platform=detectPlatform(raw);
        const normalized=platform?normalizePostUrl(raw,platform):raw;
        const externalId=platform?vqExternalIdFromUrl(normalized,platform):'';
        $edit.find('[data-vq-edit-platform]').text(platform?platformLabel(platform):'—');
        $edit.find('[data-vq-edit-post-id]').text(externalId||'—');
        $edit.toggleClass('has-unsupported-url',!!raw&&!platform);
    }
    function vqRenderRow(item){
        const status=String(item.status||'waiting').toLowerCase();
        const errorType=vqIsErrorStatus(status)?vqErrorType(item):null;
        const rowClasses=['sales-vq-row','sales-vq-card','status-'+status];
        if(errorType)rowClasses.push('is-error','error-type-'+errorType.slug);
        const displayStatus=errorType?errorType.label:vqStatusLabel(status);
        const $row=$('<article>').addClass(rowClasses.join(' '))
            .attr({
                'data-vq-id':String(item.id||''),
                'role':'button',
                'tabindex':'0',
                'aria-label':status==='passed'?'Show saved Post and clear this Passed queue item':'Open '+displayStatus+' verification details'
            })
            .data('vqItem',item);

        const $top=$('<div>').addClass('sales-vq-card-top');
        $('<span>').addClass('sales-vq-platform').text(platformLabel(item.platform)||item.platform||'Marketplace').appendTo($top);
        $('<span>').addClass('sales-vq-status '+status+(errorType?' error-label error-type-'+errorType.slug:'')).attr('title',displayStatus).text(displayStatus).appendTo($top);
        $top.appendTo($row);

        const $url=$('<div>').addClass('sales-vq-url');
        const parsed=vqValidUrl(item.canonical_url||item.submitted_url);
        if(status==='passed'){
            $url.text(vqShortUrl((parsed&&parsed.href)||item.submitted_url));
        }else if(parsed){
            $('<a>').attr({href:parsed.href,target:'_blank',rel:'noopener noreferrer',title:parsed.href}).text(vqShortUrl(parsed.href)).appendTo($url);
        }else{$url.text(vqShortUrl(item.submitted_url));}
        $url.appendTo($row);

        const $meta=$('<div>').addClass('sales-vq-meta');
        if(item.result_title)$('<strong>').addClass('sales-vq-title').text(item.result_title).appendTo($meta);
        const when=item.result_published_date||item.updated_at||item.queued_at||'';
        const account=String(item.result_platform_account_name||'').trim();
        const bits=[];
        if(item.external_post_id)bits.push('ID '+item.external_post_id);
        if(account)bits.push(account);
        if(when)bits.push(when);
        $('<span>').addClass('sales-vq-meta-line').text(bits.join(' · ')||'—').appendTo($meta);
        $meta.appendTo($row);

        if(vqIsErrorStatus(status)){
            const $failure=$('<div>').addClass('sales-vq-failure'+(status==='duplicate'?' duplicate':'')+(errorType?' error-type-'+errorType.slug:''));
            const failureMessage=String(item.failure_message||'').trim();
            $('<span>').addClass('sales-vq-error-message').text(failureMessage||(errorType?errorType.label:vqStatusLabel(status))).appendTo($failure);
            $('<span>').addClass('sales-vq-not-counted').text(salesTr('queueNotCounted')).appendTo($failure);
            $failure.appendTo($row);
        }else if(status==='passed'){
            const passedText=salesTr('queuePassedHelp')+(Number(item.post_id||0)>0?(' · Post #'+String(item.post_id)):'');
            $('<div>').addClass('sales-vq-failure passed-copy').text(passedText).appendTo($row);
        }

        const $actions=$('<div>').addClass('sales-vq-actions');
        if(vqIsErrorStatus(status)){
            $('<button type="button" class="btn">').attr('data-vq-action','edit').text(salesTr('queueEdit')).appendTo($actions);
            if(status!=='invalid')$('<button type="button" class="btn">').attr('data-vq-action','retry').text(salesTr('queueRetry')).appendTo($actions);
        }
        if(status==='waiting'||vqIsErrorStatus(status)){
            $('<button type="button" class="btn danger-soft">').attr('data-vq-action','delete').text(salesTr('queueDelete')).appendTo($actions);
        }
        if($actions.children().length)$actions.appendTo($row);
        return $row;
    }
    function vqOpenDetail($row){
        const item=$row.data('vqItem')||{};
        const status=String(item.status||'waiting').toLowerCase();
        const $modal=$('[data-vq-detail-modal]').first();
        if(!$modal.length)return;
        const platform=platformLabel(item.platform)||item.platform||'Marketplace';
        const rawUrl=String(item.canonical_url||item.submitted_url||'');
        const parsed=vqValidUrl(rawUrl);
        const title=String(item.result_title||'').trim()||'Queued listing';
        const date=String(item.result_published_date||item.updated_at||item.queued_at||'—');
        let message='Background verification is waiting to start.';
        if(status==='verifying')message='Background verification is running now.';
        if(status==='passed')message=salesTr('queuePassedHelp');
        if(vqIsErrorStatus(status)){
            const type=vqErrorType(item);
            const detail=String(item.failure_message||'').trim();
            message=detail?(type.label+': '+detail):type.label;
        }
        const displayStatus=vqDisplayStatus(item);
        $modal.find('[data-vq-detail-platform]').text(platform);
        $modal.find('[data-vq-detail-platform-value]').text(platform);
        $modal.find('[data-vq-detail-heading]').text('Verification details');
        $modal.find('[data-vq-detail-status]').attr('class','sales-post-detail-status sales-vq-detail-status '+status).text(displayStatus);
        $modal.find('[data-vq-detail-status-value]').text(displayStatus);
        $modal.find('[data-vq-detail-date]').text(date);
        $modal.find('[data-vq-detail-title]').text(title);
        $modal.find('[data-vq-detail-message]').text(message);
        $modal.find('[data-vq-detail-post-id]').text(item.external_post_id||'—');
        const $url=$modal.find('[data-vq-detail-url]');
        const $open=$modal.find('[data-vq-detail-open]');
        if(parsed){
            $url.attr('href',parsed.href).text(parsed.href);
            $open.attr('href',parsed.href).removeClass('disabled').attr('aria-disabled','false');
        }else{
            $url.attr('href','#').text(rawUrl||'—');
            $open.attr('href','#').addClass('disabled').attr('aria-disabled','true');
        }
        $modal.removeClass('hidden').attr('aria-hidden','false');
        $('body').addClass('sales-detail-open');
        window.setTimeout(function(){$modal.find('[data-vq-detail-close]').first().trigger('focus');},0);
    }
    function vqCloseDetail($modal){
        $modal=$modal&&$modal.length?$modal:$('[data-vq-detail-modal]').first();
        $modal.addClass('hidden').attr('aria-hidden','true');
        $('body').removeClass('sales-detail-open');
    }

    function vqMarkPassedFormalPost($post){
        if(!$post||!$post.length)return false;
        $post.addClass('sales-verification-passed-post');
        if($post[0]&&typeof $post[0].scrollIntoView==='function'){
            try{$post[0].scrollIntoView({behavior:'smooth',block:'center',inline:'nearest'});}catch(error){$post[0].scrollIntoView();}
        }
        window.setTimeout(function(){$post.removeClass('sales-verification-passed-post');},8000);
        return true;
    }

    function vqRevealPassedPost(item,$panel){
        item=item||{};
        const postId=Number(item.post_id||0);
        if(!postId){
            if($panel&&$panel.length)vqMessage($panel,'This Passed record does not have a saved Post ID.','error');
            return;
        }
        const selector='.sales-self-post-card[data-sales-post-id="'+String(postId)+'"]';
        const revealLoaded=function(){return vqMarkPassedFormalPost($(selector).first());};
        if(revealLoaded())return;

        // If the saved Post is outside the currently loaded range, use the existing
        // Sales AJAX range loader, then mark/scroll the real Post card when it arrives.
        const published=String(item.result_published_date||'').trim();
        const $dashboard=$('#salesPortalDashboard');
        if(!$dashboard.length||!published){
            if($panel&&$panel.length)vqMessage($panel,'Post #'+String(postId)+' is saved in Posts.','ok');
            return;
        }
        $('#salesRangeFrom').val(published);
        $('#salesRangeTo').val(published);
        syncSalesRangeConstraints('');
        setSalesRangePeriod('custom');
        renderSalesChart();
        loadSalesRange({from:published,to:published},'custom',salesPlatformFilter,'verification-passed-reveal');

        let tries=0;
        const timer=window.setInterval(function(){
            tries++;
            if(revealLoaded()||tries>=15){
                window.clearInterval(timer);
                if(tries>=15&&!$(selector).length&&$panel&&$panel.length){
                    vqMessage($panel,'Post #'+String(postId)+' is saved, but it is not visible in the current Posts view.','ok');
                }
            }
        },200);
    }

    function vqAcknowledgePassed($row){
        const item=$row.data('vqItem')||{};
        if(String(item.status||'').toLowerCase()!=='passed')return;
        const id=Number(item.id||$row.data('vq-id')||0);
        const $panel=$row.closest('[data-verification-queue-panel]');
        if(!id||$row.data('vqAckBusy'))return;
        $row.data('vqAckBusy',true).addClass('is-acknowledging').attr('aria-busy','true');
        $.ajax({
            url:window.CD_BASE_PATH+'/api/verification-queue/delete',
            method:'POST',dataType:'json',
            data:{_csrf:vqCsrf(),id:id},
            headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}
        }).done(function(){
            $row.stop(true,true).fadeOut(120,function(){$row.remove();});
            vqRevealPassedPost(item,$panel);
            vqLoadAll(false);
        }).fail(function(xhr){
            $row.data('vqAckBusy',false).removeClass('is-acknowledging').removeAttr('aria-busy');
            vqMessage($panel,(xhr.responseJSON&&xhr.responseJSON.message)||'Passed item could not be cleared.','error');
        });
    }

    function vqFilterMatches(item,filter){
        const status=String((item&&item.status)||'').toLowerCase();
        if(filter==='all')return true;
        if(filter==='error'||filter==='errors'||filter==='needs_action')return vqIsErrorStatus(status);
        return status===filter;
    }
    function vqNormalizeItems(raw){
        if(Array.isArray(raw))return raw;
        if(raw&&typeof raw==='object')return Object.keys(raw).map(function(key){return raw[key];});
        return [];
    }
    function vqRefreshFormalPostsAfterPass(){
        const $dashboard=$('#salesPortalDashboard');
        if(!$dashboard.length||typeof loadSalesRange!=='function')return;
        const from=String($('#salesRangeFrom').val()||$('#dailyPosts').attr('data-from')||$dashboard.attr('data-from')||'').trim();
        const to=String($('#salesRangeTo').val()||$('#dailyPosts').attr('data-to')||$dashboard.attr('data-to')||'').trim();
        if(!from||!to)return;
        const period=String($dashboard.attr('data-range-period')||salesRangePeriod||'custom');
        const channel=String($dashboard.attr('data-channel')||salesPlatformFilter||'all');
        loadSalesRange({from:from,to:to},period,channel,'verification-passed');
    }
    function vqRenderPanel($panel,data){
        const counts=data&&data.counts?data.counts:{};
        const previousPassed=$panel.data('vqPassedCount');
        const currentPassed=Number(counts.passed||0);
        const passedAdvanced=previousPassed!==undefined&&currentPassed>Number(previousPassed||0);
        $panel.data('vqPassedCount',currentPassed);
        Object.keys(counts).forEach(function(key){$panel.find('[data-vq-count="'+key+'"]').text(String(counts[key]||0));});
        const $list=$panel.find('[data-vq-list]').empty();
        const items=vqNormalizeItems(data&&data.items);
        items.forEach(function(item){
            try{
                vqRenderRow(item).appendTo($list);
            }catch(error){
                // Never turn a real queued record into an apparently empty queue
                // because one optional display field failed to render.
                if(window.console&&console.error)console.error('Verification Queue row render failed',error,item);
                const fallbackStatus=String((item&&item.status)||'waiting').toLowerCase();
                const fallbackType=vqIsErrorStatus(fallbackStatus)?vqErrorType(item||{}):null;
                const fallbackClasses='sales-vq-row sales-vq-card status-'+fallbackStatus+(fallbackType?' is-error error-type-'+fallbackType.slug:'');
                const $fallback=$('<article>').addClass(fallbackClasses)
                    .attr({'data-vq-id':String((item&&item.id)||''),'role':'button','tabindex':'0','aria-label':'Open verification details'})
                    .data('vqItem',item||{});
                const $fallbackTop=$('<div>').addClass('sales-vq-card-top');
                $('<span>').addClass('sales-vq-platform').text(platformLabel(item&&item.platform)||(item&&item.platform)||'Marketplace').appendTo($fallbackTop);
                $('<span>').addClass('sales-vq-status '+fallbackStatus+(fallbackType?' error-label error-type-'+fallbackType.slug:'')).attr('title',fallbackType?fallbackType.label:vqStatusLabel(fallbackStatus)).text(fallbackType?fallbackType.label:vqStatusLabel(fallbackStatus)).appendTo($fallbackTop);
                $fallbackTop.appendTo($fallback);
                $('<div>').addClass('sales-vq-url').text(vqShortUrl((item&&item.canonical_url)||(item&&item.submitted_url)||'Queued listing')).appendTo($fallback);
                $('<div>').addClass('sales-vq-meta').text('Queued item').appendTo($fallback);
                $fallback.appendTo($list);
            }
        });
        $panel.find('[data-vq-empty]').toggleClass('hidden',$list.children().length>0);
        $panel.data('vqCounts',counts);
        if(passedAdvanced){
            // A Passed queue item is already a formal counted Post. Refresh the
            // normal Post grid/counts with the existing AJAX range loader, then
            // keep the Queue compact when no active/actionable items remain.
            vqRefreshFormalPostsAfterPass();
            const filter=String($panel.attr('data-vq-current-filter')||'all');
            if(filter==='all'&&Number(counts.all||0)===0)vqSetCollapsed($panel,true,false);
        }
    }
    function vqShowAcceptedItem(item,counts){
        if(!item||!item.id)return;
        vqRevealAfterSubmission();
        $('[data-verification-queue-panel]').each(function(){
            const $panel=$(this);
            if(counts&&typeof counts==='object'){
                Object.keys(counts).forEach(function(key){$panel.find('[data-vq-count="'+key+'"]').text(String(counts[key]||0));});
                $panel.data('vqCounts',counts);
            }
            const filter=String($panel.attr('data-vq-current-filter')||'all');
            if(!vqFilterMatches(item,filter))return;
            const $list=$panel.find('[data-vq-list]');
            $list.find('[data-vq-id="'+String(item.id)+'"]').remove();
            try{vqRenderRow(item).prependTo($list);}catch(error){if(window.console&&console.error)console.error('Verification Queue immediate row render failed',error,item);}
            $panel.find('[data-vq-empty]').toggleClass('hidden',$list.children().length>0);
        });
    }
    function vqSchedulePoll(){
        if(vqPollTimer){clearTimeout(vqPollTimer);vqPollTimer=null;}
        let active=false;
        $('[data-verification-queue-panel]').each(function(){
            const counts=$(this).data('vqCounts')||{};
            if(Number(counts.waiting||0)>0||Number(counts.verifying||0)>0)active=true;
        });
        if(active)vqPollTimer=setTimeout(function(){vqLoadAll(false);},2500);
    }
    function vqLoadPanel($panel,showError){
        const filter=String($panel.attr('data-vq-current-filter')||'all');
        return $.ajax({
            url:window.CD_BASE_PATH+'/api/verification-queue',method:'GET',dataType:'json',cache:false,data:{status:filter},
            headers:{'Accept':'application/json'}
        }).done(function(data){vqRenderPanel($panel,data);if(showError)vqMessage($panel,'','ok');})
        .fail(function(xhr){if(showError)vqMessage($panel,(xhr.responseJSON&&xhr.responseJSON.message)||'Verification Queue could not be loaded.','error');});
    }
    function vqLoadAll(showError){
        const requests=[];$('[data-verification-queue-panel]').each(function(){requests.push(vqLoadPanel($(this),showError));});
        $.when.apply($,requests).always(vqSchedulePoll);
    }
    function vqPost(path,data,$panel,success){
        data=data||{};data._csrf=vqCsrf();
        return $.ajax({url:window.CD_BASE_PATH+path,method:'POST',dataType:'json',data:data,headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}})
        .done(function(resp){if($panel&&$panel.length)vqMessage($panel,resp.message||'Updated.','ok');if(success)success(resp);vqLoadAll(false);})
        .fail(function(xhr){const msg=(xhr.responseJSON&&xhr.responseJSON.message)||'Request failed.';if($panel&&$panel.length)vqMessage($panel,msg,'error');else setSalesSubmitMessage(msg,'error');if(xhr.responseJSON&&xhr.responseJSON.duplicate_url)setSalesDuplicateSource(xhr.responseJSON.duplicate_url,xhr.responseJSON.duplicate_kind);});
    }

    $('#continueVerifyButton').on('click',function(){
        if(salesInspectionBusy)return;
        if($('#salesPreflightActions').hasClass('hidden'))return;
        salesContinueAfterPreflight=true;
        $('#salesPreflightActions').addClass('hidden');
        $('#postUrl').prop('readonly',false);
        $('#inspectForm').trigger('submit');
    });

    $('#saveWaitButton').on('click',function(){
        if(salesInspectionBusy)return;
        const url=String($('#postUrl').val()||'').trim();
        if(!updateDetectedPlatform()){setSalesSubmitMessage(salesTr('useSupported'),'error');return;}
        const $btn=$(this).prop('disabled',true).text(salesTr('checking'));
        setSalesSubmitMessage('Checking platform and hard duplicate…','warning');
        vqPost('/api/verification-queue/enqueue',{url:url},$(),function(resp){
            // Show the accepted queue record immediately; the background refresh then
            // reconciles it with the worker's latest status.
            vqShowAcceptedItem(resp.item,resp.counts);
            if(resp.accepted===false){
                setSalesSubmitMessage(resp.message||'Could not add this listing to the Verification Queue.','warning');
                return;
            }

            // v0.2.105: Save & Wait is an AJAX hand-off. Once the queue accepts
            // the listing, close the Submit Post popup immediately so Sales can
            // continue from the Dashboard without dismissing a stale modal.
            $('#salesPreflightActions').addClass('hidden');
            setInspectionStep('fetch','skipped','Background');
            setInspectionStep('date','skipped','Background');
            setInspectionStep('final','skipped','Background');
            $('#postUrl').val('').prop('readonly',false).removeClass('field-error');
            updateDetectedPlatform();
            setSalesSubmitMessage('',null);
            closeSalesSubmitModal();
        }).always(function(){updateDetectedPlatform();$btn.text(salesTr('saveAndWait'));});
    });

    $('#bulkQueueButton').on('click',function(){
        const urls=String($('#salesBulkUrls').val()||'').trim();
        if(!urls){$('#salesBulkResult').removeClass('hidden').text('Paste at least one listing URL.');return;}
        const $button=$(this).prop('disabled',true).text(salesTr('checking'));
        $.ajax({
            url:window.CD_BASE_PATH+'/api/verification-queue/bulk',method:'POST',dataType:'json',
            data:{_csrf:vqCsrf(),urls:urls},headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}
        }).done(function(resp){
            // v0.2.111: Bulk Submit is an AJAX hand-off just like Save & Wait.
            // Once the server accepts and classifies the batch, close the popup;
            // the Verification Queue on the Dashboard shows queued/error results.
            $('#salesBulkResult').removeClass('hidden').text(resp.message||'Bulk Submit complete.');
            $('#salesBulkUrls').val('');
            vqRevealAfterSubmission();
            vqLoadAll(false);
            closeSalesBulkSubmitModal();
        }).fail(function(xhr){$('#salesBulkResult').removeClass('hidden').text((xhr.responseJSON&&xhr.responseJSON.message)||'Bulk Submit failed.');})
        .always(function(){$button.prop('disabled',false).text(salesTr('bulkSubmitPost'));});
    });

    // v0.2.112: the entire Verification Queue header is the open/close target.
    // Keep Refresh / arrow and any other interactive controls independent so a
    // button click never toggles the panel a second time.
    $(document).on('click','[data-verification-queue-panel] .sales-verification-queue-head',function(event){
        if($(event.target).closest('button,a,input,textarea,select,label').length)return;
        const $panel=$(this).closest('[data-verification-queue-panel]');
        vqSetCollapsed($panel,!$panel.hasClass('is-collapsed'),true);
    });
    $(document).on('keydown','[data-verification-queue-panel] [data-vq-title-toggle]',function(event){
        if(event.key!=='Enter'&&event.key!==' ')return;
        event.preventDefault();
        const $panel=$(this).closest('[data-verification-queue-panel]');
        vqSetCollapsed($panel,!$panel.hasClass('is-collapsed'),true);
    });

    $(document).on('click','[data-verification-queue-panel] [data-vq-collapse-toggle]',function(){
        const $panel=$(this).closest('[data-verification-queue-panel]');
        vqSetCollapsed($panel,!$panel.hasClass('is-collapsed'),true);
    });

    $(document).on('click','[data-verification-queue-panel] [data-vq-filter]',function(){
        const $button=$(this),$panel=$button.closest('[data-verification-queue-panel]');
        $panel.attr('data-vq-current-filter',String($button.data('vq-filter')||'all'));
        $panel.find('[data-vq-filter]').removeClass('active');$button.addClass('active');
        vqLoadPanel($panel,true).always(vqSchedulePoll);
    });
    $(document).on('click','[data-verification-queue-panel] [data-verification-queue-refresh]',function(){vqLoadPanel($(this).closest('[data-verification-queue-panel]'),true).always(vqSchedulePoll);});

    $(document).on('click','.sales-vq-row',function(event){
        if($(event.target).closest('a,button,input,textarea,select,label').length)return;
        const item=$(this).data('vqItem')||{};
        if(String(item.status||'').toLowerCase()==='passed'){vqAcknowledgePassed($(this));return;}
        vqOpenDetail($(this));
    });
    $(document).on('keydown','.sales-vq-row',function(event){
        if($(event.target).closest('a,button,input,textarea,select').length)return;
        if(event.key!=='Enter'&&event.key!==' ')return;
        event.preventDefault();
        const item=$(this).data('vqItem')||{};
        if(String(item.status||'').toLowerCase()==='passed'){vqAcknowledgePassed($(this));return;}
        vqOpenDetail($(this));
    });
    $(document).on('click','[data-vq-detail-close]',function(){vqCloseDetail($(this).closest('[data-vq-detail-modal]'));});
    $(document).on('click','[data-vq-detail-modal]',function(event){if(event.target===this)vqCloseDetail($(this));});
    $(document).on('keydown',function(event){if(event.key==='Escape'&&!$('[data-vq-detail-modal]').first().hasClass('hidden'))vqCloseDetail($('[data-vq-detail-modal]').first());});

    $(document).on('click','[data-vq-action]',function(){
        const $button=$(this),action=String($button.data('vq-action')||''),$row=$button.closest('.sales-vq-row'),id=Number($row.data('vq-id')||0),$panel=$row.closest('[data-verification-queue-panel]');
        if(!id)return;
        if(action==='retry'){
            $button.prop('disabled',true);vqPost('/api/verification-queue/retry',{id:id},$panel).always(function(){$button.prop('disabled',false);});return;
        }
        if(action==='delete'){
            // v0.2.111: Queue errors are disposable working records, not counted Posts.
            // Delete them directly without a browser confirmation dialog.
            $button.prop('disabled',true);vqPost('/api/verification-queue/delete',{id:id},$panel);return;
        }
        if(action==='edit'){
            // v0.2.124: edit mode must expose a full-width editable URL field.
            // The old 3-column grid let the two buttons squeeze the URL input to
            // nearly zero width on queue cards, making Edit & Re-verify look like
            // an immediate Save action. Platform/Post ID remain derived from URL.
            const existing=$row.find('.sales-vq-edit');
            if(existing.length){existing.remove();$row.removeClass('is-editing');return;}
            const item=$row.data('vqItem')||{};
            const current=String(item.submitted_url||item.canonical_url||$row.find('.sales-vq-url a').attr('href')||'').trim();
            const $edit=$('<div>').addClass('sales-vq-edit');
            const $field=$('<div>').addClass('sales-vq-edit-field').appendTo($edit);
            $('<label>').addClass('sales-vq-edit-label').attr('for','vq-edit-url-'+String(id)).text(salesTr('originalUrl')).appendTo($field);
            $('<input type="url">')
                .attr({id:'vq-edit-url-'+String(id),'data-vq-edit-url':'1',autocomplete:'off',spellcheck:'false'})
                .val(current)
                .appendTo($field);
            $('<span>').addClass('sales-vq-edit-help').text(salesTr('queueEditUrlHelp')).appendTo($field);

            const $preview=$('<div>').addClass('sales-vq-edit-preview').attr('aria-live','polite').appendTo($edit);
            const $platformPreview=$('<span>').appendTo($preview);
            $('<b>').text(salesTr('platform')+':').appendTo($platformPreview);
            $('<em>').attr('data-vq-edit-platform','1').text('—').appendTo($platformPreview);
            const $idPreview=$('<span>').appendTo($preview);
            $('<b>').text(salesTr('postId')+':').appendTo($idPreview);
            $('<em>').attr('data-vq-edit-post-id','1').text('—').appendTo($idPreview);

            const $editActions=$('<div>').addClass('sales-vq-edit-actions').appendTo($edit);
            $('<button type="button" class="btn primary">').attr('data-vq-edit-save','1').text(salesTr('queueSaveReverify')).appendTo($editActions);
            $('<button type="button" class="btn">').attr('data-vq-edit-cancel','1').text(salesTr('cancel')).appendTo($editActions);
            $row.addClass('is-editing');
            $edit.appendTo($row);
            vqRefreshEditPreview($edit);
            $edit.find('[data-vq-edit-url]').trigger('focus').trigger('select');
            return;
        }
    });
    $(document).on('input change','[data-vq-edit-url]',function(){vqRefreshEditPreview($(this).closest('.sales-vq-edit'));});
    $(document).on('click','[data-vq-edit-cancel]',function(){
        const $row=$(this).closest('.sales-vq-row');
        $(this).closest('.sales-vq-edit').remove();
        $row.removeClass('is-editing');
    });
    $(document).on('click','[data-vq-edit-save]',function(){
        const $button=$(this),$edit=$button.closest('.sales-vq-edit'),$row=$edit.closest('.sales-vq-row'),id=Number($row.data('vq-id')||0),url=String($edit.find('[data-vq-edit-url]').val()||'').trim(),$panel=$row.closest('[data-verification-queue-panel]');
        if(!url){vqMessage($panel,'Enter a listing URL.','error');$edit.find('[data-vq-edit-url]').trigger('focus');return;}
        const platform=detectPlatform(url);
        if(!platform){vqMessage($panel,salesTr('unsupportedUrl'),'error');$edit.find('[data-vq-edit-url]').trigger('focus');return;}
        $button.prop('disabled',true);
        vqPost('/api/verification-queue/update',{id:id,url:url},$panel).fail(function(){$button.prop('disabled',false);});
    });

    window.cdspRefreshVerificationQueue=function(){vqLoadAll(false);};
    if($('[data-verification-queue-panel]').length){
        vqInitCollapseState();
        vqLoadAll(false);
    }
})();

});

/* v0.2.34 — persistent website scan jobs + inline product grid / 持久化网站扫描与内联产品网格 */
(function($){
    'use strict';
    if(!$){return;}

    // v0.2.90: this scanner is intentionally outside the large document-ready
    // closure above, so it must own its HTML escaper too. The older code called
    // escapeHtml() from here even though that helper was scoped inside the closed
    // ready callback. Clicking Scan Website therefore threw ReferenceError before
    // the placeholder, Starting state, or /scan-start request could happen.
    function escapeHtml(value){
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    const $manager=$('.website-source-manager,#website-source-detail').first();
    if(!$manager.length){return;}

    const endpoints={
        search:String($manager.data('reference-search-url')||''),
        add:String($manager.data('reference-add-url')||''),
        delete:String($manager.data('reference-delete-url')||''),
        start:String($manager.data('scan-start-url')||''),
        step:String($manager.data('scan-step-url')||''),
        status:String($manager.data('scan-status-url')||''),
        stop:String($manager.data('scan-stop-url')||''),
        resume:String($manager.data('scan-resume-url')||'')
    };
    const csrf=String($manager.data('csrf')||'');
    // v0.2.88: loop ownership is per persisted History run, not merely per host.
    // A previous run for the same website must never block or cancel a newly-created run.
    const loops={}; // host => history_id
    const stepRequests={}; // host => {historyId, xhr}
    const watchdogGraceUntil={};
    const runningHosts={};
    const historyItemLast={};
    const historyItemsLoaded={};
    // v0.2.91: a user's manual collapse wins over live polling. A running row
    // may start open, but once the user closes it the scanner must not reopen it.
    const historyUserCollapsed={};
    let $activePanel=$('[data-products-library-panel]').first();
    let activeHost=String($activePanel.find('.website-products-host-select').val()||'').toLowerCase();

    // v0.2.39: one active website scan at a time. The backend enforces the
    // same rule; this client state keeps every Scan/Delete control honest.
    try{
        const initial=JSON.parse(String($manager.attr('data-running-hosts')||'[]'));
        if(Array.isArray(initial)){initial.forEach(function(host){host=String(host||'').toLowerCase();if(host){runningHosts[host]=true;}});}
    }catch(e){}

    function websiteForButton($button){
        const selector=String($button.data('website-input')||'').trim();
        if(selector){return String($(selector).val()||'').trim();}
        return String($button.data('website-url')||$button.closest('.website-product-source').data('website-url')||'').trim();
    }

    function hostForWebsite(website){
        try{return String((new URL(String(website||''))).hostname||'').toLowerCase();}catch(e){return '';}
    }

    function hostForButton($button){
        const direct=String($button.data('source-host')||$button.closest('.website-product-source').data('website-source')||'').toLowerCase();
        if(direct){return direct;}
        return hostForWebsite(websiteForButton($button));
    }

    function runningHostList(){return Object.keys(runningHosts).filter(function(host){return !!runningHosts[host];});}

    function syncGlobalScanControls(){
        const active=runningHostList();
        const any=active.length>0;
        const label=active.length===1?active[0]:(active.length+' websites');
        $('.website-product-scan-button').each(function(){
            const $button=$(this);
            // Step 1's URL field stays available so it can reveal an already-saved
            // website card. Server-side start still enforces one running scan globally.
            if(String($button.data('website-input')||'').trim()){
                $button.prop('disabled',false).removeAttr('data-global-scan-disabled').removeAttr('title');
                return;
            }
            const host=hostForButton($button);const same=!!(host&&runningHosts[host]);
            const block=any&&!same;
            if(block){
                $button.prop('disabled',true).attr('data-global-scan-disabled','1').attr('title','Currently scanning '+label+'. Stop it or pause it from Scan History first.');
            }else if($button.attr('data-global-scan-disabled')==='1'){
                $button.prop('disabled',false).removeAttr('data-global-scan-disabled').removeAttr('title');
            }
        });
        $('.website-source-delete').each(function(){
            const $button=$(this);
            if(any){$button.prop('disabled',true).attr('data-global-scan-disabled','1').attr('title','Stop or pause the active website scan before deleting any website.');}
            else if($button.attr('data-global-scan-disabled')==='1'){$button.prop('disabled',false).removeAttr('data-global-scan-disabled').removeAttr('title');}
        });
        $('.website-source-manager').toggleClass('has-active-website-scan',any);
    }

    function updateRunningHost(host,isRunning){
        host=String(host||'').toLowerCase();if(!host){return;}
        if(isRunning){runningHosts[host]=true;}else{delete runningHosts[host];}
        syncGlobalScanControls();
    }

    function sourceCard(host){
        host=String(host||'').trim().toLowerCase();
        if(!host){return $();}
        // V0.2.89: never depend on CSS selector escaping to identify a website
        // card. Compare the literal data value so hosts containing dots, dashes,
        // IDN/punycode text, etc. cannot make Scan History disappear.
        return $('.website-product-source').filter(function(){
            return String($(this).attr('data-website-source')||$(this).data('website-source')||'').trim().toLowerCase()===host;
        }).first();
    }

    function cssEscape(value){
        if(window.CSS&&typeof window.CSS.escape==='function'){return window.CSS.escape(String(value));}
        return String(value).replace(/(["\\])/g,'\\$1');
    }

    function compactUrlLabel(value){
        const raw=String(value||'').trim();
        if(!raw){return 'Open URL ↗';}
        let label=raw.replace(/^https?:\/\//i,'').replace(/^www\./i,'');
        if(label.length>42){label=label.slice(0,25)+'…'+label.slice(-14);}
        return label;
    }

    function revealExistingSource(host,inputSelector){
        const $card=sourceCard(host);
        if(!$card.length){return false;}
        ensureWebsiteSourceCardOpen($card,true);
        if(inputSelector){$(inputSelector).val('');}
        const node=$card.get(0);
        if(node&&typeof node.scrollIntoView==='function'){
            try{node.scrollIntoView({behavior:'smooth',block:'center'});}catch(e){node.scrollIntoView();}
        }
        showToast('Website already saved. Opened its card.',false);
        return true;
    }

    function progressWrap(host,$button){
        let $wrap=$();
        if(host){
            $wrap=sourceCard(host).find('.website-product-scan-progress-wrap').first();
            if(!$wrap.length&&String($('#website-source-detail').data('source-host')||'')===host){$wrap=$('.website-source-detail-scan-progress').first();}
        }
        if(!$wrap.length&&$button&&$button.length){
            const selector=String($button.data('progress-target')||'').trim();
            if(selector){
                const $progress=$(selector).first();
                if($progress.length){
                    $wrap=$progress.closest('.website-product-scan-progress-wrap');
                    if(!$wrap.length){$wrap=$progress;}
                }
            }
        }
        if(!$wrap.length&&$button&&$button.length){$wrap=$button.closest('.website-source-add').find('.website-product-scan-progress-wrap').first();}
        return $wrap;
    }

    function showToast(message,isError){
        $('.website-scan-toast').remove();
        const $toast=$('<div class="website-scan-toast" role="status"></div>').toggleClass('is-error',!!isError).text(message);
        $('body').append($toast);
        window.setTimeout(function(){$toast.fadeOut(180,function(){$toast.remove();});},4500);
    }

    function scanErrorRows(errors){
        if(!Array.isArray(errors)||!errors.length){return '';}
        return '<details class="website-scan-errors" open>'
            +'<summary>'+errors.length+' recent page error'+(errors.length===1?'':'s')+' — click to review</summary>'
            +'<div class="website-scan-error-table">'
            +errors.map(function(row){
                const status=row.http_status?('HTTP '+Number(row.http_status)):'Scan error';
                return '<div class="website-scan-error-row">'
                    +'<div class="website-scan-error-status">'+escapeHtml(status)+'</div>'
                    +'<div class="website-scan-error-copy"><a href="'+escapeHtml(String(row.page_url||''))+'" target="_blank" rel="noopener noreferrer">'+escapeHtml(String(row.page_url||''))+'</a>'
                    +'<strong>'+escapeHtml(String(row.explanation||row.error_message||'The page could not be scanned.'))+'</strong>'
                    +'<small>'+escapeHtml(String(row.error_message||''))+'</small></div>'
                +'</div>';
            }).join('')
            +'</div></details>';
    }

    function historyDetailText(state){
        let detail='First images found '+Number(state.images_found||0)+' · Exact fingerprints '+Number(state.indexed||0);
        if(Number(state.skipped_existing||0)){detail+=' · Existing URLs skipped '+Number(state.skipped_existing||0);}
        detail+=' · Queue '+Number(state.queue||0);
        if(String(state.last_error||'').trim()){detail+=' · '+String(state.last_error||'').trim();}
        return detail;
    }

    function websiteScanIcon(name){
        const common='viewBox="0 0 24 24" aria-hidden="true" focusable="false"';
        if(name==='pause')return '<svg '+common+'><rect x="6" y="5" width="4" height="14" rx="1" fill="currentColor"></rect><rect x="14" y="5" width="4" height="14" rx="1" fill="currentColor"></rect></svg>';
        if(name==='play')return '<svg '+common+'><path d="M8 5.8v12.4c0 .9 1 1.4 1.7.9l9-6.2a1.1 1.1 0 0 0 0-1.8l-9-6.2A1.1 1.1 0 0 0 8 5.8Z" fill="currentColor"></path></svg>';
        if(name==='stop')return '<svg '+common+'><rect x="6" y="6" width="12" height="12" rx="2" fill="currentColor"></rect></svg>';
        if(name==='done')return '<svg '+common+'><path d="m5.5 12.5 4.1 4.1L18.7 7.5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"></path></svg>';
        if(name==='failed')return '<svg '+common+'><circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="2"></circle><path d="M12 7.5v6" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path><circle cx="12" cy="17" r="1.2" fill="currentColor"></circle></svg>';
        return '<svg '+common+'><circle cx="12" cy="12" r="2.5" fill="currentColor"></circle></svg>';
    }

    function historyStatusControl(status,host,historyId){
        status=String(status||'').toLowerCase();host=String(host||'').toLowerCase();historyId=Number(historyId||0);
        if(status==='running'){
            return '<button type="button" class="website-history-control is-running" data-history-scan-control data-history-action="pause" data-history-id="'+historyId+'" data-source-host="'+escapeHtml(host)+'" aria-label="Pause this scan" title="Pause this scan">'+websiteScanIcon('pause')+'</button>';
        }
        if(status==='paused'){
            return '<button type="button" class="website-history-control is-paused" data-history-scan-control data-history-action="resume" data-history-id="'+historyId+'" data-source-host="'+escapeHtml(host)+'" aria-label="Continue this scan" title="Continue this scan">'+websiteScanIcon('play')+'</button>';
        }
        if(status==='stopped')return '<span class="website-history-control is-stopped is-static" aria-label="Scan stopped" title="Scan stopped">'+websiteScanIcon('stop')+'</span>';
        if(status==='completed')return '<span class="website-history-control is-completed is-static" aria-label="Scan completed" title="Scan completed">'+websiteScanIcon('done')+'</span>';
        if(status==='failed')return '<span class="website-history-control is-failed is-static" aria-label="Scan failed" title="Scan failed">'+websiteScanIcon('failed')+'</span>';
        return '<span class="website-history-control is-static" aria-label="Scan status" title="Scan status">'+websiteScanIcon('dot')+'</span>';
    }

    function scanHistoryWrap(host,$context){
        host=String(host||'').trim().toLowerCase();
        if(!host){return $();}
        let $card=$();
        if($context&&$context.length){$card=$context.closest('.website-product-source');}
        if(!$card.length){$card=sourceCard(host);}

        // V0.2.89: the History table belongs to the card that contains the Scan
        // button. Find it directly first. Do not rebuild an attribute selector
        // from host text and then silently lose the task when that lookup fails.
        let $wrap=$card.find('[data-scan-history-host]').filter(function(){
            return String($(this).attr('data-scan-history-host')||'').trim().toLowerCase()===host;
        }).first();
        if(!$wrap.length){
            $wrap=$('[data-scan-history-host]').filter(function(){
                return String($(this).attr('data-scan-history-host')||'').trim().toLowerCase()===host;
            }).first();
        }
        return $wrap;
    }

    function scanHistoryBody(host,$context){
        host=String(host||'').trim().toLowerCase();
        let $card=$();
        if($context&&$context.length){$card=$context.closest('.website-product-source');}
        if(!$card.length){$card=sourceCard(host);}
        // The direct card -> tbody path is the authoritative locator.
        let $body=$card.find('[data-scan-history-body]').first();
        if($body.length){return $body;}
        const $wrap=scanHistoryWrap(host,$context);
        return $wrap.find('[data-scan-history-body]').first();
    }

    function startHistoryPlaceholder(host,website,$button){
        host=String(host||'').toLowerCase();
        if(!host){return false;}
        // V0.2.89: a click must make the History task visible immediately and
        // keep the exact clicked card as its rendering context.
        let $card=$button&&$button.length?$button.closest('.website-product-source'):$();
        if(!$card.length){$card=sourceCard(host);}
        if($card.length){
            $card.addClass('is-expanded');
            $card.find('.website-source-expand').attr('aria-expanded','true');
            $card.find('[data-website-source-detail]').first().stop(true,true).removeClass('hidden').show();
        }
        const $body=scanHistoryBody(host,$button);
        if(!$body.length){return false;}
        $body.find('[data-history-empty-row]').remove();
        $body.find('[data-history-start-placeholder],[data-history-start-placeholder-detail]').filter(function(){
            return String($(this).attr('data-history-start-placeholder')||$(this).attr('data-history-start-placeholder-detail')||'').trim().toLowerCase()===host;
        }).remove();
        const now=(new Date()).toLocaleString();
        const row='<tr class="website-history-main-row is-expanded is-starting" data-history-start-placeholder="'+escapeHtml(host)+'" aria-expanded="true">'
            +'<td>'+escapeHtml(now)+'</td>'
            +'<td data-history-status-cell><span class="website-history-control is-running is-static" aria-label="Starting scan" title="Starting scan">'+websiteScanIcon('dot')+'</span></td>'
            +'<td>0</td><td>0</td><td>0</td>'
            +'<td class="website-history-details-summary"><span>Creating scan run…</span><span class="website-history-row-chevron" aria-hidden="true"></span></td></tr>'
            +'<tr class="website-history-detail-row" data-history-start-placeholder-detail="'+escapeHtml(host)+'"><td colspan="6"><div class="website-history-detail-panel">'
            +'<div class="website-history-detail-head"><strong>Processing log</strong><small>The run is being created now.</small></div>'
            +'<a href="'+escapeHtml(String(website||''))+'" target="_blank" rel="noopener noreferrer">'+escapeHtml(String(website||''))+'</a>'
            +'<div class="website-history-run-summary">Creating persisted History run…</div>'
            +'<div class="website-history-processing-head"><span>Time</span><span>Result</span><span>URL</span><span>Details</span></div>'
            +'<div class="website-history-processing-log"><div class="website-history-processing-empty">Creating scan run…</div></div>'
            +'</div></td></tr>';
        $body.prepend(row);
        const count=$body.find('[data-scan-history-row]').length+1;
        $body.closest('.website-source-card-history').find('[data-scan-history-count]').first().text(count);
        return true;
    }

    function removeStartHistoryPlaceholder(host,$context){
        host=String(host||'').toLowerCase();if(!host){return;}
        const $body=scanHistoryBody(host,$context);
        if(!$body.length){return;}
        $body.find('[data-history-start-placeholder],[data-history-start-placeholder-detail]').filter(function(){
            return String($(this).attr('data-history-start-placeholder')||$(this).attr('data-history-start-placeholder-detail')||'').trim().toLowerCase()===host;
        }).remove();
        const count=$body.find('[data-scan-history-row]').length;
        $body.closest('.website-source-card-history').find('[data-scan-history-count]').first().text(count);
        if(!count){$body.html('<tr class="website-history-empty-row" data-history-empty-row><td colspan="6">No Website Scan history yet.</td></tr>');}
    }

    function ensureHistoryRow(state,$context){
        const historyId=Number(state.history_id||0);const host=String(state.source_host||'').toLowerCase();
        if(historyId<1||!host){return $();}
        let $row=$('[data-scan-history-row][data-website-history-id="'+historyId+'"]').first();
        if($row.length){return $row;}
        const $body=scanHistoryBody(host,$context);
        if(!$body.length){return $();}
        $body.find('[data-history-empty-row]').remove();
        const started=escapeHtml(String(state.created_at||'—'));
        const summary=historyDetailText(state);
        const running=String(state.status||'').toLowerCase()==='running';
        const rowHtml='<tr class="website-history-main-row'+(running?' is-expanded':'')+'" data-scan-history-row data-website-history-id="'+historyId+'" data-history-source-host="'+escapeHtml(host)+'" tabindex="0" aria-expanded="'+(running?'true':'false')+'">'
            +'<td>'+started+'</td><td data-history-status-cell>'+historyStatusControl(String(state.status||''),host,historyId)+'</td>'
            +'<td data-history-processed>'+Number(state.checked||0)+'</td><td data-history-saved>'+Number(state.products||0)+'</td><td data-history-failed>'+Number(state.failed||0)+'</td>'
            +'<td class="website-history-details-summary"><span data-history-detail-summary>'+escapeHtml(summary)+'</span><span class="website-history-row-chevron" aria-hidden="true"></span></td></tr>'
            +'<tr class="website-history-detail-row'+(running?'':' hidden')+'" data-history-detail-row="'+historyId+'"><td colspan="6"><div class="website-history-detail-panel">'
            +'<div class="website-history-detail-head"><strong>Processing log</strong><small>Each scanned URL is recorded here as it finishes.</small></div>'
            +'<a data-history-source-link href="'+escapeHtml(String(state.website_url||''))+'" target="_blank" rel="noopener noreferrer">'+escapeHtml(String(state.website_url||''))+'</a>'
            +'<div class="website-history-run-summary" data-history-detail-text>'+escapeHtml(summary)+'</div>'
            +'<div class="website-history-processing-head"><span>Time</span><span>Result</span><span>URL</span><span>Details</span></div>'
            +'<div class="website-history-processing-log" data-history-processing-log data-history-id="'+historyId+'"><div class="website-history-processing-empty" data-history-processing-empty>Preparing first URL…</div></div>'
            +'<small>Live scan history</small></div></td></tr>';
        $body.prepend(rowHtml);
        $row=$body.find('[data-scan-history-row][data-website-history-id="'+historyId+'"]').first();
        const count=$body.find('[data-scan-history-row]').length;
        $body.closest('.website-source-card-history').find('[data-scan-history-count]').first().text(count);
        return $row;
    }

    function historyItemStatusLabel(item){
        const status=String(item.result_status||'checked').toLowerCase();
        if(status==='saved')return ['Saved','is-saved'];
        if(status==='skipped')return ['Skipped','is-skipped'];
        if(status==='failed')return ['Failed','is-failed'];
        if(status==='running')return ['Running','is-running'];
        if(status==='paused')return ['Paused','is-paused'];
        if(status==='stopped')return ['Stopped','is-stopped'];
        if(status==='completed')return ['Complete','is-completed'];
        return ['Checked','is-checked'];
    }

    function historyItemHtml(item){
        const id=Number(item.id||0);const status=historyItemStatusLabel(item);
        const url=String(item.page_url||'');const title=String(item.title||'');
        let detail=String(item.message||'');
        if(title){detail=(title+(detail?' · '+detail:''));}
        if(item.image_found){detail+=(detail?' · ':'')+'image found';}
        if(item.fingerprinted){detail+=(detail?' · ':'')+'fingerprinted';}
        return '<div class="website-history-processing-row" data-history-item-id="'+id+'">'
            +'<span class="website-history-processing-time">'+escapeHtml(String(item.created_at||''))+'</span>'
            +'<span class="website-history-processing-status '+status[1]+'">'+status[0]+'</span>'
            +'<a class="website-history-processing-url" href="'+escapeHtml(url)+'" title="'+escapeHtml(url)+'" target="_blank" rel="noopener noreferrer">'+escapeHtml(compactUrlLabel(url))+'</a>'
            +'<span class="website-history-processing-message" title="'+escapeHtml(detail||'—')+'">'+escapeHtml(detail||'—')+'</span>'
            +'</div>';
    }

    function updateActiveProcessingRow(state){
        const historyId=Number(state.history_id||0);if(historyId<1)return;
        const $log=$('[data-history-processing-log][data-history-id="'+historyId+'"]').first();if(!$log.length)return;
        $log.find('[data-history-processing-active]').remove();
        if(String(state.status||'').toLowerCase()!=='running'){return;}
        const nextUrl=String(state.next_url||'').trim();
        $log.find('[data-history-processing-empty]').remove();
        if(!nextUrl){
            $log.append('<div class="website-history-processing-empty" data-history-processing-active>Preparing next URL…</div>');
            return;
        }
        const active='<div class="website-history-processing-row is-active" data-history-processing-active>'
            +'<span class="website-history-processing-time">Now</span>'
            +'<span class="website-history-processing-status is-running">Scanning</span>'
            +'<a class="website-history-processing-url" href="'+escapeHtml(nextUrl)+'" title="'+escapeHtml(nextUrl)+'" target="_blank" rel="noopener noreferrer">'+escapeHtml(compactUrlLabel(nextUrl))+'</a>'
            +'<span class="website-history-processing-message">Request in progress…</span></div>';
        $log.append(active);
        const node=$log.get(0);if(node){node.scrollTop=node.scrollHeight;}
    }

    function appendHistoryItems(state,replaceAll){
        const historyId=Number(state.history_id||0);if(historyId<1)return;
        const items=Array.isArray(state.history_items)?state.history_items:[];
        const $log=$('[data-history-processing-log][data-history-id="'+historyId+'"]').first();if(!$log.length)return;
        const node=$log.get(0);
        const followTail=!!node&&(node.scrollTop+node.clientHeight>=node.scrollHeight-24);
        if(replaceAll){$log.empty();historyItemLast[historyId]=0;}
        items.forEach(function(item){
            const itemId=Number(item.id||0);if(itemId<1)return;
            // Processing Log is URL processing only. Run lifecycle events stay in
            // the History row/status and never create fake URL rows.
            const pageUrl=String(item.page_url||'').trim();
            const kind=String(item.result_kind||'').toLowerCase();
            historyItemLast[historyId]=Math.max(Number(historyItemLast[historyId]||0),itemId);
            if(!pageUrl||kind==='run'){return;}
            if($log.find('[data-history-item-id="'+itemId+'"]').length)return;
            $log.append(historyItemHtml(item));
        });
        if($log.find('[data-history-item-id]').length){
            $log.find('[data-history-processing-empty]').remove();
            if(followTail&&node){node.scrollTop=node.scrollHeight;}
        }else if(!$log.find('[data-history-processing-empty]').length){
            $log.html('<div class="website-history-processing-empty" data-history-processing-empty>No per-URL records yet.</div>');
        }
    }

    function loadHistoryItems(host,historyId){
        historyId=Number(historyId||0);if(historyId<1||historyItemsLoaded[historyId])return;
        historyItemsLoaded[historyId]=true;
        const $log=$('[data-history-processing-log][data-history-id="'+historyId+'"]').first();
        $.getJSON(endpoints.status,{host:host,history_id:historyId,after_item_id:0}).done(function(data){
            if(!data||!data.ok){
                historyItemsLoaded[historyId]=false;
                if($log.length){$log.html('<div class="website-history-processing-empty" data-history-processing-empty>Processing log could not be loaded.</div>');}
                return;
            }
            if(!data.state){
                if($log.length){$log.html('<div class="website-history-processing-empty" data-history-processing-empty>No per-URL processing records were stored for this older scan.</div>');}
                return;
            }
            updateHistoryRow(data.state,true);
            if($log.length&&!$log.find('[data-history-item-id]').length&&!$log.find('[data-history-processing-active]').length){
                const running=String(data.state.status||'')==='running';
                $log.html('<div class="website-history-processing-empty" data-history-processing-empty>'+(running?'Preparing next URL…':'No per-URL processing records were stored for this scan.')+'</div>');
            }
        }).fail(function(){
            historyItemsLoaded[historyId]=false;
            if($log.length){$log.html('<div class="website-history-processing-empty" data-history-processing-empty>Processing log could not be loaded.</div>');}
        });
    }

    function updateHistoryRow(state,replaceItems){
        const historyId=Number(state.history_id||0);const host=String(state.source_host||'').toLowerCase();
        if(historyId<1||!host){return;}
        const $row=ensureHistoryRow(state);if(!$row.length){return;}
        $row.find('[data-history-status-cell]').html(historyStatusControl(String(state.status||''),host,historyId));
        $row.find('[data-history-processed]').text(Number(state.checked||0));
        $row.find('[data-history-saved]').text(Number(state.products||0));
        $row.find('[data-history-failed]').text(Number(state.failed||0));
        const $detail=$('[data-history-detail-row="'+historyId+'"]').first();
        const summary=historyDetailText(state);
        $detail.find('[data-history-detail-text]').text(summary);
        $row.find('[data-history-detail-summary]').text(summary);
        const website=String(state.website_url||'');
        if(website){$detail.find('[data-history-source-link]').attr('href',website).text(website);}
        appendHistoryItems(state,!!replaceItems);
        updateActiveProcessingRow(state);
        if(String(state.status||'').toLowerCase()==='running'){
            if(!historyUserCollapsed[historyId]){
                $row.attr('aria-expanded','true').addClass('is-expanded');
                $detail.removeClass('hidden');
            }
            historyItemsLoaded[historyId]=true;
        }
    }

    function renderScanState(state,$button,showProgress){
        if(!state){return;}
        const host=String(state.source_host||'').toLowerCase();
        const $card=sourceCard(host);
        const status=String(state.status||'');
        const interrupted=!!state.client_interrupted;
        updateRunningHost(host,status==='running');
        const hasLibraryStats=!!state.library_stats;
        const stats=state.library_stats||{};

        // v0.2.84: scan processing belongs to its History run only.
        // There is intentionally no standalone "Scanning…" progress strip above History.
        if($card.length){
            if(hasLibraryStats){
                $card.find('[data-source-stat="products"]').text(Number(stats.total||0));
                $card.find('[data-source-stat="images-found"]').text(Number(stats.images_found||0));
                $card.find('[data-source-stat="indexed"]').text(Number(stats.indexed||0));
            }
            $card.find('[data-source-stat="checked"]').text(Number(state.checked||0));
            $card.find('[data-source-stat="skipped-existing"]').text(Number(state.skipped_existing||0));
            $card.find('[data-source-scan-state]').text(status==='running'&&!interrupted?'Website scanning':status==='completed'?'Scan complete':status==='paused'?'Scan paused':status==='stopped'?'Scan stopped':status==='failed'?'Scan failed':'Ready')
                .toggleClass('is-running',status==='running').toggleClass('is-error',status==='failed'||interrupted);
            const active=status==='running';
            $card.find('.website-product-scan-button').removeClass('hidden').prop('disabled',false)
                .attr('data-scan-action',active?'stop':'start').toggleClass('badbtn',active)
                .text(active?'Stop Scanning':'Scan Website');
            $card.find('.website-source-delete').prop('disabled',active).attr('title',active?'Stop or pause scanning before deleting this website.':'');
        }
        updateHistoryRow(state);
        const detailHost=String($('#website-source-detail').data('source-host')||'').toLowerCase();
        if(detailHost===host){
            const active=status==='running';
            $('.website-source-detail-head-actions .website-product-scan-button').removeClass('hidden').prop('disabled',false)
                .attr('data-scan-action',active?'stop':'start').toggleClass('badbtn',active)
                .text(active?'Stop Scanning':'Scan Website');
        }
    }

    function scanLoop(host,historyId){
        host=String(host||'').toLowerCase();
        historyId=Number(historyId||0);
        if(!host||historyId<1){return;}

        // v0.2.88: multiple History runs can exist for the same host. The old
        // boolean host loop let a stale/paused prior run leave loops[host]=true,
        // which made a brand-new run return here without ever sending scan-step.
        // Bind the browser worker to the exact persisted History id instead.
        const previousHistoryId=Number(loops[host]||0);
        if(previousHistoryId===historyId){return;}
        if(previousHistoryId>0&&previousHistoryId!==historyId){
            const previousRequest=stepRequests[host];
            if(previousRequest&&Number(previousRequest.historyId||0)===previousHistoryId
                &&previousRequest.xhr&&typeof previousRequest.xhr.abort==='function'){
                try{previousRequest.xhr.abort('scan-superseded');}catch(e){}
            }
        }
        loops[host]=historyId;

        function isCurrentRun(){return Number(loops[host]||0)===historyId;}
        function releaseCurrentRun(){if(isCurrentRun()){delete loops[host];}}

        function tick(){
            if(!isCurrentRun()){return;}
            watchdogGraceUntil[host]=Date.now()+50000;
            const xhr=$.ajax({
                url:endpoints.step,method:'POST',dataType:'json',timeout:45000,
                data:{_csrf:csrf,host:host,history_id:historyId,after_item_id:Number(historyItemLast[historyId]||0)},
                headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}
            });
            stepRequests[host]={historyId:historyId,xhr:xhr};
            xhr.done(function(data){
                // An older request may finish after a newer History run has started.
                // It is not allowed to repaint the UI or clear the new worker.
                if(!isCurrentRun()){return;}
                if(!data||!data.ok||!data.state){
                    releaseCurrentRun();showToast((data&&data.message)||'Website scan failed.',true);return;
                }
                const state=data.state;
                if(Number(state.history_id||0)!==historyId){
                    releaseCurrentRun();
                    showToast('Scanner returned a different History run. Refresh is not required; start the scan again.',true);
                    return;
                }
                renderScanState(state,null,false);
                if(String(state.status)==='running'){
                    window.setTimeout(tick,state.busy?700:220);
                    return;
                }
                releaseCurrentRun();
                if(String(state.status)==='completed'){
                    showToast('Scan complete: '+host,false);
                    if(activeHost===host){loadInlineProducts(host,'');}
                }
            }).fail(function(xhrFailed,textStatus){
                if(textStatus==='scan-superseded'){return;}
                if(!isCurrentRun()){return;}
                releaseCurrentRun();
                $.getJSON(endpoints.status,{host:host,history_id:historyId}).done(function(data){
                    if(data&&data.ok&&data.state&&Number(data.state.history_id||0)===historyId){
                        data.state.client_interrupted=true;
                        renderScanState(data.state,null,true);
                    }
                });
                const msg=(xhrFailed.responseJSON&&xhrFailed.responseJSON.message)||('Scanner connection interrupted'+(xhrFailed.status?' (HTTP '+xhrFailed.status+')':'')+'.');
                showToast(msg+' Progress is saved and status will retry automatically.',true);
            }).always(function(){
                const current=stepRequests[host];
                if(current&&Number(current.historyId||0)===historyId&&current.xhr===xhr){delete stepRequests[host];}
            });
        }
        tick();
    }

    function changeScanState(host,mode,$control){
        host=String(host||'').toLowerCase();if(!host)return;
        if($control&&$control.length){$control.prop('disabled',true).addClass('is-busy');}
        const historyId=$control&&$control.length?Number($control.data('history-id')||0):0;
        const activeHistoryId=Number(loops[host]||0);
        if(historyId<1||activeHistoryId===historyId){delete loops[host];}
        $.ajax({
            url:endpoints.stop,method:'POST',dataType:'json',
            data:{_csrf:csrf,host:host,mode:mode,history_id:historyId},
            headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}
        }).done(function(data){
            if(data&&data.ok&&data.state){
                renderScanState(data.state,null,true);
                showToast(mode==='stop'?'Scanning stopped.':'Scanning paused. Use ▶ in Scan History to continue.',false);
            }else{
                showToast((data&&data.message)||(mode==='stop'?'Could not stop scanning.':'Could not pause scanning.'),true);
            }
        }).fail(function(xhr){
            showToast((xhr.responseJSON&&xhr.responseJSON.message)||(mode==='stop'?'Could not stop scanning.':'Could not pause scanning.'),true);
        }).always(function(){
            if($control&&$control.length){$control.prop('disabled',false).removeClass('is-busy');}
        });
    }

    function resumeHistoryScan($control){
        const host=String($control.data('source-host')||'').toLowerCase();const historyId=Number($control.data('history-id')||0);
        if(!host||historyId<1)return;
        $control.prop('disabled',true).addClass('is-busy');
        $.ajax({url:endpoints.resume,method:'POST',dataType:'json',data:{_csrf:csrf,host:host,history_id:historyId},headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}})
            .done(function(data){
                if(data&&data.ok&&data.state){
                    renderScanState(data.state,null,true);
                    scanLoop(host,historyId);
                    showToast('Scanning continued from this History run.',false);
                }else{showToast((data&&data.message)||'Could not continue scanning.',true);}
            })
            .fail(function(xhr){showToast((xhr.responseJSON&&xhr.responseJSON.message)||'Could not continue scanning.',true);})
            .always(function(){$control.prop('disabled',false).removeClass('is-busy');});
    }

    function startScan($button){
        const requestedAction=String($button.attr('data-scan-action')||'start');
        if(requestedAction==='stop'){
            const stopHost=hostForButton($button);if(stopHost){changeScanState(stopHost,'stop',$button);}return;
        }
        let website=websiteForButton($button);
        if(!website){showToast('Enter a company website first.',true);return;}
        let parsed;
        try{parsed=new URL(website);}catch(e){showToast('Enter a valid HTTPS website URL.',true);return;}
        if(parsed.protocol!=='https:'){showToast('Company website scanning requires https://.',true);return;}
        const requestedHost=String(parsed.hostname||'').toLowerCase();
        const inputSelector=String($button.data('website-input')||'').trim();
        if(inputSelector){
            const $existing=sourceCard(requestedHost);
            if($existing.length){
                ensureWebsiteSourceCardOpen($existing,true);
                const savedUrl=String($existing.data('website-url')||'').trim();
                if(savedUrl){website=savedUrl;parsed=new URL(savedUrl);}
            }
        }
        const active=runningHostList();
        if(active.length&&!runningHosts[requestedHost]){
            showToast('Another website is already scanning: '+active[0]+'. Stop it or pause it from Scan History first.',true);
            syncGlobalScanControls();return;
        }

        let accepted=false;
        let recoveryTimer=null;
        let recoveryAttempts=0;
        const hasSavedCard=sourceCard(requestedHost).length>0;
        const placeholderVisible=startHistoryPlaceholder(requestedHost,website,$button);
        if(hasSavedCard&&!placeholderVisible){
            showToast('Scan History could not be opened for this website. The scan was not started.',true);
            return;
        }
        $button.prop('disabled',true).text('Starting…');

        function acceptStartedState(state){
            if(!state||String(state.source_host||'').toLowerCase()!==requestedHost){return false;}
            if(String(state.status||'')!=='running'){return false;}
            if(accepted){return true;}
            accepted=true;
            if(recoveryTimer){window.clearInterval(recoveryTimer);recoveryTimer=null;}
            const host=String(state.source_host||'').toLowerCase();
            // Create the persisted row before removing the temporary row. This
            // makes the transition atomic from the user's point of view: there is
            // never a moment where a database run exists but History is blank.
            const $historyRow=ensureHistoryRow(state,$button);
            if(hasSavedCard&&!$historyRow.length){
                accepted=false;
                $button.prop('disabled',false).text('Scan Website');
                $.ajax({
                    url:endpoints.stop,method:'POST',dataType:'json',
                    data:{_csrf:csrf,host:host,mode:'pause',history_id:Number(state.history_id||0)},
                    headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}
                });
                showToast('Scan History task could not be rendered. The scan was paused instead of running invisibly.',true);
                return false;
            }
            removeStartHistoryPlaceholder(requestedHost,$button);
            updateHistoryRow(state);
            const selector=String($button.data('website-input')||'').trim();if(selector){$(selector).val('');}
            renderScanState(state,$button,true);
            scanLoop(host,Number(state.history_id||0));
            return true;
        }

        // If the start HTTP response is delayed after the DB run was already created,
        // recover from persisted status instead of leaving the button on Starting….
        recoveryTimer=window.setInterval(function(){
            recoveryAttempts++;
            $.getJSON(endpoints.status,{host:requestedHost}).done(function(data){
                if(data&&data.ok&&data.state){acceptStartedState(data.state);}
            });
            if(recoveryAttempts>=12&&!accepted){
                window.clearInterval(recoveryTimer);recoveryTimer=null;
                removeStartHistoryPlaceholder(requestedHost,$button);
                $button.prop('disabled',false).text('Scan Website');
                showToast('Scan did not enter Running state. Try again; no page refresh is required.',true);
            }
        },500);

        $.ajax({
            url:endpoints.start,method:'POST',dataType:'json',timeout:6000,
            data:{_csrf:csrf,website_url:website},
            headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}
        }).done(function(data){
            if(!data||!data.ok||!data.state){
                if(!accepted){removeStartHistoryPlaceholder(requestedHost,$button);showToast((data&&data.message)||'Scan could not start.',true);}
                return;
            }
            const state=data.state;const host=String(state.source_host||'').toLowerCase();
            if(!acceptStartedState(state)){return;}
            const detailHost=String($('#website-source-detail').data('source-host')||'').toLowerCase();
            if(!sourceCard(host).length&&detailHost!==host){window.location.reload();}
        }).fail(function(xhr,textStatus){
            if(accepted){return;}
            $.getJSON(endpoints.status,{host:requestedHost}).done(function(data){
                if(data&&data.ok&&data.state&&acceptStartedState(data.state)){return;}
                removeStartHistoryPlaceholder(requestedHost,$button);
                showToast((xhr.responseJSON&&xhr.responseJSON.message)||(textStatus==='timeout'?'Scan start response timed out.':'Scan could not start.'),true);
            }).fail(function(){showToast((xhr.responseJSON&&xhr.responseJSON.message)||'Scan could not start.',true);});
        }).always(function(){
            if(accepted){return;}
            syncGlobalScanControls();
        });
    }

    $(document).on('click','.website-product-scan-button',function(){startScan($(this));});
    $(document).on('click','[data-history-scan-control]',function(event){
        event.preventDefault();event.stopPropagation();
        const $control=$(this);if($control.prop('disabled'))return;
        const action=String($control.data('history-action')||'');const host=String($control.data('source-host')||'');
        if(action==='pause'){changeScanState(host,'pause',$control);return;}
        if(action==='resume'){resumeHistoryScan($control);}
    });
    $(document).on('click','.website-history-main-row[data-scan-history-row]',function(event){
        if($(event.target).closest('[data-history-scan-control],a,button').length)return;
        const $row=$(this);const id=Number($row.data('website-history-id')||0);if(id<1)return;
        const $detail=$('[data-history-detail-row="'+id+'"]').first();const opening=$detail.hasClass('hidden');
        if(opening){delete historyUserCollapsed[id];}else{historyUserCollapsed[id]=true;}
        $row.attr('aria-expanded',opening?'true':'false').toggleClass('is-expanded',opening);
        $detail.toggleClass('hidden',!opening);
        if(opening){loadHistoryItems(String($row.data('history-source-host')||''),id);}
    });
    $(document).on('keydown','.website-history-main-row[data-scan-history-row]',function(event){
        if(event.key!=='Enter'&&event.key!==' ')return;
        if($(event.target).closest('[data-history-scan-control]').length)return;
        event.preventDefault();$(this).trigger('click');
    });
    function productCard(row){
        const id=Number(row.id||0);
        const title=escapeHtml(String(row.title||''));
        const description=escapeHtml(String(row.description||''));
        const page=escapeHtml(String(row.page_url||''));
        const image=escapeHtml(String(row.image_url||''));
        const imported=escapeHtml(String(row.imported_at||'—'));
        const imageHtml=image
            ?'<img src="'+image+'" alt="" loading="lazy" referrerpolicy="no-referrer">'
            :'<span>No image</span>';
        return '<article class="website-source-product-card" data-inline-reference-id="'+id+'">'
            +'<div class="website-source-product-image">'+imageHtml+'</div>'
            +'<div class="website-source-product-body"><strong>'+title+'</strong>'+(description?'<p>'+description+'</p>':'<p class="muted">No description</p>')+'</div>'
            +'<div class="website-source-product-url-cell"><a class="website-source-product-url" href="'+page+'" target="_blank" rel="noopener noreferrer">'+page+'</a></div>'
            +'<div class="website-source-product-image-state">'+(row.sha256?'Indexed ✓':(image?'Image found':'No image'))+'</div>'
            +'<div class="website-source-product-date">'+imported+'</div>'
            +'<div class="website-source-product-actions"><button type="button" class="tiny badbtn website-inline-reference-delete" data-reference-id="'+id+'">Delete</button></div>'
            +'</article>';
    }

    function renderInlineRows(rows){
        const $grid=$activePanel.find('.website-source-product-grid');
        if(!$grid.length){return;}
        $grid.html(rows.length?rows.map(productCard).join(''):'<div class="website-source-inline-empty">No matching scanned products for this website.</div>');
        $activePanel.find('[data-inline-count]').text(rows.length);
    }

    function loadInlineProducts(host,q){
        if(!$activePanel.length||activeHost!==host){return;}
        const $grid=$activePanel.find('.website-source-product-grid');
        $grid.html('<div class="website-source-inline-empty">Loading products…</div>');
        $.getJSON(endpoints.search,{host:host,q:q||''}).done(function(data){
            if(!data||!data.ok){$grid.html('<div class="website-source-inline-empty">'+escapeHtml((data&&data.message)||'Could not load products.')+'</div>');return;}
            renderInlineRows(Array.isArray(data.rows)?data.rows:[]);
        }).fail(function(xhr){
            $grid.html('<div class="website-source-inline-empty">'+escapeHtml((xhr.responseJSON&&xhr.responseJSON.message)||'Could not load products.')+'</div>');
        });
    }

    function closeWebsiteSourceCard($card,immediate){
        if(!$card||!$card.length){return;}
        const $button=$card.find('.website-source-expand').first();
        const $detail=$card.find('[data-website-source-detail]').first();
        $card.removeClass('is-expanded');
        $button.attr('aria-expanded','false');
        if(!$detail.length){return;}
        if(immediate){$detail.stop(true,true).hide().addClass('hidden').removeAttr('style');}
        else{$detail.stop(true,true).slideUp(150,function(){$(this).addClass('hidden').removeAttr('style');});}
    }

    function ensureWebsiteSourceCardOpen($card,immediate){
        if(!$card||!$card.length){return;}
        $('.website-product-source.is-expanded').not($card).each(function(){closeWebsiteSourceCard($(this),true);});
        const $button=$card.find('.website-source-expand').first();
        const $detail=$card.find('[data-website-source-detail]').first();
        $card.addClass('is-expanded');
        $button.attr('aria-expanded','true');
        if(!$detail.length){return;}
        if(immediate){$detail.stop(true,true).removeClass('hidden').show().removeAttr('style');}
        else{$detail.stop(true,true).removeClass('hidden').hide().slideDown(180,function(){$(this).removeAttr('style');});}
    }

    function toggleWebsiteSourceCard($card){
        if(!$card||!$card.length){return;}
        if($card.hasClass('is-expanded')){closeWebsiteSourceCard($card,false);return;}
        ensureWebsiteSourceCardOpen($card,false);
    }

    function selectedProductsWebsite(){
        if(!$activePanel.length){return '';}
        const $option=$activePanel.find('.website-products-host-select option:selected').first();
        return String($option.attr('data-website-url')||'').trim();
    }

    function activateProductsHost(host,loadNow){
        if(!$activePanel.length){return;}
        host=String(host||'').trim().toLowerCase();
        activeHost=host;
        const website=selectedProductsWebsite();
        $activePanel.find('input[name="website_url"]').val(website);
        $activePanel.find('[data-products-host-copy]').text(host?'Search/add/delete applies only to '+host+'.':'Save a website in Website Scan first.');
        $activePanel.find('.website-source-inline-add-toggle').prop('disabled',!host);
        if(!host){
            $activePanel.find('[data-inline-count]').text('0');
            $activePanel.find('.website-source-product-grid').html('<div class="website-source-inline-empty">No saved websites yet.</div>');
            return;
        }
        if(loadNow!==false){loadInlineProducts(host,String($activePanel.find('.website-inline-search').val()||''));}
    }

    $(document).on('click','.website-source-expand',function(){toggleWebsiteSourceCard($(this).closest('.website-product-source'));});
    $(document).on('change','.website-products-host-select',function(){
        activateProductsHost(String($(this).val()||''),true);
    });
    // v0.2.93: Scanned Products belongs inside the Website Scan detail workspace.
    // It has its own local accordion state and must never participate in the
    // top-level Website Scan / URL CSV / Page-Sitemap accordion.
    $(document).on('click','[data-scanned-products-toggle]',function(){
        const $toggle=$(this);
        const $section=$toggle.closest('[data-scanned-products-section]');
        const $body=$section.find('[data-scanned-products-body]').first();
        if(!$body.length){return;}
        const isOpen=$toggle.attr('aria-expanded')==='true';
        if(isOpen){
            $toggle.attr('aria-expanded','false');
            $body.stop(true,true).slideUp(160,function(){$(this).addClass('hidden').removeAttr('style');});
            return;
        }
        $toggle.attr('aria-expanded','true');
        $body.stop(true,true).removeClass('hidden').hide().slideDown(180,function(){$(this).removeAttr('style');});
        if($activePanel.length){
            activateProductsHost(String($activePanel.find('.website-products-host-select').val()||''),true);
        }
    });
    $(document).on('click','.website-source-inline-add-toggle',function(){
        if(!$activePanel.length){return;}
        $activePanel.find('.website-source-inline-add').toggleClass('is-open');
    });
    $(document).on('click','.website-inline-search-button',function(){
        if(activeHost){loadInlineProducts(activeHost,String($activePanel.find('.website-inline-search').val()||''));}
    });
    $(document).on('keydown','.website-inline-search',function(event){
        if(event.key==='Enter'){event.preventDefault();if(activeHost){loadInlineProducts(activeHost,String($(this).val()||''));}}
    });
    $(document).on('submit','.website-source-inline-add',function(event){
        event.preventDefault();const $form=$(this);const $submit=$form.find('button[type="submit"]');
        if(!activeHost){showToast('Select a website first.',true);return;}
        $form.find('input[name="website_url"]').val(selectedProductsWebsite());
        $submit.prop('disabled',true).text('Adding…');
        $.ajax({url:endpoints.add,method:'POST',dataType:'json',data:$form.serialize(),headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}})
            .done(function(data){
                if(!data||!data.ok){showToast((data&&data.message)||'URL could not be added.',true);return;}
                $form.removeClass('is-open');$form.find('input[name="page_url"],input[name="title"],input[name="image_url"],textarea[name="description"]').val('');
                showToast('Website URL added.',false);loadInlineProducts(activeHost,String($activePanel.find('.website-inline-search').val()||''));
            }).fail(function(xhr){showToast((xhr.responseJSON&&xhr.responseJSON.message)||'URL could not be added.',true);})
            .always(function(){$submit.prop('disabled',false).text('Add URL');});
    });
    $(document).on('click','.website-inline-reference-delete',function(){
        const $button=$(this);const id=Number($button.data('reference-id')||0);if(!id)return;
        if(!$button.hasClass('delete-armed')){
            $button.addClass('delete-armed').text('Confirm');
            window.setTimeout(function(){$button.removeClass('delete-armed').text('Delete');},5000);return;
        }
        $button.prop('disabled',true).text('Deleting…');
        $.ajax({url:endpoints.delete,method:'POST',dataType:'json',data:{_csrf:csrf,id:id},headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}})
            .done(function(data){
                if(!data||!data.ok){showToast((data&&data.message)||'Delete failed.',true);return;}
                showToast('Website product deleted.',false);
                if(activeHost){loadInlineProducts(activeHost,String($activePanel.find('.website-inline-search').val()||''));}
            }).fail(function(xhr){showToast((xhr.responseJSON&&xhr.responseJSON.message)||'Delete failed.',true);$button.prop('disabled',false).removeClass('delete-armed').text('Delete');});
    });

    syncGlobalScanControls();

    // V0.2.39 watchdog: a scan step now processes one page at a time, so a
    // normal step should finish well inside the browser timeout. If no DB
    // progress is recorded for 55 seconds, expose a recoverable paused state instead
    // of leaving the UI stuck forever on "Scanning…". If a running job has
    // no active browser loop (for example after a harmless JS interruption),
    // resume the loop automatically from the persisted queue when it is still running.
    window.setInterval(function(){
        runningHostList().forEach(function(host){
            $.getJSON(endpoints.status,{host:host}).done(function(data){
                if(!data||!data.ok||!data.state){return;}
                const state=data.state;
                const status=String(state.status||'');
                if(status!=='running'){
                    const stoppedHistoryId=Number(state.history_id||0);
                    if(stoppedHistoryId<1||Number(loops[host]||0)===stoppedHistoryId){delete loops[host];}
                    renderScanState(state,null,false);
                    return;
                }
                const stale=Math.max(0,Number(state.stale_seconds||0));
                if(stale>=55&&Date.now()>=Number(watchdogGraceUntil[host]||0)){
                    const staleHistoryId=Number(state.history_id||0);
                    const pending=stepRequests[host];
                    if(pending&&Number(pending.historyId||0)===staleHistoryId&&pending.xhr&&typeof pending.xhr.abort==='function'){
                        try{pending.xhr.abort('scan-watchdog');}catch(e){}
                    }
                    if(Number(loops[host]||0)===staleHistoryId){delete loops[host];}
                    state.client_interrupted=true;
                    renderScanState(state,null,true);
                    showToast('Website scan paused because no progress was recorded for '+stale+' seconds. Progress was saved; use ▶ in Scan History after the job is paused.',true);
                    return;
                }
                const runningHistoryId=Number(state.history_id||0);
                if(runningHistoryId>0&&Number(loops[host]||0)!==runningHistoryId){
                    renderScanState(state,null,false);
                    scanLoop(host,runningHistoryId);
                }
            });
        });
    },12000);

    // Refresh-safe resume: persisted jobs survive reload and are resumed automatically.
    $('.website-product-source').each(function(){
        const host=String($(this).data('website-source')||'');if(!host)return;
        $.getJSON(endpoints.status,{host:host}).done(function(data){
            if(!data||!data.ok||!data.state){return;}
            renderScanState(data.state,null,String(data.state.status)==='running');
            if(String(data.state.status)==='running'){scanLoop(host,Number(data.state.history_id||0));}
        });
    });
    const detailHost=String($('#website-source-detail').data('source-host')||'');
    if(detailHost){
        $.getJSON(endpoints.status,{host:detailHost}).done(function(data){
            if(!data||!data.ok||!data.state){return;}
            renderScanState(data.state,null,String(data.state.status)==='running');
            if(String(data.state.status)==='running'){scanLoop(detailHost,Number(data.state.history_id||0));}
        });
    }
})(window.jQuery);

/* v0.2.36 — Website Library 1/2/3 top-level accordion. */
/* v0.2.93 — Top-level accordion remains Website Scan / URL CSV / Page-Sitemap only.
   Scanned Products now has a separate local accordion inside Website Scan detail. */
(function($){
    'use strict';
    const storageKey='cdspWebsiteToolPanel';
    function closeWebsiteToolPanels(immediate){
        const $panels=$('[data-website-tool-panel]:not(.hidden)');
        $('[data-website-tool-toggle]').attr('aria-expanded','false');
        if(immediate){
            $panels.stop(true,true).hide().addClass('hidden').removeAttr('style');
            return;
        }
        $panels.stop(true,true).slideUp(180,function(){$(this).addClass('hidden').removeAttr('style');});
    }
    function openWebsiteToolPanel(id,remember,immediate){
        const $panel=$('#'+id);
        if(!$panel.length)return;
        const $others=$('[data-website-tool-panel]').not($panel);
        $('[data-website-tool-toggle]').attr('aria-expanded','false');
        if(immediate){
            $others.stop(true,true).hide().addClass('hidden').removeAttr('style');
            $panel.removeClass('hidden').show().removeAttr('style');
        }else{
            $others.filter(':not(.hidden)').stop(true,true).slideUp(160,function(){$(this).addClass('hidden').removeAttr('style');});
            $panel.stop(true,true).removeClass('hidden').hide().slideDown(200,function(){$(this).removeAttr('style');});
        }
        $('[data-website-tool-toggle="'+id+'"]').attr('aria-expanded','true');
        if(remember!==false){try{window.localStorage.setItem(storageKey,id);}catch(e){}}
    }
    $(document).on('click','[data-website-tool-toggle]',function(){
        const id=String($(this).data('website-tool-toggle')||'');
        const isOpen=$(this).attr('aria-expanded')==='true';
        if(isOpen){closeWebsiteToolPanels(false);try{window.localStorage.removeItem(storageKey);}catch(e){}return;}
        openWebsiteToolPanel(id,true,false);
    });
    $(document).on('click','[data-website-tool-close]',function(){
        closeWebsiteToolPanels(false);
        try{window.localStorage.removeItem(storageKey);}catch(e){}
    });
    $(function(){
        if(!$('[data-website-tools]').length)return;
        let saved='';try{saved=String(window.localStorage.getItem(storageKey)||'');}catch(e){}
        if(saved&&$('#'+saved).length){openWebsiteToolPanel(saved,false,true);}
    });
})(window.jQuery);
