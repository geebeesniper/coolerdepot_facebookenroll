<?php
/**
 * File / 文件：scripts/migrate_external_auth.php
 * EN: Operations/deployment/diagnostics script owned by this project.
 * 中文：该文件是本项目自有的运维、部署或诊断脚本。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
$config=require dirname(__DIR__).'/config/bootstrap.php';use App\Core\Database;$pdo=Database::connection();$db=$config['db']['name'];
/**
 * EN: Implements the application operation `col` (col).
 * 中文：实现应用操作 `col`（col）。
 */
function col(PDO$p,string$db,string$t,string$c):bool{$s=$p->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?");$s->execute([$db,$t,$c]);return(int)$s->fetchColumn()>0;}
/**
 * EN: Implements the application operation `idx` (idx).
 * 中文：实现应用操作 `idx`（idx）。
 */
function idx(PDO$p,string$db,string$t,string$i):bool{$s=$p->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME=?");$s->execute([$db,$t,$i]);return(int)$s->fetchColumn()>0;}
if(!col($pdo,$db,'cdsp_users','external_user_id'))$pdo->exec("ALTER TABLE cdsp_users ADD COLUMN external_user_id VARCHAR(191) NULL AFTER sales_id");
$pdo->exec("ALTER TABLE cdsp_users MODIFY COLUMN password_hash VARCHAR(255) NULL");
if(!col($pdo,$db,'cdsp_users','auth_source'))$pdo->exec("ALTER TABLE cdsp_users ADD COLUMN auth_source VARCHAR(50) NOT NULL DEFAULT 'local' AFTER active");
if(!col($pdo,$db,'cdsp_users','last_handoff_at'))$pdo->exec("ALTER TABLE cdsp_users ADD COLUMN last_handoff_at DATETIME NULL AFTER auth_source");
if(!idx($pdo,$db,'cdsp_users','uq_users_external_user_id'))$pdo->exec("ALTER TABLE cdsp_users ADD UNIQUE KEY uq_users_external_user_id (external_user_id)");
$pdo->exec("CREATE TABLE IF NOT EXISTS cdsp_auth_handoffs(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,nonce VARCHAR(128) NOT NULL,user_id INT UNSIGNED NULL,external_user_id VARCHAR(191) NOT NULL,sales_id INT UNSIGNED NULL,display_name VARCHAR(150) NOT NULL,role ENUM('sales','admin') NOT NULL,source_ip VARCHAR(45) NULL,payload_json TEXT NULL,accepted_at DATETIME NOT NULL,PRIMARY KEY(id),UNIQUE KEY uq_auth_handoff_nonce(nonce),KEY idx_auth_handoff_user(user_id),KEY idx_auth_handoff_external(external_user_id),CONSTRAINT fk_auth_handoff_user FOREIGN KEY(user_id) REFERENCES cdsp_users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS cdsp_auth_sessions(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,user_id INT UNSIGNED NOT NULL,token_hash CHAR(64) NOT NULL,source VARCHAR(50) NOT NULL,ip_address VARCHAR(45) NULL,user_agent VARCHAR(500) NULL,created_at DATETIME NOT NULL,last_seen_at DATETIME NOT NULL,expires_at DATETIME NOT NULL,revoked_at DATETIME NULL,PRIMARY KEY(id),UNIQUE KEY uq_auth_session_token(token_hash),KEY idx_auth_session_user(user_id),KEY idx_auth_session_expiry(expires_at,revoked_at),CONSTRAINT fk_auth_session_user FOREIGN KEY(user_id) REFERENCES cdsp_users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "External-auth/subdomain migration complete.\n";
