<?php
/**
 * File / 文件：app/Services/WebsiteCatalog.php
 * EN: Defines the WebsiteCatalog service used by application business, security, or provider integration flows.
 * 中文：定义 WebsiteCatalog 服务，用于应用业务、安全或 Provider 集成流程。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Services;

use App\Core\Database;
use App\Core\Util;

/**
 * EN: Application service that encapsulates website catalog business, security, or integration behavior.
 * 中文：封装 website catalog 业务、安全或外部集成行为的应用服务。
 */
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
     * EN: Create or store the import csv operation implemented by website catalog.
     * 中文：创建或保存 website catalog 实现的“import csv”操作。
     *
     * @param string $path Filesystem, route, or data path used by the operation. / 本操作使用的文件、路由或数据路径。
     * @param string $website Website value used by this operation. / 本操作使用的“website”参数值。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     *
     * @throws \DomainException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
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
     * EN: Create or store the add manual operation implemented by website catalog.
     * 中文：创建或保存 website catalog 实现的“add manual”操作。
     *
     * @param string $website Website value used by this operation. / 本操作使用的“website”参数值。
     * @param string $pageUrl Page url value used by this operation. / 本操作使用的“page url”参数值。
     * @param string $title Title value used by this operation. / 本操作使用的“title”参数值。
     * @param string $description Description value used by this operation. / 本操作使用的“description”参数值。
     * @param string $imageUrl Image url value used by this operation. / 本操作使用的“image url”参数值。
     *
     * @return int Numeric result produced by this operation. / 本操作生成的数字结果。
     *
     * @throws \DomainException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
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
     * EN: Execute the scan operation implemented by website catalog.
     * 中文：执行 website catalog 实现的“scan”操作。
     *
     * @param string $website Website value used by this operation. / 本操作使用的“website”参数值。
     * @param string $sourceUrl Source url value used by this operation. / 本操作使用的“source url”参数值。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     *
     * @throws \DomainException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
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
     * EN: Perform the search operation implemented by website catalog.
     * 中文：执行 website catalog 实现的“search”操作。
     *
     * @param string $query Query value used by this operation. / 本操作使用的“query”参数值。
     * @param int $limit Maximum number of records or items to process. / 允许处理的最大记录或数据项数量。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
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
     * EN: Delete or clean the delete reference operation implemented by website catalog.
     * 中文：删除或清理 website catalog 实现的“delete reference”操作。
     *
     * @param int $id Identifier of the record record or entity. / record 记录或实体的标识 ID。
     *
     * @return bool True when the requested condition is satisfied; otherwise false. / 请求条件满足时返回 true，否则返回 false。
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
     * EN: Normalize or format the normalize url operation implemented by website catalog.
     * 中文：规范化或格式化 website catalog 实现的“normalize url”操作。
     *
     * @param string $url URL to validate, resolve, fetch, or process. / 需要验证、解析、抓取或处理的 URL。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
     *
     * @throws \DomainException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
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
     * EN: Check or validate the assert ready operation implemented by website catalog.
     * 中文：检查或验证 website catalog 实现的“assert ready”操作。
     *
     * @return void No value is returned. / 无返回值。
     *
     * @throws \DomainException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
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
     * EN: Perform the upsert reference operation implemented by website catalog.
     * 中文：执行 website catalog 实现的“upsert reference”操作。
     *
     * @param string $host Host name used by the operation. / 本操作使用的主机名。
     * @param string $pageUrl Page url value used by this operation. / 本操作使用的“page url”参数值。
     * @param string $title Title value used by this operation. / 本操作使用的“title”参数值。
     * @param string $description Description value used by this operation. / 本操作使用的“description”参数值。
     * @param string $imageUrl Image url value used by this operation. / 本操作使用的“image url”参数值。
     *
     * @return int Numeric result produced by this operation. / 本操作生成的数字结果。
     *
     * @throws \DomainException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
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
     * EN: Perform the discover urls operation implemented by website catalog.
     * 中文：执行 website catalog 实现的“discover urls”操作。
     *
     * @param string $sourceUrl Source url value used by this operation. / 本操作使用的“source url”参数值。
     * @param string $host Host name used by the operation. / 本操作使用的主机名。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
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
     * EN: Perform the sitemap urls operation implemented by website catalog.
     * 中文：执行 website catalog 实现的“sitemap urls”操作。
     *
     * @param string $sourceUrl Source url value used by this operation. / 本操作使用的“source url”参数值。
     * @param string $xml Xml value used by this operation. / 本操作使用的“xml”参数值。
     * @param string $host Host name used by the operation. / 本操作使用的主机名。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     *
     * @throws \DomainException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
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
     * EN: Retrieve the fetch page meta operation implemented by website catalog.
     * 中文：读取 website catalog 实现的“fetch page meta”操作。
     *
     * @param string $url URL to validate, resolve, fetch, or process. / 需要验证、解析、抓取或处理的 URL。
     * @param string $host Host name used by the operation. / 本操作使用的主机名。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     *
     * @throws \DomainException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
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
     * EN: Retrieve the fetch raw operation implemented by website catalog.
     * 中文：读取 website catalog 实现的“fetch raw”操作。
     *
     * @param string $url URL to validate, resolve, fetch, or process. / 需要验证、解析、抓取或处理的 URL。
     * @param string $host Host name used by the operation. / 本操作使用的主机名。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     *
     * @throws \DomainException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
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
     * EN: Build the absolute url operation implemented by website catalog.
     * 中文：构建 website catalog 实现的“absolute url”操作。
     *
     * @param string $base Base URL path removed before route matching. / 路由匹配前需要移除的基础 URL 路径。
     * @param string $relative Relative value used by this operation. / 本操作使用的“relative”参数值。
     *
     * @return ?string String result produced by this operation, or null when no value is available. / 本操作生成的字符串结果；无可用值时返回 null。
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
     * EN: Perform the host operation implemented by website catalog.
     * 中文：执行 website catalog 实现的“host”操作。
     *
     * @param string $url URL to validate, resolve, fetch, or process. / 需要验证、解析、抓取或处理的 URL。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
     */
    private static function host(string $url): string
    {
        $p=parse_url($url);
        return strtolower((string)($p['host']??''));
    }

    /**
     * EN: Check or validate the assert public host operation implemented by website catalog.
     * 中文：检查或验证 website catalog 实现的“assert public host”操作。
     *
     * @param string $host Host name used by the operation. / 本操作使用的主机名。
     *
     * @return void No value is returned. / 无返回值。
     *
     * @throws \DomainException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
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
