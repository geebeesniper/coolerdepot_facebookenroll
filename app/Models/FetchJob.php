<?php
/**
 * File / 文件：app/Models/FetchJob.php
 * EN: Database model and query layer for this domain.
 * 中文：该文件负责此业务域的数据模型与数据库查询。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
namespace App\Models;

use App\Core\Database;

class FetchJob
{
    /**
     * EN: Creates or persists the `create` operation (create).
     * 中文：创建或持久化 `create`（create）操作。
     */
    public static function create(
        int $requestedByUserId,
        string $platform,
        string $url,
        ?string $externalPostId,
        string $provider
    ): int {
        $stmt = Database::connection()->prepare(
            "INSERT INTO cdsp_fetch_jobs
             (
                requested_by_user_id, platform, submitted_url, external_post_id,
                provider, status, created_at, updated_at
             )
             VALUES (?, ?, ?, ?, ?, 'starting', NOW(), NOW())"
        );
        $stmt->execute([
            $requestedByUserId,
            $platform,
            $url,
            $externalPostId,
            $provider,
        ]);

        return (int)Database::connection()->lastInsertId();
    }

    /**
     * EN: Updates application state for `setSnapshot` (set Snapshot).
     * 中文：更新 `setSnapshot`（set Snapshot）对应的应用状态。
     */
    public static function setSnapshot(int $id, string $snapshotId, ?int $httpStatus = null): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE cdsp_fetch_jobs
             SET provider_job_id = ?, provider_http_status = ?, status = 'running', updated_at = NOW()
             WHERE id = ?"
        );
        $stmt->execute([$snapshotId, $httpStatus, $id]);
    }

    /**
     * EN: Updates application state for `setStatus` (set Status).
     * 中文：更新 `setStatus`（set Status）对应的应用状态。
     */
    public static function setStatus(int $id, string $status, ?int $httpStatus = null, ?string $error = null): void
    {
        $completedSql = in_array($status, ['ready','failed'], true)
            ? ", completed_at = NOW()"
            : "";

        $stmt = Database::connection()->prepare(
            "UPDATE cdsp_fetch_jobs
             SET status = ?, provider_http_status = COALESCE(?, provider_http_status),
                 error_message = ?, updated_at = NOW()
                 {$completedSql}
             WHERE id = ?"
        );
        $stmt->execute([$status, $httpStatus, $error, $id]);
    }

    /**
     * EN: Updates application state for `setReady` (set Ready).
     * 中文：更新 `setReady`（set Ready）对应的应用状态。
     */
    public static function setReady(int $id, array $response, ?int $httpStatus = 200): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE cdsp_fetch_jobs
             SET status='ready', provider_http_status=?, response_json=?,
                 error_message=NULL, updated_at=NOW(), completed_at=NOW()
             WHERE id=?"
        );
        $stmt->execute([
            $httpStatus,
            json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $id,
        ]);
    }

    /**
     * EN: Implements the application operation `recentReady` (recent Ready).
     * 中文：实现应用操作 `recentReady`（recent Ready）。
     */
    public static function recentReady(
        string $platform,
        ?string $externalPostId,
        int $minutes = 10,
        ?string $provider = null
    ): ?array {
        if (!$externalPostId) {
            return null;
        }

        $sql =
            "SELECT *
             FROM cdsp_fetch_jobs
             WHERE platform=?
               AND external_post_id=?
               AND status='ready'
               AND response_json IS NOT NULL
               AND completed_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)";

        $params = [$platform, $externalPostId, max(1, $minutes)];

        if ($provider !== null) {
            $sql .= " AND provider=?";
            $params[] = $provider;
        }

        $sql .= " ORDER BY completed_at DESC LIMIT 1";

        $stmt = Database::connection()->prepare($sql);

        foreach ($params as $i => $value) {
            $stmt->bindValue(
                $i + 1,
                $value,
                is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR
            );
        }

        $stmt->execute();
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $data = json_decode((string)$row['response_json'], true);

        return is_array($data) ? $data : null;
    }

    /**
     * EN: Implements the application operation `recent` (recent).
     * 中文：实现应用操作 `recent`（recent）。
     */
    public static function recent(int $limit = 12): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT j.*, u.display_name
             FROM cdsp_fetch_jobs j
             JOIN cdsp_users u ON u.id=j.requested_by_user_id
             ORDER BY j.id DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, max(1, min(50, $limit)), \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
