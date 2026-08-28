<?php
namespace App\Services;
use App\Core\Database;
class UploadService {
    public function save(string $type,int $entityId,int $uid,string $field='images'):void{
        global $config;if(empty($_FILES[$field])||!is_array($_FILES[$field]['name']))return;
        $finfo=new \finfo(FILEINFO_MIME_TYPE);$base=dirname(__DIR__,2).'/storage/uploads';$sub=date('Y/m');$dir=$base.'/'.$sub;if(!is_dir($dir))mkdir($dir,0750,true);
        foreach($_FILES[$field]['name'] as $i=>$name){
            if($_FILES[$field]['error'][$i]===UPLOAD_ERR_NO_FILE)continue;if($_FILES[$field]['error'][$i]!==UPLOAD_ERR_OK)throw new \RuntimeException('Upload failed.');
            $tmp=$_FILES[$field]['tmp_name'][$i];$size=(int)$_FILES[$field]['size'][$i];if($size<=0||$size>(int)$config['app']['upload_max_bytes'])throw new \RuntimeException('Image too large.');
            $mime=$finfo->file($tmp);$allowed=$config['security']['allowed_upload_mimes'];if(!isset($allowed[$mime]))throw new \RuntimeException('Only JPG, PNG and WEBP allowed.');
            $fn=bin2hex(random_bytes(20)).'.'.$allowed[$mime];$rel=$sub.'/'.$fn;if(!move_uploaded_file($tmp,$base.'/'.$rel))throw new \RuntimeException('Could not store image.');
            $s=Database::connection()->prepare("INSERT INTO review_attachments(entity_type,entity_id,uploaded_by,original_name,stored_path,mime_type,size_bytes,created_at) VALUES(?,?,?,?,?,?,?,NOW())");
            $s->execute([$type,$entityId,$uid,basename((string)$name),$rel,$mime,$size]);
        }
    }
}
