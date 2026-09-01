<?php
/**
 * File / 文件：config/config.php
 * EN: Application configuration/bootstrap file for config.
 * 中文：用于 config 的应用配置/启动文件。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
$appVersionFile = dirname(__DIR__) . '/VERSION';
$appVersion = is_file($appVersionFile) ? trim((string)file_get_contents($appVersionFile)) : 'dev';
$basePath = getenv('APP_BASE_PATH');
if ($basePath === false) $basePath = '/sales-posts';
return [
 'app'=>[
  'name'=>'CoolerDepot Sales Post Tracker',
  'version'=>$appVersion,
  'base_path'=>rtrim($basePath,'/'),
  'host'=>getenv('APP_HOST') ?: '',
  'enforce_host'=>getenv('ENFORCE_APP_HOST') === '1',
  'timezone'=>getenv('APP_TIMEZONE') ?: 'America/Los_Angeles',
  'inspection_ttl_minutes'=>15,
  'daily_posts_initial_days'=>3,
  'daily_posts_load_days'=>3,
  'upload_max_bytes'=>8*1024*1024,
 ],
 'db'=>[
  'host'=>getenv('DB_HOST') ?: 'db','port'=>getenv('DB_PORT') ?: '3306','name'=>getenv('DB_NAME') ?: 'app',
  'user'=>getenv('DB_USER') ?: 'app','pass'=>getenv('DB_PASSWORD') ?: 'app','charset'=>'utf8mb4',
 ],
 'auth'=>[
  'handoff_secret'=>getenv('AUTH_HANDOFF_SECRET') ?: '',
  'handoff_max_age_seconds'=>(int)(getenv('AUTH_HANDOFF_MAX_AGE') ?: 120),
  'session_hours'=>(int)(getenv('AUTH_SESSION_HOURS') ?: 12),
  'allow_local_login'=>getenv('ALLOW_LOCAL_LOGIN') === '1',
 ],
 'api'=>[
  'token_hours'=>max(1,min(24,(int)(getenv('API_TOKEN_HOURS') ?: 1))),
  'max_body_bytes'=>max(1024,(int)(getenv('API_MAX_BODY_BYTES') ?: 1048576)),
  'allowed_origins'=>array_values(array_filter(array_map('trim',explode(',',getenv('API_ALLOWED_ORIGINS') ?: '')))),
  'graphql_max_depth'=>max(2,min(20,(int)(getenv('GRAPHQL_MAX_DEPTH') ?: 8))),
  'graphql_max_fields'=>max(5,min(200,(int)(getenv('GRAPHQL_MAX_FIELDS') ?: 50))),
  'graphql_max_tokens'=>max(100,min(10000,(int)(getenv('GRAPHQL_MAX_TOKENS') ?: 2000))),
  'graphql_max_operations'=>max(1,min(20,(int)(getenv('GRAPHQL_MAX_OPERATIONS') ?: 5))),
 ],
 'security'=>[
  'session_name'=>'coolerdepot_sales_posts','cookie_domain'=>getenv('SESSION_COOKIE_DOMAIN') ?: '',
  'allowed_upload_mimes'=>['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'],
 ],
 'logging'=>[
  // JSONL application diagnostics. Relative paths are resolved from project root.
  'path'=>getenv('LOG_PATH') ?: 'storage/logs',
  'level'=>strtolower(getenv('LOG_LEVEL') ?: 'warning'),
  'retention_days'=>max(1,(int)(getenv('LOG_RETENTION_DAYS') ?: 30)),
  'max_bytes'=>max(1048576,(int)(getenv('LOG_MAX_BYTES') ?: 26214400)),
 ],
];
