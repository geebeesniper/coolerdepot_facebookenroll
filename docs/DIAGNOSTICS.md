# Diagnostics and error logging

The application uses `App\Core\Logger` as the central diagnostics sink.

## Source of truth

Application diagnostics are JSON Lines files in:

```text
storage/logs/app-YYYY-MM-DD.log
```

The file sink is intentionally independent of MySQL. Database outages therefore remain visible instead of failing the log write at the same time as the application.

Each entry includes:

- timestamp and severity;
- channel (`php`, `router`, `database`, `client`, `provider`, `http`, etc.);
- one server request/correlation id;
- the originating page request id on browser-side reports;
- request method/path (query strings are intentionally omitted);
- authenticated user id/role when already resolved;
- event-specific context;
- exception class, origin and bounded stack trace when applicable.

`error` and `critical` entries are also mirrored to PHP/Docker `error_log` with the request id.

## What is captured

The central logger covers:

- uncaught PHP exceptions;
- PHP warnings/notices/deprecations that reach the active PHP error handler;
- fatal/parse/core/compile/recoverable shutdown errors;
- database connection failures;
- router failures and unresolved 404/405 routes;
- HTTP/JSON error responses;
- CSRF failures;
- provider failover exceptions and provider job status-write failures;
- post-inspection verification failures;
- important caught upload/catalog/auth failures that otherwise become user-facing warnings;
- browser `window.onerror` events;
- unhandled Promise rejections;
- jQuery AJAX transport/HTTP failures;
- handled native `fetch()` failures in the Sales dashboard data loader.

Provider-specific `cdsp_fetch_jobs` remains useful for provider job history, but it is supplemental rather than a replacement for the central log.

## Privacy and secrets

The logger redacts context keys containing password/secret/token/authorization/cookie/session/API-key/credential terms. Common token-style values embedded in strings are also masked. Request query strings are never stored by the request-context collector.

Do not add raw request headers, `$_COOKIE`, session tokens, provider credentials, or `.env` contents to log context.

## Browser diagnostics

`public/assets/diagnostics.js` reports same-origin browser failures to:

```text
POST /api/client-log
```

The endpoint is same-origin and CSRF-protected and is limited to 30 reports per session per minute. It can accept failures from the login page before authentication; authenticated user context is attached when available. Repeated identical browser errors are deduplicated client-side for 10 seconds. Browser reports carry the request id from the page that loaded `diagnostics.js`; failed jQuery responses also include the server request id returned in `X-CDSP-Request-ID` when available. This makes a front-end symptom traceable back to the corresponding server-side request without logging URL query strings.
JavaScript exceptions, unhandled Promise rejections and HTTP 5xx browser failures are recorded at `error`; resource/network/HTTP 4xx diagnostics are recorded at `warning` so expected validation failures do not masquerade as server crashes.

## Configuration

`.env.example` includes:

```text
LOG_PATH=storage/logs
LOG_LEVEL=warning
LOG_RETENTION_DAYS=30
LOG_MAX_BYTES=26214400
```

Relative `LOG_PATH` values resolve from the project root. Active logs rotate once they reach the configured byte limit (25 MiB by default), and daily/rotated log files older than the retention period are removed by low-frequency best-effort cleanup. Rotation is intentionally best-effort so a rotation failure never suppresses the original application error.

## Health check / tail

From the PHP runtime:

```bash
php /var/www/html/sales-posts/scripts/diagnostics_status.php --tail=30
```

To verify that the sink is writable:

```bash
php /var/www/html/sales-posts/scripts/diagnostics_status.php --write-test --tail=5
```

The test creates a warning-level event named `diagnostics_write_test`.

## Release packaging

Production `.env`, log files, uploads, nested backup archives and local CSV data are excluded by `scripts/package_release.sh`. `.env.example` and `storage/logs/.gitkeep` remain in releases. The packager runs `scripts/validate_release.sh` after creating the ZIP and, when a production `.env` exists, also checks that credential values were not accidentally hard-coded into another packaged file. Secret values are never printed by the validator.


## Boundary of application logging

The application logger starts as early as `config/bootstrap.php`, but no in-process
logger can record failures that prevent PHP from reaching that bootstrap at all.
Examples include a syntax error in the logger/bootstrap itself, PHP-FPM being down,
Apache failing before PHP is invoked, container/runtime crashes, or host/OS failure.
Those failures remain in the infrastructure logs and should be checked alongside the
application JSONL files:

```bash
cd /opt/coolerdepot
docker compose logs --tail=200 php
docker compose logs --tail=200 apache
```

This is also why release validation runs `php -l` over every PHP file before a ZIP is
accepted: bootstrap-level parse failures must be prevented before deployment rather
than relying on an application logger that cannot start.
