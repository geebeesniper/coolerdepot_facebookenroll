<?php use App\Core\Util; ?>
<div class="page-head">
    <div>
        <div class="eyebrow">Administrator</div>
        <h1>Status Pages</h1>
        <p>Unified application responses for redirects, permissions, missing pages, and server errors.</p>
    </div>
</div>

<div class="panel">
    <div class="status-preview-grid">
        <?php foreach ([301=>'Moved Permanently',302=>'Redirecting',400=>'Bad Request',401=>'Sign In Required',403=>'Access Denied',404=>'Page Not Found',405=>'Method Not Allowed',408=>'Request Timeout',429=>'Too Many Requests',500=>'Server Error',502=>'Provider Error',503=>'Temporarily Unavailable'] as $code=>$label): ?>
            <div class="status-preview-item">
                <strong><?= (int)$code ?></strong>
                <span><?= Util::e($label) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>
