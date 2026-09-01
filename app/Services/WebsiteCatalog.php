<?php
/**
 * File / 文件：app/Services/WebsiteCatalog.php
 * EN: Application service for reusable business or integration logic.
 * 中文：该文件负责可复用的业务逻辑或外部集成服务。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
namespace App\Services;

use App\Core\Database;
use App\Core\Util;

class WebsiteCatalog
{
    private const MAX_FETCH_BYTES = 2500000;
    private const MAX_SCAN_URLS = 75;

    /**
     * Import either a URL-only CSV or a richer reference CSV.
     * Supported headers:
     *   url
     *   page_url,title,description,image_url
     * Missing title/description/image fields are fetched from the page.
     */
    /**
     * EN: Implements the application operation `importCsv` (import Csv).
     * 中文：实现应用操作 `importCsv`（import Csv）。
     */
    public static function importCsv(string $path, string $website): array
    {
        $website=self::normalizeUrl($website);
        $host=self::host($website);
        self::assertReady();

        if(!is_file($path)||filesize($path)>5*1024*1024){
            throw new \DomainException('Upload a CSV file smaller than 5 MB.');
        }

        $file=fopen($path,'rb');
        if(!$file){throw new \DomainException('CSV could not be opened.');}

        $processed=0;$saved=0;$failed=[];
        try{
            $header=fgetcsv($file,0,',','"','');
            if(!$header){throw new \DomainException('CSV is empty.');}
            $header=array_map(
                static fn($s)=>strtolower(trim(ltrim((string)$s,"\xEF\xBB\xBF"))),
                $header
            );

            $urlKey=in_array('page_url',$header,true)?'page_url':(in_array('url',$header,true)?'url':null);
            if(!$urlKey){throw new \DomainException('CSV must include a url or page_url column.');}

            while(($row=fgetcsv($file,0,',','"',''))!==false){
                if($row===[null]){continue;}
                $processed++;
                if($processed>5000){throw new \DomainException('Maximum 5000 rows per CSV import.');}
                if(count($row)!==count($header)){
                    $failed[]='Row '.($processed+1).': wrong number of columns.';
                    continue;
                }

                $r=array_map('trim',array_combine($header,$row));
                try{
                    $pageUrl=self::normalizeUrl((string)($r[$urlKey]??''));
                    if(self::host($pageUrl)!==$host){
                        throw new \DomainException('URL is outside the configured website.');
                    }

                    $title=trim((string)($r['title']??''));
                    $description=trim((string)($r['description']??''));
                    $imageUrl=trim((string)($r['image_url']??''));

                    if($title===''){
                        $meta=self::fetchPageMeta($pageUrl,$host);
                        $title=$meta['title'];
                        if($description===''){$description=$meta['description'];}
                        if($imageUrl===''){$imageUrl=$meta['image_url'];}
                    }

                    self::upsertReference($host,$pageUrl,$title,$description,$imageUrl);
                    $saved++;
                }catch(\Throwable $e){
                    \App\Core\Logger::exception(
                        $e,
                        'website-catalog',
                        ['event' => 'Website CSV row import failed', 'row' => $processed + 1],
                        'warning'
                    );
                    if(count($failed)<25){$failed[]='Row '.($processed+1).': '.$e->getMessage();}
                }
            }

            if(!$processed){throw new \DomainException('CSV contains no rows.');}
            if(!$saved){throw new \DomainException('No website references could be imported. '.($failed[0]??''));}
        }finally{
            fclose($file);
        }

        return ['processed'=>$processed,'saved'=>$saved,'failed'=>$failed];
    }

    /**
     * EN: Creates or persists the `addManual` operation (add Manual).
     * 中文：创建或持久化 `addManual`（add Manual）操作。
     */
    public static function addManual(
        string $website,
        string $pageUrl,
        string $title,
        string $description,
        string $imageUrl
    ): int {
        self::assertReady();
        $website=self::normalizeUrl($website);
        $host=self::host($website);
        $pageUrl=self::normalizeUrl($pageUrl);
        if(self::host($pageUrl)!==$host){
            throw new \DomainException('Page URL must belong to the configured company website.');
        }
        return self::upsertReference($host,$pageUrl,$title,$description,$imageUrl);
    }

    /**
     * EN: Implements the application operation `scan` (scan).
     * 中文：实现应用操作 `scan`（scan）。
     */
    public static function scan(string $website,string $sourceUrl=''): array
    {
        self::assertReady();
        $website=self::normalizeUrl($website);
        $host=self::host($website);
        $sourceUrl=trim($sourceUrl)!==''?self::normalizeUrl($sourceUrl):$website;
        if(self::host($sourceUrl)!==$host){
            throw new \DomainException('Website or sitemap URL must use the configured company website host.');
        }

        $urls=self::discoverUrls($sourceUrl,$host);
        $results=[];$saved=0;$failed=0;
        foreach(array_slice($urls,0,self::MAX_SCAN_URLS) as $url){
            try{
                $meta=self::fetchPageMeta($url,$host);
                $id=self::upsertReference(
                    $host,
                    $meta['page_url'],
                    $meta['title'],
                    $meta['description'],
                    $meta['image_url']
                );
                $saved++;
                $results[]=['url'=>$url,'ok'=>true,'id'=>$id,'title'=>$meta['title']];
            }catch(\Throwable $e){
                \App\Core\Logger::exception(
                    $e,
                    'website-catalog',
                    ['event' => 'Website page scan failed', 'url' => $url],
                    'warning'
                );
                $failed++;
                $results[]=['url'=>$url,'ok'=>false,'message'=>$e->getMessage()];
            }
        }

        return [
            'discovered'=>count($urls),
            'checked'=>count($results),
            'saved'=>$saved,
            'failed'=>$failed,
            'limited'=>count($urls)>self::MAX_SCAN_URLS,
            'results'=>$results,
        ];
    }

    /**
     * EN: Retrieves or loads data for `search` (search).
     * 中文：读取或加载 `search`（search）所需的数据。
     */
    public static function search(string $query='',int $limit=100): array
    {
        self::assertReady();
        $limit=max(1,min(200,$limit));
        $pdo=Database::connection();
        $query=trim($query);
        if($query===''){
            return $pdo->query(
                "SELECT id,source_host,page_url,title,description,image_url,sha256,checked_at,imported_at
                 FROM cdsp_website_references
                 ORDER BY imported_at DESC,id DESC
                 LIMIT {$limit}"
            )->fetchAll();
        }
        $like='%'.$query.'%';
        $q=$pdo->prepare(
            "SELECT id,source_host,page_url,title,description,image_url,sha256,checked_at,imported_at
             FROM cdsp_website_references
             WHERE title LIKE ? OR description LIKE ? OR page_url LIKE ? OR source_host LIKE ?
             ORDER BY imported_at DESC,id DESC
             LIMIT {$limit}"
        );
        $q->execute([$like,$like,$like,$like]);
        return $q->fetchAll();
    }

    /**
     * EN: Removes or cleans data/state for `deleteReference` (delete Reference).
     * 中文：删除或清理 `deleteReference`（delete Reference）相关的数据或状态。
     */
    public static function deleteReference(int $id): bool
    {
        self::assertReady();
        if($id<1){return false;}
        $q=Database::connection()->prepare('DELETE FROM cdsp_website_references WHERE id=?');
        $q->execute([$id]);
        return $q->rowCount()===1;
    }

    /**
     * EN: Builds, formats, or transforms data for `normalizeUrl` (normalize Url).
     * 中文：为 `normalizeUrl`（normalize Url）构建、格式化或转换数据。
     */
    public static function normalizeUrl(string $url): string
    {
        $url=trim($url);
        if($url===''){throw new \DomainException('Enter a website URL.');}
        if(!preg_match('~^https?://~i',$url)){$url='https://'.$url;}
        $p=parse_url($url);
        $host=strtolower((string)($p['host']??''));
        if(strlen($url)>4096||!filter_var($url,FILTER_VALIDATE_URL)
            ||strtolower((string)($p['scheme']??''))!=='https'
            ||isset($p['user'])||isset($p['pass'])
            ||(($p['port']??443)!==443)
            ||strlen($host)>191||!str_contains($host,'.')||filter_var($host,FILTER_VALIDATE_IP)){
            throw new \DomainException('Use a complete public HTTPS website URL.');
        }
        self::assertPublicHost($host);
        return $url;
    }

    /**
     * EN: Checks or validates the condition represented by `assertReady` (assert Ready).
     * 中文：检查或校验 `assertReady`（assert Ready）所表示的条件。
     */
    private static function assertReady(): void
    {
        if(!DuplicateIndex::ready()){
            throw new \DomainException('Run scripts/migrate_v0_1_70.php first.');
        }
        try{
            Database::connection()->query('SELECT description FROM cdsp_website_references LIMIT 0');
        }catch(\Throwable $e){
            // Preserve the underlying database/schema failure before converting
            // it to the stable operator-facing migration message below.
            \App\Core\Logger::exception(
                $e,
                'website-catalog',
                ['event' => 'Website reference schema readiness check failed'],
                'error'
            );
            throw new \DomainException('Run scripts/migrate_v0_1_71.php before using the website library.');
        }
    }

    /**
     * EN: Implements the application operation `upsertReference` (upsert Reference).
     * 中文：实现应用操作 `upsertReference`（upsert Reference）。
     */
    private static function upsertReference(
        string $host,
        string $pageUrl,
        string $title,
        string $description,
        string $imageUrl
    ): int {
        $title=trim($title);
        $description=trim($description);
        $imageUrl=trim($imageUrl);
        if($title===''||mb_strlen($title)>500){
            throw new \DomainException('Each title must contain 1–500 characters.');
        }
        if(mb_strlen($description)>65000){$description=mb_substr($description,0,65000);}
        if($imageUrl!==''){$imageUrl=self::normalizeUrl($imageUrl);}

        $pdo=Database::connection();
        $q=$pdo->prepare(
            "INSERT INTO cdsp_website_references
             (source_host,page_url,page_url_hash,title,description,title_hash,image_url,imported_at)
             VALUES(?,?,?,?,?,?,?,NOW())
             ON DUPLICATE KEY UPDATE
                source_host=VALUES(source_host),
                title=VALUES(title),
                description=VALUES(description),
                title_hash=VALUES(title_hash),
                sha256=IF(COALESCE(image_url,'')=COALESCE(VALUES(image_url),''),sha256,NULL),
                dhash=IF(COALESCE(image_url,'')=COALESCE(VALUES(image_url),''),dhash,NULL),
                checked_at=IF(COALESCE(image_url,'')=COALESCE(VALUES(image_url),''),checked_at,NULL),
                image_url=VALUES(image_url),
                imported_at=NOW()"
        );
        $q->execute([
            $host,$pageUrl,hash('sha256',$pageUrl),$title,$description,
            Util::hashText($title),$imageUrl!==''?$imageUrl:null
        ]);
        $find=$pdo->prepare('SELECT id FROM cdsp_website_references WHERE page_url_hash=? LIMIT 1');
        $find->execute([hash('sha256',$pageUrl)]);
        return (int)$find->fetchColumn();
    }

    /**
     * EN: Implements the application operation `discoverUrls` (discover Urls).
     * 中文：实现应用操作 `discoverUrls`（discover Urls）。
     */
    private static function discoverUrls(string $sourceUrl,string $host): array
    {
        $first=self::fetchRaw($sourceUrl,$host);
        $contentType=strtolower($first['content_type']);
        $looksXml=str_contains($contentType,'xml')
            ||preg_match('/<\s*(urlset|sitemapindex)\b/i',$first['body']);

        if($looksXml){
            return self::sitemapUrls($sourceUrl,$first['body'],$host);
        }

        $urls=[$first['effective_url']];
        libxml_use_internal_errors(true);
        $doc=new \DOMDocument();
        if(@$doc->loadHTML($first['body'])){
            $xp=new \DOMXPath($doc);
            foreach($xp->query('//a[@href]/@href')?:[] as $node){
                $absolute=self::absoluteUrl($first['effective_url'],trim((string)$node->nodeValue));
                if(!$absolute){continue;}
                try{
                    if(self::host($absolute)!==$host){continue;}
                }catch(\Throwable $e){continue;}
                $path=(string)(parse_url($absolute,PHP_URL_PATH)??'');
                if(preg_match('/\.(?:jpg|jpeg|png|gif|webp|svg|pdf|zip|css|js|xml)$/i',$path)){continue;}
                $urls[]=$absolute;
                if(count(array_unique($urls))>=self::MAX_SCAN_URLS){break;}
            }
        }
        libxml_clear_errors();
        return array_values(array_unique($urls));
    }

    /**
     * EN: Implements the application operation `sitemapUrls` (sitemap Urls).
     * 中文：实现应用操作 `sitemapUrls`（sitemap Urls）。
     */
    private static function sitemapUrls(string $sourceUrl,string $xml,string $host): array
    {
        $urls=[];$sitemaps=[];
        libxml_use_internal_errors(true);
        $doc=new \DOMDocument();
        if(!@$doc->loadXML($xml,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING)){
            libxml_clear_errors();
            throw new \DomainException('The sitemap XML could not be read.');
        }
        $xp=new \DOMXPath($doc);
        foreach($xp->query('//*[local-name()="loc"]')?:[] as $node){
            $loc=trim((string)$node->textContent);
            if($loc===''){continue;}
            // Malformed third-party sitemap URLs are rejected input, not an application failure.
            try{$loc=self::normalizeUrl($loc);}catch(\Throwable $e){continue;}
            if(self::host($loc)!==$host){continue;}
            $parent=strtolower((string)$node->parentNode?->localName);
            if($parent==='sitemap'){$sitemaps[]=$loc;}else{$urls[]=$loc;}
            if(count($urls)>=self::MAX_SCAN_URLS){break;}
        }
        libxml_clear_errors();

        foreach(array_slice(array_unique($sitemaps),0,10) as $sitemap){
            if(count($urls)>=self::MAX_SCAN_URLS){break;}
            try{
                $child=self::fetchRaw($sitemap,$host);
                libxml_use_internal_errors(true);
                $d=new \DOMDocument();
                if(@$d->loadXML($child['body'],LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING)){
                    $x=new \DOMXPath($d);
                    foreach($x->query('//*[local-name()="url"]/*[local-name()="loc"]')?:[] as $node){
                        $loc=trim((string)$node->textContent);
                        // Malformed child-sitemap URLs are rejected input, not an application failure.
                        try{$loc=self::normalizeUrl($loc);}catch(\Throwable $e){continue;}
                        if(self::host($loc)===$host){$urls[]=$loc;}
                        if(count(array_unique($urls))>=self::MAX_SCAN_URLS){break;}
                    }
                }
                libxml_clear_errors();
            }catch(\Throwable $e){
                // A child sitemap may fail without invalidating the others,
                // but the failure is still recorded for later diagnosis.
                \App\Core\Logger::exception(
                    $e,
                    'website-catalog',
                    ['event' => 'Child sitemap scan failed', 'sitemap_url' => $sitemap],
                    'warning'
                );
            }
        }

        $urls=array_values(array_unique($urls));
        if(!$urls){throw new \DomainException('No page URLs were found in this sitemap.');}
        return $urls;
    }

    /**
     * EN: Retrieves or loads data for `fetchPageMeta` (fetch Page Meta).
     * 中文：读取或加载 `fetchPageMeta`（fetch Page Meta）所需的数据。
     */
    private static function fetchPageMeta(string $url,string $host): array
    {
        $f=self::fetchRaw($url,$host);
        $contentType=strtolower($f['content_type']);
        if($contentType!==''&&!str_contains($contentType,'html')){
            throw new \DomainException('URL did not return an HTML page.');
        }

        $title='';$description='';$image='';$canonical=$f['effective_url'];
        libxml_use_internal_errors(true);
        $doc=new \DOMDocument();
        if(!@$doc->loadHTML($f['body'])){
            libxml_clear_errors();
            throw new \DomainException('Page HTML could not be read.');
        }
        $xp=new \DOMXPath($doc);
        $read=function(array $queries)use($xp):string{
            foreach($queries as $query){
                $nodes=$xp->query($query);
                if($nodes&&$nodes->length){
                    $value=trim(html_entity_decode((string)$nodes->item(0)->nodeValue,ENT_QUOTES|ENT_HTML5,'UTF-8'));
                    if($value!==''){return preg_replace('/\s+/u',' ',$value)??$value;}
                }
            }
            return '';
        };
        $title=$read(["//meta[@property='og:title']/@content","//meta[@name='twitter:title']/@content","//title/text()"]);
        $description=$read(["//meta[@property='og:description']/@content","//meta[@name='description']/@content"]);
        $image=$read(["//meta[@property='og:image:secure_url']/@content","//meta[@property='og:image']/@content","//meta[@name='twitter:image']/@content"]);
        $canonicalFound=$read(["//link[@rel='canonical']/@href","//meta[@property='og:url']/@content"]);
        libxml_clear_errors();

        if($canonicalFound!==''){
            $candidate=self::absoluteUrl($f['effective_url'],$canonicalFound);
            if($candidate){
                // A malformed third-party canonical URL is rejected input,
                // not an application failure; keep the fetched page URL.
                try{
                    if(self::host($candidate)===$host){$canonical=$candidate;}
                }catch(\Throwable $e){
                    // Malformed third-party canonical metadata is safely ignored.
                }
            }
        }
        if($image!==''){$image=self::absoluteUrl($f['effective_url'],$image)?:$image;}
        if($title===''){throw new \DomainException('Page title could not be detected.');}

        return [
            'page_url'=>$canonical,
            'title'=>$title,
            'description'=>$description,
            'image_url'=>$image,
        ];
    }

    /**
     * EN: Retrieves or loads data for `fetchRaw` (fetch Raw).
     * 中文：读取或加载 `fetchRaw`（fetch Raw）所需的数据。
     */
    private static function fetchRaw(string $url,string $host): array
    {
        $url=self::normalizeUrl($url);
        if(self::host($url)!==$host){throw new \DomainException('URL is outside the configured website.');}
        if(!function_exists('curl_init')){throw new \DomainException('PHP cURL is required for website scanning.');}

        $body='';
        $ch=curl_init($url);
        curl_setopt_array($ch,[
            CURLOPT_FOLLOWLOCATION=>true,
            CURLOPT_MAXREDIRS=>4,
            CURLOPT_CONNECTTIMEOUT=>8,
            CURLOPT_TIMEOUT=>18,
            CURLOPT_RETURNTRANSFER=>false,
            CURLOPT_HEADER=>false,
            CURLOPT_USERAGENT=>'CoolerDepot-SalesPosts/0.1.72',
            CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS,
            CURLOPT_WRITEFUNCTION=>static function($ch,$data)use(&$body){
                $body.=$data;
                return strlen($body)>self::MAX_FETCH_BYTES?0:strlen($data);
            },
        ]);
        $ok=curl_exec($ch);
        $error=curl_error($ch);
        $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
        $type=(string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE);
        $effective=(string)curl_getinfo($ch,CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        if($ok===false){
            if(strlen($body)>self::MAX_FETCH_BYTES){throw new \DomainException('Page is larger than 2.5 MB.');}
            throw new \DomainException('Website request failed'.($error!==''?': '.$error:'.'));
        }
        if($status<200||$status>=400){throw new \DomainException('Website returned HTTP '.$status.'.');}
        $effective=self::normalizeUrl($effective?:$url);
        if(self::host($effective)!==$host){throw new \DomainException('Website redirected outside the configured host.');}
        return ['body'=>$body,'content_type'=>$type,'effective_url'=>$effective];
    }

    /**
     * EN: Implements the application operation `absoluteUrl` (absolute Url).
     * 中文：实现应用操作 `absoluteUrl`（absolute Url）。
     */
    private static function absoluteUrl(string $base,string $relative): ?string
    {
        $relative=trim(html_entity_decode($relative,ENT_QUOTES|ENT_HTML5,'UTF-8'));
        if($relative===''||str_starts_with($relative,'#')||preg_match('~^(?:mailto:|tel:|javascript:)~i',$relative)){return null;}
        if(preg_match('~^https://~i',$relative)){return $relative;}
        if(str_starts_with($relative,'//')){return 'https:'.$relative;}
        $bp=parse_url($base);
        if(empty($bp['host'])){return null;}
        $origin='https://'.$bp['host'];
        if(str_starts_with($relative,'/')){return $origin.$relative;}
        $path=(string)($bp['path']??'/');
        $dir=preg_replace('~/[^/]*$~','/',$path)?:'/';
        $combined=$dir.$relative;
        $parts=[];
        foreach(explode('/',$combined) as $part){
            if($part===''||$part==='.')continue;
            if($part==='..'){array_pop($parts);continue;}
            $parts[]=$part;
        }
        return $origin.'/'.implode('/',$parts);
    }

    /**
     * EN: Implements the application operation `host` (host).
     * 中文：实现应用操作 `host`（host）。
     */
    private static function host(string $url): string
    {
        $p=parse_url($url);
        return strtolower((string)($p['host']??''));
    }

    /**
     * EN: Checks or validates the condition represented by `assertPublicHost` (assert Public Host).
     * 中文：检查或校验 `assertPublicHost`（assert Public Host）所表示的条件。
     */
    private static function assertPublicHost(string $host): void
    {
        $ips=gethostbynamel($host)?:[];
        foreach($ips as $ip){
            if(filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)===false){
                throw new \DomainException('Website host must resolve to a public address.');
            }
        }
    }
}
