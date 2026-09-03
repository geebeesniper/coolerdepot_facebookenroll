<?php
/**
 * File / 文件：app/Models/User.php
 * EN: Defines the User database model and its persistence/query helpers.
 * 中文：定义 User 数据库模型及其持久化与查询辅助逻辑。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Models;

use App\Core\Database;

/**
 * EN: Database model for user records, queries, and persistence operations.
 * 中文：负责 user 记录、查询及持久化操作的数据库 Model。
 */
class User
{
    /**
     * EN: Retrieve the find data for user in the application database.
     * 中文：读取 user 的“find”数据，并访问应用数据库。
     *
     * @param int $id Identifier of the record record or entity. / record 记录或实体的标识 ID。
     *
     * @return ?array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
    public static function find(int $id): ?array
    {
        $s = Database::connection()->prepare(
            "SELECT
                u.id,
                u.sales_id,
                u.external_user_id,
                u.username,
                u.password_hash,
                u.display_name,
                u.role,
                u.active,
                u.daily_post_target,
                u.location_id,
                l.name AS location_name,
                u.auth_source,
                u.last_handoff_at
             FROM cdsp_users u
             LEFT JOIN cdsp_locations l
               ON l.id=u.location_id
              AND l.active=1
             WHERE u.id=?
             LIMIT 1"
        );
        $s->execute([$id]);

        return $s->fetch() ?: null;
    }

    /**
     * EN: Record the login row data for user in the application database.
     * 中文：记录 user 的“login row”数据，并访问应用数据库。
     *
     * @param string $username Username value used by this operation. / 本操作使用的“username”参数值。
     *
     * @return ?array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
    public static function loginRow(string $username): ?array
    {
        $s = Database::connection()->prepare(
            "SELECT *
             FROM cdsp_users
             WHERE username=?
               AND active=1
             LIMIT 1"
        );
        $s->execute([$username]);

        return $s->fetch() ?: null;
    }

    /**
     * EN: Retrieve the all sales data for user in the application database.
     * 中文：读取 user 的“all sales”数据，并访问应用数据库。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
    public static function allSales(): array
    {
        return Database::connection()->query(
            "SELECT
                u.id,
                u.sales_id,
                u.external_user_id,
                u.username,
                u.display_name,
                u.daily_post_target,
                u.location_id,
                l.name AS location_name,
                u.last_handoff_at
             FROM cdsp_users u
             LEFT JOIN cdsp_locations l
               ON l.id=u.location_id
              AND l.active=1
             WHERE u.role='sales'
               AND u.active=1
             ORDER BY u.display_name"
        )->fetchAll();
    }

    /**
     * EN: Update the set daily post target data for user in the application database.
     * 中文：更新 user 的“set daily post target”数据，并访问应用数据库。
     *
     * @param int $userId Application user identifier. / 应用用户 ID。
     * @param int $target Target value used by this operation. / 本操作使用的“target”参数值。
     *
     * @return bool True when the requested condition is satisfied; otherwise false. / 请求条件满足时返回 true，否则返回 false。
     */
    public static function setDailyPostTarget(
        int $userId,
        int $target
    ): bool {
        $target = max(1, min(999, $target));

        $s = Database::connection()->prepare(
            "UPDATE cdsp_users
             SET daily_post_target=?,
                 updated_at=NOW()
             WHERE id=?
               AND role='sales'
               AND active=1"
        );

        $s->execute([$target, $userId]);

        return $s->rowCount() > 0;
    }

    /**
     * EN: Save the Admin-managed daily target and optional location for an active Sales user.
     * 中文：保存 Admin 为启用中的 Sales 设置的每日目标与可选 Location。
     *
     * @param int $userId Application Sales user identifier. / Sales 用户 ID。
     * @param int $target Daily post target. / 每日发帖目标。
     * @param ?int $locationId Assigned Location ID, or null for Unassigned. / Location ID；未分配时为 null。
     * @return bool True when MySQL reports a changed row. / MySQL 报告记录有变更时返回 true。
     * @throws \InvalidArgumentException When the selected location is unavailable. / Location 不可用时抛出。
     */
    public static function setSalesSettings(
        int $userId,
        int $target,
        ?int $locationId
    ): bool {
        $target = max(1, min(999, $target));
        $locationId = $locationId !== null && $locationId > 0
            ? $locationId
            : null;

        if ($locationId !== null) {
            $location = Location::find($locationId);
            if (!$location || !(int)($location['active'] ?? 0)) {
                throw new \InvalidArgumentException('Selected location is not available.');
            }
        }

        $s = Database::connection()->prepare(
            "UPDATE cdsp_users
             SET daily_post_target=?,
                 location_id=?,
                 updated_at=NOW()
             WHERE id=?
               AND role='sales'
               AND active=1"
        );
        $s->execute([$target, $locationId, $userId]);

        return $s->rowCount() > 0;
    }

    /**
     * EN: Retrieve the all for api data for user in the application database.
     * 中文：读取 user 的“all for api”数据，并访问应用数据库。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
    public static function allForApi(): array
    {
        return Database::connection()->query(
            "SELECT id,sales_id,external_user_id,display_name,role,active,daily_post_target,auth_source
             FROM cdsp_users
             WHERE active=1
             ORDER BY role,display_name,id"
        )->fetchAll();
    }

}
