<?php
namespace App\Models;

use App\Core\Database;

class FetchJob
{
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

    public static function setSnapshot(int $id, string $snapshotId, ?int $httpStatus = null): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE cdsp_fetch_jobs
             SET provider_job_id = ?, provider_http_status = ?, status = 'running', updated_at = NOW()
             WHERE id = ?"
        );
        $stmt->execute([$snapshotId, $httpStatus, $id]);
    }

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

    public static function recentReady(string $platform, ?string $externalPostId, int $minutes = 10): ?array
    {
        if (!$externalPostId) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            "SELECT *
             FROM cdsp_fetch_jobs
             WHERE platform=?
               AND external_post_id=?
               AND status='ready'
               AND response_json IS NOT NULL
               AND completed_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
             ORDER BY completed_at DESC
             LIMIT 1"
        );
        $stmt->bindValue(1, $platform);
        $stmt->bindValue(2, $externalPostId);
        $stmt->bindValue(3, max(1, $minutes), \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $data = json_decode((string)$row['response_json'], true);

        return is_array($data) ? $data : null;
    }

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
