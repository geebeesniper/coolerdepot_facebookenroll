<?php
/**
 * File / 文件：app/Models/Inspection.php
 * EN: Defines the Inspection database model and its persistence/query helpers.
 * 中文：定义 Inspection 数据库模型及其持久化与查询辅助逻辑。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Models;
use App\Core\Database;
/**
 * EN: Database model for inspection records, queries, and persistence operations.
 * 中文：负责 inspection 记录、查询及持久化操作的数据库 Model。
 */
class Inspection {
    /**
     * EN: Create or store the create data for inspection in the application database.
     * 中文：创建或保存 inspection 的“create”数据，并访问应用数据库。
     *
     * @param array $d D value used by this operation. / 本操作使用的“d”参数值。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
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
     * EN: Perform the verified data for inspection in the application database.
     * 中文：执行 inspection 的“verified”数据，并访问应用数据库。
     *
     * @param string $token Authentication, inspection, or operation token being processed. / 正在处理的认证、检查或操作 Token。
     * @param int $uid External user identifier supplied by the parent authentication system. / 父级认证系统提供的外部用户 ID。
     * @param bool $lock Lock value used by this operation. / 本操作使用的“lock”参数值。
     *
     * @return ?array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
    public static function verified(string $token,int $uid,bool $lock=false):?array{
        $s=Database::connection()->prepare("SELECT * FROM cdsp_post_inspections WHERE token=? AND sales_user_id=? AND verification_status='verified' AND consumed_at IS NULL AND expires_at>=NOW() LIMIT 1".($lock?' FOR UPDATE':''));
        $s->execute([$token,$uid]);return$s->fetch()?:null;
    }
    /**
     * EN: Update the consume data for inspection in the application database.
     * 中文：更新 inspection 的“consume”数据，并访问应用数据库。
     *
     * @param int $id Identifier of the record record or entity. / record 记录或实体的标识 ID。
     *
     * @return void No value is returned. / 无返回值。
     */
    public static function consume(int $id):void{$s=Database::connection()->prepare("UPDATE cdsp_post_inspections SET consumed_at=NOW() WHERE id=?");$s->execute([$id]);}
}
