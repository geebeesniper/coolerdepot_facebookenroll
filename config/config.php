<?php
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
 'security'=>[
  'session_name'=>'coolerdepot_sales_posts','cookie_domain'=>getenv('SESSION_COOKIE_DOMAIN') ?: '',
  'allowed_upload_mimes'=>['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'],
 ],
];
