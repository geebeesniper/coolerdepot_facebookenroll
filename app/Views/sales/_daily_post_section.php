<?php
use App\Core\Csrf;
use App\Core\Util;

$date = $day['date'];
$postCount = (int)$day['post_count'];
$goodCount = (int)$day['good_count'];
$badCount = (int)$day['bad_count'];
?>
<section class="daily-post-section" data-post-date="<?= Util::e($date) ?>">
    <div class="daily-post-head">
        <div>
            <div class="daily-post-date"><?= Util::e($date) ?></div>
            <h2>Daily Posts</h2>
        </div>

        <div class="daily-post-summary">
            <span class="post-summary total">Posts <?= $postCount ?></span>
            <span class="post-summary good">Good <?= $goodCount ?></span>
            <span class="post-summary bad">Bad <?= $badCount ?></span>
        </div>
    </div>

    <div class="daily-post-grid">
        <?php foreach ($day['posts'] as $p): ?>
            <article class="post">
                <div class="post-top">
                    <span class="platform <?= Util::e($p['platform']) ?>">
                        <?= Util::e(ucfirst($p['platform'])) ?>
                    </span>

                    <?php if (in_array(($p['admin_review_status'] ?? null), ['good','bad'], true)): ?>
                        <span class="status <?= Util::e($p['admin_review_status']) ?>">
                            <?= Util::e(ucfirst($p['admin_review_status'])) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="post-main">
                    <b class="post-title"><?= Util::e($p['title']) ?></b>

                    <a
                        class="post-url"
                        target="_blank"
                        rel="noopener"
                        href="<?= Util::e($p['canonical_url']) ?>"
                        title="<?= Util::e($p['canonical_url']) ?>"
                    ><?= Util::e($p['canonical_url']) ?></a>

                    <div class="post-dates">
                        <small><span>Published</span><?= Util::e($p['published_at']) ?></small>
                        <small><span>Saved</span><?= Util::e($p['created_at']) ?></small>
                    </div>
                </div>

                <details class="delete-request">
                    <summary>Request deletion</summary>
                    <form method="post" action="<?= $config['app']['base_path'] ?>/sales/delete-request">
                        <input type="hidden" name="_csrf" value="<?= Util::e(Csrf::token()) ?>">
                        <input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>">
                        <input name="reason" required placeholder="Reason">
                        <button class="tiny badbtn">Send</button>
                    </form>
                </details>
            </article>
        <?php endforeach; ?>
    </div>
</section>
