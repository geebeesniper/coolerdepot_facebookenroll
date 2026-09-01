<?php
/**
 * File / 文件：app/Controllers/AttachmentController.php
 * EN: Defines the AttachmentController HTTP controller and request/response actions.
 * 中文：定义 AttachmentController HTTP Controller 及其请求/响应操作。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Logger;

/**
 * EN: HTTP controller for attachment requests, responses, and server-side authorization.
 * 中文：负责 attachment 请求、响应及服务器端权限控制的 HTTP Controller。
 */
class AttachmentController extends Controller
{
    /**
     * EN: Handle the show HTTP action for attachment controller and return the appropriate response.
     * 中文：处理 attachment controller 的“show”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function show(): void
    {
        Auth::requireLogin();

        $id = (int)($_GET['id'] ?? 0);
        $stmt = Database::connection()->prepare(
            'SELECT * FROM cdsp_review_attachments WHERE id=? LIMIT 1'
        );
        $stmt->execute([$id]);
        $attachment = $stmt->fetch();

        if (!$attachment) {
            Logger::httpStatus(
                404,
                ['event' => 'attachment_record_not_found', 'attachment_id' => $id]
            );
            http_response_code(404);
            exit('Not found');
        }

        $base = realpath(dirname(__DIR__, 2) . '/storage/uploads');
        $path = realpath(
            dirname(__DIR__, 2)
            . '/storage/uploads/'
            . $attachment['stored_path']
        );

        // realpath + prefix verification prevents a database path from escaping
        // storage/uploads even if a stored_path row is malformed or tampered with.
        if (!$base || !$path || strpos($path, $base) !== 0 || !is_file($path)) {
            Logger::httpStatus(
                404,
                [
                    'event' => 'attachment_file_not_found',
                    'attachment_id' => $id,
                ]
            );
            http_response_code(404);
            exit('Not found');
        }

        header('Content-Type: ' . $attachment['mime_type']);
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}
