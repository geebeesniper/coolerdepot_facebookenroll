<?php
use App\Core\Csrf;
use App\Core\Util;
use App\Services\MarketplaceProviderDraft;
?>

<div class="page-head provider-page-head">
    <div>
        <div class="eyebrow">Administrator</div>
        <h1>API Providers</h1>
        <p>
            Providers are tried from top to bottom. Drag them to change failover priority.
        </p>
    </div>

    <button type="button" class="btn primary provider-add-open" id="providerAddOpen">
        <span class="provider-plus">+</span>
        Add Provider
    </button>
</div>

<?php if (!$registryReady): ?>
    <div class="banner bad">
        Provider Registry migration has not been enabled yet.
        Run the v0.1.12 provider registry migration before using this page.
    </div>
<?php endif; ?>

<div id="providerPageNotice" class="provider-page-notice hidden" role="status"></div>

<section class="panel provider-manager">
    <div class="panel-head">
        <div>
            <h2>Facebook Marketplace Provider Chain</h2>
            <p class="settings-subtitle">
                Only providers that passed a real Marketplace test can be added.
                Disabled providers stay in the list but are skipped.
            </p>
        </div>
        <span class="provider-count"><?= count($providers) ?> providers</span>
    </div>

    <div
        id="providerSortable"
        class="provider-list"
        data-reorder-url="<?= Util::e($config['app']['base_path']) ?>/admin/providers/reorder"
    >
        <?php foreach ($providers as $index => $provider): ?>
            <?php
                $type = (string)$provider['provider_type'];
                $verified = !empty($provider['verified_at']);
                $enabled = (int)$provider['enabled'] === 1;
            ?>
            <article
                class="provider-card <?= $enabled ? 'is-enabled' : 'is-disabled' ?>"
                data-provider-id="<?= (int)$provider['id'] ?>"
            >
                <button
                    type="button"
                    class="provider-drag"
                    draggable="true"
                    aria-label="Drag to reorder"
                    title="Drag to change priority"
                >
                    <span>⋮⋮</span>
                </button>

                <div class="provider-priority">
                    <span class="provider-priority-label">Priority</span>
                    <strong data-provider-priority><?= $index + 1 ?></strong>
                </div>

                <div class="provider-card-main">
                    <div class="provider-card-title">
                        <h3><?= Util::e((string)$provider['name']) ?></h3>
                        <span class="provider-type">
                            <?= Util::e(MarketplaceProviderDraft::typeLabel($type)) ?>
                        </span>

                        <?php if ($verified): ?>
                            <span class="provider-verified">Tested</span>
                        <?php else: ?>
                            <span class="provider-unverified">Needs test</span>
                        <?php endif; ?>
                    </div>

                    <div class="provider-meta">
                        <?php if (!empty($provider['website_url'])): ?>
                            <a
                                href="<?= Util::e((string)$provider['website_url']) ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Website ↗
                            </a>
                        <?php endif; ?>

                        <span>
                            Token:
                            <?= !empty($provider['token_configured']) ? 'Stored' : 'None' ?>
                        </span>

                        <?php if (!empty($provider['last_tested_at'])): ?>
                            <span>
                                Last test:
                                <?= Util::e((string)$provider['last_tested_at']) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($provider['last_test_message'])): ?>
                        <div class="provider-last-test">
                            <?= Util::e((string)$provider['last_test_message']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="provider-card-actions">
                    <label class="provider-enable-switch">
                        <input
                            type="checkbox"
                            class="provider-toggle"
                            data-toggle-url="<?= Util::e($config['app']['base_path']) ?>/admin/providers/toggle"
                            <?= $enabled ? 'checked' : '' ?>
                        >
                        <span><?= $enabled ? 'Enabled' : 'Disabled' ?></span>
                    </label>

                    <button
                        type="button"
                        class="btn ghost small provider-delete"
                        data-delete-url="<?= Util::e($config['app']['base_path']) ?>/admin/providers/delete"
                    >
                        Remove
                    </button>
                </div>
            </article>
        <?php endforeach; ?>

        <?php if (!$providers): ?>
            <div class="provider-empty" id="providerEmpty">
                <strong>No providers yet</strong>
                <span>Click + Add Provider, test it, then add it to the chain.</span>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="panel provider-composer hidden" id="providerComposer">
    <div class="panel-head">
        <div>
            <h2>Add Provider</h2>
            <p class="settings-subtitle">
                Enter the provider/API settings, run a real test, then Add Provider becomes available.
            </p>
        </div>

        <button type="button" class="btn ghost small" id="providerAddClose">Close</button>
    </div>

    <form id="providerDraftForm" autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= Util::e(Csrf::token()) ?>">
        <input type="hidden" name="test_ticket" id="providerTestTicket" value="">

        <div class="provider-form-grid">
            <label>
                Provider Type
                <select name="provider_type" id="providerType" required>
                    <option value="brightdata">Bright Data</option>
                    <option value="apify">Apify</option>
                    <option value="scrapecreators">ScrapeCreators</option>
                    <option value="generic_json">Custom JSON API</option>
                </select>
            </label>

            <label>
                Name
                <input
                    type="text"
                    name="provider_name"
                    id="providerName"
                    value="Bright Data"
                    maxlength="100"
                    required
                >
            </label>

            <label>
                Website Link
                <input
                    type="url"
                    name="website_url"
                    id="providerWebsite"
                    value="https://brightdata.com/"
                    placeholder="https://provider.example/"
                >
            </label>

            <label class="provider-custom-only hidden">
                API Endpoint
                <input
                    type="url"
                    name="api_endpoint"
                    id="providerEndpoint"
                    placeholder="https://api.example.com/marketplace/item"
                >
            </label>

            <label class="provider-token-field">
                Token / API Key
                <input
                    type="password"
                    name="api_token"
                    id="providerToken"
                    placeholder="Paste token or API key"
                    autocomplete="new-password"
                >
                <small>Stored encrypted after the provider passes its test.</small>
            </label>

            <label>
                Test Facebook Marketplace URL
                <input
                    type="url"
                    name="test_url"
                    id="providerTestUrl"
                    placeholder="https://www.facebook.com/marketplace/item/..."
                    required
                >
                <small>The test must return ID, title, description, and a real listing date.</small>
            </label>
        </div>

        <div class="provider-type-settings" data-provider-settings="brightdata">
            <div class="provider-form-grid">
                <label>
                    Marketplace Dataset ID
                    <input
                        type="text"
                        name="brightdata_dataset_id"
                        value="gd_lvt9iwuh6fbcwmx1a"
                    >
                </label>

                <label>
                    Max Wait
                    <div class="input-suffix">
                        <input
                            type="number"
                            name="timeout_seconds"
                            value="45"
                            min="15"
                            max="180"
                        >
                        <span>sec</span>
                    </div>
                </label>

                <label>
                    Poll Every
                    <div class="input-suffix">
                        <input
                            type="number"
                            name="poll_seconds"
                            value="3"
                            min="2"
                            max="10"
                        >
                        <span>sec</span>
                    </div>
                </label>
            </div>
        </div>

        <div class="provider-type-settings hidden" data-provider-settings="apify">
            <div class="provider-form-grid">
                <label>
                    Max Wait
                    <div class="input-suffix">
                        <input
                            type="number"
                            name="timeout_seconds"
                            value="90"
                            min="20"
                            max="180"
                        >
                        <span>sec</span>
                    </div>
                </label>
            </div>
        </div>

        <div class="provider-type-settings hidden" data-provider-settings="scrapecreators">
            <div class="provider-form-grid">
                <label>
                    Max Wait
                    <div class="input-suffix">
                        <input
                            type="number"
                            name="timeout_seconds"
                            value="20"
                            min="8"
                            max="45"
                        >
                        <span>sec</span>
                    </div>
                </label>
            </div>
        </div>

        <div class="provider-type-settings hidden" data-provider-settings="generic_json">
            <div class="provider-custom-box">
                <div class="provider-form-grid">
                    <label>
                        Request Method
                        <select name="request_method">
                            <option value="GET">GET</option>
                            <option value="POST">POST</option>
                        </select>
                    </label>

                    <label>
                        Authentication
                        <select name="auth_mode" id="providerAuthMode">
                            <option value="bearer">Bearer Token</option>
                            <option value="header">Custom Header</option>
                            <option value="query">Query Parameter</option>
                            <option value="none">No Authentication</option>
                        </select>
                    </label>

                    <label id="providerAuthNameWrap" class="hidden">
                        Header / Query Name
                        <input
                            type="text"
                            name="auth_name"
                            placeholder="x-api-key"
                        >
                    </label>

                    <label>
                        Listing URL Input
                        <select name="input_mode">
                            <option value="query">Query Parameter</option>
                            <option value="json">JSON Body</option>
                        </select>
                    </label>

                    <label>
                        Listing URL Field Name
                        <input type="text" name="input_key" value="url">
                    </label>

                    <label>
                        Max Wait
                        <div class="input-suffix">
                            <input
                                type="number"
                                name="timeout_seconds"
                                value="20"
                                min="8"
                                max="60"
                            >
                            <span>sec</span>
                        </div>
                    </label>
                </div>

                <h3>JSON Field Mapping</h3>
                <p class="settings-subtitle">
                    Dot notation is supported, for example <code>data.item.title</code> or <code>0.id</code>.
                </p>

                <div class="provider-map-grid">
                    <label>
                        Item ID Path
                        <input type="text" name="id_path" value="id">
                    </label>

                    <label>
                        Title Path
                        <input type="text" name="title_path" value="title">
                    </label>

                    <label>
                        Description Path
                        <input
                            type="text"
                            name="description_path"
                            value="description"
                        >
                    </label>

                    <label>
                        Listing Date Path
                        <input
                            type="text"
                            name="date_path"
                            value="creation_time"
                        >
                    </label>

                    <label>
                        Canonical URL Path
                        <input type="text" name="url_path" value="url">
                    </label>
                </div>
            </div>
        </div>

        <div class="provider-test-result hidden" id="providerDraftResult"></div>

        <div class="provider-composer-actions">
            <button
                type="button"
                class="btn"
                id="providerTestButton"
                data-test-url="<?= Util::e($config['app']['base_path']) ?>/admin/providers/test"
            >
                Test Provider
            </button>

            <button
                type="button"
                class="btn primary"
                id="providerAddButton"
                data-add-url="<?= Util::e($config['app']['base_path']) ?>/admin/providers/add"
                disabled
            >
                Add Provider
            </button>
        </div>
    </form>
</section>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Recent Provider Jobs</h2>
            <p class="settings-subtitle">
                Test attempts and live failover attempts are logged here.
            </p>
        </div>
    </div>

    <div class="tablewrap">
        <table>
            <tr>
                <th>Time</th>
                <th>User</th>
                <th>Provider</th>
                <th>Item</th>
                <th>Status</th>
                <th>HTTP</th>
                <th>Error</th>
            </tr>

            <?php foreach ($jobs as $job): ?>
                <tr>
                    <td><?= Util::e((string)$job['created_at']) ?></td>
                    <td><?= Util::e((string)$job['display_name']) ?></td>
                    <td>
                        <?= Util::e((string)(
                            $providerNames[(string)$job['provider']]
                            ?? ucwords(str_replace('_', ' ', (string)$job['provider']))
                        )) ?>
                    </td>
                    <td><?= Util::e((string)($job['external_post_id'] ?: '—')) ?></td>
                    <td>
                        <span class="provider-job <?= Util::e((string)$job['status']) ?>">
                            <?= Util::e(ucfirst((string)$job['status'])) ?>
                        </span>
                    </td>
                    <td>
                        <?= $job['provider_http_status'] !== null
                            ? (int)$job['provider_http_status']
                            : '—' ?>
                    </td>
                    <td class="job-error">
                        <?= Util::e((string)($job['error_message'] ?: '—')) ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (!$jobs): ?>
                <tr><td colspan="7">No provider jobs yet.</td></tr>
            <?php endif; ?>
        </table>
    </div>
</section>
