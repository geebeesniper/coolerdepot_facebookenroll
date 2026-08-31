<?php
use App\Core\Csrf;
use App\Core\Util;
?>
<div
    id="salesSubmitMessage"
    class="sales-submit-message hidden"
    aria-live="polite"
></div>

<div class="sales-submit-layout">
    <section class="panel sales-submit-panel">
        <div class="sales-submit-section-head">
            <div>
                <span class="eyebrow" data-sales-i18n="stepOne">
                    Step 1
                </span>
                <h2 data-sales-i18n="verifyListing">
                    Verify Listing
                </h2>
            </div>
        </div>

        <form id="inspectForm" novalidate>
            <input
                type="hidden"
                name="_csrf"
                value="<?= Util::e(Csrf::token()) ?>"
            >
            <input
                type="hidden"
                name="platform"
                id="detectedPlatformValue"
                value=""
            >

            <label for="postUrl">
                <span data-sales-i18n="postUrl">
                    Post URL / Share Link
                </span>
            </label>

            <div class="sales-url-row">
                <input
                    type="url"
                    name="url"
                    id="postUrl"
                    required
                    autocomplete="off"
                    placeholder="Paste Facebook, OfferUp, or Craigslist URL"
                    data-sales-placeholder="postUrl"
                >

                <button
                    id="inspectButton"
                    class="btn primary"
                    disabled
                    type="submit"
                >
                    <span data-sales-i18n="checkPost">Check Post</span>
                </button>
            </div>

            <div class="platform-detect-row">
                <span class="muted-label" data-sales-i18n="platform">
                    Platform
                </span>
                <span
                    id="detectedPlatform"
                    class="detected-platform empty-platform"
                >
                    <span data-sales-i18n="pasteSupported">
                        Paste a supported URL
                    </span>
                </span>
            </div>
        </form>

        <div id="inspectionProgress" class="sales-inspection-progress hidden">
            <div data-inspection-step="platform"><span data-sales-i18n="detectingPlatform">Detecting platform…</span><strong class="inspection-step-state">Waiting</strong></div>
            <div data-inspection-step="duplicate"><span data-sales-i18n="checkingDuplicates">Checking duplicates…</span><strong class="inspection-step-state">Waiting</strong></div>
            <div data-inspection-step="fetch"><span data-sales-i18n="fetchingPost">Fetching verified post information…</span><strong class="inspection-step-state">Waiting</strong></div>
            <div data-inspection-step="date"><span data-sales-i18n="checkingDate">Checking listing date…</span><strong class="inspection-step-state">Waiting</strong></div>
            <div data-inspection-step="final"><span data-sales-i18n="finalDuplicate">Final duplicate check…</span><strong class="inspection-step-state">Waiting</strong></div>
        </div>
    </section>

    <section class="panel sales-submit-panel">
        <div class="sales-submit-section-head">
            <div>
                <span class="eyebrow" data-sales-i18n="stepTwo">
                    Step 2
                </span>
                <h2 data-sales-i18n="verificationResult">
                    Verification Result
                </h2>
            </div>
        </div>

        <div
            id="inspectionEmpty"
            class="sales-verification-empty"
        >
            <div class="sales-verification-empty-icon">✓</div>
            <strong data-sales-i18n="readyToVerify">
                Ready to verify
            </strong>
            <span data-sales-i18n="pasteAndCheck">
                Paste a listing URL and click Check Post.
            </span>
        </div>

        <div id="inspectionResult" class="hidden">
            <div id="verificationBanner" class="banner"></div>
            <a id="salesDuplicateSource" class="sales-duplicate-source hidden" href="#" target="_blank" rel="noopener noreferrer">Open existing duplicate post ↗</a>
            <div id="duplicateComparisonWarnings" class="duplicate-comparison-warnings hidden" role="status"></div>
            <p class="sales-verification-description">Comparison covers saved same-platform posts and imported website references. Image checks use available indexed photos; review any warnings below.</p>

            <div class="sales-verification-card">
                <div class="sales-verification-title-row">
                    <div>
                        <span
                            class="sales-verification-platform"
                            id="resultPlatform"
                        >
                            —
                        </span>
                        <h3 id="resultTitle">—</h3>
                    </div>
                </div>

                <p
                    class="sales-verification-description"
                    id="resultDescription"
                >
                    —
                </p>

                <dl class="sales-verification-facts">
                    <div>
                        <dt data-sales-i18n="published">Published</dt>
                        <dd id="resultDate">—</dd>
                    </div>
                    <div>
                        <dt data-sales-i18n="postId">Post ID</dt>
                        <dd id="resultExternalId">—</dd>
                    </div>
                    <div class="wide">
                        <dt data-sales-i18n="originalUrl">Original URL</dt>
                        <dd id="resultCanonical">—</dd>
                    </div>
                </dl>
            </div>

            <form
                method="post"
                id="salesVerifiedSaveForm"
                action="<?= Util::e($config['app']['base_path']) ?>/sales/save"
            >
                <input
                    type="hidden"
                    name="_csrf"
                    value="<?= Util::e(Csrf::token()) ?>"
                >
                <input
                    type="hidden"
                    name="inspection_token"
                    id="inspectionToken"
                >

                <button
                    id="saveButton"
                    class="btn success full"
                    disabled
                >
                    <span data-sales-i18n="saveVerified">
                        Save Verified Post
                    </span>
                </button>
            </form>

            <div
                class="sales-post-save-complete hidden"
                id="salesPostSaveComplete"
            >
                <strong>Saved ✓</strong>
                <span>Your verified post was saved.</span>
                <a
                    class="btn"
                    href="<?= Util::e($config['app']['base_path']) ?>/sales"
                >
                    View My Posts
                </a>
            </div>
        </div>
    </section>
</div>
