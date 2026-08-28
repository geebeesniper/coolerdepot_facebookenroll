<?php
namespace App\Services;
class SafeFetcher {
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
    private function assertPublicHost(string $url):void{
        $host=(string)parse_url($url,PHP_URL_HOST);if(!$host)throw new \RuntimeException('Invalid host.');
        foreach(gethostbynamel($host)?:[] as $ip){
            if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))throw new \RuntimeException('Private/reserved target blocked.');
        }
    }
    private function absolute(string $base,string $loc):string{
        if(preg_match('~^https?://~i',$loc))return$loc;
        $p=parse_url($base);$scheme=$p['scheme']??'https';$host=$p['host']??'';
        if(strpos($loc,'//')===0)return$scheme.':'.$loc;
        if(strpos($loc,'/')===0)return$scheme.'://'.$host.$loc;
        $dir=rtrim(str_replace('\\','/',dirname($p['path']??'/')),'/');
        return$scheme.'://'.$host.($dir?'/'.ltrim($dir,'/'):'').'/'.$loc;
    }
}
