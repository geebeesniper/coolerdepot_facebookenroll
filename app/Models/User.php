<?php
namespace App\Models;

use App\Core\Database;

class User
{
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
