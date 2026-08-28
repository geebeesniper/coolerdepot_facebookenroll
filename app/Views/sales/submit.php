<?php use App\Core\Csrf;use App\Core\Util;?>
<div class="page-head"><div><div class="eyebrow">New Post</div><h1>Verify before saving</h1><p>Resolve URL → fetch metadata → verify today's date → duplicate check → save.</p></div></div>
<div class="two">
<div class="panel"><form id="inspectForm"><input type="hidden" name="_csrf" value="<?=Util::e(Csrf::token())?>">
<label>Platform</label><select name="platform"><option value="facebook">Facebook Marketplace</option><option value="offerup">OfferUp</option><option value="craigslist">Craigslist</option></select>
<label>Post URL / Share Link</label><input type="url" name="url" required placeholder="https://..."><button id="inspectButton" class="btn primary">Check Post</button></form>
<div id="inspectionProgress" class="steps hidden"><div>Resolving share URL...</div><div>Fetching post information...</div><div>Checking date...</div><div>Checking duplicates...</div></div></div>
<div class="panel"><h2>Verification Result</h2><div id="inspectionEmpty" class="empty">Run Check Post first.</div>
<div id="inspectionResult" class="hidden"><div id="verificationBanner" class="banner"></div><dl class="details"><dt>Title</dt><dd id="resultTitle">—</dd><dt>Published</dt><dd id="resultDate">—</dd><dt>Original URL</dt><dd id="resultCanonical">—</dd><dt>Post ID</dt><dd id="resultExternalId">—</dd><dt>Description</dt><dd id="resultDescription">—</dd></dl>
<form method="post" action="<?=$config['app']['base_path']?>/sales/save"><input type="hidden" name="_csrf" value="<?=Util::e(Csrf::token())?>"><input type="hidden" name="inspection_token" id="inspectionToken"><button id="saveButton" class="btn success full" disabled>Save Verified Post</button></form></div></div></div>
