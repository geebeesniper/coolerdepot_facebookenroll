<?php
/**
 * File / 文件：app/Models/Location.php
 * EN: Stores Admin-managed Sales locations and exposes location counts used by Settings and the Admin Sales dashboard.
 * 中文：保存 Admin 管理的 Sales Location，并提供 Settings 与 Admin Sales Dashboard 使用的 Location 人数统计。
 */
namespace App\Models;

use App\Core\Database;

/**
 * EN: Database model for Admin-managed Sales locations and Sales assignment counts.
 * 中文：负责 Admin 管理的 Sales Location 以及 Sales 分配人数统计的数据库 Model。
 */
class Location
{
    /**
     * EN: Return active locations with the number of active Sales users assigned to each location.
     * 中文：返回启用中的 Location，并统计每个 Location 当前分配的启用 Sales 人数。
     *
     * @return array Active location rows with sales_count. / 包含 sales_count 的启用 Location 记录。
     */
    public static function allWithSalesCounts(): array
    {
        $sql = "SELECT
                    l.id,
                    l.name,
                    l.active,
                    l.sort_order,
                    COUNT(u.id) AS sales_count
                FROM cdsp_locations l
                LEFT JOIN cdsp_users u
                  ON u.location_id=l.id
                 AND u.role='sales'
                 AND u.active=1
                WHERE l.active=1
                GROUP BY l.id,l.name,l.active,l.sort_order
                ORDER BY l.sort_order,l.name,l.id";

        return Database::connection()->query($sql)->fetchAll();
    }

    /**
     * EN: Return active locations for assignment controls.
     * 中文：返回启用中的 Location，供 Sales 分配控件使用。
     *
     * @return array Active location rows. / 启用中的 Location 记录。
     */
    public static function active(): array
    {
        return Database::connection()->query(
            "SELECT id,name,sort_order
             FROM cdsp_locations
             WHERE active=1
             ORDER BY sort_order,name,id"
        )->fetchAll();
    }

    /**
     * EN: Find one location by numeric identifier.
     * 中文：按数字 ID 读取单个 Location。
     *
     * @param int $id Location identifier. / Location ID。
     * @return ?array Location row when found, otherwise null. / 找到时返回 Location，否则返回 null。
     */
    public static function find(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }

        $s = Database::connection()->prepare(
            "SELECT id,name,active,sort_order
             FROM cdsp_locations
             WHERE id=?
             LIMIT 1"
        );
        $s->execute([$id]);

        return $s->fetch() ?: null;
    }

    /**
     * EN: Create a location, or reactivate an inactive row with the same name.
     * 中文：建立新的 Location；若同名记录处于停用状态，则重新启用该记录。
     *
     * @param string $name Admin-entered location name. / Admin 输入的 Location 名称。
     * @return array Newly created or reactivated location row. / 新建或重新启用的 Location 记录。
     * @throws \InvalidArgumentException When the name is invalid or already active. / 名称无效或已存在时抛出。
     */
    public static function create(string $name): array
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
        if ($name === '' || mb_strlen($name) > 120) {
            throw new \InvalidArgumentException('Location name must be 1–120 characters.');
        }

        $pdo = Database::connection();
        $existing = $pdo->prepare(
            "SELECT id,name,active,sort_order
             FROM cdsp_locations
             WHERE LOWER(name)=LOWER(?)
             LIMIT 1"
        );
        $existing->execute([$name]);
        $row = $existing->fetch();

        if ($row) {
            if (!(int)$row['active']) {
                $s = $pdo->prepare(
                    "UPDATE cdsp_locations
                     SET name=?,active=1,updated_at=NOW()
                     WHERE id=?"
                );
                $s->execute([$name, (int)$row['id']]);
                return self::find((int)$row['id']) ?? $row;
            }

            throw new \InvalidArgumentException('That location already exists.');
        }

        $nextOrder = (int)$pdo->query(
            "SELECT COALESCE(MAX(sort_order),0)+10 FROM cdsp_locations"
        )->fetchColumn();

        $s = $pdo->prepare(
            "INSERT INTO cdsp_locations(name,active,sort_order,created_at,updated_at)
             VALUES(?,1,?,NOW(),NOW())"
        );
        $s->execute([$name, $nextOrder]);

        return self::find((int)$pdo->lastInsertId()) ?? [
            'id'=>(int)$pdo->lastInsertId(),
            'name'=>$name,
            'active'=>1,
            'sort_order'=>$nextOrder,
        ];
    }

    /**
     * EN: Rename an active location while preserving the location id and every Sales assignment.
     * 中文：修改启用中 Location 的名称，同时保留 Location ID 与所有 Sales 分配关系。
     *
     * @param int $id Location identifier. / Location ID。
     * @param string $name New Admin-entered location name. / Admin 输入的新 Location 名称。
     * @return ?array Updated location row when found. / 找到时返回更新后的 Location 记录。
     * @throws \InvalidArgumentException When the name is invalid or already belongs to another location. / 名称无效或已被其他 Location 使用时抛出。
     */
    public static function rename(int $id, string $name): ?array
    {
        if($id < 1){
            return null;
        }

        $name=trim(preg_replace('/\s+/u',' ',$name) ?? $name);
        if($name==='' || mb_strlen($name)>120){
            throw new \InvalidArgumentException('Location name must be 1–120 characters.');
        }

        $pdo=Database::connection();
        $current=self::find($id);
        if(!$current || !(int)($current['active'] ?? 0)){
            return null;
        }

        $duplicate=$pdo->prepare(
            "SELECT id
             FROM cdsp_locations
             WHERE LOWER(name)=LOWER(?)
               AND id<>?
             LIMIT 1"
        );
        $duplicate->execute([$name,$id]);
        if($duplicate->fetchColumn()){
            throw new \InvalidArgumentException('That location already exists.');
        }

        $update=$pdo->prepare(
            "UPDATE cdsp_locations
             SET name=?,updated_at=NOW()
             WHERE id=? AND active=1"
        );
        $update->execute([$name,$id]);

        return self::find($id);
    }

    /**
     * EN: Delete a location and move every assigned user to Unassigned in one transaction.
     * 中文：删除 Location，并在同一事务中把所有已分配用户自动移到 Unassigned。
     *
     * @param int $id Location identifier. / Location ID。
     * @return bool True when a location row was deleted. / 成功删除 Location 时返回 true。
     */
    public static function deleteWithUnassign(int $id): bool
    {
        if ($id < 1) {
            return false;
        }

        $pdo = Database::connection();
        $startedTransaction = !$pdo->inTransaction();

        if ($startedTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $exists = $pdo->prepare(
                "SELECT id
                 FROM cdsp_locations
                 WHERE id=?
                 FOR UPDATE"
            );
            $exists->execute([$id]);
            if (!$exists->fetchColumn()) {
                if ($startedTransaction && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                return false;
            }

            // EN: Preserve Sales accounts; only clear their Location assignment.
            // 中文：保留 Sales 账号，只清除 Location 分配，使其进入 Unassigned。
            $clear = $pdo->prepare(
                "UPDATE cdsp_users
                 SET location_id=NULL
                 WHERE location_id=?"
            );
            $clear->execute([$id]);

            $delete = $pdo->prepare("DELETE FROM cdsp_locations WHERE id=?");
            $delete->execute([$id]);
            $deleted = $delete->rowCount() > 0;

            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->commit();
            }

            return $deleted;
        } catch (\Throwable $e) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * EN: Count active Sales users with no active assigned location.
     * 中文：统计当前没有启用 Location 分配的启用 Sales 人数。
     *
     * @return int Number of active unassigned Sales users. / 未分配 Location 的启用 Sales 人数。
     */
    public static function unassignedSalesCount(): int
    {
        return (int)Database::connection()->query(
            "SELECT COUNT(*)
             FROM cdsp_users u
             LEFT JOIN cdsp_locations l
               ON l.id=u.location_id
              AND l.active=1
             WHERE u.role='sales'
               AND u.active=1
               AND l.id IS NULL"
        )->fetchColumn();
    }
}
