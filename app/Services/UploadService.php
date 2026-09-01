<?php
/**
 * File / 文件：app/Services/UploadService.php
 * EN: Application service for reusable business or integration logic.
 * 中文：该文件负责可复用的业务逻辑或外部集成服务。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
namespace App\Services;

use App\Core\Database;

class UploadService
{
    /**
     * EN: Creates or persists the `save` operation (save).
     * 中文：创建或持久化 `save`（save）操作。
     */
    public function save(
        string $type,
        int $entityId,
        int $uid,
        string $field = 'images',
        ?int $historyId = null
    ): array {
        global $config;

        if (empty($_FILES[$field])) {
            return [];
        }

        $file = $_FILES[$field];
        $names = is_array($file['name'] ?? null) ? $file['name'] : [$file['name'] ?? ''];
        $tmpNames = is_array($file['tmp_name'] ?? null) ? $file['tmp_name'] : [$file['tmp_name'] ?? ''];
        $errors = is_array($file['error'] ?? null) ? $file['error'] : [$file['error'] ?? UPLOAD_ERR_NO_FILE];
        $sizes = is_array($file['size'] ?? null) ? $file['size'] : [$file['size'] ?? 0];

        $base = dirname(__DIR__, 2) . '/storage/uploads';
        $sub = date('Y/m');
        $dir = $base . '/' . $sub;

        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException('Upload storage directory could not be created.');
        }
        if (!is_writable($dir)) {
            throw new \RuntimeException('Upload storage directory is not writable.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $allowed = $config['security']['allowed_upload_mimes'];
        $maxBytes = (int)$config['app']['upload_max_bytes'];
        $saved = [];

        foreach ($names as $i => $name) {
            $error = (int)($errors[$i] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) continue;
            if ($error !== UPLOAD_ERR_OK) {
                throw new \RuntimeException($this->uploadErrorMessage($error));
            }

            $tmp = (string)($tmpNames[$i] ?? '');
            $size = (int)($sizes[$i] ?? 0);
            if ($size <= 0) throw new \RuntimeException('Uploaded image is empty.');
            if ($size > $maxBytes) {
                throw new \RuntimeException('Image is too large. Maximum allowed size is ' . round($maxBytes/1024/1024,1) . ' MB.');
            }
            if ($tmp === '' || !is_uploaded_file($tmp)) {
                throw new \RuntimeException('The uploaded image was not received correctly by PHP.');
            }

            $mime = (string)$finfo->file($tmp);
            if (!isset($allowed[$mime])) {
                throw new \RuntimeException('Unsupported image type. Use JPG, PNG, or WEBP.');
            }

            $filename = bin2hex(random_bytes(20)) . '.' . $allowed[$mime];
            $relativePath = $sub . '/' . $filename;
            $absolutePath = $base . '/' . $relativePath;
            if (!move_uploaded_file($tmp, $absolutePath)) {
                throw new \RuntimeException('Image could not be written to upload storage.');
            }

            if ($historyId !== null && $historyId > 0) {
                $s = Database::connection()->prepare("INSERT INTO cdsp_review_attachments(entity_type,entity_id,history_id,uploaded_by,original_name,stored_path,mime_type,size_bytes,created_at) VALUES(?,?,?,?,?,?,?,?,NOW())");
                $s->execute([$type,$entityId,$historyId,$uid,basename((string)$name),$relativePath,$mime,$size]);
            } else {
                $s = Database::connection()->prepare("INSERT INTO cdsp_review_attachments(entity_type,entity_id,uploaded_by,original_name,stored_path,mime_type,size_bytes,created_at) VALUES(?,?,?,?,?,?,?,NOW())");
                $s->execute([$type,$entityId,$uid,basename((string)$name),$relativePath,$mime,$size]);
            }
            $saved[]=[
                'id'=>(int)Database::connection()->lastInsertId(),
                'name'=>basename((string)$name),
                'stored_path'=>$relativePath,
                'mime_type'=>$mime,
                'size_bytes'=>$size,
            ];
        }
        return $saved;
    }

    /**
     * EN: Creates or persists the `uploadErrorMessage` operation (upload Error Message).
     * 中文：创建或持久化 `uploadErrorMessage`（upload Error Message）操作。
     */
    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE => 'Image exceeds the PHP upload_max_filesize limit.',
            UPLOAD_ERR_FORM_SIZE => 'Image exceeds the form upload size limit.',
            UPLOAD_ERR_PARTIAL => 'Image upload was interrupted before completion.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server temporary upload directory is missing.',
            UPLOAD_ERR_CANT_WRITE => 'Server could not write the uploaded image.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the image upload.',
            default => 'Image upload failed with error code ' . $error . '.',
        };
    }
}
