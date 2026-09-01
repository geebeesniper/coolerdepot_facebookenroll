<?php
/**
 * File / 文件：app/Services/UploadService.php
 * EN: Defines the UploadService service used by application business, security, or provider integration flows.
 * 中文：定义 UploadService 服务，用于应用业务、安全或 Provider 集成流程。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Services;

use App\Core\Database;

/**
 * EN: Application service that encapsulates upload service business, security, or integration behavior.
 * 中文：封装 upload service 业务、安全或外部集成行为的应用服务。
 */
class UploadService
{
    /**
     * EN: Create or store the save operation implemented by upload service.
     * 中文：创建或保存 upload service 实现的“save”操作。
     *
     * @param string $type Type value used by this operation. / 本操作使用的“type”参数值。
     * @param int $entityId Identifier of the entity record or entity. / entity 记录或实体的标识 ID。
     * @param int $uid External user identifier supplied by the parent authentication system. / 父级认证系统提供的外部用户 ID。
     * @param string $field Field value used by this operation. / 本操作使用的“field”参数值。
     * @param ?int $historyId Identifier of the history record or entity. / history 记录或实体的标识 ID。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     *
     * @throws \RuntimeException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
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
     * EN: Perform the upload error message operation implemented by upload service.
     * 中文：执行 upload service 实现的“upload error message”操作。
     *
     * @param int $error Error value used by this operation. / 本操作使用的“error”参数值。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
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
