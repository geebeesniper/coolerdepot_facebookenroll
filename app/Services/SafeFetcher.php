<?php
/**
 * File / 文件：app/Services/SafeFetcher.php
 * EN: Defines the SafeFetcher service used by application business, security, or provider integration flows.
 * 中文：定义 SafeFetcher 服务，用于应用业务、安全或 Provider 集成流程。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Services;
/**
 * EN: Application service that encapsulates safe fetcher business, security, or integration behavior.
 * 中文：封装 safe fetcher 业务、安全或外部集成行为的应用服务。
 */
class SafeFetcher {
    /**
     * EN: Retrieve the fetch operation implemented by safe fetcher.
     * 中文：读取 safe fetcher 实现的“fetch”操作。
     *
     * @param string $url URL to validate, resolve, fetch, or process. / 需要验证、解析、抓取或处理的 URL。
     * @param string $platform Platform value used by this operation. / 本操作使用的“platform”参数值。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     *
     * @throws \RuntimeException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    public function fetch(string $url,string $platform):array{
        $current=$url;
        for($i=0;$i<7;$i++){
            if(!PlatformUrl::allowed($current,$platform))throw new \RuntimeException('URL is outside the selected platform.');
            $this->assertPublicHost($current);
            $headers=[];
            $ch=curl_init($current);
            curl_setopt_array($ch,[
                CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>15,
                CURLOPT_USERAGENT=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/151 Safari/537.36',
                CURLOPT_ENCODING=>'',
                CURLOPT_HTTPHEADER=>['Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8','Accept-Language: en-US,en;q=0.8'],
                CURLOPT_HEADERFUNCTION=>function($ch,$line)use(&$headers){$len=strlen($line);$p=explode(':',$line,2);if(count($p)===2)$headers[strtolower(trim($p[0]))]=trim($p[1]);return$len;}
            ]);
            $body=curl_exec($ch);
            if($body===false){$e=curl_error($ch);curl_close($ch);throw new \RuntimeException('Fetch failed: '.$e);}
            $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
            if($status>=300&&$status<400&&!empty($headers['location'])){
                $current=$this->absolute($current,$headers['location']);continue;
            }
            if($status<200||$status>=400)throw new \RuntimeException('Remote page returned HTTP '.$status);
            return['resolved_url'=>$current,'html'=>substr($body,0,4000000),'headers'=>$headers,'status'=>$status];
        }
        throw new \RuntimeException('Too many redirects.');
    }
    /**
     * EN: Check or validate the assert public host operation implemented by safe fetcher.
     * 中文：检查或验证 safe fetcher 实现的“assert public host”操作。
     *
     * @param string $url URL to validate, resolve, fetch, or process. / 需要验证、解析、抓取或处理的 URL。
     *
     * @return void No value is returned. / 无返回值。
     *
     * @throws \RuntimeException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    private function assertPublicHost(string $url):void{
        $host=(string)parse_url($url,PHP_URL_HOST);if(!$host)throw new \RuntimeException('Invalid host.');
        foreach(gethostbynamel($host)?:[] as $ip){
            if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))throw new \RuntimeException('Private/reserved target blocked.');
        }
    }
    /**
     * EN: Build the absolute operation implemented by safe fetcher.
     * 中文：构建 safe fetcher 实现的“absolute”操作。
     *
     * @param string $base Base URL path removed before route matching. / 路由匹配前需要移除的基础 URL 路径。
     * @param string $loc Loc value used by this operation. / 本操作使用的“loc”参数值。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
     */
    private function absolute(string $base,string $loc):string{
        if(preg_match('~^https?://~i',$loc))return$loc;
        $p=parse_url($base);$scheme=$p['scheme']??'https';$host=$p['host']??'';
        if(strpos($loc,'//')===0)return$scheme.':'.$loc;
        if(strpos($loc,'/')===0)return$scheme.'://'.$host.$loc;
        $dir=rtrim(str_replace('\\','/',dirname($p['path']??'/')),'/');
        return$scheme.'://'.$host.($dir?'/'.ltrim($dir,'/'):'').'/'.$loc;
    }
}
