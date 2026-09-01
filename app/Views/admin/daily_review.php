<?php
/**
 * File / 文件：app/Views/admin/daily_review.php
 * EN: Server-rendered view for this screen or partial.
 * 中文：该文件负责此页面或局部组件的服务端渲染。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
use App\Core\Csrf;
use App\Core\Util;

$t = [
    'facebook' => 0,
    'offerup' => 0,
    'craigslist' => 0,
    'good' => 0,
    'bad' => 0,
];

foreach ($posts as $p) {
    $t[$p['platform']]++;

    if (in_array(($p['admin_review_status'] ?? null), ['good','bad'], true)) {
        $t[$p['admin_review_status']]++;
    }
}
?>

<div class="page-head">
    <div>
        <div class="eyebrow">Daily Review</div>
        <h1><?= Util::e($salesUser['display_name']) ?> — <?= Util::e($date) ?></h1>
    </div>
</div>

<div class="metrics">
    <div class="metric"><strong><?= count($posts) ?></strong><span>Total</span></div>
    <div class="metric"><strong><?= $t['facebook'] ?></strong><span>Facebook</span></div>
    <div class="metric"><strong><?= $t['offerup'] ?></strong><span>OfferUp</span></div>
    <div class="metric"><strong><?= $t['craigslist'] ?></strong><span>Craigslist</span></div>
    <div class="metric"><strong><?= $t['good'] ?></strong><span>Good</span></div>
    <div class="metric"><strong><?= $t['bad'] ?></strong><span>Bad</span></div>
</div>

<div class="two">
    <div class="panel">
        <h2>Posts</h2>

        <?php foreach ($posts as $p): ?>
            <div class="mini">
                <span class="platform <?= Util::e($p['platform']) ?>">
                    <?= Util::e(ucfirst($p['platform'])) ?>
                </span>

                <div>
                    <b><?= Util::e($p['title']) ?></b>
                    <small><?= Util::e($p['published_at']) ?></small>
                </div>

                <?php if (in_array(($p['admin_review_status'] ?? null), ['good','bad'], true)): ?>
                    <span class="status <?= Util::e($p['admin_review_status']) ?>">
                        <?= Util::e(ucfirst($p['admin_review_status'])) ?>
                    </span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="panel">
        <h2>Daily Admin Note</h2>

        <form method="post" enctype="multipart/form-data" action="<?= $config['app']['base_path'] ?>/admin/daily/review">
            <input type="hidden" name="_csrf" value="<?= Util::e(Csrf::token()) ?>">
            <input type="hidden" name="sales_user_id" value="<?= (int)$salesUser['id'] ?>">
            <input type="hidden" name="work_date" value="<?= Util::e($date) ?>">
<?php
$fieldName = 'note';
$fieldId = 'admin-note';
$noteValue = (string)($review['note'] ?? '');
require __DIR__ . '/_html_note_editor.php';
?>


            <label>Images</label>
            <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>

            <button class="btn primary">Save Daily Review</button>
        </form>
    </div>
</div>
