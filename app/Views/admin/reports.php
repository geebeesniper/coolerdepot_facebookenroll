<?php use App\Core\Csrf; use App\Core\Util; ?>

<div class="page-head">
    <div>
        <div class="eyebrow">Management Reports</div>
        <h1><?= Util::e(ucfirst($period)) ?> Progress</h1>
        <p><?= Util::e($start) ?> → <?= Util::e($end) ?></p>
    </div>
</div>

<div class="panel">
    <form class="filters">
        <select name="period">
            <option value="week" <?= $period==='week'?'selected':'' ?>>Week</option>
            <option value="month" <?= $period==='month'?'selected':'' ?>>Month</option>
        </select>

        <input type="date" name="start" value="<?= Util::e($start) ?>">

        <select name="sales_id">
            
            <?php foreach ($sales as $s): ?>
                <option value="<?= (int)$s['id'] ?>" <?= $salesUserId===(int)$s['id']?'selected':'' ?>>
                    <?= Util::e($s['display_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button class="btn">Run</button>
    </form>
</div>

<div class="panel tablewrap">
    <table>
        <tr>
            <th>Sales</th>
            <th>Total</th>
            <th>Facebook</th>
            <th>OfferUp</th>
            <th>Craigslist</th>
            <th>Good</th>
            <th>Bad</th>
            <th>Good %</th>
        </tr>

        <?php foreach ($rows as $r):
            $reviewed = (int)$r['good_posts'] + (int)$r['bad_posts'];
            $pct = $reviewed ? round((int)$r['good_posts'] / $reviewed * 100, 1) : 0;
        ?>
            <tr>
                <td><?= Util::e($r['display_name']) ?></td>
                <td><?= (int)$r['total_posts'] ?></td>
                <td><?= (int)$r['facebook_posts'] ?></td>
                <td><?= (int)$r['offerup_posts'] ?></td>
                <td><?= (int)$r['craigslist_posts'] ?></td>
                <td><?= (int)$r['good_posts'] ?></td>
                <td><?= (int)$r['bad_posts'] ?></td>
                <td><?= $pct ?>%</td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<?php if ($salesUserId > 0): ?>
<div class="panel">
    <h2><?= Util::e(ucfirst($period)) ?> Admin Review</h2>

    <form method="post" enctype="multipart/form-data" action="<?= $config['app']['base_path'] ?>/admin/period/review">
        <input type="hidden" name="_csrf" value="<?= Util::e(Csrf::token()) ?>">
        <input type="hidden" name="sales_user_id" value="<?= (int)$salesUserId ?>">
        <input type="hidden" name="period_type" value="<?= Util::e($period) ?>">
        <input type="hidden" name="period_start" value="<?= Util::e($start) ?>">
        <input type="hidden" name="period_end" value="<?= Util::e($end) ?>">
<?php
$fieldName = 'note';
$fieldId = 'admin-note';
$noteValue = '';
require __DIR__ . '/_html_note_editor.php';
?>


        <label>Images</label>
        <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp">

        <button class="btn primary">Save Review</button>
    </form>
</div>
<?php endif; ?>
