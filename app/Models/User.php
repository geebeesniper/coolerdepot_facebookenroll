<?php
/**
 * File / 文件：app/Models/User.php
 * EN: Database model and query layer for this domain.
 * 中文：该文件负责此业务域的数据模型与数据库查询。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
namespace App\Models;

use App\Core\Database;

class User
{
    /**
     * EN: Retrieves or loads data for `find` (find).
     * 中文：读取或加载 `find`（find）所需的数据。
     */
    public static function find(int $id): ?array
    {
        $s = Database::connection()->prepare(
            "SELECT
                id,
                sales_id,
                external_user_id,
                username,
                password_hash,
                display_name,
                role,
                active,
                daily_post_target,
                auth_source,
                last_handoff_at
             FROM cdsp_users
             WHERE id=?
             LIMIT 1"
        );
        $s->execute([$id]);

        return $s->fetch() ?: null;
    }

    /**
     * EN: Implements the application operation `loginRow` (login Row).
     * 中文：实现应用操作 `loginRow`（login Row）。
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
     * EN: Implements the application operation `allSales` (all Sales).
     * 中文：实现应用操作 `allSales`（all Sales）。
     */
    public static function allSales(): array
    {
        return Database::connection()->query(
            "SELECT
                id,
                sales_id,
                external_user_id,
                username,
                display_name,
                daily_post_target,
                last_handoff_at
             FROM cdsp_users
             WHERE role='sales'
               AND active=1
             ORDER BY display_name"
        )->fetchAll();
    }

    /**
     * EN: Updates application state for `setDailyPostTarget` (set Daily Post Target).
     * 中文：更新 `setDailyPostTarget`（set Daily Post Target）对应的应用状态。
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
}
