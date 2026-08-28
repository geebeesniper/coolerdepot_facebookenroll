<?php
use App\Core\Csrf;
use App\Core\Util;
?>

<div class="page-head">
    <div>
        <div class="eyebrow">Administrator</div>
        <h1>API Settings</h1>
        <p>Configure the Facebook Marketplace verification provider.</p>
    </div>
</div>

<div class="settings-grid">
    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>Bright Data</h2>
                <p class="settings-subtitle">Facebook Marketplace scraper</p>
            </div>

            <span class="provider-state <?= $tokenConfigured && $settings['enabled'] ? 'ok' : 'off' ?>">
                <?= $tokenConfigured && $settings['enabled'] ? 'Enabled' : 'Not active' ?>
            </span>
        </div>

        <form method="post" action="<?= Util::e($config['app']['base_path']) ?>/admin/settings/save">
            <input type="hidden" name="_csrf" value="<?= Util::e(Csrf::token()) ?>">

            <label class="switch-row">
                <input
                    type="checkbox"
                    name="brightdata_enabled"
                    value="1"
                    <?= $settings['enabled'] ? 'checked' : '' ?>
                >
                <span>
                    <b>Use Bright Data for Facebook</b>
                    <small>Facebook checks will use Bright Data instead of direct server scraping.</small>
                </span>
            </label>

            <label>API Token</label>
            <input
                type="password"
                name="api_token"
                autocomplete="new-password"
                value=""
                placeholder="<?= $tokenConfigured ? 'Token is configured — leave blank to keep it' : 'Paste Bright Data API token' ?>"
            >
            <div class="field-help">
                <?= $tokenConfigured
                    ? 'A token is already stored encrypted. The saved token is never displayed back in this page.'
                    : 'No API token is currently saved.' ?>
            </div>

            <?php if ($tokenConfigured): ?>
                <label class="switch-row compact-switch">
                    <input type="checkbox" name="remove_api_token" value="1">
                    <span>
                        <b>Remove saved token</b>
                        <small>Check this and Save Settings to delete the stored token.</small>
                    </span>
                </label>
            <?php endif; ?>

            <label>Marketplace Dataset ID</label>
            <input
                type="text"
                name="dataset_id"
                value="<?= Util::e((string)$settings['dataset_id']) ?>"
                spellcheck="false"
            >

            <div class="settings-two">
                <label>
                    Max wait
                    <div class="input-suffix">
                        <input
                            type="number"
                            name="timeout_seconds"
                            min="15"
                            max="90"
                            value="<?= (int)$settings['timeout_seconds'] ?>"
                        >
                        <span>sec</span>
                    </div>
                </label>

                <label>
                    Poll every
                    <div class="input-suffix">
                        <input
                            type="number"
                            name="poll_seconds"
                            min="2"
                            max="10"
                            value="<?= (int)$settings['poll_seconds'] ?>"
                        >
                        <span>sec</span>
                    </div>
                </label>
            </div>

            <button class="btn primary">Save Settings</button>
        </form>
    </section>

    <section class="panel">
        <h2>Test Facebook Marketplace</h2>
        <p class="settings-subtitle">
            This performs a real Bright Data request and normally uses one Marketplace query.
        </p>

        <form method="post" action="<?= Util::e($config['app']['base_path']) ?>/admin/settings/test">
            <input type="hidden" name="_csrf" value="<?= Util::e(Csrf::token()) ?>">

            <label>Facebook Marketplace URL</label>
            <input
                type="url"
                name="test_url"
                value="<?= Util::e($testUrl) ?>"
                placeholder="https://www.facebook.com/marketplace/item/..."
                required
            >

            <button class="btn">Test Bright Data</button>
        </form>

        <?php if ($testError): ?>
            <div class="settings-test bad">
                <b>Test failed</b>
                <span><?= Util::e($testError) ?></span>
            </div>
        <?php elseif ($testResult): ?>
            <div class="settings-test ok">
                <b>Bright Data returned a listing</b>

                <dl class="details compact-details">
                    <dt>Item ID</dt>
                    <dd><?= Util::e((string)($testResult['external_post_id'] ?? '—')) ?></dd>

                    <dt>Title</dt>
                    <dd><?= Util::e((string)($testResult['title'] ?? '—')) ?></dd>

                    <dt>Listing date</dt>
                    <dd><?= Util::e((string)($testResult['published_raw'] ?? 'Not returned')) ?></dd>

                    <dt>Description</dt>
                    <dd><?= Util::e((string)($testResult['description'] ?? '—')) ?></dd>
                </dl>

                <?php if (empty($testResult['published_raw'])): ?>
                    <div class="provider-warning">
                        The listing was found, but Bright Data did not return a verifiable listing date.
                        Sales will not be allowed to save it until a true listing date is available.
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Recent Provider Jobs</h2>
            <p class="settings-subtitle">Internal diagnostics. Snapshot IDs stay in the database and are not shown to Sales.</p>
        </div>
    </div>

    <div class="tablewrap">
        <table>
            <tr>
                <th>Time</th>
                <th>User</th>
                <th>Item</th>
                <th>Status</th>
                <th>HTTP</th>
                <th>Error</th>
            </tr>

            <?php foreach ($jobs as $job): ?>
                <tr>
                    <td><?= Util::e((string)$job['created_at']) ?></td>
                    <td><?= Util::e((string)$job['display_name']) ?></td>
                    <td><?= Util::e((string)($job['external_post_id'] ?: '—')) ?></td>
                    <td>
                        <span class="provider-job <?= Util::e((string)$job['status']) ?>">
                            <?= Util::e(ucfirst((string)$job['status'])) ?>
                        </span>
                    </td>
                    <td><?= $job['provider_http_status'] !== null ? (int)$job['provider_http_status'] : '—' ?></td>
                    <td class="job-error"><?= Util::e((string)($job['error_message'] ?: '—')) ?></td>
                </tr>
            <?php endforeach; ?>

            <?php if (!$jobs): ?>
                <tr><td colspan="6">No provider jobs yet.</td></tr>
            <?php endif; ?>
        </table>
    </div>
</section>
