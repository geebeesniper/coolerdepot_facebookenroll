<?php
/**
 * File / 文件：app/Views/admin/settings.php
 * EN: Renders the admin/settings application view template.
 * 中文：渲染应用视图模板 admin/settings。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
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

<section class="panel settings-card" id="application-settings">
    <div class="panel-head settings-section-head">
        <div>
            <div class="eyebrow">Application</div>
            <h2>Application Settings</h2>
            <p class="settings-subtitle">Set the display name and the Portal address used when login re-check fails.</p>
        </div>
    </div>
    <div class="application-settings-stack">
        <form method="post" class="application-setting-form" action="<?= Util::e($config['app']['base_path']) ?>/admin/settings/brand">
            <input type="hidden" name="_csrf" value="<?= Util::e(Csrf::token()) ?>">
            <input type="hidden" name="setting_scope" value="company_name">
            <label class="application-setting-label" for="applicationCompanyName">Company name</label>
            <div class="application-setting-control-row">
                <input
                    id="applicationCompanyName"
                    type="text"
                    name="company_name"
                    maxlength="80"
                    required
                    value="<?= Util::e((string)$companyName) ?>"
                    placeholder="CoolerDepot"
                >
                <button class="btn primary application-setting-save" type="submit">Save Name</button>
            </div>
        </form>

        <form method="post" class="application-setting-form" action="<?= Util::e($config['app']['base_path']) ?>/admin/settings/brand">
            <input type="hidden" name="_csrf" value="<?= Util::e(Csrf::token()) ?>">
            <input type="hidden" name="setting_scope" value="portal_url">
            <label class="application-setting-label" for="applicationPortalFallbackUrl">Portal fallback URL</label>
            <div class="application-setting-control-row">
                <input
                    id="applicationPortalFallbackUrl"
                    type="url"
                    name="auth_failure_redirect_url"
                    maxlength="2048"
                    value="<?= Util::e((string)($authFailureRedirectUrl ?? '')) ?>"
                    placeholder="https://portal.example.com/login"
                    autocomplete="url"
                >
                <button class="btn primary application-setting-save" type="submit">Save URL</button>
            </div>
            <small class="settings-field-help application-setting-help">If session re-check fails, the browser redirects to this fixed http/https address.</small>
        </form>
    </div>
</section>

<?php
$locationNoticeKey = [
    'added'=>'locationAdded',
    'updated'=>'locationUpdated',
    'deleted'=>'locationDeleted',
    'duplicate'=>'locationDuplicate',
    'invalid'=>'locationInvalid',
    'in-use'=>'locationInUse',
    'missing'=>'locationMissing',
    'error'=>'locationError',
][(string)($locationNotice ?? '')] ?? '';
?>
<section
    class="panel settings-card sales-locations-panel"
    id="sales-locations"
    data-location-add-url="<?= Util::e($config['app']['base_path']) ?>/admin/settings/location/add"
    data-location-update-url="<?= Util::e($config['app']['base_path']) ?>/admin/settings/location/update"
    data-location-delete-url="<?= Util::e($config['app']['base_path']) ?>/admin/settings/location/delete"
    data-csrf="<?= Util::e(Csrf::token()) ?>"
>
    <div class="panel-head settings-section-head sales-locations-head">
        <div>
            <div class="eyebrow" data-app-i18n="salesOrganization">Sales Organization</div>
            <h2 data-app-i18n="locations">Locations</h2>
            <p class="settings-subtitle" data-app-i18n="locationsHelp">Create locations here, then assign one from each Sales card Settings button on the Admin dashboard.</p>
        </div>
        <span class="provider-count">
            <b id="salesLocationCount"><?= count($locations ?? []) ?></b>
            <span data-app-i18n="locationsLower">locations</span>
        </span>
    </div>

    <?php if ($locationNoticeKey !== ''): ?>
        <div class="notice <?= in_array((string)$locationNotice,['added','updated','deleted'],true) ? 'ok' : 'bad' ?> sales-location-notice" role="status">
            <span data-app-i18n="<?= Util::e($locationNoticeKey) ?>"></span>
        </div>
    <?php endif; ?>
    <div class="notice sales-location-live-notice hidden" id="salesLocationLiveNotice" role="status" aria-live="polite"></div>

    <form method="post" class="sales-location-add-form js-location-add-form" action="<?= Util::e($config['app']['base_path']) ?>/admin/settings/location/add">
        <input type="hidden" name="_csrf" value="<?= Util::e(Csrf::token()) ?>">
        <label for="newSalesLocation" data-app-i18n="locationName">Location name</label>
        <div class="sales-location-add-row">
            <input id="newSalesLocation" type="text" name="location_name" maxlength="120" required placeholder="Los Angeles" autocomplete="off">
            <button class="btn primary" type="submit" data-app-i18n="addLocation">Add Location</button>
        </div>
    </form>

    <div class="sales-location-list" id="salesLocationList" aria-label="Sales locations">
        <?php foreach (($locations ?? []) as $location): ?>
            <?php $locationSalesCount=(int)($location['sales_count'] ?? 0); ?>
            <article
                class="sales-location-card"
                data-location-card
                data-location-id="<?= (int)$location['id'] ?>"
                data-sales-count="<?= $locationSalesCount ?>"
            >
                <div class="sales-location-card-main">
                    <div class="sales-location-card-copy">
                        <strong data-location-name><?= Util::e((string)$location['name']) ?></strong>
                        <span>
                            <b data-location-sales-count><?= $locationSalesCount ?></b>
                            <span data-app-i18n="sales">Sales</span>
                        </span>
                    </div>
                    <div class="sales-location-card-actions">
                        <button class="tiny" type="button" data-location-edit data-app-i18n="editLocation">Edit</button>
                        <form method="post" class="js-location-delete-form" action="<?= Util::e($config['app']['base_path']) ?>/admin/settings/location/delete">
                            <input type="hidden" name="_csrf" value="<?= Util::e(Csrf::token()) ?>">
                            <input type="hidden" name="location_id" value="<?= (int)$location['id'] ?>">
                            <button
                                class="tiny badbtn"
                                type="submit"
                                data-app-i18n="deleteLocation"
                            >Delete</button>
                        </form>
                    </div>
                </div>
                <form method="post" class="sales-location-edit-form js-location-edit-form hidden" action="<?= Util::e($config['app']['base_path']) ?>/admin/settings/location/update">
                    <input type="hidden" name="_csrf" value="<?= Util::e(Csrf::token()) ?>">
                    <input type="hidden" name="location_id" value="<?= (int)$location['id'] ?>">
                    <input
                        class="sales-location-edit-input"
                        type="text"
                        name="location_name"
                        maxlength="120"
                        required
                        autocomplete="off"
                        value="<?= Util::e((string)$location['name']) ?>"
                        aria-label="Location name"
                    >
                    <div class="sales-location-edit-actions">
                        <button class="tiny primary" type="submit" data-app-i18n="saveLocation">Save</button>
                        <button class="tiny" type="button" data-location-edit-cancel data-app-i18n="cancel">Cancel</button>
                    </div>
                </form>
            </article>
        <?php endforeach; ?>

        <?php if (empty($locations)): ?>
            <div class="sales-location-empty" id="salesLocationEmpty" data-app-i18n="noLocations">No locations yet. Add the first location above.</div>
        <?php endif; ?>
    </div>

    <p
        class="settings-subtitle sales-location-unassigned <?= (int)($unassignedSalesCount ?? 0) > 0 ? '' : 'hidden' ?>"
        id="salesLocationUnassigned"
    >
        <b data-unassigned-count><?= (int)($unassignedSalesCount ?? 0) ?></b>
        <span data-app-i18n="salesUnassignedLocation">Sales currently have no location assigned.</span>
    </p>
</section>

<section class="panel settings-card verification-locks-panel" id="verification-locks">
    <div class="panel-head settings-section-head">
        <div>
            <div class="eyebrow" data-app-i18n="verificationControl">Verification Control</div>
            <h2 data-app-i18n="postVerificationLocks">Post Verification Locks</h2>
            <p class="settings-subtitle" data-app-i18n="verificationLocksHelp">
                One Sales user can run only one Marketplace verification at a time. Use Unlock only when a Sales verification is stuck.
            </p>
        </div>
        <span class="provider-count"
              data-app-i18n-count="verificationLocksActive"
              data-i18n-count="<?= count($inspectionLocks ?? []) ?>"><?= count($inspectionLocks ?? []) ?> active</span>
    </div>

    <?php if (!empty($inspectionLockError)): ?>
        <div class="banner bad"><span data-app-i18n="verificationLocksReadError">Verification locks could not be read</span>: <?= Util::e((string)$inspectionLockError) ?></div>
    <?php elseif (empty($inspectionLocks)): ?>
        <div class="verification-lock-empty" data-app-i18n="verificationLocksEmpty">No Sales verification is currently locked.</div>
    <?php else: ?>
        <div class="verification-lock-list">
            <?php foreach ($inspectionLocks as $lock): ?>
                <div class="verification-lock-row">
                    <div class="verification-lock-person">
                        <strong><?= Util::e((string)($lock['display_name'] ?: ('Sales #' . (int)$lock['sales_user_id']))) ?></strong>
                        <span>#<?= Util::e((string)($lock['sales_id'] ?: $lock['sales_user_id'])) ?></span>
                    </div>
                    <div class="verification-lock-meta">
                        <span><?= Util::e(ucfirst((string)($lock['platform'] ?: 'Marketplace'))) ?></span>
                        <span><span data-app-i18n="started">Started</span> <?= Util::e((string)$lock['started_at']) ?></span>
                    </div>
                    <form method="post" action="<?= Util::e($config['app']['base_path']) ?>/admin/inspection-lock/unlock">
                        <input type="hidden" name="_csrf" value="<?= Util::e(Csrf::token()) ?>">
                        <input type="hidden" name="sales_user_id" value="<?= (int)$lock['sales_user_id'] ?>">
                        <button class="btn badbtn" type="submit" data-app-i18n="unlock">Unlock</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="settings-subtitle verification-lock-warning" data-app-i18n="verificationUnlockWarning">
            Unlock removes the verification gate so the Sales user can try again. It does not forcibly terminate a provider request already executing.
        </p>
    <?php endif; ?>
</section>

<div class="provider-operations-grid">
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
                    aria-label="Drag to reorder"
                    aria-grabbed="false"
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

<section
    class="panel provider-jobs-panel"
    id="providerJobsMonitor"
    data-jobs-url="<?= Util::e($config['app']['base_path']) ?>/admin/providers/jobs"
    data-page="<?= (int)($jobPageData['page'] ?? 1) ?>"
    data-pages="<?= (int)($jobPageData['pages'] ?? 1) ?>"
    data-per-page="<?= (int)($jobPageData['per_page'] ?? 8) ?>"
    data-time-filter="<?= Util::e((string)($jobPageData['time_filter'] ?? '24h')) ?>"
>
    <div class="panel-head provider-jobs-head">
        <div>
            <h2>Recent Provider Jobs</h2>
            <p class="settings-subtitle">
                Test attempts and live failover attempts are logged here.
            </p>
        </div>
        <span class="provider-jobs-live is-live" id="providerJobsLive">
            <span class="provider-live-dot" aria-hidden="true"></span>
            <span id="providerJobsLiveText">Live</span>
        </span>
    </div>

    <div class="provider-jobs-toolbar">
        <label class="provider-jobs-time-control" for="providerJobsTimeFilter">
            <span>Time</span>
            <select id="providerJobsTimeFilter" aria-label="Provider jobs time range">
                <?php foreach ([
                    '1h' => 'Last 1 Hour',
                    '24h' => 'Last 24 Hours',
                    '7d' => 'Last 7 Days',
                    '30d' => 'Last 30 Days',
                    'all' => 'All Time',
                ] as $value => $label): ?>
                    <option
                        value="<?= Util::e($value) ?>"
                        <?= (($jobPageData['time_filter'] ?? '24h') === $value) ? 'selected' : '' ?>
                    ><?= Util::e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <div class="provider-jobs-summary" aria-live="polite">
            <strong id="providerJobsTotal"><?= (int)($jobPageData['total'] ?? 0) ?></strong>
            <span>jobs</span>
        </div>
    </div>

    <div class="tablewrap provider-jobs-tablewrap">
        <table class="provider-jobs-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>User</th>
                    <th>Provider</th>
                    <th>Item</th>
                    <th>Status</th>
                    <th>HTTP</th>
                    <th>Error</th>
                </tr>
            </thead>
            <tbody id="providerJobsBody">
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
                    <tr class="provider-jobs-empty"><td colspan="7">No provider jobs in this time range.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="provider-jobs-pagination" id="providerJobsPagination">
        <button
            type="button"
            class="btn ghost small"
            id="providerJobsPrev"
            <?= ((int)($jobPageData['page'] ?? 1) <= 1) ? 'disabled' : '' ?>
        >Previous</button>
        <span class="provider-jobs-page-copy">
            Page <strong id="providerJobsPage"><?= (int)($jobPageData['page'] ?? 1) ?></strong>
            of <strong id="providerJobsPages"><?= (int)($jobPageData['pages'] ?? 1) ?></strong>
        </span>
        <button
            type="button"
            class="btn ghost small"
            id="providerJobsNext"
            <?= ((int)($jobPageData['page'] ?? 1) >= (int)($jobPageData['pages'] ?? 1)) ? 'disabled' : '' ?>
        >Next</button>
    </div>
</section>

</div>

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

<?php
$websiteScanIcon = static function(string $name): string {
    return match($name){
        'pause'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="6" y="5" width="4" height="14" rx="1" fill="currentColor"/><rect x="14" y="5" width="4" height="14" rx="1" fill="currentColor"/></svg>',
        'play'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.8v12.4c0 .9 1 1.4 1.7.9l9-6.2a1.1 1.1 0 0 0 0-1.8l-9-6.2A1.1 1.1 0 0 0 8 5.8Z" fill="currentColor"/></svg>',
        'stop'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="6" y="6" width="12" height="12" rx="2" fill="currentColor"/></svg>',
        'done'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5.5 12.5 4.1 4.1L18.7 7.5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'failed'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 7.5v6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="17" r="1.2" fill="currentColor"/></svg>',
        default=>'<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="2.5" fill="currentColor"/></svg>',
    };
};
$renderWebsiteHistory = static function(
    array $rows,
    string $emptyText,
    bool $showWebsite=true,
    bool $interactiveScan=false,
    string $historyHost='',
    array $resumableHistoryIds=[]
) use ($websiteScanIcon): void {
    if(!$rows && !$interactiveScan){
        echo '<div class="website-history-empty">'.Util::e($emptyText).'</div>';
        return;
    }
    $colspan=$showWebsite?7:6;
    echo '<div class="website-history-tablewrap'.($interactiveScan?' website-scan-history-tablewrap':'').'"'
        .($interactiveScan?' data-scan-history-host="'.Util::e($historyHost).'"':'').'>'
        .'<table class="website-history-table'.($showWebsite?'':' website-history-table-compact').($interactiveScan?' website-scan-history-table':'').'">'
        .'<thead><tr><th>Started</th>'.($showWebsite?'<th>Website</th>':'').'<th>Status</th><th>Processed</th><th>Saved</th><th>Failed</th><th>Details</th></tr></thead>'
        .'<tbody'.($interactiveScan?' data-scan-history-body':'').'>';
    if(!$rows && $interactiveScan){
        echo '<tr class="website-history-empty-row" data-history-empty-row><td colspan="'.$colspan.'">'.Util::e($emptyText).'</td></tr>';
    }
    foreach($rows as $row){
        $status=strtolower((string)($row['status']??''));
        $statusClass=in_array($status,['completed','running','paused','stopped','failed'],true)?$status:'';
        $details=trim((string)($row['message']??''));
        $sourceUrl=trim((string)($row['source_url']??''));
        $historyId=(int)($row['id']??0);
        $rowHost=strtolower(trim((string)($row['source_host']??$historyHost)));
        if($interactiveScan){
            $statusTitle=match($status){
                'running'=>'Pause this scan',
                'paused'=>'Continue this scan',
                'stopped'=>'Scan stopped',
                'completed'=>'Scan completed',
                'failed'=>'Scan failed',
                default=>'Scan status',
            };
            if($status==='running'){
                $statusHtml='<button type="button" class="website-history-control is-running" data-history-scan-control data-history-action="pause" data-history-id="'.$historyId.'" data-source-host="'.Util::e($rowHost).'" aria-label="'.Util::e($statusTitle).'" title="'.Util::e($statusTitle).'">'.$websiteScanIcon('pause').'</button>';
            }elseif($status==='paused'){
                $statusHtml='<button type="button" class="website-history-control is-paused" data-history-scan-control data-history-action="resume" data-history-id="'.$historyId.'" data-source-host="'.Util::e($rowHost).'" aria-label="'.Util::e($statusTitle).'" title="'.Util::e($statusTitle).'">'.$websiteScanIcon('play').'</button>';
            }elseif($status==='stopped'){
                $statusHtml='<span class="website-history-control is-stopped is-static" aria-label="'.Util::e($statusTitle).'" title="'.Util::e($statusTitle).'">'.$websiteScanIcon('stop').'</span>';
            }elseif($status==='completed'){
                $statusHtml='<span class="website-history-control is-completed is-static" aria-label="'.Util::e($statusTitle).'" title="'.Util::e($statusTitle).'">'.$websiteScanIcon('done').'</span>';
            }elseif($status==='failed'){
                $statusHtml='<span class="website-history-control is-failed is-static" aria-label="'.Util::e($statusTitle).'" title="'.Util::e($statusTitle).'">'.$websiteScanIcon('failed').'</span>';
            }else{
                $statusHtml='<span class="website-history-control is-static" aria-label="'.Util::e($statusTitle).'" title="'.Util::e($statusTitle).'">'.$websiteScanIcon('dot').'</span>';
            }
            echo '<tr class="website-history-main-row" data-scan-history-row data-website-history-id="'.$historyId.'" data-history-source-host="'.Util::e($rowHost).'" tabindex="0" aria-expanded="false">'
                .'<td>'.Util::e((string)($row['created_at']??'')).'</td>'
                .($showWebsite?'<td><strong>'.Util::e((string)($row['source_host']??'')).'</strong></td>':'')
                .'<td data-history-status-cell>'.$statusHtml.'</td>'
                .'<td data-history-processed>'.(int)($row['processed']??0).'</td>'
                .'<td data-history-saved>'.(int)($row['saved']??0).'</td>'
                .'<td data-history-failed>'.(int)($row['failed']??0).'</td>'
                .'<td class="website-history-details-summary"><span data-history-detail-summary>'.Util::e($details!==''?$details:'Click to view processing log.').'</span><span class="website-history-row-chevron" aria-hidden="true"></span></td>'
                .'</tr>';
            echo '<tr class="website-history-detail-row hidden" data-history-detail-row="'.$historyId.'"><td colspan="'.$colspan.'">'
                .'<div class="website-history-detail-panel">'
                .'<div class="website-history-detail-head"><strong>Processing log</strong><small>Each scanned URL is recorded here as it finishes.</small></div>';
            if($sourceUrl!==''){
                echo '<a data-history-source-link href="'.Util::e($sourceUrl).'" target="_blank" rel="noopener noreferrer">'.Util::e($sourceUrl).'</a>';
            }
            echo '<div class="website-history-run-summary" data-history-detail-text>'.Util::e($details!==''?$details:'No additional details recorded.').'</div>'
                .'<div class="website-history-processing-head"><span>Time</span><span>Result</span><span>Type</span><span>URL</span><span>Details</span></div>'
                .'<div class="website-history-processing-log" data-history-processing-log data-history-id="'.$historyId.'">'
                .'<div class="website-history-processing-empty" data-history-processing-empty>Click this row to load the per-URL processing log.</div>'
                .'</div>'
                .'<small>Updated '.Util::e((string)($row['updated_at']??'—')).'</small>'
                .'</div></td></tr>';
            continue;
        }
        echo '<tr data-website-history-id="'.$historyId.'">'
            .'<td>'.Util::e((string)($row['created_at']??'')).'</td>'
            .($showWebsite?'<td><strong>'.Util::e((string)($row['source_host']??'')).'</strong></td>':'')
            .'<td><span data-history-status class="website-history-status is-'.Util::e($statusClass).'">'.Util::e($status!==''?ucfirst($status):'—').'</span></td>'
            .'<td data-history-processed>'.(int)($row['processed']??0).'</td>'
            .'<td data-history-saved>'.(int)($row['saved']??0).'</td>'
            .'<td data-history-failed>'.(int)($row['failed']??0).'</td>'
            .'<td class="website-history-detail" data-history-detail>';
        if($sourceUrl!==''){
            echo '<a href="'.Util::e($sourceUrl).'" target="_blank" rel="noopener noreferrer">'.Util::e($sourceUrl).'</a>';
            if($details!==''){echo '<small>'.Util::e($details).'</small>';}
        }else{
            echo Util::e($details!==''?$details:'—');
        }
        echo '</td></tr>';
    }
    echo '</tbody></table></div>';
}
?>

<section class="panel website-library" id="website-comparison"
    data-search-url="<?= Util::e($config['app']['base_path']) ?>/admin/website/references"
    data-delete-url="<?= Util::e($config['app']['base_path']) ?>/admin/website/reference/delete"
    data-csrf="<?= Util::e(Csrf::token()) ?>">
    <div class="panel-head settings-section-head">
        <div>
            <div class="eyebrow">Duplicate Sources</div>
            <h2>Company Website Library</h2>
            <p class="settings-subtitle">Website Scan, CSV Import and Page / Sitemap Import are separated below. Open 1, 2 or 3 to see its controls and history.</p>
        </div>
        <?php if (!empty($websiteStats['library_ready'])): ?>
            <div class="website-library-stats">
                <strong><?= (int)$websiteStats['total'] ?></strong>
                <span>references</span>
                <strong><?= (int)$websiteStats['pending'] ?></strong>
                <span>images pending</span>
            </div>
        <?php endif; ?>
    </div>

    <?php if (empty($websiteStats['library_ready'])): ?>
        <div class="banner bad">Run <code>php scripts/migrate_v0_1_70.php</code>, then <code>php scripts/migrate_v0_1_71.php</code>.</div>
    <?php else: ?>
        <div class="website-tools-grid" data-website-tools>
            <button type="button" class="website-tool-card website-tool-card-one" data-website-tool-toggle="website-tool-panel-1" aria-expanded="false" aria-controls="website-tool-panel-1">
                <span class="settings-step">1</span>
                <span class="website-tool-card-copy">
                    <strong>Website Scan</strong>
                    <small>Add websites, scan products, manage scanned URLs and review scan history.</small>
                    <span class="website-tool-card-count"><?= count($websiteSources ?? []) ?> website<?= count($websiteSources ?? [])===1?'':'s' ?></span>
                </span>
                <span class="website-tool-arrow" aria-hidden="true"></span>
            </button>

            <button type="button" class="website-tool-card website-tool-card-two" data-website-tool-toggle="website-tool-panel-2" aria-expanded="false" aria-controls="website-tool-panel-2">
                <span class="settings-step">2</span>
                <span class="website-tool-card-copy">
                    <strong>URL CSV</strong>
                    <small>Import a prepared CSV; the website is detected from its URLs automatically.</small>
                    <span class="website-tool-card-count"><?= count($websiteCsvHistory ?? []) ?> import record<?= count($websiteCsvHistory ?? [])===1?'':'s' ?></span>
                </span>
                <span class="website-tool-arrow" aria-hidden="true"></span>
            </button>

            <button type="button" class="website-tool-card website-tool-card-three" data-website-tool-toggle="website-tool-panel-3" aria-expanded="false" aria-controls="website-tool-panel-3">
                <span class="settings-step">3</span>
                <span class="website-tool-card-copy">
                    <strong>Page / Sitemap Import</strong>
                    <small>Scan one page or sitemap; its website is detected automatically from the URL.</small>
                    <span class="website-tool-card-count"><?= count($websiteAdvancedHistory ?? []) ?> scan/import record<?= count($websiteAdvancedHistory ?? [])===1?'':'s' ?></span>
                </span>
                <span class="website-tool-arrow" aria-hidden="true"></span>
            </button>
        </div>

        <section class="website-tool-detail website-tool-detail-one hidden" id="website-tool-panel-1" data-website-tool-panel="website-tool-panel-1">
            <div class="website-tool-detail-head">
                <div><span class="settings-step">1</span><div><strong>Website Scan</strong><small>The Website list belongs inside Step 1. Starting a scan saves a new website automatically.</small></div></div>
                <button type="button" class="website-tool-detail-close" data-website-tool-close="website-tool-panel-1" aria-label="Close Website Scan">×</button>
            </div>

            <div class="website-source-manager"
                data-reference-search-url="<?= Util::e($config['app']['base_path']) ?>/admin/website/references"
                data-reference-add-url="<?= Util::e($config['app']['base_path']) ?>/admin/website/reference/add"
                data-reference-delete-url="<?= Util::e($config['app']['base_path']) ?>/admin/website/reference/delete"
                data-scan-start-url="<?= Util::e($config['app']['base_path']) ?>/admin/website/products/scan-start"
                data-scan-step-url="<?= Util::e($config['app']['base_path']) ?>/admin/website/products/scan-step"
                data-scan-status-url="<?= Util::e($config['app']['base_path']) ?>/admin/website/products/scan-status"
                data-scan-stop-url="<?= Util::e($config['app']['base_path']) ?>/admin/website/products/scan-stop"
                data-scan-resume-url="<?= Util::e($config['app']['base_path']) ?>/admin/website/products/scan-resume"
                data-running-hosts="<?= Util::e(json_encode(array_values($websiteRunningScanHosts ?? []), JSON_UNESCAPED_SLASHES) ?: '[]') ?>"
                data-csrf="<?= Util::e(Csrf::token()) ?>">
                <?php $primaryWebsite=''; $hasActiveWebsiteScan=!empty($websiteRunningScanHosts); ?>
                <form method="post" class="website-source-add" action="<?= Util::e($config['app']['base_path']) ?>/admin/settings/website">
                    <input type="hidden" name="_csrf" value="<?= Util::e(Csrf::token()) ?>">
                    <label class="website-source-add-label" for="companyWebsiteScanUrl">Website URL</label>
                    <input id="companyWebsiteScanUrl" type="url" name="website_url" required
                        value="<?= Util::e($primaryWebsite) ?>" placeholder="https://your-company.com">
                    <div class="website-source-add-actions">
                        <button class="btn primary website-product-scan-button" type="button"
                            data-website-input="#companyWebsiteScanUrl">Scan Website</button>
                    </div>
                    <div class="website-product-scan-progress-wrap hidden">
                        <div class="website-product-scan-progress website-product-scan-primary-progress" aria-live="polite"></div>
                        <button type="button" class="website-scan-progress-close" aria-label="Hide scan progress">×</button>
                    </div>
                </form>

                <div class="website-detail-section-head">
                    <div><strong>Saved Websites</strong><small>Click a website card to open scan controls, live counters and scanned product details.</small></div>
                    <span><?= count($websiteSources ?? []) ?> total</span>
                </div>

                <div class="website-source-list">
                    <?php foreach (($websiteSources ?? []) as $source):
                        $host=(string)$source['host'];$stat=$websiteSourceStats[$host]??[]; ?>
                        <article class="website-product-source" data-website-source="<?= Util::e($host) ?>" data-website-url="<?= Util::e((string)$source['url']) ?>">
                            <button type="button" class="website-source-expand" data-source-host="<?= Util::e($host) ?>" aria-expanded="false">
                                <span class="website-source-expand-copy">
                                    <strong><?= Util::e($host) ?></strong>
                                    <span><?= Util::e((string)$source['url']) ?></span>
                                </span>
                                <span class="website-source-card-state" data-source-scan-state>Ready</span>
                                <span class="website-source-card-meta"><b data-source-stat="products"><?= (int)($stat['total']??0) ?></b> products · <b data-source-stat="images-found"><?= (int)($stat['images_found']??0) ?></b> images</span>
                                <span class="website-source-expand-arrow" aria-hidden="true"></span>
                            </button>
                            <div class="website-source-card-detail hidden" data-website-source-detail>
                                <div class="website-product-source-stats">
                                    <span><b data-source-stat="products"><?= (int)($stat['total']??0) ?></b> unique products</span>
                                    <span><b data-source-stat="checked">0</b> pages checked</span>
                                    <span><b data-source-stat="images-found"><?= (int)($stat['images_found']??0) ?></b> first images</span>
                                    <span><b data-source-stat="indexed"><?= (int)($stat['indexed']??0) ?></b> fingerprints</span>
                                    <span class="website-source-skip-stat"><b data-source-stat="skipped-existing">0</b> existing product URLs skipped</span>
                                </div>
                                <div class="website-product-source-actions">
                                    <a class="btn ghost" href="<?= Util::e((string)$source['url']) ?>" target="_blank" rel="noopener noreferrer">Open Website ↗</a>
                                    <button type="button" class="btn website-product-scan-button"
                                        data-website-url="<?= Util::e((string)$source['url']) ?>"
                                        <?= $hasActiveWebsiteScan && !in_array($host,$websiteRunningScanHosts,true) ? 'disabled data-global-scan-disabled="1" title="Wait for the active website scan to finish or pause it first."' : '' ?>>Scan Website</button>
                                    <form method="post" class="website-source-delete-form" action="<?= Util::e($config['app']['base_path']) ?>/admin/website/source/remove">
                                        <input type="hidden" name="_csrf" value="<?= Util::e(Csrf::token()) ?>">
                                        <input type="hidden" name="host" value="<?= Util::e($host) ?>">
                                        <button class="btn badbtn website-source-delete" type="submit"
                                            data-reference-count="<?= (int)($stat['total']??0) ?>"
                                            <?= $hasActiveWebsiteScan ? 'disabled data-global-scan-disabled="1" title="Pause the active website scan before deleting any website."' : '' ?>>Delete Website</button>
                                    </form>
                                </div>
                                <div class="website-product-scan-progress-wrap hidden">
                                    <div class="website-product-scan-progress" aria-live="polite"></div>
                                    <button type="button" class="website-scan-progress-close" aria-label="Hide scan progress">×</button>
                                </div>
                                <?php
                                $sourceScanHistory=array_values(array_filter(($websiteProductScanHistory ?? []),static function(array $row) use ($host): bool {
                                    return strcasecmp(trim((string)($row['source_host']??'')),$host)===0;
                                }));
                                ?>
                                <div class="website-source-card-history">
                                    <div class="website-detail-section-head website-history-heading">
                                        <div><strong>Product Scan History</strong><small>History for this website only.</small></div>
                                        <span><b data-scan-history-count><?= count($sourceScanHistory) ?></b> records</span>
                                    </div>
                                    <?php $renderWebsiteHistory($sourceScanHistory,'No Website Scan history yet.',false,true,$host,$websiteResumableScanHistoryIds ?? []); ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                    <?php if (empty($websiteSources)): ?>
                        <div class="website-source-empty">No website sources yet. Enter a URL above and click Scan Website.</div>
                    <?php endif; ?>
                </div>

            </div>
        </section>

        <section class="website-tool-detail website-tool-detail-two hidden" id="website-tool-panel-2" data-website-tool-panel="website-tool-panel-2">
            <div class="website-tool-detail-head">
                <div><span class="settings-step">2</span><div><strong>URL CSV</strong><small>Upload a CSV; its URL column determines the website automatically. Import history stays below.</small></div></div>
                <button type="button" class="website-tool-detail-close" data-website-tool-close="website-tool-panel-2" aria-label="Close URL CSV">×</button>
            </div>
            <form method="post" enctype="multipart/form-data" class="website-source-card website-tool-form website-tool-form-csv"
                action="<?= Util::e($config['app']['base_path']) ?>/admin/duplicate-catalog/import">
                <input type="hidden" name="_csrf" value="<?= Util::e(Csrf::token()) ?>">
                <div class="website-tool-field website-tool-field-file">
                    <label for="websiteCsvFile">CSV file</label>
                    <input id="websiteCsvFile" type="file" name="catalog" accept=".csv,text/csv" required>
                </div>
                <div class="website-tool-action-field">
                    <span class="website-tool-field-label" aria-hidden="true">Actions</span>
                    <div class="website-card-actions">
                        <button class="btn" type="submit">Import CSV</button>
                        <a class="btn ghost" href="<?= Util::e($config['app']['base_path']) ?>/admin/website-catalog/sample.csv">Download Sample CSV</a>
                    </div>
                </div>
            </form>
            <div class="website-detail-section-head website-history-heading">
                <div><strong>CSV Import History</strong><small>Processed, saved and failed row counts are kept per run.</small></div>
                <span><?= count($websiteCsvHistory ?? []) ?> records</span>
            </div>
            <?php $renderWebsiteHistory($websiteCsvHistory ?? [],'No CSV import history yet.'); ?>
        </section>

        <section class="website-tool-detail website-tool-detail-three hidden" id="website-tool-panel-3" data-website-tool-panel="website-tool-panel-3">
            <div class="website-tool-detail-head">
                <div><span class="settings-step">3</span><div><strong>Page / Sitemap Import</strong><small>Enter a page or sitemap URL; its website is detected automatically. History stays below.</small></div></div>
                <button type="button" class="website-tool-detail-close" data-website-tool-close="website-tool-panel-3" aria-label="Close Page / Sitemap Import">×</button>
            </div>
            <form method="post" class="website-source-card website-tool-form website-tool-form-sitemap" action="<?= Util::e($config['app']['base_path']) ?>/admin/website/scan">
                <input type="hidden" name="_csrf" value="<?= Util::e(Csrf::token()) ?>">
                <div class="website-tool-field">
                    <label for="websiteSitemapUrl">Page / Sitemap URL</label>
                    <input id="websiteSitemapUrl" type="url" name="source_url" required placeholder="https://your-company.com/sitemap.xml">
                </div>
                <div class="website-tool-action-field">
                    <span class="website-tool-field-label" aria-hidden="true">Action</span>
                    <button class="btn" type="submit">Scan &amp; Import</button>
                </div>
            </form>
            <div class="website-detail-section-head website-history-heading">
                <div><strong>Scan &amp; Import History</strong><small>Every manual page/sitemap run is recorded with its source URL and result.</small></div>
                <span><?= count($websiteAdvancedHistory ?? []) ?> records</span>
            </div>
            <?php $renderWebsiteHistory($websiteAdvancedHistory ?? [],'No Page / Sitemap import history yet.'); ?>
        </section>
    <?php endif; ?>
</section>
