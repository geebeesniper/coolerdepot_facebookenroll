<?php
/**
 * File / 文件：app/Views/admin/website-source.php
 * EN: Admin detail page for one company website source and its indexed product URLs.
 * 中文：单个公司网站来源及其已索引产品 URL 的 Admin 管理页面。
 */
use App\Core\Csrf;
use App\Core\Util;

$host=(string)$source['host'];
$websiteUrl=(string)$source['url'];
?>

<div class="page-head website-source-detail-head">
    <div>
        <div class="eyebrow">Website Product Library</div>
        <h1><?= Util::e($host) ?></h1>
        <p>Search, add or remove URLs indexed from this website. / 搜索、添加或删除该网站扫描得到的产品 URL。</p>
    </div>
    <div class="website-source-detail-head-actions">
        <a class="btn ghost" href="<?= Util::e($config['app']['base_path']) ?>/admin/settings#website-comparison">← Back to Websites</a>
        <a class="btn ghost" href="<?= Util::e($websiteUrl) ?>" target="_blank" rel="noopener noreferrer">Open Website ↗</a>
        <button type="button" class="btn primary website-product-scan-button"
            data-website-url="<?= Util::e($websiteUrl) ?>"
            data-scan-url="<?= Util::e($config['app']['base_path']) ?>/admin/website/products/scan-batch"
            data-csrf="<?= Util::e(Csrf::token()) ?>"
            data-progress-target=".website-source-detail-scan-progress"
            data-reload-on-complete="1">Scan Website</button>
    </div>
</div>

<div class="website-product-scan-progress website-source-detail-scan-progress" aria-live="polite"></div>

<section class="panel website-source-detail" id="website-source-detail"
    data-reference-search-url="<?= Util::e($config['app']['base_path']) ?>/admin/website/references"
    data-reference-add-url="<?= Util::e($config['app']['base_path']) ?>/admin/website/reference/add"
    data-reference-delete-url="<?= Util::e($config['app']['base_path']) ?>/admin/website/reference/delete"
    data-scan-start-url="<?= Util::e($config['app']['base_path']) ?>/admin/website/products/scan-start"
    data-scan-step-url="<?= Util::e($config['app']['base_path']) ?>/admin/website/products/scan-step"
    data-scan-status-url="<?= Util::e($config['app']['base_path']) ?>/admin/website/products/scan-status"
    data-scan-stop-url="<?= Util::e($config['app']['base_path']) ?>/admin/website/products/scan-stop"
            data-scan-resume-url="<?= Util::e($config['app']['base_path']) ?>/admin/website/products/scan-resume"
    data-search-url="<?= Util::e($config['app']['base_path']) ?>/admin/website/references"
    data-delete-url="<?= Util::e($config['app']['base_path']) ?>/admin/website/reference/delete"
    data-source-host="<?= Util::e($host) ?>"
    data-csrf="<?= Util::e(Csrf::token()) ?>">

    <div class="website-source-searchbar">
        <div>
            <strong>Search scanned URLs</strong>
            <small>Search this website only by title, URL, description or image URL.</small>
        </div>
        <div class="website-library-search-row">
            <input type="search" id="websiteReferenceSearch" value="<?= Util::e((string)$query) ?>"
                placeholder="Search title, URL or description">
            <button type="button" class="btn primary" id="websiteReferenceSearchButton">Search</button>
        </div>
    </div>

    <div class="website-source-summary">
        <span><strong><?= (int)($sourceStats['total']??0) ?></strong> scanned URLs</span>
        <span><strong><?= (int)($sourceStats['indexed']??0) ?></strong> exact image fingerprints</span>
        <span>Last scan: <strong><?= Util::e((string)($sourceStats['last_imported']??'—')) ?></strong></span>
    </div>

    <div id="websiteReferenceMessage" class="provider-page-notice hidden" role="status"></div>

    <form method="post" class="website-source-detail-add" action="<?= Util::e($config['app']['base_path']) ?>/admin/website/reference/add">
        <input type="hidden" name="_csrf" value="<?= Util::e(Csrf::token()) ?>">
        <input type="hidden" name="website_url" value="<?= Util::e($websiteUrl) ?>">
        <input type="hidden" name="return_host" value="<?= Util::e($host) ?>">
        <div class="website-card-title">
            <span class="settings-step">+</span>
            <div><strong>Add URL manually</strong><small>Add one product/reference that belongs to <?= Util::e($host) ?>.</small></div>
        </div>
        <div class="website-source-detail-add-grid">
            <label>Page URL<input type="url" name="page_url" required placeholder="<?= Util::e($websiteUrl) ?>product/example"></label>
            <label>Title<input type="text" name="title" maxlength="500" required placeholder="Product title"></label>
            <label class="wide">Description<textarea name="description" rows="2" placeholder="Optional product description"></textarea></label>
            <label class="wide">First image URL<input type="url" name="image_url" placeholder="https://cdn.example.com/product.jpg"></label>
        </div>
        <button class="btn" type="submit">Add URL</button>
    </form>

    <div class="tablewrap website-reference-tablewrap website-source-detail-tablewrap">
        <table class="website-reference-table website-source-detail-table">
            <thead>
                <tr><th>Product / Title</th><th>Page URL</th><th>First Image</th><th>Exact Image</th><th>Indexed</th><th></th></tr>
            </thead>
            <tbody id="websiteReferenceRows">
                <?php foreach ($references as $reference): ?>
                    <tr data-website-reference-id="<?= (int)$reference['id'] ?>">
                        <td>
                            <strong><?= Util::e((string)$reference['title']) ?></strong>
                            <?php if (!empty($reference['description'])): ?>
                                <small class="website-source-reference-description"><?= Util::e((string)$reference['description']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="website-source-url-cell">
                            <a href="<?= Util::e((string)$reference['page_url']) ?>" target="_blank" rel="noopener noreferrer"><?= Util::e((string)$reference['page_url']) ?></a>
                        </td>
                        <td>
                            <?php if (!empty($reference['image_url'])): ?>
                                <a href="<?= Util::e((string)$reference['image_url']) ?>" target="_blank" rel="noopener noreferrer">Open image ↗</a>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td><?= !empty($reference['sha256'])?'SHA-256 ✓':'—' ?></td>
                        <td><?= Util::e((string)($reference['imported_at']??'—')) ?></td>
                        <td><button type="button" class="tiny badbtn website-reference-delete" data-reference-id="<?= (int)$reference['id'] ?>">Delete</button></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$references): ?><tr class="website-reference-empty"><td colspan="6">No matching URLs for this website.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
