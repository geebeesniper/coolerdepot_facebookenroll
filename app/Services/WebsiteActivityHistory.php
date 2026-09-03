<?php
/**
 * File / 文件：app/Services/WebsiteActivityHistory.php
 * EN: Stores durable history for website source scans and imports shown in Admin Settings.
 * 中文：保存 Admin Settings 中网站扫描与导入的持久历史记录。
 */
namespace App\Services;

use App\Core\Database;

class WebsiteActivityHistory
{
    public static function ensureTable(): void
    {
        Database::connection()->exec(
            "CREATE TABLE IF NOT EXISTS cdsp_website_activity_history (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                source_host VARCHAR(191) NOT NULL,
                website_url TEXT NOT NULL,
                action VARCHAR(40) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'running',
                source_url TEXT NULL,
                processed INT UNSIGNED NOT NULL DEFAULT 0,
                saved INT UNSIGNED NOT NULL DEFAULT 0,
                failed INT UNSIGNED NOT NULL DEFAULT 0,
                message TEXT NULL,
                started_by BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                finished_at DATETIME NULL,
                PRIMARY KEY (id),
                KEY idx_cdsp_website_activity_host (source_host,created_at),
                KEY idx_cdsp_website_activity_action (action,created_at),
                KEY idx_cdsp_website_activity_status (status,updated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public static function begin(
        string $host,
        string $websiteUrl,
        string $action,
        ?int $adminId=null,
        string $sourceUrl='',
        string $message=''
    ): int {
        self::ensureTable();
        $host=strtolower(trim($host));
        $action=trim($action);
        if($host===''||$action===''){throw new \DomainException('Website history requires a source and action.');}
        $q=Database::connection()->prepare(
            "INSERT INTO cdsp_website_activity_history
             (source_host,website_url,action,status,source_url,processed,saved,failed,message,started_by,created_at,updated_at,finished_at)
             VALUES(?,?,?,'running',?,0,0,0,?,?,NOW(),NOW(),NULL)"
        );
        $q->execute([
            $host,
            trim($websiteUrl),
            $action,
            trim($sourceUrl)!==''?trim($sourceUrl):null,
            trim($message)!==''?trim($message):null,
            $adminId,
        ]);
        return (int)Database::connection()->lastInsertId();
    }

    public static function update(
        int $id,
        string $status,
        int $processed,
        int $saved,
        int $failed,
        string $message='',
        bool $finished=false
    ): void {
        if($id<1){return;}
        self::ensureTable();
        $allowed=['running','completed','paused','stopped','failed'];
        if(!in_array($status,$allowed,true)){$status='running';}
        $q=Database::connection()->prepare(
            "UPDATE cdsp_website_activity_history
             SET status=?,processed=?,saved=?,failed=?,message=?,updated_at=NOW(),finished_at=?
             WHERE id=?"
        );
        $q->execute([
            $status,
            max(0,$processed),
            max(0,$saved),
            max(0,$failed),
            trim($message)!==''?trim($message):null,
            $finished?date('Y-m-d H:i:s'):null,
            $id,
        ]);
    }

    public static function fail(int $id,string $message,int $processed=0,int $saved=0,int $failed=1): void
    {
        self::update($id,'failed',$processed,$saved,max(1,$failed),$message,true);
    }

    /** @return array<int,array<string,mixed>> */
    public static function allForActions(array $actions=[]): array
    {
        self::ensureTable();
        $actions=array_values(array_filter(array_map('strval',$actions),static fn($v)=>trim($v)!==''));
        $pdo=Database::connection();
        if(!$actions){
            return $pdo->query(
                "SELECT id,source_host,website_url,action,status,source_url,processed,saved,failed,message,created_at,updated_at,finished_at
                 FROM cdsp_website_activity_history ORDER BY id DESC"
            )->fetchAll()?:[];
        }
        $placeholders=implode(',',array_fill(0,count($actions),'?'));
        $q=$pdo->prepare(
            "SELECT id,source_host,website_url,action,status,source_url,processed,saved,failed,message,created_at,updated_at,finished_at
             FROM cdsp_website_activity_history WHERE action IN ({$placeholders}) ORDER BY id DESC"
        );
        $q->execute($actions);
        return $q->fetchAll()?:[];
    }

    /** @return array<int,array<string,mixed>> */
    public static function recent(array $actions=[],int $limit=80): array
    {
        self::ensureTable();
        $limit=max(1,min(200,$limit));
        $actions=array_values(array_filter(array_map('strval',$actions),static fn($v)=>trim($v)!==''));
        $pdo=Database::connection();
        if(!$actions){
            return $pdo->query(
                "SELECT id,source_host,website_url,action,status,source_url,processed,saved,failed,message,created_at,updated_at,finished_at
                 FROM cdsp_website_activity_history ORDER BY id DESC LIMIT {$limit}"
            )->fetchAll()?:[];
        }
        $placeholders=implode(',',array_fill(0,count($actions),'?'));
        $q=$pdo->prepare(
            "SELECT id,source_host,website_url,action,status,source_url,processed,saved,failed,message,created_at,updated_at,finished_at
             FROM cdsp_website_activity_history WHERE action IN ({$placeholders}) ORDER BY id DESC LIMIT {$limit}"
        );
        $q->execute($actions);
        return $q->fetchAll()?:[];
    }

    public static function removeHost(string $host): void
    {
        self::ensureTable();
        $host=strtolower(trim($host));
        if($host===''){return;}
        $q=Database::connection()->prepare('DELETE FROM cdsp_website_activity_history WHERE source_host=?');
        $q->execute([$host]);
    }
}
