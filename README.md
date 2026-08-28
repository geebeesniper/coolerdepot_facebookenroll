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
- Admin per-post approve/reject, rating, note, and image attachments.
- Admin per-day Sales review, rating, note, and image attachments.
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
