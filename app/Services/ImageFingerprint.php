<?php
/**
 * File / 文件：app/Services/ImageFingerprint.php
 * EN: Defines the ImageFingerprint service used by application business, security, or provider integration flows.
 * 中文：定义 ImageFingerprint 服务，用于应用业务、安全或 Provider 集成流程。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Services;

/**
 * EN: Application service that encapsulates image fingerprint business, security, or integration behavior.
 * 中文：封装 image fingerprint 业务、安全或外部集成行为的应用服务。
 */
class ImageFingerprint
{
    /**
     * EN: Perform the urls operation implemented by image fingerprint.
     * 中文：执行 image fingerprint 实现的“urls”操作。
     *
     * @param array $meta Meta value used by this operation. / 本操作使用的“meta”参数值。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
    public static function urls(array $meta): array
    {
        $urls=[];
        $imageKeys=['image','images','image_url','imageurl','photos','listingphotos','listing_photos','photo','photo_url','thumbnail','thumbnail_url','fetched_image_url','primary_listing_photo'];
        $excludedKeys=['seller','seller_profile','sellerprofile','user','owner','author','profile','brand','logo','avatar','profile_picture','recommendations','related_listings','relatedlistings','similar_listings'];
        $walk=function($value, bool $image=false, int $depth=0) use (&$walk,&$urls,$imageKeys,$excludedKeys): void {
            if($depth>12){return;}
            if(is_string($value)&&$image&&str_starts_with(trim($value),'https://')){
                $urls[trim($value)]=true;
            }elseif(is_array($value)){
                foreach($value as $key=>$child){
                    if(in_array(strtolower((string)$key),$excludedKeys,true)){continue;}
                    $walk($child,$image||in_array(strtolower((string)$key),$imageKeys,true),$depth+1);
                }
            }
        };
        $walk($meta);
        return array_keys($urls);
    }

    /**
     * EN: Perform the from url operation implemented by image fingerprint.
     * 中文：执行 image fingerprint 实现的“from url”操作。
     *
     * @param string $url URL to validate, resolve, fetch, or process. / 需要验证、解析、抓取或处理的 URL。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     *
     * @throws \RuntimeException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    public static function fromUrl(string $url): array
    {
        $original=$url;
        for($redirect=0;$redirect<4;$redirect++){
            $parts=parse_url($url);
            if(!filter_var($url,FILTER_VALIDATE_URL)||strtolower($parts['scheme']??'')!=='https'
                ||isset($parts['user'])||isset($parts['pass'])||(($parts['port']??443)!==443)){
                throw new \RuntimeException('Image URL must be public HTTPS without credentials.');
            }
            $host=strtolower($parts['host']??'');
            if(!$host||filter_var($host,FILTER_VALIDATE_IP)||str_ends_with($host,'.local')||str_ends_with($host,'.internal')){
                throw new \RuntimeException('Image host is not allowed.');
            }
            $ips=@gethostbynamel($host)?:[];
            if(!$ips){throw new \RuntimeException('Image host could not be resolved.');}
            foreach($ips as $ip){
                $n=ip2long($ip);
                if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)
                    ||($n&0xffc00000)===ip2long('100.64.0.0')
                    ||($n&0xfffe0000)===ip2long('198.18.0.0')){
                    throw new \RuntimeException('Private image destination blocked.');
                }
            }
            $body='';$headers=[];$tooLarge=false;
            $ch=curl_init($url);
            curl_setopt_array($ch,[
                CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>7,
                CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
                CURLOPT_PROXY=>'',CURLOPT_RESOLVE=>[$host.':443:'.$ips[0]],
                CURLOPT_USERAGENT=>'CoolerDepot-Image-Comparison/0.1.72',
                CURLOPT_HTTPHEADER=>['Accept: image/jpeg,image/png,image/webp'],
                CURLOPT_WRITEFUNCTION=>function($ch,$chunk)use(&$body,&$tooLarge){
                    if(strlen($body)+strlen($chunk)>8*1024*1024){$tooLarge=true;return 0;}
                    $body.=$chunk;return strlen($chunk);
                },
                CURLOPT_HEADERFUNCTION=>function($ch,$line)use(&$headers){
                    $v=explode(':',$line,2);if(count($v)===2){$headers[strtolower(trim($v[0]))]=trim($v[1]);}return strlen($line);
                },
            ]);
            $ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
            if($tooLarge){throw new \RuntimeException('Image exceeds the 8 MB comparison limit.');}
            if($status>=300&&$status<400&&!empty($headers['location'])){
                $next=$headers['location'];
                if(str_starts_with($next,'//')){$next='https:'.$next;}
                elseif(str_starts_with($next,'/')){$next='https://'.$host.$next;}
                $url=$next;continue;
            }
            if($ok===false||$status!==200){throw new \RuntimeException('Image could not be downloaded for comparison.');}
            return ['url'=>$original]+self::fromBytes($body);
        }
        throw new \RuntimeException('Too many image redirects.');
    }

    /**
     * EN: Perform the from bytes operation implemented by image fingerprint.
     * 中文：执行 image fingerprint 实现的“from bytes”操作。
     *
     * @param string $bytes Bytes value used by this operation. / 本操作使用的“bytes”参数值。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     *
     * @throws \RuntimeException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    public static function fromBytes(string $bytes): array
    {
        $info=@getimagesizefromstring($bytes);
        if(!$info||!in_array($info[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG,IMAGETYPE_WEBP],true)){
            throw new \RuntimeException('Unsupported image format for comparison.');
        }
        if($info[0]>10000||$info[1]>10000||$info[0]*$info[1]>16000000){
            throw new \RuntimeException('Image dimensions exceed the comparison limit.');
        }
        $out=['sha256'=>hash('sha256',$bytes),'dhash'=>null];
        if(!function_exists('imagecreatefromstring')){return $out;}
        $src=@imagecreatefromstring($bytes);
        if(!$src){throw new \RuntimeException('Image could not be decoded.');}
        $small=imagecreatetruecolor(9,8);
        imagefill($small,0,0,imagecolorallocate($small,255,255,255));
        imagecopyresampled($small,$src,0,0,0,0,9,8,$info[0],$info[1]);
        $hex='';$nibble=0;$bits=0;
        $gray=static function(int $rgb):int{return 299*(($rgb>>16)&255)+587*(($rgb>>8)&255)+114*($rgb&255);};
        for($y=0;$y<8;$y++){
            for($x=0;$x<8;$x++){
                $nibble=($nibble<<1)|($gray(imagecolorat($small,$x,$y))>$gray(imagecolorat($small,$x+1,$y))?1:0);
                if(++$bits===4){$hex.=dechex($nibble);$nibble=0;$bits=0;}
            }
        }
        imagedestroy($small);imagedestroy($src);$out['dhash']=$hex;
        return $out;
    }

    /**
     * EN: Perform the distance operation implemented by image fingerprint.
     * 中文：执行 image fingerprint 实现的“distance”操作。
     *
     * @param string $a A value used by this operation. / 本操作使用的“a”参数值。
     * @param string $b B value used by this operation. / 本操作使用的“b”参数值。
     *
     * @return int Numeric result produced by this operation. / 本操作生成的数字结果。
     */
    public static function distance(string $a,string $b): int
    {
        if(!preg_match('/^[a-f0-9]{16}$/i',$a)||!preg_match('/^[a-f0-9]{16}$/i',$b)){return 65;}
        $bits=[0,1,1,2,1,2,2,3,1,2,2,3,2,3,3,4];$n=0;
        for($i=0;$i<16;$i++){$n+=$bits[hexdec($a[$i])^hexdec($b[$i])];}return $n;
    }
}
