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
<div class="panel unified-review-redirect">
    <div>
        <div class="eyebrow">
            <?= Util::e(ucfirst($period)) ?> Review
        </div>
        <h2>Sales Management Review</h2>
        <p>
            Rating, notes, and review history are managed in the
            Sales Activity &amp; Attendance dashboard so there is only
            one review interface.
        </p>
    </div>

    <a
        class="btn primary"
        href="<?= Util::e(
            $config['app']['base_path']
            .'/admin?date='.rawurlencode($start)
            .'&period='.rawurlencode($period)
            .'&sales_id='.(int)$salesUserId
            .'&review=1'
        ) ?>"
    >
        Open <?= Util::e(ucfirst($period)) ?> Review
    </a>
</div>
<?php endif; ?>
