# CoolerDepot Sales Post Tracker — External Auth / Subdomain Edition

The module is now designed to receive the logged-in user from the parent CoolerDepot portal. The parent passes `role=admin` or `role=sales` together with user identity in a signed handoff. A raw `?role=admin` is never trusted.

On successful handoff, user identity/role is persisted in `cdsp_users`, the handoff is recorded in `cdsp_auth_handoffs`, and the active server-side login is persisted in `cdsp_auth_sessions`. Post metadata, inspections, duplicate results, saved posts, reviews, daily reviews, weekly/monthly reviews, deletion requests, and attachment metadata are stored in MySQL.

Current path mode: `http://144.126.218.94/sales-posts/`. Subdomain mode is configurable with `APP_BASE_PATH=` and `APP_HOST=salesposts.YOURDOMAIN.com`. See `deploy/SUBDOMAIN.md`.

---

# CoolerDepot Sales Post Tracker

PHP 8.1 / MySQL 5.6 MVC module for CoolerDepot sales-post verification and admin review.

## Database table prefix

All Sales Post Tracker tables use the `cdsp_` prefix so this module can safely share the CoolerDepot `app` database with other modules.

Fresh install creates:

- `cdsp_users`
- `cdsp_auth_handoffs`
- `cdsp_auth_sessions`
- `cdsp_post_inspections`
- `cdsp_sales_posts`
- `cdsp_post_reviews`
- `cdsp_daily_sales_reviews`
- `cdsp_period_sales_reviews`
- `cdsp_review_attachments`
- `cdsp_deletion_requests`


## Features

- Sales login; numeric Sales ID can be used as username.
- Facebook Marketplace, OfferUp, and Craigslist submission.
- Share-link redirect resolution.
- Title, description, canonical URL, platform item/post ID extraction.
- Publication-date verification in `America/Los_Angeles`.
- Sales can submit verified historical or current posts; attribution follows the original publication date. Future or unverified dates are rejected.
- Company-wide duplicate canonical URL blocking for active posts.
- Company-wide duplicate platform + item/post ID blocking for active posts.
- Company-wide same-platform byte-for-byte exact title blocking (all salespeople).
- Same Sales + same platform exact normalized description blocking.
- jQuery inspect-first UI: Save stays disabled until verification succeeds.
- Server-side duplicate re-check immediately before INSERT.
- Sales dashboard with daily counts and list/grid views.
- Saved posts cannot be deleted by Sales; Sales submits deletion requests.
- Admin per-post Good/Bad decision, HTML note, and image attachments.
- Admin per-day Sales HTML note and image attachments.
- Weekly/monthly progress report and period review.
- Review images are stored outside the public asset directory and served through an authenticated route.

## Important platform limitation

Craigslist public pages are normally straightforward to inspect.

Facebook Marketplace and OfferUp may omit metadata, require login, dynamically render content,
or block automated requests. This application intentionally **fails closed**: if the title or
publication date cannot be independently verified, Sales cannot save the post.

The platform inspection code is isolated in `app/Services/PostInspector.php`, so an approved or
licensed API provider can later be added for Facebook/OfferUp without redesigning the rest of the
application.

## Recommended server location

    /opt/coolerdepot/www/sales-posts

Application URL:

    http://144.126.218.94/sales-posts/

## Required PHP extensions

Check with:

    php -m | egrep 'pdo_mysql|mysqli|curl|dom|mbstring|fileinfo'

Required:

- pdo_mysql
- mysqli
- curl
- dom/xml
- mbstring
- fileinfo

## Apache

The project uses `.htaccess`, so Apache must have `mod_rewrite` enabled and `AllowOverride All`
for the web root.

Example:

    <Directory "/var/www/html">
        AllowOverride All
        Require all granted
    </Directory>

## Database environment

The module reads:

    DB_HOST
    DB_PORT
    DB_NAME
    DB_USER
    DB_PASSWORD
    APP_TIMEZONE

Defaults in `config/config.php` are:

    DB_HOST=db
    DB_PORT=3306
    DB_NAME=app
    DB_USER=app
    DB_PASSWORD=app
    APP_TIMEZONE=America/Los_Angeles

On DigitalOcean, use the existing Docker environment/database credentials. Do not commit a
production `.env` file.

## Install database

From `/opt/coolerdepot`:

    docker compose exec php php /var/www/html/sales-posts/scripts/install.php

## Create an admin

    docker compose exec php php /var/www/html/sales-posts/scripts/create_user.php \
      admin admin "Administrator" 'CHANGE-THIS-PASSWORD'

## Create a Sales login

Example for Sales ID 100006 / David:

    docker compose exec php php /var/www/html/sales-posts/scripts/create_user.php \
      sales 100006 "David" 'CHANGE-THIS-PASSWORD' 100006

## Import SALES-LIST CSV

The importer expects:

    id,username

The numeric `id` becomes `sales_id` and login username. The CSV `username` becomes display name.

Run:

    docker compose exec php php \
      /var/www/html/sales-posts/scripts/import_sales_csv.php \
      /var/www/html/sales-posts/SALES-LIST-20260827.csv

Imported accounts remain disabled until a password is explicitly assigned with `create_user.php`.

## Git

This directory should remain its own repository:

    /opt/coolerdepot/www/sales-posts/.git

Typical workflow from VS Code Remote SSH:

    git status
    git add .
    git commit -m "Update sales post tracker"
    git push

Do not initialize the parent `/opt/coolerdepot` as this repository because the parent contains
server configuration and secrets.

## Verification flow

1. Sales selects Facebook / OfferUp / Craigslist.
2. Sales pastes a public listing URL or supported share link.
3. jQuery calls `/sales-posts/api/inspect`.
4. PHP validates the domain and blocks private/reserved network targets.
5. Redirects are resolved only while remaining on the selected platform.
6. Page metadata is extracted.
7. Publication timestamp is normalized to `America/Los_Angeles`.
8. Publication date may be today or earlier; future/unverifiable dates are rejected.
9. Duplicate rules are checked.
10. A short-lived inspection token is stored.
11. Save becomes enabled only for a verified token.
12. Server checks duplicates again before INSERT.

## Duplicate rules

A post is blocked by these rules:

- Canonical URL among active posts.
- Platform + external post/item ID among active posts.
- Any Sales + same platform + byte-for-byte exact title among active posts.
- Any Sales + same platform + identical indexed image file among active posts.
- Same Sales + same platform + exact normalized description.

The database also contains unique keys for canonical URL and platform/external item ID.

## Security included

- Prepared PDO queries.
- `password_hash()` / `password_verify()`.
- CSRF protection.
- Session regeneration at login.
- Role checks.
- SSRF protection for inspection requests.
- Platform host allow-listing.
- Private/reserved IP blocking.
- Image MIME validation and size limit.
- Sales deletion requests with permanent Admin hard delete on approval.
- Inspection token expiration and single-use consumption.

## Recommended next production upgrades

- HTTPS before employee production use.
- Approved/licensed Facebook/OfferUp data/API provider when needed.
- Rate limiting for `/api/inspect`.
- Background queue for slow inspections.
- Admin account-management/password-reset screen.
- Admin Sales CSV upload UI.
- Better audit log coverage for every change.
- Export/report charts.
## UI update

Sales dashboard now defaults to a compact responsive grid for daily metrics and post cards. Grid/List choice is remembered per browser.

## UI radius convention

All border-radius values in this project are standardized to `4px`.

## Facebook real-link diagnostic

Run the bundled real-link test from the PHP container:

    php /var/www/html/sales-posts/scripts/test_facebook_links.php

It tests the supplied Facebook Marketplace URLs through the same `PostInspector`
used by the Sales submit workflow, without saving the posts.

## Platform detection and post quality status

Sales no longer selects a platform manually. The submit page detects Facebook,
OfferUp, or Craigslist from the pasted URL, and the API independently detects it
again server-side.

Post review quality uses only `good` or `bad`. An unreviewed post has a NULL quality
status and displays no status badge. This is separate from deletion-request workflow
statuses, which still use pending/approved/rejected.


## Release versioning

Current release: `v0.1.80`

`VERSION` in the project root is the application version source of truth.
The footer reads this value and displays it on every page.

Release ZIP naming convention:

    sales-posts-vX.Y.Z-<change>.zip

Every packaged release must increment `VERSION` and use the same version in the ZIP filename.

## v0.1.1

- Sales dashboard renamed from a flat Posts area to **Daily Posts**.
- Posts are grouped by `published_date`, one section per day.
- Initial dashboard load returns only the newest few dates.
- Earlier date sections are loaded progressively over AJAX when the Load Earlier control approaches the viewport.
- Demo data removes the old fake refrigerator/Craigslist rows and uses the 10 Facebook Marketplace URLs supplied for testing.
- UI border radius remains standardized at `4px`.

## v0.1.2 — Bright Data Facebook provider

- Added Admin → Settings for Bright Data.
- Bright Data API token is entered in the Admin UI and encrypted before storage in `cdsp_settings`.
- The token is never rendered back into HTML after it is saved.
- Facebook Marketplace checks use Bright Data async flow automatically:
  trigger → snapshot progress → snapshot download.
- Bright Data snapshot IDs are stored only in `cdsp_fetch_jobs` for diagnostics.
- Sales never sees or manually copies snapshot IDs.
- Re-checking the same Facebook item within 10 minutes can reuse a successful provider result to avoid another query.
- Existing external Facebook post ID duplicate checks run before Bright Data is called.
- Generic Bright Data `timestamp` is intentionally NOT treated as the Facebook listing date.
  A semantic listing/post creation field such as `listing_date` must be present or the post is blocked.
- UI border radius remains standardized to `4px`.

## v0.1.3 — Unified HTTP status pages

- Replaced raw `Forbidden` and raw `404 Not Found` responses with branded application pages.
- Added unified UI handling for 301, 302, 400, 401, 403, 404, 405, 408, 421, 429, 500, 502, and 503.
- Sales users who open Admin-only routes now receive a friendly 403 with a Sales Dashboard action.
- Admin users receive an Admin Dashboard action when access is denied.
- HEAD requests use GET routing, so health checks no longer get false 404 responses.
- A known path requested with the wrong HTTP method returns 405 rather than 404.
- API errors remain JSON instead of rendering HTML status pages.
- 301/302 redirects include a branded HTML fallback while still sending the correct Location header/status.
- Added Admin → Status Pages overview.
- All border radius remains `4px`.

## v0.1.4 — Apache-level status pages

- Added a standalone `http-status.php` page that does not depend on MySQL or login sessions.
- Added Apache `ErrorDocument` mappings for 400, 403, 404, 405, 408, 421, 429, 500, 502, and 503.
- Root-level missing URLs such as `/admin` now use the same CoolerDepot-branded status UI instead of Apache's default white page.
- Apache Basic Auth 401 remains Apache-managed so the browser login challenge continues to work correctly.
- 301/302 remain real redirects; application redirects already include a branded fallback page.
- Added `scripts/install_apache_status_pages.sh` for the current `/opt/coolerdepot` Docker deployment.
- All border radius remains `4px`.

## v0.1.5 — Simplified status pages

- Removed application name from HTTP status pages.
- Removed version text from HTTP status pages.
- Removed administrator help copy.
- Removed the browser-history Go Back button.
- Status pages now show only status code, title, message, and one `Go Back` action.
- `Go Back` always links to the application root, which routes the signed-in user to the correct dashboard.
- No Sales Dashboard/Admin Dashboard wording is shown on status pages.
- Apache-level status pages use the same simplified layout.
- All border radius remains `4px`.

## v0.1.6 — HTML review notes

- Removed the 1–5 star Rating field from post, daily, weekly, and monthly Admin reviews.
- Review notes are stored as sanitized HTML.
- Added an inline rich-text editor with an `Editor On / Editor Off` toggle.
- With the editor off, Admin can edit the raw HTML source directly.
- Allowed note HTML is intentionally restricted to safe formatting tags and safe links.
- Bright Data test result heading is now `Test Result`.
- Added spacing above the `Test Bright Data` button.
- All border radius remains `4px`.

## v0.1.7 — Facebook URL normalization

- Fixed duplicated pasted Facebook URLs such as
  `.../item/123https://www.facebook.com/marketplace/item/123`.
- Facebook Marketplace item URLs are canonicalized to
  `https://www.facebook.com/marketplace/item/<ID>` before any Bright Data query.
- URL normalization runs in the browser, Sales API, Admin Bright Data test, and provider layer.
- Bright Data `input + timestamp` only responses are no longer treated as successful Marketplace listings.
- A successful provider record must contain at least one listing-specific field.
- The generic provider `timestamp` remains excluded from Facebook publication-date verification.
- Added an optional cleanup script for old malformed fetch-job records.
- All border radius remains `4px`.

## v0.1.8 — Bright Data test button spacing

- Fixed vertical spacing above the `Test Bright Data` button.
- Button is now block-level with a 14px top margin.
- All border radius remains `4px`.


## v0.1.9 — Facebook provider fallback

- Bright Data remains the primary Facebook Marketplace provider.
- Added ScrapeCreators Marketplace Item as automatic fallback.
- ScrapeCreators uses `creation_time` as the preferred listing timestamp.
- Fallback occurs only when the primary provider fails or returns incomplete required metadata.
- A valid Bright Data listing with an old posting date is NOT sent to the fallback provider.
  Business date validation happens after provider selection.
- Provider cache is provider-specific to prevent one provider's cached response from being mistaken for another.
- ScrapeCreators API key is never embedded in source code or release ZIPs.
- Added one-time encrypted key installer scripts.
- All border radius remains `4px`.

## v0.1.10 — Three-provider Facebook failover

Provider order:

1. Bright Data — primary
2. Apify official `apify/facebook-marketplace-scraper` — first fallback
3. ScrapeCreators — second fallback

Apify input uses one canonical Marketplace item URL, `resultsLimit=1`, and
`includeListingDetails=true`. The provider sends the API token using the
Authorization header, requests at most one dataset item, and caps the run charge.

For the official Apify Actor, `timestamp` is accepted as the listing timestamp
because detailed listing output exposes that field and the project test on item
`1609835460847233` matched Bright Data exactly:
`2026-08-27 23:54:00 UTC`.

Fallback is only triggered when a provider fails or returns incomplete required
metadata. A successful provider result whose listing date is old is returned to
business validation and does not consume another fallback provider request.

API tokens are not stored in source code or release ZIP files. They are encrypted
in `cdsp_settings`.

All border radius remains `4px`.

## v0.1.11 — Dual Bright Data credential failover

Facebook Marketplace verification order:

1. Bright Data primary API token
2. Bright Data secondary API token
3. Apify
4. ScrapeCreators

The second Bright Data credential is attempted automatically when the first
Bright Data attempt fails due to provider/API failure, HTTP 401/402/403/429,
timeout, Bright Data job status `failed`, empty result, snapshot failure, or
incomplete required Marketplace metadata.

A valid complete listing is returned immediately. If its verified publication
date is old, that is a business-validation result rather than an API failure,
so the secondary key and other providers are not consumed.

Primary and secondary Bright Data tokens are encrypted independently in
`cdsp_settings`. Neither token is written into source code, logs, release ZIPs,
or Git.

Admin Settings now supports Primary API Token and Secondary API Token fields
and the Bright Data test displays which credential succeeded.

All border radius remains `4px`.

## v0.1.12 — Dynamic Provider Manager

Admin API Settings is now a provider registry instead of hard-coded Bright Data fields.

- `+ Add Provider` supports Bright Data, Apify, ScrapeCreators, and a Custom JSON API.
- New providers are not saved until a real Facebook Marketplace test succeeds.
- The successful test ticket expires after 10 minutes and is invalidated if the form changes.
- Provider cards can be dragged to change live failover order.
- Providers can be enabled/disabled or removed without editing code.
- Each API credential is its own provider card, so multiple Bright Data keys can be ordered independently.
- Custom JSON APIs support GET/POST, Bearer/header/query/no auth, query/JSON listing URL input, and dot-path response mapping.
- Custom endpoints must be HTTPS and resolve to public IP space; redirects are disabled to reduce SSRF risk.
- API tokens are encrypted in `cdsp_provider_profiles.token_encrypted` and are never rendered back to the browser.
- Migration `005_provider_registry.sql` plus `scripts/migrate_provider_registry.php` imports existing Bright Data / Apify / ScrapeCreators credentials in their existing failover order.
- Runtime provider order is now database-driven; the first complete successful result wins.
- A provider returning a valid old listing date is a successful provider result and is handled by business date validation, so lower-priority providers are not consumed.
- All border radius remains `4px`.

Migration:

```bash
cd /opt/coolerdepot
docker compose exec php php /var/www/html/sales-posts/scripts/migrate_provider_registry.php
```

## v0.1.13 — Live Provider Jobs

- `Recent Provider Jobs` now refreshes automatically every 2 seconds.
- Provider tests show live `Starting -> Running -> Ready/Failed` transitions without page reload.
- Polling pauses while the browser tab is hidden and resumes immediately when visible.
- Added a Live / Paused / Reconnect status indicator.
- Provider test requests release the PHP session lock during long external API calls so the same Admin session can poll job status concurrently.
- Added read-only `/admin/providers/jobs` JSON endpoint with `no-store` caching.
- No database migration is required.
- All border radius remains `4px`.

## v0.1.14 — Field-level Provider validation

- Provider form validation now marks the exact invalid input with a red border.
- The error message is rendered immediately below the invalid field.
- The form automatically scrolls to and focuses the invalid field.
- Facebook Marketplace test URLs are validated in the browser before an API credit is consumed.
- Server validation responses now include a field identifier for provider type, name, token, Dataset ID, custom API mapping, auth, and request settings.
- Generic remote provider failures still appear in the provider test result box because they are not tied to one input field.
- No database migration is required.
- All border radius remains `4px`.

## v0.1.15 — Apify detailed-description fix

- Fixed Apify Marketplace detailed output where `description` is an object such as `{"text":"..."}`.
- Apify now normalizes nested text values without PHP `Array to string conversion` warnings.
- Provider Test converts parser/runtime PHP warnings into a clean JSON error instead of allowing warning output to break the AJAX response.
- The tested item `1609835460847233` now normalizes description as `3 door refrigerator freezer`.
- No database migration is required.
- All border radius remains `4px`.

## v0.1.16 — Admin posting-performance dashboard

- Added a top-of-page Admin posting-performance chart.
- Daily / Weekly / Monthly period switching uses the verified `published_date`.
- Sales filter can show all Sales or one salesperson; clicking a Sales bar filters the dashboard.
- Each vertical bar scales to total posting volume. Good/Pass is green from the bottom, Bad/Issue is red immediately above it, and the remaining neutral track represents unreviewed posts.
- Added Total Posts / Pass / Issues / Unreviewed summary metrics.
- The daily post table now follows the verified publication date instead of submission timestamp and follows the selected Sales filter.
- Removed Rating from post, daily, weekly, and monthly review logic.
- Legacy rating database columns remain nullable for safe backwards compatibility; new reviews always store NULL.
- HTML Note editor remains enabled and now includes H3/H4 toolbar controls plus HTML source toggle.
- All border radius remains `4px`.

## v0.1.17 — SaaS-style daily Sales progress grid

- Replaced the Admin Daily/Weekly/Monthly bar chart with a Sales card grid.
- Each active Sales user has one card showing today's verified post count against a configurable daily target.
- Daily target defaults to `10` and is stored per Sales user in `cdsp_users.daily_post_target`.
- Admin can change a target inline from the card without reloading the page.
- Progress bars cap visually at 100%; cards show `Target met` when the daily count reaches or exceeds the target.
- Clicking `View Posts` filters the existing Daily Posts table to that Sales user.
- Added a lightweight SaaS-style activity watcher. The Admin dashboard checks every 5 seconds for newly added posts for the selected date.
- New Sales activity does not auto-reload or move the dashboard. A `New posts available — Refresh` notice appears and waits for the Admin to refresh manually.
- The previous large Posting Performance bar chart has been removed.
- Requires migration `007_sales_daily_target`.
- All border radius remains `4px`.

## v0.1.18 — Dynamic Daily / Weekly / Monthly Sales progress

- Added a SaaS-style `Daily / Weekly / Monthly` segmented filter above the Sales progress grid.
- Period changes happen through AJAX without a full page reload.
- Sales cards stay in place and animate their count, target, progress fill, review counts, and action link.
- Daily target remains the only manually configured target.
- Weekly target is `daily target × 7`.
- Monthly target is `daily target × number of calendar days in the selected month`.
- Weekly ranges use Monday through Sunday around the selected dashboard date.
- Monthly ranges use the selected date's full calendar month.
- Weekly/Monthly `View Report` links route to the existing period report for that Sales user.
- The detailed Posts table remains tied to the selected calendar day.
- New-post activity polling follows the currently selected Daily/Weekly/Monthly period and still shows the non-disruptive `Refresh` notice rather than auto-reloading.
- Added staggered card updates, count-up animation, smooth progress transitions, and loading-state motion.
- No database migration is required.
- All border radius remains `4px`.

## v0.1.19 — WordPress/Shopify-style HTML note editor

- Replaced the old `Editor Off` toggle with `Visual / HTML` tabs.
- Visual mode now resembles a classic WordPress/Shopify rich-text editor.
- Added Paragraph / Heading 3 / Heading 4 / Quote format selector.
- Added Bold, Italic, Underline, Strikethrough, bulleted list, numbered list, blockquote, link, unlink, clear formatting, undo, and redo controls.
- Link editing uses an inline link bar instead of browser `prompt()`.
- HTML mode uses a dedicated dark monospace source editor.
- Visual and HTML modes stay synchronized and submit the same sanitized HTML field.
- Existing server-side HTML sanitizer remains in force.
- No database migration is required.
- All border radius remains `4px`.

## v0.1.20 — Colorful touch-first Sales grid

- Each Sales card now receives one of eight distinct accent palettes.
- Increased progress-bar thickness for faster scanning on desktop, tablet, and touch devices.
- The whole Sales card is now the primary interaction target; click/tap/Enter/Space expands that Sales user's post list directly below the grid.
- Expanded lists follow the currently selected Daily / Weekly / Monthly period and load asynchronously without a page refresh.
- Clicking the same Sales card again collapses the list; selecting another Sales card switches the expanded list.
- Removed the redundant `All Sales` button and the small `View Posts/View Report` card link.
- Target input and Save controls are isolated from card toggle behavior.
- Added touch-first 44px interaction targets, coarse-pointer behavior, keyboard accessibility, focus states, and responsive 4/3/2/1-column layouts.
- Mobile expanded post rows collapse into a compact two-column layout.
- No database migration is required.
- All border radius remains `4px`.

## v0.1.21 — Date-synced Sales progress and ordered Post List

- Fixed the Admin date flow so the selected date is the single source of truth for Sales progress.
- Changing the date now submits immediately, and the grid also performs an initial AJAX reconciliation against that exact selected date.
- Removed the redundant standalone Daily Posts table from the Admin dashboard.
- Removed the standalone `Daily Sales Reviews` panel.
- Added `Daily Review` directly inside each Sales card while the Daily period is selected.
- Clicking/tapping a Sales card opens one ordered Post List below the grid.
- Post List rows are chronological and numbered `1, 2, 3, 4...`.
- Post List no longer shows title or description.
- Reviewed `Good` posts use a green row, reviewed `Bad` posts use a red row, and unreviewed posts remain neutral/white.
- Weekly and Monthly expanded lists use the same chronological numbered format.
- Corrected malformed duplicate `<section>` markup left by the prior dashboard revision.
- No database migration is required.
- All border radius remains `4px`.

## v0.1.22 — Date-control alignment and card footer cleanup

- Aligned the dashboard date picker and `View` button to the same 44px control height/baseline.
- When the selected date is not today, a `Back to today` link appears beside the date controls and preserves the current Daily/Weekly/Monthly period.
- Removed the visually awkward `Tap to view posts` block from the middle of each Sales card.
- Added a full-width `View posts` footer at the bottom of every Sales card.
- Replaced the text glyph arrow with a CSS chevron that rotates cleanly when the card is expanded.
- Touch targets increase to 48px on coarse-pointer devices.
- Date controls wrap cleanly on mobile and `Back to today` moves to its own line when needed.
- No database migration is required.
- All border radius remains `4px`.

## v0.1.23 — AJAX dashboard, Post Grid, and review popup

- Dashboard date changes no longer reload the page. Date picker, View, and Back to today now update the Sales grid through AJAX and keep browser history in sync.
- SaaS activity `Refresh` also refreshes through AJAX instead of reloading the document.
- Expanded Sales posts are now a responsive Post Grid instead of a long list.
- Each post tile shows sequence, time, date, platform, and review state; title and description remain hidden from the grid.
- Entire post tiles are clickable/tappable/keyboard-accessible and open a review popup.
- Removed per-post navigation to the separate review page from the dashboard flow.
- Review popup supports Good/Bad, the Visual/HTML rich note editor, image uploads, existing attachments, Open original, and AJAX Save.
- Saving a review immediately recolors the post tile and refreshes Sales Good/Issue/Unreviewed counts without closing the popup or reloading the page.
- Good post tiles are green, Bad tiles are red, and unreviewed tiles remain neutral.
- Post Grid Close is now a compact `×` control.
- Modal closes with `×`, Cancel, backdrop click, or Escape.
- Responsive Post Grid uses auto-fill desktop layout, two columns on tablet/mobile, and one column on small phones.
- No database migration is required.
- All border radius remains `4px`.

## v0.1.24 — Source editor, thumb decisions, and refined Post Grid

- HTML Note mode is now a real source editor rather than a plain textarea.
- Added line numbers, HTML syntax highlighting, synchronized scrolling, Tab indentation, and live line/column position.
- Visual and HTML modes continue to edit the same sanitized HTML field.
- Replaced plain Good/Bad radio rows with large thumbs-up / thumbs-down review controls.
- Good selection turns green; Bad selection turns red; both show a selected check state.
- Icons use inline SVG for consistent rendering on Windows, tablets, and phones.
- Refined Post Grid cards with a review/edit icon in the upper-right.
- Removed the redundant `Review` word from the bottom of Post cards because the entire card is already clickable.
- Increased time hierarchy and simplified the card footer to platform + review state.
- No database migration is required.
- All border radius remains `4px`.

## v0.1.25 — Platform icons and Get Content

- Replaced the upper-right Post Grid edit/pencil icon with the post's actual platform identity.
- Facebook uses a Facebook SVG mark, OfferUp uses its own green/orange platform mark, Craigslist uses its purple peace-mark treatment, and unknown platforms use a neutral fallback icon.
- Added `Get Content` to the Review popup.
- `Get Content` forces a fresh Facebook Marketplace provider-chain request instead of using the recent provider cache.
- Fresh content preview includes title, description, listing date, price when returned, location when returned, up to eight Marketplace photos, provider name, and failover state.
- The fetched title and description are persisted back to the existing post; the existing dashboard historical published date/time is intentionally not moved by this Admin content-preview action.
- Content fetch errors remain inside the Review popup and do not navigate away.
- No database migration is required.
- All border radius remains `4px`.

## v0.1.26 — Composer editor, image insertion, lightbox, and reliable review saves

- Replaced the previous Note editor UI with a cleaner Shopify/Elementor-inspired composer.
- Rich text mode has compact formatting controls, link insertion, and a dedicated image button.
- Images can be inserted from an HTTPS URL, from the first fetched Marketplace listing photo, or by uploading JPG/PNG/WEBP directly from the Review popup.
- Added `post_note` attachment support for securely hosted images inserted into HTML notes.
- HTML sanitizer now safely supports `<img src alt title>` while continuing to reject unsafe URL schemes and dangerous embedded elements.
- HTML source mode keeps line numbers, syntax highlighting, Tab indentation, and cursor line/column status in a more compact layout.
- Marketplace content preview now displays only the first fetched listing photo.
- Clicking that photo opens a full lightbox popup with an `×` close control; backdrop click and Escape also close it.
- Review database saving is transactional and independent from optional attachment upload.
- If a review saves but an attachment fails, the UI reports `Review saved` plus the exact image warning instead of falsely saying the review failed.
- AJAX review saving now reports real errors for expired CSRF, PHP `post_max_size`, PHP upload errors, unsupported image type, image size, and unwritable storage.
- Requires migration `008_note_editor_images`.
- All border radius remains `4px`.

## v0.1.27 — Image-aware Get Content, upload repair, and required review decisions

- `Get Content` now treats the first Marketplace image as part of the requested content. If a provider returns valid listing metadata but no image, the provider chain continues to the next configured provider.
- The first fetched image URL is stored in `cdsp_sales_posts.fetched_image_url`, so reopening Review can display the last fetched image without another API call.
- Marketplace photo extraction now walks nested provider response shapes instead of depending on only a few fixed keys.
- Added migration `009_fetched_image_url`.
- Added `scripts/migrate_v0_1_27.php`; run it as container root to add the image column and repair `storage/uploads` ownership and permissions for `www-data`.
- Good/Bad is now visibly required. Submitting without a choice adds a red Decision frame, red option borders, an inline `Select Good or Bad before saving` message, and scrolls the Decision section into view.
- Selecting Good or Bad immediately clears the validation state.
- Replaced the Note editor presentation with a cleaner single-toolbar Visual/HTML composer.
- The image insertion panel now uses a responsive grid with equal-height controls and a dedicated full-width message row.
- Image insertion still supports URL, the first fetched listing photo, and local JPG/PNG/WEBP upload.
- All border radius remains `4px`.

## v0.1.28 — Custom Decision validation fix

- Disabled native browser constraint validation on the Review popup with `novalidate`.
- Removed native radio `required` attributes that caused Chrome/Edge to intercept submission before the custom dashboard validation could run.
- Preserved accessibility using `aria-required`.
- Good/Bad validation is now fully controlled by the dashboard UI.
- Submitting without a decision highlights the entire Decision fieldset in red, gives both Good and Bad cards red borders, shows an inline red message, scrolls the field into view, and places keyboard focus on the first decision control.
- Selecting Good or Bad clears the custom error immediately.
- No database migration is required.
- All border radius remains `4px`.

## v0.1.29 — Persistent Post Review comments

- Replaced the single overwritten Post Review `note` behavior with a persistent comment history.
- Added `cdsp_post_review_comments` with soft-delete and audit fields.
- Migration `010_post_review_comments` automatically copies existing non-empty `cdsp_post_reviews.note` values into the new comment history without duplicating them on rerun.
- Opening a Post Review now returns and renders every existing note in chronological order.
- The rich editor is now an `Add Note` composer. Adding a note appends a new comment and never overwrites previous comments.
- Each comment shows author, timestamp, and Edited state.
- Each comment has AJAX Edit and Delete actions.
- Edit reuses the existing rich Visual/HTML composer and changes `Add Note` to `Update Note`; Cancel Edit restores a clean composer.
- Delete uses an inline confirmation row rather than browser `confirm()`. Deleted comments are soft-deleted for auditability and disappear immediately from the UI.
- Saving Good/Bad no longer overwrites the legacy `cdsp_post_reviews.note` column.
- Existing review attachments and review decision AJAX behavior remain unchanged.
- Requires migration `010_post_review_comments`.
- All border radius remains `4px`.

## v0.1.30 — Anchored comment delete confirmation

- Delete/Cancel no longer appears at the bottom of a comment.
- Clicking the trash icon opens a viewport-level confirmation popover beside that exact icon.
- Long comments and image-heavy comments cannot push the confirmation out of sight.
- Placement automatically chooses left, right, below, or above and clamps within the viewport.
- Scroll and resize keep the popover aligned to the trash icon.
- Outside click, Cancel, or Escape closes it.
- Delete remains AJAX + soft-delete; no browser `confirm()` is used.
- Touch targets are enlarged on coarse-pointer devices.
- No database migration is required.
- All border radius remains `4px`.

## v0.1.31 — Comment-linked images and image deletion

- Images selected below `Add Note` now belong to that exact comment and are uploaded together with Add/Update Note through AJAX.
- Comment History renders each comment's attached images directly beneath the comment body.
- Comment images are clickable and open the existing image lightbox.
- Every comment image has its own `×` delete control.
- Existing Review-level images receive delete controls too.
- Added `post_comment` to the attachment entity types.
- Migration `011_comment_attachments` automatically moves legacy `post_review` images into the migrated legacy comment when `legacy_review_id` provides an unambiguous mapping.
- Images that cannot be mapped remain under `Other review images` and can still be deleted.
- Comments may be image-only; text is not required when at least one image is selected or already attached.
- Review Save no longer owns the comment-image picker; comment images are saved only by Add Note / Update Note.
- Requires migration `011_comment_attachments`.
- All border radius remains `4px`.
## v0.1.32 — Remove Status Pages preview and show Review save completion

- Removed the non-editable `Status Pages` item from Admin navigation and removed its preview route. Branded runtime error handling remains intact.
- The full release no longer includes the unused Status preview controller/view.
- The Review footer is sticky so its controls remain visible with long comments or images.
- After `Save Review` succeeds, a green `Review saved` state is shown, the save button becomes `Saved ✓`, and `Cancel` becomes `Close`.
- A saved review with an image warning stays visibly saved while showing the warning separately.
- Changing Good/Bad after a successful save restores `Save Review` and `Cancel`.
- No database migration is required.
- All border radius remains `4px`.

## v0.1.33 — Review-state progress and save-flow synchronization

- Sales progress bars now show review state instead of the Sales card accent color.
- Good is green, Bad/Issue is red, and Unreviewed is gray.
- If there are no Good/Bad reviews yet, the filled portion stays gray even when the card border is purple, blue, teal, or another identity color.
- Saving a Post Review immediately recomputes the expanded Sales card's Good / Issues / Unreviewed counts from the visible Post Grid.
- The existing dashboard progress AJAX call remains the authoritative server refresh after that immediate UI update.
- Saved Good/Bad state is restored with an explicit selected class as well as the radio checked state.
- The review API falls back to the persisted `admin_review_status` when an older post has status but no matching review-row decision.
- A successful Review save shows `Saved ✓` and automatically closes the popup after 650ms.
- If the save contains an image warning, the popup stays open so the warning remains visible.
- Review popup layout is now header + scrollable middle body + bottom action bar. Cancel / Save is flush against the bottom edge and does not float above unused padding.
- No database migration is required.
- All border radius remains `4px`.

## v0.1.34 — Marketplace cards and persistent Review History

- Expanded Sales Post cards are redesigned around Marketplace content rather than a large timestamp.
- Each card now shows the first persisted Marketplace thumbnail, Title, Description, compact published date/time, platform identity, sequence, and review status.
- If no thumbnail has been fetched yet, the card displays a clean platform placeholder without triggering another paid provider request.
- Added immutable database table `cdsp_post_review_history`.
- Every successful `Save Review` inserts a separate Good/Bad review-history event while the existing `cdsp_post_reviews` row remains the current-state record.
- Migration `011_post_review_history` backfills one historical event from every existing saved Post Review, so already-reviewed posts immediately receive visible history.
- Reopening Review now uses `cdsp_sales_posts.admin_review_status` as the canonical current Good/Bad state and falls back to `cdsp_post_reviews` only for older data.
- The former Comment History panel is now `History` and combines Review events with editable/deletable notes in chronological order.
- Review events are immutable audit records; comment notes retain Edit/Delete behavior.
- No external API request is performed merely to render the card thumbnail.
- Requires migration `011_post_review_history`.
- All border radius remains `4px`.

## v0.1.35 — Full audit history and soft deletion

- Every successful `Save Review` continues to append an immutable Good/Bad event even when the decision is unchanged from the previous save.
- Review History now explicitly labels those entries as `Review saved · Decision only · Good/Bad` and shows the exact administrator and database timestamp.
- Comment activity remains in the same chronological History and now labels whether the action contains a comment, photos only, or `Comment + N photos`.
- Comment rows show author and creation time; edits show the last editor and edit time.
- Comment attachments show filename, uploader, upload time, and remain associated with the comment.
- Comment deletion is audit-only: the row is never physically removed. It stays visible in History and is marked `Marked as deleted` with who marked it and when.
- Attachment deletion is now audit-only. `cdsp_review_attachments` gains `deleted_at` and `deleted_by`; the database row and physical file are preserved.
- Soft-deleted images stay visible in History with a `Marked as deleted` overlay and deletion audit metadata.
- Legacy review-level images also use the same soft-delete behavior.
- The AJAX Save Review response now returns the exact persisted history event instead of inventing a local Administrator/time value.
- Migration `012_soft_delete_audit` adds attachment deletion audit columns. `scripts/migrate_v0_1_35.php` is idempotent and also ensures/backfills the v0.1.34 review-history table.
- All border radius remains `4px`.

## v0.1.36 — Compact History thumbnails and authoritative saved Decision

- Comment History images are now fixed evidence thumbnails instead of expanding to the full History width.
- Desktop thumbnails are 136×96; coarse-pointer devices use 150×106; very narrow screens use 118×84.
- Multiple comment photos wrap into a compact thumbnail row. Clicking a thumbnail still opens the existing image lightbox.
- Deleted-image audit overlays and uploader/deletion metadata remain visible inside the compact thumbnail card.
- Reopening Post Review now treats the latest immutable `cdsp_post_review_history` event as the authoritative saved Decision.
- Backend returns the latest History decision first, falling back to `cdsp_sales_posts.admin_review_status` and then `cdsp_post_reviews` only when no History event exists.
- Frontend independently performs the same history-first fallback, protecting the selected Good/Bad state even if an older state field is stale.
- The saved Good/Bad radio is explicitly checked and receives the existing `is-selected` visual state on every popup open.
- Decision now shows a compact `Last saved: Good/Bad · administrator · timestamp` line so the persisted database state is visible.
- A successful Save Review immediately refreshes that Last saved line from the exact history event returned by the server.
- No database migration is required.
- All border radius remains `4px`.

## v0.1.37 — Canonical History-driven Post state and progress

- Fixed reviewed Post cards reverting to gray/Unreviewed after the expanded grid was closed and reopened or after period/date AJAX navigation.
- `cdsp_post_review_history` latest event is now the canonical persisted state for Dashboard Post cards.
- `adminSalesPostsForPeriod()` derives `current_review_status` from the latest History event, falling back to the legacy `admin_review_status` and current review row only for old data.
- Dashboard Daily/Weekly/Monthly progress counts now derive Good/Bad from the same latest History event.
- Progress therefore persists across collapse/reopen, Daily/Weekly/Monthly switching, date changes, and full reloads.
- Progress remains a stacked ratio against the target: latest Good = green, latest Bad = red, Unreviewed = gray.
- Example: target 10 with 2 Good, 1 Bad, 1 Unreviewed displays 20% green + 10% red + 10% gray, with the remaining target capacity as the track background.
- Sales daily-date Good/Bad summaries now use the same canonical History status.
- The old `admin_review_status` column is still maintained for backward compatibility, but it no longer controls the dashboard when History exists.
- Reviewed Post borders are reinforced after AJAX reload: Good green, Bad red, Unreviewed neutral.
- No database migration is required.
- All border radius remains `4px`.

## v0.1.38 — Permanent image deletion and restored Comment Edit

- Image deletion now differs intentionally from Comment deletion.
- Comments remain audit records: deleting a Comment only marks it `Marked as deleted` with administrator/time metadata.
- Review/comment images may now be permanently removed when they were uploaded by mistake, preventing incorrect evidence from remaining visible and causing ambiguity.
- Permanent image deletion removes both the `cdsp_review_attachments` database row and the physical file in `storage/uploads`.
- The delete endpoint validates that the resolved file path remains inside the managed upload directory before unlinking it.
- Images that were already soft-deleted by v0.1.35 are given a visible `×` again, allowing administrators to purge those old tombstones permanently.
- Comment History updates immediately after image deletion; no page reload is required.
- Restored a clearly labeled `Edit` button on every Comment instead of relying on a small pencil icon.
- `Edit` remains available even when a Comment is marked deleted. Editing corrects its text but does not clear `deleted_at` or `deleted_by`, so the audit state remains intact.
- Deleted Comments do not accept new uploaded images during Edit, preventing new evidence from being attached to a deleted record.
- No database migration is required. Existing attachment soft-delete columns remain for backward compatibility with old records.
- All border radius remains `4px`.

## v0.1.39 — Inline period reviews, listing-date hierarchy, and deleted-comment filter

- Expanded Sales Post List now includes the selected Sales user's management review directly above the Post Grid.
- The review follows the dashboard period switch automatically: Daily tab shows Daily Review, Weekly shows Weekly Review, Monthly shows Monthly Review.
- Existing period reviews display their HTML note, reviewer, review time, and period; the action changes from `Add Review` to `Edit Review`.
- Add/Edit opens an in-page rich-text popup using the existing Visual/HTML editor and saves by AJAX without reloading the dashboard.
- Daily reviews persist in `cdsp_daily_sales_reviews`; Weekly and Monthly reviews persist in `cdsp_period_sales_reviews`. Existing records are updated instead of duplicated.
- Listing date was removed from the old fact-box presentation and is now shown as a compact `Listed · date/time` line directly above the Marketplace listing title.
- History now hides soft-deleted Comments by default.
- When at least one deleted Comment exists, a `See full comments` switch appears in the History header. Enabling it includes deleted Comments and their audit tombstones; disabling it hides them again.
- Good/Bad Review history always remains visible and is never filtered by the deleted-comment switch.
- The switch resets to the default hidden-deleted state whenever a different Post Review is opened.
- No database migration is required; existing daily/period review tables are reused.
- All border radius remains `4px`.

## v0.1.40 — Admin greeting, attendance naming, and four-language dashboard

- Replaced the `Administrator` eyebrow with `Hi, {admin display name}`.
- Renamed `Sales Work Progress` to `Sales Activity & Attendance`, better matching the dashboard's target/participation/period-review purpose without implying time-clock punches.
- Removed the duplicate period/date line under the page title; the date picker on the right remains the single date control.
- Added four always-visible language buttons — English, 简体中文, 繁體中文, Español — with no dropdown.
- English is the default on first use. The user's selected dashboard language is stored in browser localStorage for subsequent visits.
- Language switching is instant and does not reload the page.
- Dashboard localization covers the greeting/title, date actions, Daily/Weekly/Monthly tabs, Sales/Post counts, posting-progress labels, target/card labels, Post List states, period reviews, primary Review controls, History deleted-comment filter, and Admin header navigation.
- Dynamic AJAX content uses the active language as it is rendered, including Post status labels and Post List empty/error states.
- Dashboard date/time formatting follows the active locale.
- Existing Post data and Post List behavior are unchanged. A view showing `0 Posts` is a filter result, not removed data; for example, selecting 2026-08-19 does not display posts created on 2026-08-28.
- No database migration is required.
- All border radius remains `4px`.

## v0.1.41 — Menu language switch and Admin-aligned Sales Portal

- Moved the four-language switch from the Admin page body into the global top navigation menu.
- English / 简体中文 / 繁體中文 / Español now use one shared language preference across Admin and Sales.
- Removed the duplicate page-level language control from the Admin dashboard.
- Rebuilt the Sales dashboard to use the same SaaS hierarchy as Admin: greeting/title, compact date controls, primary CTA, summary cards, status-aware Post cards, platform icons, thumbnails, compact metadata, and 4px geometry.
- Sales Post cards now use the latest immutable `cdsp_post_review_history` status, matching Admin. Good remains green, Bad/Issue red, Unreviewed neutral across reloads.
- Sales cards show the fetched first thumbnail when available; otherwise they display the platform placeholder without consuming another provider/API request.
- Replaced the legacy URL-heavy Sales post presentation with thumbnail + published date + title + description + status + Open original.
- Replaced native `<details>` deletion UX with an inline SaaS-style deletion request panel.
- Sales deletion requests now submit by AJAX and show inline success/error state without reloading the whole dashboard.
- Added range summary cards for Posts / Good / Issues / Unreviewed.
- Rebuilt the Sales Submit screen to match Admin panels and status UX.
- Removed browser `alert()` from the Sales post verification flow; verification errors and provider failures are rendered inline.
- Sales Dashboard and Submit are localized in all four supported languages, including AJAX-loaded earlier-day sections.
- Header navigation labels also follow the selected language.
- No database migration is required.
- All border radius remains `4px`.

## v0.1.42 — Admin From/To, Sales Apply, and Sales partial fatal fix

- Admin now uses `From / To` date inputs like Sales; the old `View` button is removed.
- Admin From/To changes apply immediately through the existing AJAX dashboard refresh.
- From cannot move after To, and To cannot move before From. The opposite endpoint is automatically clamped and the native `min` / `max` values stay synchronized.
- Exact Admin ranges use a custom `range` mode for progress, expanded Post Lists, URL state, and live update polling.
- Daily / Weekly / Monthly remain quick presets and use the current To date as their anchor.
- Sales From/To now uses the same mutual date constraints plus server-side normalization.
- Sales `Apply` is an explicit JavaScript navigation action, so it no longer depends on ambiguous native form submission behavior.
- Fixed `Fatal error: Cannot redeclare salesPlatformIcon()` by replacing the Sales daily-post partial's global function with a local closure. The same partial can now be included repeatedly in one full-page or AJAX request.
- Fixed the Admin language listener selector after the language switch moved into the global top menu.
- No database migration is required.
- All border radius remains `4px`.

## v0.1.43 — Unified people reviews, star rating history, and Sales AJAX cleanup

- Consolidated Sales-person Daily / Weekly / Monthly management reviews into the Sales Activity & Attendance dashboard. The old standalone Daily Review page redirects into the same dashboard popup, and the Reports page no longer contains a second weekly/monthly editor.
- Added a required clickable 1–5 star rating for Sales-person management reviews. Rating is selected directly on stars; there is no dropdown.
- Reopening a person review restores its current rating. The expanded review summary also displays the latest rating.
- Added `cdsp_sales_review_history`, an immutable save audit. Every successful press of Save Review writes a new event containing administrator, timestamp, rating, and note, even when the rating or note did not change.
- Review History in the popup displays the rating on every saved event. Existing legacy reviews are backfilled once; old reviews that never had a rating can show `Not rated`.
- Added migration `013_sales_review_history.sql` and `scripts/migrate_v0_1_43.php`.
- Sales date-range Apply now refreshes the range through AJAX instead of reloading the full page. Summary metrics, daily sections, empty state, pagination state, and the browser URL update in place.
- Fixed AJAX Load Earlier Days after changing the date range by reading the current `data-from` / `data-to` attributes instead of stale jQuery cached data.
- Sales Verify Listing remains AJAX, deletion requests remain AJAX, and Save Verified Post is now AJAX with inline success/error state instead of a page redirect.
- Fixed Save Verified Post to call the existing `Inspection::verified()` API and consume the inspection transactionally.
- Starting a new verification clears the previous Saved state so the next listing has a clean workflow.
- Legacy Daily/Weekly/Monthly save routes no longer write no-rating reviews; stale forms are redirected to the unified rating-enabled dashboard.
- Post Good/Bad review remains separate from the person rating. No star rating was added back to individual Post Review.
- All border radius remains `4px`.

## v0.1.44 — Right-aligned HTML switch, light source editor, compact Sales grid

- Visual / HTML mode controls are forced to the far right for the current Prose editor and older compatible editor class names.
- Switching to HTML no longer causes the mode switch to jump to the left when the formatting toolbar is hidden.
- HTML source mode now uses a white background, light line-number gutter, dark caret, and syntax colors designed for a light editor.
- The same light-source treatment covers Prose, legacy WordPress-style, and Composer-style HTML editors for consistent UX.
- Sales Daily Post cards now use six columns on desktop.
- Sales cards were compacted together with the six-column layout: smaller media area, tighter body, metadata, footer, platform badge, status, and deletion control.
- Responsive layout falls back to 4 columns at <=1200px, 3 at <=900px, 2 at <=720px, and 1 at <=560px.
- No database migration is required.
- All border radius remains `4px`.

## v0.1.45 — Clickable/touchable Sales cards and cleaner Review header

- Removed the `Get Content` button from the Admin Post Review header. Existing saved/fetched listing content remains visible; Open original remains available.
- Sales Daily Post cards are now real interactive cards. Mouse click, touch tap, Enter, and Space open a read-only post-details popup.
- Interactive controls inside a card (Request deletion and other buttons/links) do not trigger the card popup, preventing accidental actions on touch devices.
- Added a clear `View details` action in each Sales card footer while keeping the whole card clickable.
- Sales post details show listing image, review status, published timestamp, full title, full description, platform, item ID, and Open original.
- Clicking the listing image opens a dedicated full-screen image viewer.
- The post-details modal is AJAX-safe because event handlers are delegated; cards loaded by From/To filtering or Load earlier days work immediately without a reload.
- Added keyboard focus states and `role=button`/`tabindex=0` to Sales cards.
- Touch controls use >=44px targets and do not rely on hover.
- Desktop remains six Sales cards per row; responsive breakpoints are 4 columns <=1150px, 3 <=900px, 2 <=680px, and 1 <=480px.
- No database migration is required.
- All border radius remains `4px`.

## v0.1.46 — Daily status filters and compact date controls

- Converted each Daily Posts `Posts / Good / Issues / Unreviewed` counter into an actual per-day filter.
- `Posts` is the default All state; Good, Issues, and Unreviewed instantly filter that day's cards without a page reload.
- Filtering uses delegated events, so sections loaded later through AJAX work immediately.
- Filters expose pressed state for keyboard/screen-reader users and have 44px minimum targets on coarse-pointer/touch devices.
- From / To date controls are visually compact on desktop: 132px fields, larger text, less empty horizontal space, and tighter spacing.
- Touch devices keep 44px minimum input/button height despite the compact desktop presentation.
- Long Sales card descriptions are explicitly clamped to two lines, and the card body has a fixed height so long descriptions cannot make one card taller than neighboring cards.
- Titles are also clamped to two lines for a stable six-column grid.
- Full title and description remain visible in the card detail popup.
- No database migration is required.
- All border radius remains `4px`.

## v0.1.47 — Sales daily coordinate chart and per-person Admin target settings

- Replaced the four large Sales summary cards with a real daily coordinate/bar chart.
- X axis follows the selected From/To date range, including zero-post days.
- Each day is a stacked bar: Good at the bottom, Issues in the middle, Unreviewed on top.
- Bar height represents total posts. The vertical chart scale tops out at 120% of the Admin-configured daily target; actual values above 120% are visually capped at 120% and marked `120%+`, while hover/touch tooltips still show the real counts.
- A horizontal Daily target line is drawn across the chart using that Sales person's `daily_post_target`.
- When a day is below target, the missing amount is shown as a dashed block between the actual total and the target line. Hover/focus/touch displays Total / Good / Issues / Unreviewed / Missing / Target.
- Added dashboard platform filters: All, Facebook, Instagram, OfferUp, Craigslist. The filter updates both the chart and loaded Daily Post cards without reloading the page.
- Instagram is exposed as a dashboard filter now; the current submit/database pipeline still has no Instagram provider, so it remains zero until Instagram posts are supported.
- Removed the Sales Apply button. Changing From or To immediately reloads the chart and Daily Posts through AJAX.
- Fixed the Sales-card detail bug introduced by the previous Apply-handler scope: card mouse click, View details, keyboard Enter/Space, and touch now bind at page initialization instead of only after Apply was clicked.
- Admin Daily Post Target is moved into a per-person `Settings` popup on each Sales progress card. It uses the existing `daily_post_target` database field and existing `/admin/sales-target` endpoint, so no migration is required.
- Saving an Admin target immediately updates that person's Admin progress card; the target becomes the horizontal line on that Sales person's dashboard.
- Touch targets remain at least 44px where needed, chart/filter controls support horizontal touch scrolling, and all border radius remains `4px`.

## v0.1.48 — Adaptive Sales chart, working compact filters, grouped Admin Post List

- Sales activity chart now scales its X axis dynamically from the selected From/To range instead of assigning a fixed pixel width per day.
- Short and medium ranges fit the available chart width automatically. Long ranges reduce label density and bar width; only very long ranges switch to horizontal scrolling.
- A ResizeObserver recalculates chart geometry when the browser, responsive layout, split view, tablet orientation, or dashboard container width changes.
- Date labels are sampled automatically for larger ranges while the first and last dates always remain visible.
- The 120% vertical cap, Daily Post Target line, Good/Issues/Unreviewed stack, Missing dashed section, hover tooltip, keyboard focus, and touch tooltip remain unchanged.
- Daily `All / Good / Issues / Unreviewed` controls were redesigned as a compact segmented filter with substantially smaller typography.
- Status filtering now sets the native `hidden` attribute on non-matching cards in addition to CSS state, so later stylesheet rules cannot accidentally prevent the filter from working.
- Status filters continue to combine with the global platform filter.
- Admin expanded Post List is now grouped by published date. Each date has its own heading and cards remain in chronological order within the period.
- Admin expanded Post cards now render six columns on desktop, then 4 / 3 / 2 / 1 responsively.
- Admin cards are compacted to match the six-column density while keeping title/description clamped and status visible.
- No database migration is required.
- All border radius remains `4px`.

## v0.1.50 — Today-only range, Back to today, complete X-axis chart

- v0.1.50 supersedes the invalid v0.1.49 build.
- Sales From/To can never exceed today. The browser date field and SalesController both enforce this.
- Added Back to today; it sets From and To to today and refreshes through AJAX.
- Every date in the selected range is printed on the X axis.
- The chart compresses the day slots first and only uses a horizontal scrollbar when the selected range becomes too dense.
- The first chart is also rendered server-side so cards cannot appear below a completely empty graph during JavaScript startup.
- Loaded Sales cards are re-counted into chart data as a second correctness layer.
- Good, Issues, and Unreviewed remain stacked; Missing is dashed up to the Admin target; visual height still caps at 120%.
- Zero-result filtering preserves one card-row height.
- Request deletion is removed from Sales cards and its client-side handlers.
- No database migration is required.
- All border radius remains 4px.

## v0.1.51 — Calmer Sales chart and unified date picker

- Empty Sales chart dates now keep only their X-axis date label; a completely empty day no longer draws a full dashed Missing target box.
- A partially completed day still draws the dashed Missing portion from the current post count up to the Admin Daily Post Target.
- Good / Issues / Unreviewed chart colors were changed to lower-saturation green, red, and slate tones.
- Daily Post Target line was also softened to a muted blue-gray.
- X-axis dates now sit physically below the horizontal coordinate axis. The axis line is drawn at the top of the date-label row rather than at the bottom of the whole chart.
- Sales From/To now uses the same Admin date-picker markup and sizing.
- Sales AJAX date changes no longer display the literal `Loading...` status text; the chart/list use a subtle busy state and only errors produce status text.
- Admin From and To date inputs now have a hard max of today.
- Admin range JavaScript and AdminController also clamp crafted/future dates to today.
- Admin week/month period calculations are clamped to today, so a current period cannot silently include future dates.
- Sales continues to disallow future dates in both the UI and SalesController.
- No database migration is required.
- All border radius remains `4px`.

## v0.1.52 — Sales Daily / Weekly / Monthly presets and single-day chart correction

- Restored an explicit Daily / Weekly / Monthly switch to the Sales dashboard.
- Daily uses one selected/anchor day; Weekly uses the anchor date's Monday-through-Sunday range but never extends beyond today; Monthly uses the anchor month and never extends beyond today.
- Weekly and Monthly still show one X-axis bucket for every individual date because the chart's vertical target is a Daily Post Target.
- Manual From / To remains supported. Exact day/week/month ranges activate the matching preset; arbitrary ranges are treated as custom.
- Back to today always switches the Sales dashboard to Daily and sets both From and To to today.
- Fixed the stale-grid failure where a prior Monthly chart could be reflowed into one column and display 8/1, 8/2, 8/3 ... vertically after Back to today.
- Chart children are now replaced before new grid geometry is applied, and implicit grid-column flow is disabled.
- A one-day range renders exactly one centered, deliberately wider column (72px desktop / 82px coarse pointer) rather than a full month's worth of narrow slots.
- Empty one-day ranges continue to show only the date on the X axis, consistent with the rule that totally empty days do not draw Missing boxes.
- Period state is reflected in the URL as `period=day|week|month`; custom manual ranges omit the period query parameter.
- No database migration is required.
- All border radius remains `4px`.

## v0.1.53 — Rectangular stacked bars and visible Daily / Weekly / Monthly controls

- Removed the dashed Missing visual from the Sales activity chart completely. Missing count is still available in the hover/touch tooltip, but it is no longer drawn as a dashed box.
- The Admin Daily Post Target remains a horizontal reference line only.
- Sales bars now use a classic stacked-column UX: Good on the bottom, Issues in the middle, Unreviewed on top.
- Bar colors remain muted management-dashboard colors rather than bright red/green.
- Chart bars and stacked segments explicitly use square corners (`border-radius: 0`) per the requested graph style; the rest of the application continues using 4px UI radius.
- Daily / Weekly / Monthly was moved from the page-head actions into the Posting Activity panel toolbar beside the platform filters, so the period controls stay visible at desktop/tablet widths.
- The activity title changes with the selected preset: Daily Post Progress / Weekly Post Progress / Monthly Post Progress.
- Daily still renders one wider centered bar; Weekly and Monthly show one rectangular bar per day in their clamped range.
- Platform filtering remains independent of the period switch.
- No database migration is required.

## v0.1.54 — Working Sales period/channel controls and Back-to-today placement

- Moved Sales Daily / Weekly / Monthly back to the page-head actions immediately left of the From/To date picker.
- Added the missing delegated click handler for Daily / Weekly / Monthly. These buttons now calculate the correct preset range, clear stale chart state, update the date fields, and reload Sales cards/chart through AJAX.
- Daily uses one day; Weekly uses the anchor date's Monday-through-Sunday range clamped to today; Monthly uses the anchor month clamped to today.
- Back to today is now directly below the To date picker on both Admin and Sales.
- Added a Channels title and channel/network icon above the All / Facebook / Instagram / OfferUp / Craigslist controls.
- Fixed Channels filtering by normalizing platform values to lowercase before comparing card data with the selected channel.
- Channel changes now update active button state, chart aggregation, per-day status counters, and visible post cards with a single chart redraw.
- Removed the accidental duplicate chart redraw in the previous platform-filter handler.
- Touch controls remain delegated and AJAX-loaded post sections participate automatically.
- No database migration is required.
- All normal UI border radius remains 4px; chart bars remain the explicit square-corner exception.

## v0.1.55 — Aug 28 post-date correction and aligned Sales header controls

- Corrected the three Facebook Marketplace posts previously grouped under 2026-08-26 so their real posting date is 2026-08-28:
  - `970768882088732`
  - `1556421559266266`
  - `1994325934606833`
- The migration changes both `published_at` date part and `published_date`, while preserving each existing time-of-day.
- Updated `database/demo.sql` so future demo refresh/install keeps those three posts on August 28, 2026.
- Sales header controls were rebuilt as an aligned control deck.
- Daily / Weekly / Monthly, From/To date inputs, and Submit Post now use the same 38px desktop primary-control height and input-row baseline.
- Back to today remains directly below the To field and no longer pushes the other controls vertically out of alignment.
- Touch/coarse-pointer mode uses matching 44px primary control heights.
- Responsive layout keeps equal sizing and stacks cleanly at narrow breakpoints.
- Database data migration is required for an already-running installation.
- Normal UI radius remains 4px; chart bars remain the explicit square-corner exception.

## v0.1.56 — Platform icons and authoritative Channels filtering

- Added a platform icon to every Channels control: All, Facebook, Instagram, OfferUp, and Craigslist.
- Channels is no longer only a client-side show/hide operation.
- Clicking a channel immediately applies local UI feedback, then requests `/sales/daily-posts` with the selected `channel` and replaces Daily Posts, date groups, status counts, paging data, and chart rows with the server-filtered result.
- SalesController validates channel to `all|facebook|instagram|offerup|craigslist`.
- Sales Post model range methods now accept an optional platform filter and use parameterized `LOWER(platform)=?` queries.
- Full-page Sales URLs can preserve a channel with `?channel=facebook` etc.; page refresh keeps the selected channel.
- Switching back to All removes the channel query parameter.
- Load Earlier preserves the current channel.
- Channel matching is case-insensitive on both server and client.
- No database migration is required.
- Normal UI radius remains 4px; chart bars remain the explicit square-corner exception.

## v0.1.57 — Rolling 3-day/week/month ranges, remaining Aug-28 correction, readable Sales UX

- Corrected the remaining three Facebook Marketplace posts that were still grouped under August 27 so they are stored on August 28:
  - `1546388710570410` — funnel cake gas fryer
  - `3813795918762562` — 10 mold popsicle maker machine PM-10
  - `1606074697620900` — NSF 40LB GAS FRYER HRFR-90B-NG
- The migration preserves each post's existing time-of-day while changing both `published_at` date part and `published_date` to `2026-08-28`.
- Updated demo data so future refreshes keep those records on August 28.
- Renamed the Sales `Daily` preset to `3 Days`.
- All Sales presets are now rolling ranges based on the current `To` date:
  - 3 Days = To plus the previous 2 days.
  - Weekly = To plus the previous 6 days.
  - Monthly = one rolling calendar month ending on To.
- Back to today no longer forces a one-day view. It keeps the currently selected 3 Days / Weekly / Monthly preset and moves that rolling window so its `To` is today.
- Sales typography was enlarged across period buttons, date controls, Channels, Daily Posts headers, per-day filters, cards, View details, statuses, and chart labels.
- Back to today is pinned directly beneath the To date input on both Sales and Admin desktop layouts; narrow layouts return it to normal flow.
- Channels now use a short fade/slide transition while the authoritative AJAX filter is loading.
- When a Channel returns no posts, the Daily Posts area preserves its pre-filter height instead of collapsing.
- Per-day Good / Issues / Unreviewed zero-result filters also retain a stable card-row height.
- Database migration is required on existing installations.
- Normal UI radius remains 4px; chart bars remain the explicit square-corner exception.

## v0.1.58 — Exact target line and reliable period chart refresh

- Fixed the plotting-coordinate mismatch that made exactly 10 posts appear above a Daily Target of 10.
- The Target line now uses the actual rendered canvas height minus the X-axis row, exactly matching the coordinate system used by stacked bar percentage heights.
- Exactly 10 posts meet Target 10; 11+ posts may extend above it.
- Good / Bad / Unreviewed totals are normalized against the real post total before rendering.
- Chart colors now exactly match review-state UI colors: Good #22c55e, Bad #ef4444, Unreviewed #94a3b8.
- Fixed Monthly after Back to today losing the August 28 bar.
- Full-range server chart_rows is authoritative; paged Daily Posts DOM no longer overwrites the complete chart data.
- The selected Channel is set before chart rendering.
- 3 Days / Weekly / Monthly / Back to today no longer blank the chart before AJAX returns.
- Added request sequencing so an older aborted/slow range request cannot overwrite the newest period or Channel selection.
- No database migration is required.
- Normal UI radius remains 4px; chart columns remain square.

## v0.1.59 — SaaS-style Sales transitions and stable Empty states

- Date changes, 3 Days / Weekly / Monthly changes, Back to today, and Channels now use a consistent content-replacement transition instead of abruptly replacing the Sales dashboard.
- Existing Daily Posts and chart softly fade/shift while the AJAX request runs; the new chart, date sections, and post cards animate into place after the authoritative response arrives.
- New date sections and cards use short staggered enter animations so large ranges still feel responsive rather than flashing all at once.
- Good / Issues / Unreviewed per-day filters now animate cards out before the grid reflows and animate newly visible cards back in.
- Channels no longer immediately hide the current cards before the server response; the active Channel button changes immediately while the content transitions to the authoritative server-filtered result.
- An entirely empty date range now always renders a real `Empty` placeholder card with at least one normal Sales-card footprint.
- An empty Good / Issues / Unreviewed filter also renders one card-sized Empty placeholder inside the day grid.
- Channel filtering to zero posts preserves the pre-filter Daily Posts area height; a date/range with zero posts uses the normal one-card minimum height.
- Added reduced-motion handling so users who disable animation do not receive transition effects.
- No database migration is required.
- Normal UI radius remains 4px; chart bars remain square.

## v0.1.60 — Working Channel filters, complete Y-axis, and correct Back-to-today state

- Fixed Channels appearing unresponsive after one click. The loading transition no longer disables pointer events on the entire Posting Activity panel; only the chart plot fades while Channels controls stay interactive.
- Channel selection now gives immediate local feedback: existing post cards animate/reflow instantly, and a card-sized Empty state appears immediately when there are no current matches. The authoritative server-filtered AJAX result then replaces the local state.
- Added a 15-second AJAX timeout so a failed provider/database request cannot leave the Sales dashboard permanently stuck in a loading state.
- Added a compact spinner to the active Channel button during the AJAX update. Rapid Channel switching remains supported.
- Empty states are enforced for date ranges, Channels, and Good / Issues / Unreviewed filters. Channel-to-zero-results continues to preserve the previous Posts-area height.
- Back to today now hides whenever `To` already equals today. A Monthly/Weekly/3 Days range ending today no longer incorrectly shows Back to today just because its From date is earlier.
- Applied the same Back-to-today visibility rule to Admin.
- Replaced the sparse `0 / target / max` Y-axis with dynamically generated full ticks. With the default Target 10, the axis is `0, 2, 4, 6, 8, 10, 12`.
- Changed the Daily Target line from blue to neutral gray while keeping Good / Bad / Unreviewed as the data colors.
- No database migration is required.
- Normal UI radius remains 4px; chart columns remain square. The small loading spinner is intentionally circular.

## v0.1.61 — Non-blocking Sales loading and right-aligned Back to today

- Fixed the remaining source of the apparent forever-loading Channels state: legacy `.sales-activity-chart-panel.sales-range-loading` CSS could still set `pointer-events:none` on the entire Posting Activity panel.
- AJAX no longer applies the loading class to the whole chart panel.
- Only the chart body and Daily Posts body receive a short replacement animation; Channels, date pickers, and period controls remain interactive.
- Added centralized `startSalesRangeVisualState()` / `clearSalesRangeVisualState()` lifecycle helpers so every success, failure, abort, invalid range, and replacement request clears the same loading state.
- Loading/spinner visuals automatically clear after 900ms even if a slow network request is still running, so the interface cannot remain dimmed/spinning indefinitely.
- The server request may continue in the background and still replaces the view when it returns; request sequencing still prevents stale responses from winning.
- Back to today now right-aligns to the right edge of the To date picker on Sales and Admin.
- Back-to-today visibility also treats the To picker's own `max` value as the latest day, preventing timezone/cache drift from showing the link when the picker is already at its newest date.
- No database migration is required.
- Normal UI radius remains 4px; chart bars remain square; the tiny loading spinner remains circular.

## v0.1.62 — True 3-Day view, Custom Range state, and per-day Empty sections

- `3 Days` is now a real authoritative period, not only a button label.
- When `period=day`, the server forces the range to exactly `To - 2 days` through `To`. A contradictory URL such as `period=day&from=08/01&to=08/31` is normalized to `08/29 → 08/31`.
- The Sales chart therefore renders exactly three X-axis dates in 3 Days mode.
- 3 Days always returns all three calendar-day sections, even when one or all days contain zero posts.
- A zero-post day uses the same Daily Posts section header/status-filter framework and shows one card-sized Empty placeholder instead of removing the entire day.
- Added a fourth period state: `Custom Range`.
- Manually changing either From or To always activates Custom Range; 3 Days / Weekly / Monthly are all inactive.
- Custom Range is persisted explicitly as `period=custom` in the browser URL, so reloads keep the correct state.
- Clicking Custom Range keeps the current From/To values and reloads that exact range.
- Period is now sent with Sales AJAX requests and returned by the server together with authoritative From/To values.
- The client updates both date inputs from the server-resolved range before rendering the chart, preventing stale month X-axis labels after selecting 3 Days.
- No database migration is required.
- Normal UI radius remains 4px; chart bars remain square.

## v0.1.63 — Canonical Sales chart geometry and exact X/Y axes

- Replaced the accumulated Sales chart geometry overrides with one final canonical chart contract.
- The chart now uses fixed shared geometry: 280px total, 248px plot, and 32px X-axis row.
- Y-axis ticks, horizontal grid lines, Target line, and stacked bars all use the same top-origin 248px plot coordinate system.
- With Daily Target 10 and 20% headroom, the cap is 12 and the Y axis renders 0 / 2 / 4 / 6 / 8 / 10 / 12.
- A 10-post stacked bar and the Target 10 line are mathematically and visually the exact same pixel height.
- Good remains `#22c55e`, Issues `#ef4444`, and Unreviewed `#94a3b8`; chart bars remain square.
- X-axis dates are generated only from the current From/To inputs. Custom Range 08/11 → 08/31 renders 08/11 through 08/31, never 08/01 through 08/31.
- 3 Days updates the date inputs and immediately redraws exactly three X-axis dates before AJAX completes.
- Weekly, Monthly, Custom Range, and Back to today also redraw X-axis geometry immediately, so a failed/slow AJAX request cannot leave the prior range's labels on screen.
- Period titles now update their `data-sales-i18n` key together with the visible text, preventing a later language refresh from changing Custom Range Progress back to Daily Post Progress.
- Server-resolved period is applied before language/render on AJAX responses.
- No database migration is required.

## v0.1.64 — Isolated Sales dashboard controller

- The screenshot from v0.1.63 proved the newer chart renderer was not actually controlling the visible Sales chart: date inputs could show 08/01 → 08/10 while the X axis stayed 08/01 → 08/31 and the Y-axis labels never appeared.
- Sales range, period, channel and chart behavior is now isolated in `public/assets/sales-dashboard.js`, loaded after the legacy `app.js`.
- On Sales pages, the new controller explicitly detaches the previous accumulated range/period/channel/status/chart handlers before binding one authoritative implementation.
- Native `input` and `change` listeners are used for From/To. Changing To from 08/31 to 08/10 immediately rebuilds the X axis to 08/01 → 08/10 before any AJAX request finishes.
- `window.renderSalesChart` is redirected to the isolated renderer so older ResizeObserver callbacks also use the new chart engine rather than the legacy renderer.
- Y-axis tick labels and grid lines are also server-rendered as a fallback, so 0 / 2 / 4 / 6 / 8 / 10 / 12 are visible even before client rendering.
- Target line receives a correct server-side top coordinate on first paint.
- Good / Bad / Unreviewed segment `height` transitions are disabled. Bars no longer grow upward from zero on reload or filter changes.
- Data replacement still uses a short whole-content fade/shift, preserving SaaS feedback without animating the numeric bar height.
- Channel filtering, Custom Range, 3 Days, Weekly, Monthly, Back to today, Load Earlier and per-day review-state filters are handled by the isolated controller.
- AJAX uses `fetch()` with abort/request sequencing and `cache: no-store`.
- No database migration is required.
- Normal UI radius remains 4px; chart bars remain square.

## v0.1.65 — Unified Empty state and calendar-day Daily Posts

- Daily Posts are now paged by calendar date instead of by only the dates that contain posts.
- Empty dates are therefore retained between populated dates. If August 28 has posts but August 29/30/31 do not, each empty date still renders its normal `date + Daily Posts + All/Good/Issues/Unreviewed` section with zero counts.
- 3 Days always returns all 3 calendar days; Weekly always returns all 7 calendar days.
- Monthly and Custom Range initially return the newest 10 calendar days and use Load Earlier for older calendar days, so nearby empty dates and populated dates remain visible together without rendering an unbounded number of sections.
- Added `Post::forSalesPublishedRange()` so each calendar page loads its posts in one range query rather than one query per date.
- Channel filtering uses the same calendar-day section builder. A Channel with zero posts no longer falls back to a different giant Empty layout; each visible date uses the same Daily Posts shell and the same Empty presentation.
- Unified every Sales Empty presentation into `.sales-empty-message`.
- Empty is centered in the available section, has no dashed border, no white inner card, no box-shadow, and no separate framing.
- Good / Issues / Unreviewed zero-result filters use the exact same icon/title/message component.
- The legacy dashed empty-card styles are explicitly neutralized to protect against stale markup or cached fragments.
- No database migration is required.
- Normal UI components remain 4px; chart bars remain square; the borderless Empty message intentionally has no radius because it has no box.

## v0.1.66 — One Posts module per selected range

- Removed the date-by-date `Daily Posts` modules from the Sales dashboard.
- The selected From/To range now has exactly one `Posts` module.
- Every post in the selected range is returned in one query and rendered in one six-column responsive grid.
- Posts are ordered newest to oldest using `published_date DESC, published_at DESC, id DESC`.
- Each post card now displays its own `Post date`, including date and time.
- All / Good / Issues / Unreviewed is one filter belonging only to the single Posts module.
- Channel filtering returns the exact same single Posts module; zero results use the same header, zero counts and centered Empty state.
- Removed Load Earlier/calendar-day pagination from Sales; all posts in the selected range are returned as requested.
- The existing AJAX route remains `/sales/daily-posts` for compatibility, but it now returns the complete range Posts module and `has_more=false`.
- Hovering a chart color segment now calls out that segment count first (Good / Issues / Unreviewed), while still showing Total and Missing.
- No database migration is required.

## v0.1.67 — 1 Day default, stable menus, bottom-up bars

- Added **1 Day** before **3 Days**, with English, Simplified Chinese, Traditional Chinese and Spanish labels.
- Opening `/sales` without a date range or preset now selects 1 Day and sets From/To to today's existing business date. Explicit dates and presets remain respected.
- The new one-day preset uses `period=single`; existing `period=day` links continue to mean 3 Days. Weekly and Monthly keep their existing rolling ranges.
- Back to today preserves the active preset, including 1 Day. From Custom Range it returns to today in 1 Day mode.
- Top navigation links, preset slots and Submit Post reserve fixed widths across languages. Narrow screens use viewport breakpoints to wrap controls without text-driven resizing.
- On first render and each chart data/range/channel update, the colored stack grows upward from zero over 520ms. Axes, dates and target line stay stationary. Reduced-motion preferences are respected.
- Legacy language/resize redraws now use the isolated controller's current data. Duplicate resize notifications do not interrupt growth.
- Posts status filtering remains independent from the chart, channel and date controls. Its handler is now attached once, including on first load.
- VERSION is 0.1.67 (the existing footer reads VERSION). No database migration or configuration change.

### Applying this patch

Overlay the included `sales-posts/` files onto the existing installation that already has v0.1.66 and its dependencies. This is a patch, not a standalone installation. It retains all eight files from the supplied v0.1.66 patch. Follow the existing stage/commit/push deployment workflow, then hard-refresh the browser if assets are cached.

### Validation for v0.1.67

- JavaScript syntax checks passed for app.js and sales-dashboard.js. All three supplied PHP files passed PHP 8.1 parser checks.
- DOM simulation with the actual scripts/styles and mocked endpoint data passed: first-load 1 Day, all four presets, four-language labels/fixed CSS widths, independent Posts filters, current channel rows after language changes, repeated growth markup, resize preservation, historical dates, Back to today, and zero-result chart rows.
- The test environment could not open a rendered browser preview and did not have the live PHP/MySQL application or Git checkout. Real browser layout/animation and live deployment were not verified.

## v0.1.68 — Restore Posts filter UX and remove duplicate title

- Removed the duplicate POST/Posts eyebrow: one Posts title, total count and selected dates remain.
- Restored the existing `.sales-day-filter` segmented control styling that was missing on the range-wide Posts module: gray group, white active button, status text colors and separated counts.
- Retained `data-sales-post-filter` as the module's own event namespace. The right-side status control never changes chart data, date range or channel.
- Status changes now use both native `hidden` and the existing CSS class. All restores every card; zero-result filters show the same Empty component.
- Review states are normalized; blank/unknown states count as Unreviewed. Counts, hover titles and accessible labels reflect the actual cards in the current range and channel. Labels and Empty feedback update with language changes.
- The group can shrink/wrap on narrow screens without the old full-width stretch.
- Preserved v0.1.67: 1 Day before 3 Days, today's default range, fixed top menu widths and bottom-up chart growth.
- No database migration. VERSION/footer source is 0.1.68.

Validation: all previous DOM simulation checks and PHP/JS syntax checks pass. Added the reported 10-post case (9 Good, 1 Issue, 0 Unreviewed), clicks on both labels and counts, All restoration, zero-result feedback, unchanged chart/date/channel, translated tooltips, and repeated AJAX replacements. These are local simulations; rendered browser layout and live deployment were not verified.

## v0.1.69 — Plain Arial typography

- The shared UI font is now Arial, with Helvetica/sans-serif fallback. Form controls inherit the UI font; code editors keep their monospace font.
- Posts card dates, descriptions, View details, status and sequence labels use regular weight 400. Post titles use standard bold 700 instead of heavy 900.
- The Posts range/date labels and status filter labels use normal weights, with standard bold counts.
- Font sizes, card layout, colors, fixed menu widths, independent filters and bar animation are unchanged. No database migration. VERSION/footer source is 0.1.69.
- Checked the typography CSS and re-ran the existing local DOM regression checks. No live server deployment or rendered browser verification was performed.

### SSH and deployment


The server address and project path come from this project's existing setup. The commands below assume a root SSH login; use your configured account and port if different. No passwords are included.

From Windows PowerShell after downloading the patch to Downloads:

```powershell
scp "$env:USERPROFILE\Downloads\sales-posts-v0.1.69-arial-font-patch.zip" root@144.126.218.94:/opt/coolerdepot/www/sales-posts/
ssh root@144.126.218.94
```

Then run in the server's Bash shell. This backs up only the eight patch files, never the database or secrets; it applies the patch and commits only those paths in the existing Git repository.

```bash
set -e
cd /opt/coolerdepot/www/sales-posts
patch_files=(VERSION README.md app/Controllers/SalesController.php app/Views/sales/dashboard.php app/Views/sales/_post_range_section.php public/assets/app.js public/assets/app.css public/assets/sales-dashboard.js)
archive=/opt/coolerdepot/www/sales-posts/sales-posts-v0.1.69-arial-font-patch.zip
backup="/tmp/sales-posts-before-v0.1.69-$(date +%Y%m%d-%H%M%S).tgz"
test -f "$archive"
git rev-parse --is-inside-work-tree
unzip -t "$archive"
tar -czf "$backup" -- "${patch_files[@]}"
unzip -o "$archive" -d /opt/coolerdepot/www
git diff --check -- "${patch_files[@]}"
git add -- "${patch_files[@]}"
git commit --only -m "v0.1.69: use Arial typography" -- "${patch_files[@]}"
git push
cat VERSION
```

If commit/push fails, do not reset/discard local changes; inspect that error. The ZIP overlay has already updated the files on this server. Open the Sales page and use Ctrl+F5 if the browser cached the old assets; verify the footer shows 0.1.69.

## v0.1.70 — Historical submissions and duplicate comparison

- `Posts` has one heading without a repeated total. Status filters retain their counts.
- Compact fixed menu slots keep the same widths in all four languages. Arial, 1 Day/default today, status filters and chart growth from v0.1.67–69 remain unchanged.
- Verified past/current listings are saved under their original publication date in the configured company timezone. The success link opens that day. Unknown/invalid/future dates remain blocked.
- Canonical URL and platform + external ID duplicates block across all salespeople and dates, including deleted records. Existing database unique constraints remain in place.
- Exact normalized titles and identical image files on the same platform block across all active salespeople. Existing own-description blocking remains.
- Save acquires a platform-level MySQL advisory lock, re-reads/locks the unconsumed inspection, and repeats duplicate checks in the insertion transaction. Old inspection tokens must be checked again after this upgrade.
- Image downloads use public HTTPS destinations with DNS pinning, redirect validation, bounded sizes and supported raster formats. Up to eight listing photos are checked per inspection. Exact file hashes block; GD perceptual hashes identify possible re-encoded/resized images for review without automatic blocking.
- Seller avatars, logos and related listings are excluded. Missing/failed images, unavailable GD, absent website references and unindexed records are reported explicitly. Comparison covers available indexed photos, not all images on the public internet.
- Website matches are advisory with source links. Import your company's UTF-8 CSV in Admin Settings (`page_url,title,image_url`, exact product-page host, HTTPS image URLs; CDN hosts allowed). This compares the supplied primary image per product, not an automatic full-site crawl. Reimport and reindex when products change; old references are retained, not silently deleted.

### Upgrade from v0.1.69

The ZIP belongs in `/opt/coolerdepot/www/sales-posts`; it contains a `sales-posts/` root and must be extracted into `/opt/coolerdepot/www`. Back up application files and the database using your normal backup process first.

```bash
ssh root@144.126.218.94
cd /opt/coolerdepot/www/sales-posts
unzip -o sales-posts-v0.1.70-history-duplicate-patch.zip -d /opt/coolerdepot/www
cd /opt/coolerdepot
docker compose exec -T php php /var/www/html/sales-posts/scripts/migrate_v0_1_70.php
docker compose exec -T php php /var/www/html/sales-posts/scripts/index_duplicate_images.php --limit=200
```

The migration only adds comparison tables. It is repeatable and does not rewrite post dates, delete data, change provider credentials or remove unique constraints. New submissions report comparison unavailable until the migration succeeds.

Index historical images using stored listing metadata; the indexer makes no paid listing-provider queries. It reports missing sources, download failures and a last ID. For additional batches repeat with the printed `--after=ID` value. Exit code 2 means incomplete checks; do not interpret it as complete coverage. For listings with no stored photos, fetch their content in Admin, then rerun indexing. Use `--all` to retry/refresh already indexed records.

After importing the company website CSV, run:

```bash
cd /opt/coolerdepot
docker compose exec -T php php /var/www/html/sales-posts/scripts/index_duplicate_images.php --website --limit=200
```

Continue additional batches with `--website --after=ID`; add `--all` when refreshing existing image URLs. The website cannot actually be compared until real product references and images have been supplied/indexed. GD is optional for exact file comparison but required for perceptual similarity.

### Validation

`php tests/duplicate_comparison.php` uses an isolated in-memory SQLite fixture, never production credentials. It verifies historical dates/timezone boundaries, invalid/future dates, own/other-user ID/title/image checks, save-time duplicate rechecks, website warnings/sources and image extraction/security/hash behavior. Requires CLI PDO SQLite + mbstring; DOM/GD enable metadata/image decoding cases.

Local validation: 32 backend regression checks, 85 PHP syntax checks, JavaScript syntax, and dashboard DOM regressions for dates, filters, languages and bar redraws. Live MySQL advisory-lock behavior, real provider image downloads and production browser rendering still need deployment verification.

## v0.1.71 — UI refinement, exact-title duplicate blocking, website library, hard delete

- Removed visible post sequence/number badges from Sales cards and Admin expanded post cards.
- Submit Post opens in a dashboard modal. A successful save refreshes the Sales dashboard.
- Exact same-platform titles now block only when the stored title text is byte-for-byte identical. Duplicate responses include a clickable original post URL.
- Sales can request deletion only. Admin approval or direct Admin deletion permanently removes the post and its post-level dependent database records.
- Deleted/legacy soft-deleted posts no longer participate in URL, external ID, title, description, or image duplicate lookup.
- Admin Settings now owns the company website URL. Website/sitemap scan, URL CSV import, manual title/description/image URL entry, search, and permanent reference deletion are available there.
- Added a downloadable sample CSV from Admin Settings.
- Added a plain-text Company Name setting used by the visible application branding.
- Long-running post inspections release the PHP session lock so checks from the same user are not serialized.
- Run `php scripts/migrate_v0_1_71.php` after the existing v0.1.70 migration.


### SSH upgrade for v0.1.71

From Windows PowerShell after downloading `sales-posts-v0.1.71-ui-website-hard-delete.zip`:

```powershell
scp "$env:USERPROFILE\Downloads\sales-posts-v0.1.71-ui-website-hard-delete.zip" root@144.126.218.94:/opt/coolerdepot/www/sales-posts/
ssh root@144.126.218.94
```

Then on the server:

```bash
set -e
cd /opt/coolerdepot/www/sales-posts
archive=/opt/coolerdepot/www/sales-posts/sales-posts-v0.1.71-ui-website-hard-delete.zip
backup="/tmp/sales-posts-before-v0.1.71-$(date +%Y%m%d-%H%M%S).tgz"
unzip -t "$archive"
tar --exclude=.git --exclude='*.zip' -czf "$backup" .
unzip -o "$archive" -d /opt/coolerdepot/www

cd /opt/coolerdepot
docker compose exec -T php php /var/www/html/sales-posts/scripts/migrate_v0_1_71.php

cd /opt/coolerdepot/www/sales-posts
git diff --check
git add .
git commit -m "v0.1.71: refine UI website checks and hard delete"
git push
cat VERSION
```

After deployment, hard-refresh the browser. New/changed website image URLs can be indexed later with `docker compose exec -T php php /var/www/html/sales-posts/scripts/index_duplicate_images.php --website --limit=200`.


## v0.1.72 — Facebook share canonical ID + review-status save fix

- Fixes production Save failures caused by legacy `cdsp_sales_posts.admin_review_status` schemas that still reject `NULL`.
- The v0.1.72 migration safely widens the legacy review enum, maps `approved -> good`, `rejected -> bad`, converts `pending -> NULL`, then makes the final `good/bad` column nullable.
- Facebook `/share/...` links are still accepted as submitted URLs. After the provider returns the real numeric Marketplace post/listing ID, the verified record now uses `https://www.facebook.com/marketplace/item/{ID}` as its canonical URL.
- Duplicate detection therefore checks the provider-resolved Facebook `external_post_id`, not the share token. Different share links that resolve to the same Marketplace ID are treated as the same post.
- The originally submitted share URL remains stored separately in `submitted_url` for audit/history.

### SSH upgrade for v0.1.72

Upload `sales-posts-v0.1.72-share-id-save-fix.zip` to `/opt/coolerdepot/www/sales-posts/`, extract it into `/opt/coolerdepot/www`, then run `scripts/migrate_v0_1_72.php` inside the PHP container before testing Save again.

## v0.1.73 — Sales chart tooltip overflow fix

- Keeps the Sales activity-chart hover tooltip in the document body instead of inside the clipped chart panel.
- The tooltip remains fixed to viewport coordinates, so bars near the left/right/top panel edges can show the complete hover card without being cut off by the chart boundary.
- No chart data, range/filter behavior, database schema, duplicate logic, provider logic, or post workflow was changed.

### SSH upgrade for v0.1.73

Upload `sales-posts-v0.1.73-chart-tooltip-overflow-fix.zip` to `/opt/coolerdepot/www/sales-posts/`, back up the current app, then extract it into `/opt/coolerdepot/www`. This release has no database migration.



## v0.1.74 — Sales chart pointer-follow tooltip

- Replaces the chart tooltip's fixed day/bar anchor with true pointer-follow positioning.
- The tooltip continuously tracks the mouse with a small offset while the pointer moves within a chart day.
- Near the viewport right/bottom edge, the tooltip automatically flips to the opposite side of the pointer and remains inside the viewport.
- Moving across Good/Issues/Unreviewed segments updates the focused segment line without freezing the tooltip position.
- Leaving the chart hides the tooltip. No chart data, filters, database schema, post workflow, duplicate logic, or provider behavior changed.

### SSH upgrade for v0.1.74

Upload `sales-posts-v0.1.74-chart-pointer-tooltip.zip` to `/opt/coolerdepot/www/sales-posts/`, back up the current app, then extract it into `/opt/coolerdepot/www`. This release has no database migration.


## v0.1.75 — instant responsive chart tooltip + compact Custom preset

- Desktop chart tooltip appears immediately on pointer entry and follows the pointer with viewport collision handling.
- Touch/pen taps pin a day tooltip; tapping another day switches it, and tapping outside closes it.
- Removed the competing second tooltip controller from the dedicated dashboard module.
- Renamed `Custom Range` to `Custom` and reduced the preset switch width on desktop and narrow screens.
- No database migration is required.


## v0.1.76 — 3-second desktop tooltip hover + matching Admin title

- Desktop Sales Activity chart tooltips wait for a continuous 3-second mouse hover before appearing.
- After appearing, the tooltip continues to follow the mouse and stays viewport-aware.
- Touch/pen remains responsive: tap a day to show/pin the tooltip; no 3-second long-press is required.
- Admin dashboard title now matches the Sales dashboard title (`My Sales Activity`) in all supported UI languages.


## v0.1.77 — deletion request completion state

- Fixes the Sales post detail deletion-request form staying on `Sending…` after the server already accepted the request.
- On success the request button changes to `Deletion requested`, the send button changes to `Sent`, and the current dashboard reloads after a short confirmation delay so all visible post cards reflect the pending request.
- Failed requests restore the send control and keep the existing error message behavior.
- No database migration is required.

## v0.1.79 — information center, Admin Sales activity, tooltip reliability

- Moves pending Sales deletion requests out of the standalone Admin dashboard panel and into a compact Information Center menu in the Admin header.
- Deletion-request post titles open the original Marketplace URL directly; approve/reject actions remain available in the same notification item.
- Adds a Sales-style Posting Activity chart to each Sales person expanded view in Admin, using that Sales user's daily target and the current Admin date range. Channel filters update both the chart and visible post grid.
- Fixes the 3-second desktop chart tooltip by using stable mouse enter/leave timing instead of pointer transitions through chart child elements. Once visible it follows the mouse; touch/pen remains tap-based.
- After a Sales deletion request succeeds, the reason form collapses immediately and the post detail action becomes `Deletion requested ✓` without leaving the editor open.
- No database migration is required.



## v0.1.80 — clearer notifications, live reports, Sales motion polish

- Information Center deletion requests are now a readable accordion list. Multiple requests stay separated; clicking one opens its detail card, where Admin can open the original post, approve the permanent delete, or reject the request.
- Approve/reject can complete by AJAX and the handled notification animates out while the pending count updates.
- Report controls are aligned on one baseline and use the same 1 Day / 3 Days / Weekly / Monthly / Custom range model. Changing range, From/To, or Sales automatically refreshes the result panel; Run still forces an immediate refresh.
- Download CSV always follows the currently refreshed report scope, including All Sales.
- Sales post detail, Submit Post, image lightbox, and delete-request editor use small jQuery fade/slide transitions while respecting reduced-motion preferences.
- No database migration is required.

## v0.1.81 — report toolbar placement cleanup

- Moves the shared report range / From / To / Sales / Run controls into the report result header where the old dynamic `Monthly Range` / `Weekly Range` label used to appear.
- Removes the dynamic range title and removes the duplicated date-range text below the `Sales Report` page title.
- Keeps the current Sales label as the report subject while removing the redundant date line beneath it.
- Aligns `Download CSV` with the report control bar on desktop and stacks it cleanly on narrow screens.
- Live report refresh now updates only the result table, selected Sales label, and download URL so the moved toolbar remains interactive after every AJAX refresh.
- No database migration is required.


## v0.1.84

- Admin sales progress target/period labels no longer inherit a second nested border around `/day` or `day(s)`.
- Management Reports use an exact Sales + work_date Daily Review mapping and defensively select only the latest row for that exact pair on legacy databases. Ratings are never carried from one date to another.
- Download CSV button typography is reduced to match the report toolbar.
- No database migration. VERSION/footer source is 0.1.84.


## v0.1.85

- Renamed the Admin navigation entry to Dashboard while preserving the existing admin dashboard route.
- Moved the Admin Dashboard date-range controls directly above Posting Progress and made the range bar sticky for long-page review workflows.
- Moved the Sales/Post summary onto the Posting Progress heading row and removed the oversized vertical gap.
- Tightened the Admin top navigation spacing and control sizing to match the compact Sales navigation.
- No database migration. VERSION/footer source is 0.1.85.


### v0.1.86
- Separates Post Review metrics from Person Review in Management Reports.
- Adds image attachments to Dashboard Person Reviews with history association.

### v0.1.88

- Person Review save history now supports audit-style **Mark as deleted** plus a **Deleted** filter, matching the Post Review history pattern without physically removing audit rows.
- Person Review attachments now use permanent deletion, the same behavior as Post Review attachments. Legacy v0.1.86 Person Review attachment tombstones are cleaned by the v0.1.87 migration when their files can be safely removed.
- Admin navigation continues to use the universal `Views/layout/header.php` and `Views/layout/footer.php`; the Dashboard-specific JavaScript override that renamed `Dashboard` back to `Admin` was removed.
- No live-sync behavior was changed in this release: Admin still checks for newly added Sales posts every 5 seconds and shows a refresh notice; Sales post-review status still refreshes when the Sales view reloads or its AJAX range is reloaded.


## v0.1.88
- Keeps the sticky Admin date toolbar compact.
- Separates Post Review from Sales Review / Sales Rating in labels and reports.
- Sales Rating reports use the latest non-deleted Sales Review history for each Sales + date.
- Fixes the Sales Review deleted-history filter rendering.


## v0.1.89

- Treats every exact one-day Admin selection as a day-level Sales Review context, including Custom ranges whose From and To are the same date. This restores the Sales Review button for historical single dates such as 2026-08-28 without conflating it with Post Review.
- Restores natural, full-size range controls while the Admin toolbar is in normal document flow. The toolbar only receives the elevated sticky background/shadow after it actually reaches the viewport edge.
- Removes the always-on desktop hard height cap that made the non-sticky toolbar look detached from Posting Progress.
- No database migration. VERSION/footer source is 0.1.89.
