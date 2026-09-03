CoolerDepot Sales Post Tracker
V0.2.07 -> V0.2.17 Direct Overlay Package

Usage:
  unzip -o V0.2.07-to-V0.2.17.zip -d /opt/coolerdepot/www/sales-posts

This package intentionally does NOT contain:
  .env
  config/config.php

Existing server/database credentials and secrets are preserved.

Database compatibility:
  V0.2.13 introduced verification_status=manual_pending.
  This direct-overlay package performs an idempotent first-request schema check and
  automatically expands the two existing MySQL ENUM columns when required.
  Existing business rows are not deleted or rewritten.

After extraction:
  - VERSION should read 0.2.17
  - Refresh the browser with Ctrl+Shift+R
  - First application request performs the one-time schema compatibility check.

中文：
本包可从 V0.2.07 直接覆盖到 V0.2.17。不会包含或覆盖 .env 与 config/config.php。
V0.2.13 所需的 manual_pending ENUM 会在覆盖后的第一次应用请求中自动检查并按需扩展，
不会删除或重写现有业务数据。
