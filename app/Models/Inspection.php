<?php
/**
 * File / 文件：app/Models/Inspection.php
 * EN: Database model and query layer for this domain.
 * 中文：该文件负责此业务域的数据模型与数据库查询。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
namespace App\Models;
use App\Core\Database;
class Inspection {
    /**
     * EN: Creates or persists the `create` operation (create).
     * 中文：创建或持久化 `create`（create）操作。
     */
    public static function create(array $d):string{
        global $config;$t=bin2hex(random_bytes(32));
        $s=Database::connection()->prepare("INSERT INTO cdsp_post_inspections
        (token,sales_user_id,platform,submitted_url,resolved_url,canonical_url,external_post_id,title,description,published_at,published_date,fetched_at,verification_status,failure_code,failure_message,raw_meta_json,created_at,expires_at)
        VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),DATE_ADD(NOW(),INTERVAL ? MINUTE))");
        $s->execute([$t,$d['sales_user_id'],$d['platform'],$d['submitted_url'],$d['resolved_url']??null,$d['canonical_url']??null,$d['external_post_id']??null,$d['title']??null,$d['description']??null,$d['published_at']??null,$d['published_date']??null,$d['fetched_at']??date('Y-m-d H:i:s'),$d['verification_status'],$d['failure_code']??null,$d['failure_message']??null,json_encode($d['raw_meta']??[],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),(int)$config['app']['inspection_ttl_minutes']]);
        return$t;
    }
    /**
     * EN: Implements the application operation `verified` (verified).
     * 中文：实现应用操作 `verified`（verified）。
     */
    public static function verified(string $token,int $uid,bool $lock=false):?array{
        $s=Database::connection()->prepare("SELECT * FROM cdsp_post_inspections WHERE token=? AND sales_user_id=? AND verification_status='verified' AND consumed_at IS NULL AND expires_at>=NOW() LIMIT 1".($lock?' FOR UPDATE':''));
        $s->execute([$token,$uid]);return$s->fetch()?:null;
    }
    /**
     * EN: Implements the application operation `consume` (consume).
     * 中文：实现应用操作 `consume`（consume）。
     */
    public static function consume(int $id):void{$s=Database::connection()->prepare("UPDATE cdsp_post_inspections SET consumed_at=NOW() WHERE id=?");$s->execute([$id]);}
}
