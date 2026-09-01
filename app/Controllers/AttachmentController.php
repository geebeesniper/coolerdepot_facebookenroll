<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Logger;

class AttachmentController extends Controller
{
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
