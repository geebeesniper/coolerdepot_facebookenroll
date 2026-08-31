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
- Sales can submit only posts verified as published today.
- Company-wide duplicate canonical URL blocking.
- Company-wide duplicate platform + item/post ID blocking.
- Same Sales + same platform exact normalized title blocking.
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
8. Publication date must equal today's business date.
9. Duplicate rules are checked.
10. A short-lived inspection token is stored.
11. Save becomes enabled only for a verified token.
12. Server checks duplicates again before INSERT.

## Duplicate rules

A post is blocked when any of these match a non-deleted post:

- Canonical URL.
- Platform + external post/item ID.
- Same Sales + same platform + exact normalized title.
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
- Soft deletion via Admin approval.
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

Current release: `v0.1.0`

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
