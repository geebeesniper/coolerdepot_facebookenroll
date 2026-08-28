<?php use App\Core\Csrf; use App\Core\Util; ?>

<div class="page-head">
    <div>
        <div class="eyebrow">New Post</div>
        <h1>Verify before saving</h1>
        <p>Detect platform → resolve URL → fetch metadata → verify today's date → duplicate check → save.</p>
    </div>
</div>

<div class="two">
    <div class="panel">
        <form id="inspectForm">
            <input type="hidden" name="_csrf" value="<?= Util::e(Csrf::token()) ?>">
            <input type="hidden" name="platform" id="detectedPlatformValue" value="">

            <label>Post URL / Share Link</label>
            <input
                type="url"
                name="url"
                id="postUrl"
                required
                autocomplete="off"
                placeholder="Paste Facebook, OfferUp, or Craigslist URL"
            >

            <div class="platform-detect-row">
                <span class="muted-label">Platform</span>
                <span id="detectedPlatform" class="detected-platform empty-platform">Paste a supported URL</span>
            </div>

            <button id="inspectButton" class="btn primary" disabled>Check Post</button>
        </form>

        <div id="inspectionProgress" class="steps hidden">
            <div>Detecting platform...</div>
            <div>Resolving share URL...</div>
            <div>Fetching post information...</div>
            <div>Checking date...</div>
            <div>Checking duplicates...</div>
        </div>
    </div>

    <div class="panel">
        <h2>Verification Result</h2>
        <div id="inspectionEmpty" class="empty">Paste a URL and run Check Post.</div>

        <div id="inspectionResult" class="hidden">
            <div id="verificationBanner" class="banner"></div>

            <dl class="details">
                <dt>Platform</dt>
                <dd id="resultPlatform">—</dd>

                <dt>Title</dt>
                <dd id="resultTitle">—</dd>

                <dt>Published</dt>
                <dd id="resultDate">—</dd>

                <dt>Original URL</dt>
                <dd id="resultCanonical">—</dd>

                <dt>Post ID</dt>
                <dd id="resultExternalId">—</dd>

                <dt>Description</dt>
                <dd id="resultDescription">—</dd>
            </dl>

            <form method="post" action="<?= $config['app']['base_path'] ?>/sales/save">
                <input type="hidden" name="_csrf" value="<?= Util::e(Csrf::token()) ?>">
                <input type="hidden" name="inspection_token" id="inspectionToken">
                <button id="saveButton" class="btn success full" disabled>Save Verified Post</button>
            </form>
        </div>
    </div>
</div>
