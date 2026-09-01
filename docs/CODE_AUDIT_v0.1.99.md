# Code audit — v0.1.99

This audit was performed against the full v0.1.98 server backup, not an earlier patch archive.

## Findings fixed in v0.1.99

### 1. Error logging was fragmented

Before this audit, logging was split between PHP `error_log()`, router exception catches and provider job rows. Many handled exceptions, PHP runtime warnings/fatal shutdown errors and browser-side failures could disappear from the central operational picture.

v0.1.99 adds one JSONL diagnostics logger with correlation ids, redaction, PHP runtime handlers, HTTP error recording and browser reporting. Browser diagnostics now carry the originating page request id, and active log files have a size cap/rotation path in addition to daily retention so an error storm cannot grow one file without bound. See `docs/DIAGNOSTICS.md`.

### 2. Release packaging could include production `.env`

The full backup contained a production `.env`. `scripts/package_release.sh` previously excluded Git, ZIPs, uploads and CSVs, but not `.env` files. The release packager now keeps `.env.example` while excluding `.env`, `.env.*`, runtime logs, uploads and nested backups. It also validates the finished archive and checks production credential values for accidental hard-coding without printing those values.

### 3. Routing source was unnecessarily compressed

`index.php` was a single dense line containing all routes. It has been reformatted into grouped Auth / Sales / Admin / Settings / Attachment sections without changing existing routes, and the new browser-diagnostics route is listed explicitly.

### 4. Tooltip ownership comment/global was stale

The Sales dashboard now owns its own tooltip controller, while `app.js` still contained an old global marker/comment from the earlier ownership model. The stale global was removed and the comment now documents the actual split so future changes do not accidentally reintroduce two competing Sales tooltip controllers.

### 5. Authentication maintenance code was compressed

`AuthController` and `ExternalAuthService` contained security-sensitive behavior in dense one-line code. They are reformatted without changing the handoff contract, and comments now explain replay protection, credential handling and the local-login maintenance boundary. Disabled/failed local-login attempts are centrally logged without recording passwords.

### 6. Backend comments were uneven

Recent UI patches had detailed intent comments, but critical bootstrap/database/diagnostics behavior had little documentation. v0.1.99 adds comments/docblocks around error-handling, redaction, request correlation, browser diagnostics and release-security behavior. Comments are intentionally focused on *why* non-obvious code exists rather than narrating obvious assignments.

## CSS / JavaScript conflict audit

`public/assets/app.css` is large and contains many repeated selectors accumulated across historical UI releases. A raw duplicate-selector count is not a safe cleanup plan because media queries, state selectors and later canonical overrides intentionally rely on CSS cascade order.

For this release, provably stale JavaScript ownership state and the dead legacy `.admin-dashboard-range-bar.is-stuck` / `.range-is-stuck` CSS branches were removed. The remaining repeated selectors were **not mechanically deduplicated**, because doing so without browser regression snapshots would risk reintroducing the exact layout regressions that the later canonical blocks fixed.

Recommended future structural cleanup: split the stylesheet into base/navigation, Admin dashboard, Sales dashboard, review modal and settings modules; then remove historical blocks only after browser regression coverage exists for desktop/mobile and all four UI languages.

## Validation notes

Run before deployment:

```bash
php -l <all PHP files>
node --check public/assets/diagnostics.js
node --check public/assets/app.js
node --check public/assets/sales-dashboard.js
bash -n scripts/package_release.sh
```

`tests/duplicate_comparison.php` requires CLI PDO SQLite. If that extension is absent, the test now reports an explicit `SKIP` and exits successfully instead of producing a misleading fatal error.
