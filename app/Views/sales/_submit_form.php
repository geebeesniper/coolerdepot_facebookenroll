<?php
/**
 * File / 文件：app/Views/sales/_submit_form.php
 * EN: Renders the sales/_submit_form application view template.
 * 中文：渲染应用视图模板 sales/_submit_form。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
use App\Core\Csrf;
use App\Core\Util;
?>
<div
    id="salesSubmitMessage"
    class="sales-submit-message hidden"
    aria-live="polite"
></div>
<a
    id="salesDuplicateSource"
    class="sales-duplicate-source hidden"
    href="#"
    target="_blank"
    rel="noopener noreferrer"
>Duplicate link</a>

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

        <div class="sales-preflight-actions hidden" id="salesPreflightActions" aria-live="polite">
            <div class="sales-preflight-copy">
                <strong data-sales-i18n="preflightPassed">First two checks passed.</strong>
                <span data-sales-i18n="preflightChoice">Save &amp; Wait now, or continue with the full verification.</span>
            </div>
            <div class="sales-preflight-buttons">
                <button type="button" class="btn" id="saveWaitButton" disabled>
                    <span data-sales-i18n="saveAndWait">Save &amp; Wait</span>
                </button>
                <button type="button" class="btn primary" id="continueVerifyButton">
                    <span data-sales-i18n="continueVerification">Continue Verification</span>
                </button>
            </div>
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
            <div id="duplicateComparisonWarnings" class="duplicate-comparison-warnings hidden" role="status"></div>
            <p class="sales-verification-description">Comparison covers saved same-platform posts and imported website references. Image checks use available indexed photos; review any warnings below.</p>

            <div class="sales-verification-card">
                <div class="sales-verification-main">
                    <div id="resultImagesWrap" class="sales-verification-images sales-verification-media hidden" aria-label="Listing images">
                        <strong class="sales-verification-images-title">Listing Images</strong>
                        <div id="resultImages" class="sales-verification-image-grid"></div>
                    </div>

                    <div class="sales-verification-copy">
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
                            class="sales-verification-description sales-verification-listing-description"
                            id="resultDescription"
                        >
                            —
                        </p>
                    </div>
                </div>

                <dl class="sales-verification-facts">
                    <div>
                        <dt data-sales-i18n="published">Published</dt>
                        <dd id="resultDate">—</dd>
                    </div>
                    <div>
                        <dt data-sales-i18n="postId">Post ID</dt>
                        <dd id="resultExternalId">—</dd>
                    </div>
                    <div id="resultPlatformAccountFact" class="hidden">
                        <dt data-sales-i18n="platformAccount">Account</dt>
                        <dd id="resultPlatformAccount">—</dd>
                    </div>
                    <div class="wide">
                        <dt data-sales-i18n="originalUrl">Original URL</dt>
                        <dd id="resultCanonical">—</dd>
                    </div>
                </dl>
            </div>

            <form id="craigslistManualVerification" class="craigslist-manual-verification hidden" novalidate>
                <input
                    type="hidden"
                    name="_csrf"
                    value="<?= Util::e(Csrf::token()) ?>"
                >
                <input type="hidden" name="manual_marketplace" value="1">
                <input
                    type="hidden"
                    name="inspection_token"
                    id="craigslistManualInspectionToken"
                    value=""
                >

                <div class="craigslist-manual-head">
                    <strong data-sales-i18n="manualVerificationTitle">Manual marketplace verification</strong>
                    <span data-sales-i18n="manualVerificationHelp">The marketplace blocked the server request and automatic provider fallback was unavailable. Confirm the listing details below; Admin will review this post.</span>
                </div>

                <label for="craigslistManualTitle" data-sales-i18n="manualTitleLabel">Listing title</label>
                <input
                    id="craigslistManualTitle"
                    name="manual_title"
                    type="text"
                    maxlength="500"
                    required
                    autocomplete="off"
                >

                <label for="craigslistManualPublishedDate" data-sales-i18n="manualDateLabel">Published date</label>
                <input
                    id="craigslistManualPublishedDate"
                    name="manual_published_date"
                    type="date"
                    required
                >

                <label for="craigslistManualDescription" data-sales-i18n="manualDescriptionLabel">Description (optional)</label>
                <textarea
                    id="craigslistManualDescription"
                    name="manual_description"
                    rows="3"
                ></textarea>

                <button type="submit" class="btn primary full" id="craigslistManualContinue">
                    <span data-sales-i18n="continueManualVerification">Continue Manual Verification</span>
                </button>
            </form>

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
