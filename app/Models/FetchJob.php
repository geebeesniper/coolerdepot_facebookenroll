<?php
/**
 * File / 文件：app/Models/FetchJob.php
 * EN: Defines the FetchJob database model and its persistence/query helpers.
 * 中文：定义 FetchJob 数据库模型及其持久化与查询辅助逻辑。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Models;

use App\Core\Database;

/**
 * EN: Database model for fetch job records, queries, and persistence operations.
 * 中文：负责 fetch job 记录、查询及持久化操作的数据库 Model。
 */
class FetchJob
{
    /**
     * EN: Create or store the create data for fetch job in the application database.
     * 中文：创建或保存 fetch job 的“create”数据，并访问应用数据库。
     *
     * @param int $requestedByUserId Application or external user identifier. / 应用或外部用户 ID。
     * @param string $platform Platform value used by this operation. / 本操作使用的“platform”参数值。
     * @param string $url URL to validate, resolve, fetch, or process. / 需要验证、解析、抓取或处理的 URL。
     * @param ?string $externalPostId Sales post identifier. / 销售 Post ID。
     * @param string $provider Provider value or provider configuration used by this operation. / 本操作使用的 Provider 值或 Provider 配置。
     *
     * @return int Numeric result produced by this operation. / 本操作生成的数字结果。
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
     * EN: Update the set snapshot data for fetch job in the application database.
     * 中文：更新 fetch job 的“set snapshot”数据，并访问应用数据库。
     *
     * @param int $id Identifier of the record record or entity. / record 记录或实体的标识 ID。
     * @param string $snapshotId Identifier of the snapshot record or entity. / snapshot 记录或实体的标识 ID。
     * @param ?int $httpStatus Http status value used by this operation. / 本操作使用的“http status”参数值。
     *
     * @return void No value is returned. / 无返回值。
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
     * EN: Update the set status data for fetch job in the application database.
     * 中文：更新 fetch job 的“set status”数据，并访问应用数据库。
     *
     * @param int $id Identifier of the record record or entity. / record 记录或实体的标识 ID。
     * @param string $status Status value applied or evaluated by the operation. / 本操作设置或判断的状态值。
     * @param ?int $httpStatus Http status value used by this operation. / 本操作使用的“http status”参数值。
     * @param ?string $error Error value used by this operation. / 本操作使用的“error”参数值。
     *
     * @return void No value is returned. / 无返回值。
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
     * EN: Update the set ready data for fetch job in the application database.
     * 中文：更新 fetch job 的“set ready”数据，并访问应用数据库。
     *
     * @param int $id Identifier of the record record or entity. / record 记录或实体的标识 ID。
     * @param array $response Response value used by this operation. / 本操作使用的“response”参数值。
     * @param ?int $httpStatus Http status value used by this operation. / 本操作使用的“http status”参数值。
     *
     * @return void No value is returned. / 无返回值。
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
     * EN: Retrieve the recent ready data for fetch job in the application database.
     * 中文：读取 fetch job 的“recent ready”数据，并访问应用数据库。
     *
     * @param string $platform Platform value used by this operation. / 本操作使用的“platform”参数值。
     * @param ?string $externalPostId Sales post identifier. / 销售 Post ID。
     * @param int $minutes Minutes value used by this operation. / 本操作使用的“minutes”参数值。
     * @param ?string $provider Provider value or provider configuration used by this operation. / 本操作使用的 Provider 值或 Provider 配置。
     *
     * @return ?array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
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
     * EN: Retrieve the recent data for fetch job in the application database.
     * 中文：读取 fetch job 的“recent”数据，并访问应用数据库。
     *
     * @param int $limit Maximum number of records or items to process. / 允许处理的最大记录或数据项数量。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
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
