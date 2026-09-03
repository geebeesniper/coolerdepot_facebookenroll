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
     * EN: Retrieve an inspection that is allowed to be saved as a Sales post.
     * 中文：读取允许保存为 Sales 帖子的 inspection 记录。
     *
     * Fully verified inspections are savable. Manual marketplace inspections are
     * savable only after the manual fields were validated and failure_code was cleared.
     * 完整验证通过的 Inspection 可以保存；Marketplace 手动 Inspection 只有在手动字段
     * 已验证并清除 failure_code 后才允许保存。
     *
     * @param string $token Inspection token. / Inspection Token。
     * @param int $uid Sales user ID. / Sales 用户 ID。
     * @param bool $lock Whether to lock the selected row. / 是否锁定选中的记录。
     *
     * @return ?array Savable inspection row or null. / 可保存的 inspection 记录，不存在时返回 null。
     */
    public static function savable(string $token,int $uid,bool $lock=false):?array{
        $s=Database::connection()->prepare(
            "SELECT * FROM cdsp_post_inspections
             WHERE token=?
               AND sales_user_id=?
               AND verification_status IN ('verified','manual_pending')
               AND failure_code IS NULL
               AND consumed_at IS NULL
               AND expires_at>=NOW()
             LIMIT 1".($lock?' FOR UPDATE':'')
        );
        $s->execute([$token,$uid]);return$s->fetch()?:null;
    }

    /**
     * EN: Retrieve an active Craigslist or OfferUp inspection waiting for manual confirmation.
     * 中文：读取等待 Sales 手动确认的 Craigslist 或 OfferUp Inspection。
     *
     * @param string $token Inspection token. / Inspection Token。
     * @param int $uid Sales user ID. / Sales 用户 ID。
     * @param bool $lock Whether to lock the row. / 是否锁定记录。
     *
     * @return ?array Manual-pending row or null. / 待手动确认记录或 null。
     */
    public static function manualCandidate(string $token,int $uid,bool $lock=false):?array{
        $s=Database::connection()->prepare(
            "SELECT * FROM cdsp_post_inspections
             WHERE token=?
               AND sales_user_id=?
               AND verification_status='manual_pending'
               AND (
                    (platform='craigslist' AND failure_code='CRAIGSLIST_REMOTE_BLOCKED')
                    OR
                    (platform='offerup' AND failure_code='OFFERUP_REMOTE_BLOCKED')
               )
               AND consumed_at IS NULL
               AND expires_at>=NOW()
             LIMIT 1".($lock?' FOR UPDATE':'')
        );
        $s->execute([$token,$uid]);return$s->fetch()?:null;
    }

    /**
     * EN: V0.2.13 compatibility lookup restricted to Craigslist.
     * 中文：V0.2.13 兼容查询，仅返回 Craigslist 待手动确认记录。
     *
     * @param string $token Inspection token. / Inspection Token。
     * @param int $uid Sales user ID. / Sales 用户 ID。
     * @param bool $lock Whether to lock the row. / 是否锁定记录。
     *
     * @return ?array Craigslist manual-pending row or null. / Craigslist 待手动确认记录或 null。
     */
    public static function craigslistManualCandidate(string $token,int $uid,bool $lock=false):?array{
        $row=self::manualCandidate($token,$uid,$lock);
        return $row && strtolower((string)($row['platform']??''))==='craigslist'?$row:null;
    }

    /**
     * EN: Replace a manual-required marketplace inspection with validated manual details.
     * 中文：使用已验证的手动详情更新 Marketplace 待手动确认 Inspection。
     *
     * @param int $id Inspection row ID. / Inspection 记录 ID。
     * @param array $d Validated inspection data. / 已验证的 Inspection 数据。
     *
     * @return void No value is returned. / 无返回值。
     */
    public static function updateManual(int $id,array $d):void{
        $s=Database::connection()->prepare(
            "UPDATE cdsp_post_inspections
             SET resolved_url=?,
                 canonical_url=?,
                 external_post_id=?,
                 title=?,
                 description=?,
                 published_at=?,
                 published_date=?,
                 fetched_at=?,
                 verification_status='manual_pending',
                 failure_code=NULL,
                 failure_message=NULL,
                 raw_meta_json=?
             WHERE id=?"
        );
        $s->execute([
            $d['resolved_url']??null,
            $d['canonical_url']??null,
            $d['external_post_id']??null,
            $d['title']??null,
            $d['description']??null,
            $d['published_at']??null,
            $d['published_date']??null,
            $d['fetched_at']??date('Y-m-d H:i:s'),
            json_encode($d['raw_meta']??[],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
            $id,
        ]);
    }

    /**
     * EN: V0.2.13 compatibility wrapper for the former Craigslist-specific update method.
     * 中文：V0.2.13 原 Craigslist 专用更新方法的兼容包装。
     *
     * @param int $id Inspection row ID. / Inspection 记录 ID。
     * @param array $d Validated inspection data. / 已验证的 Inspection 数据。
     *
     * @return void No value is returned. / 无返回值。
     */
    public static function updateCraigslistManual(int $id,array $d):void{
        self::updateManual($id,$d);
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
