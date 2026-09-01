# External API Integration / 外部 API 集成

Version: **v0.2.07**

Sales Post Tracker exposes three authentication entry modes that converge on the same `cdsp_users` identity and server-side `admin` / `sales` RBAC.

Sales Post Tracker 提供三种认证入口，最终都映射到同一个 `cdsp_users` 用户身份，并统一执行服务端 `admin` / `sales` RBAC。

1. Browser SSO: `GET|POST /auth/handoff`
2. REST: `/api/v1/...`
3. GraphQL: `POST /graphql`

## Shared signed identity payload / 共用签名身份载荷

The parent system must already know the authenticated user's identity and role. It signs these fields with `AUTH_HANDOFF_SECRET`:

父系统必须已经确认当前用户身份与角色，然后使用 `AUTH_HANDOFF_SECRET` 对以下字段签名：

- `uid`: stable external user ID / 外部系统稳定用户 ID
- `sales_id`: numeric Sales ID for `sales`; empty for `admin`
- `name`: display name / 显示名称
- `role`: `admin` or `sales`
- `ts`: current Unix timestamp / 当前 Unix 时间戳
- `nonce`: random 8-128 character one-time value / 一次性随机值
- `sig`: lowercase HMAC-SHA256 hex signature / 小写 HMAC-SHA256 十六进制签名

Canonical string / 规范签名字符串：

```text
uid\nsales_id\nname\nrole\nts\nnonce
```

The signature is:

```text
hex(HMAC-SHA256(canonical_string, AUTH_HANDOFF_SECRET))
```

The signed payload expires according to `AUTH_HANDOFF_MAX_AGE` (default 120 seconds), and each nonce may be accepted only once.

签名载荷按 `AUTH_HANDOFF_MAX_AGE` 过期（默认 120 秒），每个 nonce 只能成功使用一次。

## REST API v1

### Health

```http
GET /api/v1/health
```

No authentication required. It returns application/API version and a non-sensitive DB readiness result.

### Exchange signed identity for Bearer token

```http
POST /api/v1/auth/exchange
Content-Type: application/json
```

```json
{
  "uid": "employee-84521",
  "sales_id": "100023",
  "name": "John Smith",
  "role": "sales",
  "ts": 1788290000,
  "nonce": "a-random-one-time-value",
  "sig": "hmac-sha256-hex"
}
```

Success:

```json
{
  "ok": true,
  "access_token": "cdsp_at_...",
  "token_type": "Bearer",
  "expires_in": 3600,
  "expires_at": "2026-09-01T20:00:00+00:00",
  "user": {
    "id": 12,
    "externalUserId": "employee-84521",
    "salesId": 100023,
    "displayName": "John Smith",
    "role": "sales",
    "dailyPostTarget": 10,
    "authSource": "coolerdepot"
  }
}
```

Use the returned token on later requests:

```http
Authorization: Bearer cdsp_at_...
```

Bearer tokens are short-lived (`API_TOKEN_HOURS`, default 1 hour). Only the SHA-256 hash is stored in `cdsp_api_tokens`.

Bearer Token 为短期 Token（`API_TOKEN_HOURS`，默认 1 小时），数据库 `cdsp_api_tokens` 仅保存 SHA-256 哈希。

### Current user

```http
GET /api/v1/auth/me
Authorization: Bearer <token>
```

Any authenticated `admin` or `sales` user.

### Logout/revoke current API token

```http
POST /api/v1/auth/logout
Authorization: Bearer <token>
```

Only the current API token is revoked. Browser SSO sessions and other API tokens are unaffected.

### Admin-only user directory

```http
GET /api/v1/admin/users
Authorization: Bearer <admin-token>
```

A Sales token receives `403 forbidden_role`.

### Sales-only profile

```http
GET /api/v1/sales/profile
Authorization: Bearer <sales-token>
```

An Admin token receives `403 forbidden_role`.

## GraphQL API

Endpoint:

```http
POST /graphql
Content-Type: application/json
Authorization: Bearer <token>   # required except authExchange/apiVersion/appVersion
```

The canonical SDL is included in `docs/schema.graphql`.

### GraphQL signed exchange

```graphql
mutation AuthExchange($input: AuthHandoffInput!) {
  authExchange(input: $input) {
    accessToken
    tokenType
    expiresIn
    expiresAt
    user {
      id
      externalUserId
      salesId
      displayName
      role
    }
  }
}
```

Variables:

```json
{
  "input": {
    "uid": "employee-84521",
    "salesId": 100023,
    "name": "John Smith",
    "role": "sales",
    "ts": 1788290000,
    "nonce": "a-new-one-time-value",
    "sig": "hmac-sha256-hex"
  }
}
```

### Current user

```graphql
query {
  me {
    id
    externalUserId
    salesId
    displayName
    role
  }
}
```

### Admin-only query

```graphql
query {
  adminUsers {
    id
    displayName
    role
    salesId
  }
}
```

### Sales-only query

```graphql
query {
  salesProfile {
    id
    displayName
    salesId
    dailyPostTarget
  }
}
```

### Revoke current GraphQL Bearer token

```graphql
mutation {
  logout
}
```

### GraphQL implementation scope

v0.2.07 intentionally exposes a small explicit GraphQL schema for authentication and RBAC integration. It supports query/mutation operations, variables, aliases, nested selections, scalar/input-object/list values, and `__typename`. Fragments, directives, subscriptions, and full schema introspection are not enabled in this release. The authoritative schema is shipped as `docs/schema.graphql`.

v0.2.07 的 GraphQL Schema 有意保持小而明确，用于认证与 RBAC 集成。支持 Query/Mutation、Variables、Alias、嵌套 Selection、Scalar/Input Object/List 以及 `__typename`；本版本不开放 Fragment、Directive、Subscription 与完整 Schema Introspection，权威 Schema 随包提供于 `docs/schema.graphql`。


## v0.2.07 request/security limits / v0.2.07 请求与安全限制

All REST/GraphQL endpoints that accept a request body require `Content-Type: application/json` (or a `+json` media type). This intentionally forces browser cross-origin JSON calls through CORS preflight. REST/GraphQL requests are stateless and do not start the browser PHP session.

所有接收 Request Body 的 REST/GraphQL 端点必须使用 `Content-Type: application/json`（或 `+json` Media Type），以确保浏览器跨域 JSON 请求经过 CORS Preflight。REST/GraphQL 请求保持无状态，不启动浏览器 PHP Session。

GraphQL applies bounded parser/complexity limits before resolver execution:

- `GRAPHQL_MAX_DEPTH=8`
- `GRAPHQL_MAX_FIELDS=50`
- `GRAPHQL_MAX_TOKENS=2000`
- `GRAPHQL_MAX_OPERATIONS=5`

Signed identity fields are length/format checked before database work. Embedded control/newline characters are rejected from canonical identity fields, `sig` must be exactly 64 lowercase hexadecimal HMAC-SHA256 characters, and Sales IDs are range checked.

签名身份字段会在数据库操作前执行长度与格式校验；Canonical Identity 字段禁止嵌入控制字符/换行，`sig` 必须是 64 位小写十六进制 HMAC-SHA256，Sales ID 会执行数值范围检查。

The release includes `scripts/test_api_live_v0_2_06.php`, which performs live REST/GraphQL, RBAC, replay, token-hash, logout/revocation, GraphQL complexity, CORS, and sensitive-path checks against the deployed server.

## CORS

By default `API_ALLOWED_ORIGINS` is empty. REST/GraphQL therefore work for same-origin and server-to-server calls without enabling browser cross-origin access.

默认 `API_ALLOWED_ORIGINS` 为空，因此 REST/GraphQL 可用于同源和服务器到服务器调用，但不会主动允许浏览器跨域。

To allow browser origins, configure exact comma-separated origins:

```text
API_ALLOWED_ORIGINS=https://portal.example.com,https://manager.example.com
```

Do not use `*` unless the security implications are understood. Bearer tokens must never be placed in URLs.

## Error behavior / 错误行为

REST uses a stable JSON envelope:

```json
{
  "ok": false,
  "error": "forbidden_role",
  "message": "This API operation is not available to your role.",
  "request_id": "..."
}
```

GraphQL uses `data` plus `errors[].extensions.code/httpStatus`.

REST 与 GraphQL 错误都会进入中央诊断日志，但代码不会记录原始 Bearer Token、`AUTH_HANDOFF_SECRET` 或签名密钥。
