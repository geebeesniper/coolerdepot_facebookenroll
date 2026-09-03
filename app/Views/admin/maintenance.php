<?php
/**
 * File / 文件：app/Views/admin/maintenance.php
 * EN: Admin-only browser UI for database compatibility checks, repairs, and guarded SQL execution.
 * 中文：仅供 Admin 使用的数据库兼容检查、修复及受保护 SQL 执行页面。
 */
use App\Core\Csrf;
use App\Core\Util;

$base = $config['app']['base_path'];
$provider = $status['provider_registry'] ?? [];
$inspection = $status['inspection_manual_pending'] ?? [];
$post = $status['post_manual_pending'] ?? [];
?>

<div class="page-head maintenance-page-head">
    <div>
        <div class="eyebrow">Administrator</div>
        <h1>Database Maintenance</h1>
        <p>Run database compatibility repairs and diagnostic SQL from the browser. / 通过浏览器运行数据库兼容修复与诊断 SQL。</p>
    </div>
    <a class="btn ghost" href="<?= Util::e($base) ?>/admin/settings">← Back to Settings</a>
</div>

<section class="panel maintenance-card">
    <div class="panel-head settings-section-head">
        <div>
            <div class="eyebrow">Compatibility</div>
            <h2>Database Checks / 数据库检查</h2>
            <p class="settings-subtitle">These checks do not change the database until you click Run Recommended Repairs.</p>
        </div>
        <span class="maintenance-version">App v<?= Util::e((string)$config['app']['version']) ?></span>
    </div>

    <div class="maintenance-check-grid">
        <article class="maintenance-check <?= !empty($provider['healthy']) ? 'is-ok' : 'needs-attention' ?>">
            <strong>Provider Registry</strong>
            <span>Table: <?= !empty($provider['table_exists']) ? 'Yes' : 'Missing' ?></span>
            <span>Providers: <?= (int)($provider['provider_count'] ?? 0) ?></span>
            <span>Enabled flag: <?= Util::e((string)($provider['flag'] ?? 'missing')) ?></span>
            <b><?= !empty($provider['healthy']) ? 'Ready ✓' : 'Needs attention' ?></b>
            <?php if (!empty($provider['error'])): ?><small class="maintenance-error"><?= Util::e((string)$provider['error']) ?></small><?php endif; ?>
        </article>

        <article class="maintenance-check <?= !empty($inspection['healthy']) ? 'is-ok' : 'needs-attention' ?>">
            <strong>Post Inspections</strong>
            <span>verification_status</span>
            <span class="maintenance-code"><?= Util::e((string)($inspection['column_type'] ?? 'missing')) ?></span>
            <b><?= !empty($inspection['healthy']) ? 'manual_pending ✓' : 'manual_pending missing' ?></b>
            <?php if (!empty($inspection['error'])): ?><small class="maintenance-error"><?= Util::e((string)$inspection['error']) ?></small><?php endif; ?>
        </article>

        <article class="maintenance-check <?= !empty($post['healthy']) ? 'is-ok' : 'needs-attention' ?>">
            <strong>Sales Posts</strong>
            <span>verification_status</span>
            <span class="maintenance-code"><?= Util::e((string)($post['column_type'] ?? 'missing')) ?></span>
            <b><?= !empty($post['healthy']) ? 'manual_pending ✓' : 'manual_pending missing' ?></b>
            <?php if (!empty($post['error'])): ?><small class="maintenance-error"><?= Util::e((string)$post['error']) ?></small><?php endif; ?>
        </article>
    </div>

    <form method="post" action="<?= Util::e($base) ?>/admin/maintenance/repairs" class="maintenance-action-row">
        <input type="hidden" name="_csrf" value="<?= Util::e(Csrf::token()) ?>">
        <div>
            <strong>Recommended Repairs / 推荐修复</strong>
            <small>Fixes the Provider Registry flag when Provider profiles already exist and adds missing manual_pending ENUM values. Existing business rows are not deleted.</small>
        </div>
        <button class="btn primary" type="submit">Run Recommended Repairs</button>
    </form>

    <?php if (is_array($repairResults)): ?>
        <div class="maintenance-results">
            <h3>Repair Results / 修复结果</h3>
            <?php foreach ($repairResults as $row): ?>
                <div class="maintenance-result-row status-<?= Util::e((string)($row['status'] ?? 'unknown')) ?>">
                    <strong><?= Util::e((string)($row['key'] ?? '')) ?></strong>
                    <span><?= Util::e((string)($row['status'] ?? '')) ?></span>
                    <small><?= Util::e((string)($row['message'] ?? '')) ?></small>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="panel maintenance-card">
    <div class="panel-head settings-section-head">
        <div>
            <div class="eyebrow">SQL Console</div>
            <h2>Run One Query / 执行一条 SQL</h2>
            <p class="settings-subtitle">Admin only. CSRF protected. Only one statement per request. Results are limited to 200 rows.</p>
        </div>
    </div>

    <div class="banner warn maintenance-warning">
        Do not paste passwords, API tokens, or AUTH_HANDOFF_SECRET into SQL. Write mode changes the production database.
        / 不要把密码、API Token 或 AUTH_HANDOFF_SECRET 写入 SQL。Write 模式会直接修改生产数据库。
    </div>

    <form method="post" action="<?= Util::e($base) ?>/admin/maintenance/query" class="maintenance-query-form">
        <input type="hidden" name="_csrf" value="<?= Util::e(Csrf::token()) ?>">
        <label>
            Mode / 模式
            <select name="mode" id="maintenanceSqlMode">
                <option value="read" <?= (($queryResult['mode'] ?? 'read') === 'read') ? 'selected' : '' ?>>Read Only — SELECT / SHOW / DESCRIBE / EXPLAIN</option>
                <option value="write" <?= (($queryResult['mode'] ?? '') === 'write') ? 'selected' : '' ?>>Write SQL — ALTER / INSERT / UPDATE / DELETE / CREATE / REPLACE</option>
            </select>
        </label>

        <label class="maintenance-sql-label">
            SQL
            <textarea name="sql" rows="8" spellcheck="false" placeholder="SELECT * FROM cdsp_provider_profiles ORDER BY sort_order;"><?= Util::e((string)($queryResult['submitted_sql'] ?? '')) ?></textarea>
        </label>

        <label id="maintenanceWriteConfirm" class="maintenance-write-confirm">
            Write confirmation / 写入确认
            <input type="text" name="write_confirmation" autocomplete="off" placeholder="RUN WRITE SQL">
            <small>Required only in Write SQL mode. Type exactly: <b>RUN WRITE SQL</b></small>
        </label>

        <div class="maintenance-query-actions">
            <button class="btn primary" type="submit">Run SQL</button>
            <span>Blocked: DROP DATABASE/USER, TRUNCATE, GRANT/REVOKE, LOAD DATA/FILE, OUTFILE, SHUTDOWN, SET GLOBAL, KILL, SLEEP/BENCHMARK.</span>
        </div>
    </form>

    <?php if (is_array($queryResult)): ?>
        <div class="maintenance-query-output">
            <h3>Query Result / 查询结果</h3>
            <?php if (!empty($queryResult['error'])): ?>
                <div class="banner bad"><?= Util::e((string)$queryResult['error']) ?></div>
            <?php elseif (($queryResult['mode'] ?? '') === 'write'): ?>
                <div class="banner good">
                    <?= Util::e((string)($queryResult['statement'] ?? 'WRITE')) ?> completed.
                    Affected rows: <?= (int)($queryResult['affected_rows'] ?? 0) ?>.
                    Query hash: <?= Util::e((string)($queryResult['query_sha256'] ?? '')) ?>
                </div>
            <?php else: ?>
                <div class="maintenance-query-meta">
                    <span><?= (int)($queryResult['row_count'] ?? 0) ?> rows shown</span>
                    <?php if (!empty($queryResult['truncated'])): ?><b>First 200 rows only</b><?php endif; ?>
                    <code><?= Util::e((string)($queryResult['query_sha256'] ?? '')) ?></code>
                </div>
                <?php $rows = $queryResult['rows'] ?? []; $columns = $queryResult['columns'] ?? []; ?>
                <?php if ($columns): ?>
                    <div class="tablewrap maintenance-tablewrap">
                        <table class="maintenance-query-table">
                            <thead><tr><?php foreach ($columns as $column): ?><th><?= Util::e((string)$column) ?></th><?php endforeach; ?></tr></thead>
                            <tbody>
                                <?php foreach ($rows as $row): ?>
                                    <tr><?php foreach ($columns as $column): ?><td><?= Util::e((string)($row[$column] ?? '')) ?></td><?php endforeach; ?></tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="banner">Query completed and returned no rows.</div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<script>
(() => {
    const mode = document.getElementById('maintenanceSqlMode');
    const confirm = document.getElementById('maintenanceWriteConfirm');
    if (!mode || !confirm) return;
    const update = () => {
        confirm.classList.toggle('hidden', mode.value !== 'write');
    };
    mode.addEventListener('change', update);
    update();
})();
</script>
