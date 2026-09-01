# Production Transfer / 正式环境迁移

## 1. Code package / 代码包

EN: The production ZIP intentionally contains no `.env`, runtime uploads, logs, demo installer, database dump, active browser session, or active API Bearer token. Secrets and live database data must not be committed into the normal code archive.

中文：正式代码 ZIP 故意不包含 `.env`、运行时上传文件、日志、Demo 安装器、数据库 Dump、浏览器登录态或有效 API Bearer Token。真实密钥和正式数据不能混入普通代码发布包。

## 2. Environment variables / 环境变量

The application reads configuration with `getenv()`. `.env.example` is a template; the PHP runtime must actually receive the values through Docker Compose, PHP-FPM/Apache configuration, or the hosting platform's secret/environment facility.

应用通过 `getenv()` 读取配置。`.env.example` 只是模板，Docker Compose、PHP-FPM/Apache 或 Hosting Secret/Environment 功能必须把变量真正注入 PHP Runtime。

Required database/auth values include:

```text
DB_HOST
DB_PORT
DB_NAME
DB_USER
DB_PASSWORD
AUTH_HANDOFF_SECRET
```

v0.2.06 API values:

```text
API_TOKEN_HOURS=1
API_MAX_BODY_BYTES=1048576
API_ALLOWED_ORIGINS=
```

## 3. Clean current database Demo rows / 清理当前数据库 Demo 数据

Preview first / 先预览：

```bash
cd /opt/coolerdepot
docker compose exec -T php php /var/www/html/sales-posts/scripts/cleanup_demo_database.php
```

If the list is correct, apply / 确认列表正确后执行：

```bash
cd /opt/coolerdepot
docker compose exec -T php php /var/www/html/sales-posts/scripts/cleanup_demo_database.php --apply
```

The script never deletes the `sales_id=100006` user merely because historical Demo data used that account. / 脚本不会因为历史 Demo 曾使用 `sales_id=100006` 就删除该 Sales 用户。

## 4. Upgrade an existing pre-v0.2.05 database / 升级已有旧数据库

Existing databases require the API token table once:

```bash
php scripts/migrate_v0_2_05_api.php
```

Fresh deployments that import the v0.2.06 schema/token-only SQL do not need this migration separately.

已有旧数据库需执行一次 API Token 表迁移；全新部署直接导入 v0.2.06 Schema/Token-only SQL 时无需额外执行。

## 5. Export production database / 导出正式数据库

```bash
cd /opt/coolerdepot
docker compose exec -T php php \
  /var/www/html/sales-posts/scripts/export_transfer_database.php \
  --output=/var/www/html/sales-posts/storage/transfer/cdsp-production-clean.sql
```

The exporter excludes known Demo posts and deliberately exports **zero rows** from `cdsp_auth_sessions`, `cdsp_auth_handoffs`, and `cdsp_api_tokens`. Runtime authentication credentials must not remain active on a different server.

导出器会排除已知 Demo Post，并且故意不导出 `cdsp_auth_sessions`、`cdsp_auth_handoffs`、`cdsp_api_tokens` 中的运行时凭据；旧服务器登录态不能在新服务器继续有效。

## 6. Provider-token encryption / Provider Token 加密

`cdsp_provider_profiles.token_encrypted` and secret rows in `cdsp_settings` use a key derived from `AUTH_HANDOFF_SECRET`. The destination must use the **exact same** `AUTH_HANDOFF_SECRET` if migrated Provider tokens should continue working.

`cdsp_provider_profiles.token_encrypted` 与 `cdsp_settings` secret 记录使用由 `AUTH_HANDOFF_SECRET` 派生的密钥。如果要继续使用迁移后的 Provider Token，新服务器必须配置**完全相同**的 `AUTH_HANDOFF_SECRET`。

Send the secret separately through a secure channel. Never place it in the ZIP, SQL, Git, documentation, or browser JavaScript.

Secret 必须通过独立安全渠道发送，严禁写入 ZIP、SQL、Git、文档或浏览器 JavaScript。

## 7. Import on destination / 目标服务器导入

For a new deployment, use the supplied v0.2.06 token-only SQL or have the customer DBA create/import the schema. The application itself does not require MySQL root.

全新部署使用 v0.2.06 token-only SQL，或由客户 DBA 创建/导入。应用本身不需要 MySQL root。

Example:

```bash
mysql -h DB_HOST -P 3306 -u DB_USER -p DB_NAME < sales-posts-v0.2.06-production-token-only.sql
```

## 8. External user authorization / 外部用户授权

Three entry modes share one user table and RBAC:

- Browser SSO: `GET|POST /auth/handoff`
- REST: `POST /api/v1/auth/exchange` then `Authorization: Bearer ...`
- GraphQL: `POST /graphql`, using `authExchange` then the same Bearer header

See `docs/API.md`, `docs/openapi-v1.yaml`, and `docs/schema.graphql`.

三种入口共用 `cdsp_users` 与服务端 Admin/Sales RBAC，详细协议见随包 API 文档。

## 9. Files handed to the deployer / 交给部署人员的文件

- `sales-posts-v0.2.06-responsive-mobile-rest-graphql-production-transfer.zip`
- `sales-posts-v0.2.06-production-token-only.sql` (for a clean new deployment)
- Manager deployment/integration guide
- Current `AUTH_HANDOFF_SECRET`, sent separately and securely
- Customer-provided destination DB connection parameters
