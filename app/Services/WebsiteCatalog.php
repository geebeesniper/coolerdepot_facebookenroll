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
use App\Models\Setting;

/**
 * EN: Application service that encapsulates website catalog business, security, or integration behavior.
 * 中文：封装 website catalog 业务、安全或外部集成行为的应用服务。
 */
class WebsiteCatalog
{
    private const MAX_FETCH_BYTES = 2500000;
    private const MAX_SCAN_URLS = 75;
    private const PRODUCT_SCAN_BATCH = 8;
    private const PRODUCT_SCAN_LINK_LIMIT = 1000;

    /**
     * Return all configured company website sources. The legacy single URL is
     * automatically adopted so existing installations gain multi-site support
     * without a database migration.
     * 返回全部公司网站来源；旧版单一 URL 会自动纳入，升级无需数据库 migration。
     *
     * @return array<int,array{url:string,host:string}>
     */
    public static function sources(): array
    {
        $urls=[];
        $raw=(string)Setting::get('company_website_sources_json','');
        $decoded=$raw!==''?json_decode($raw,true):null;
        if(is_array($decoded)){
            foreach($decoded as $value){
                if(!is_string($value)||trim($value)===''){continue;}
                try{$urls[]=self::normalizeUrl($value);}catch(\Throwable $e){}
            }
        }
        $legacy=trim((string)Setting::get('company_website_url',''));
        if($legacy!==''){
            try{$urls[]=self::normalizeUrl($legacy);}catch(\Throwable $e){}
        }
        $out=[];$seen=[];
        foreach($urls as $url){
            $host=self::host($url);
            if(isset($seen[$host])){continue;}
            $seen[$host]=true;
            $out[]=['url'=>$url,'host'=>$host];
        }
        return $out;
    }

    /**
     * Add or update one company website source, keyed by host.
     * 新增或更新一个公司网站来源；同一 host 只保留一条。
     */
    public static function addSource(string $url,int $adminId): array
    {
        $url=self::normalizeUrl($url);$host=self::host($url);
        $sources=self::sources();$found=false;
        foreach($sources as &$source){
            if($source['host']===$host){$source=['url'=>$url,'host'=>$host];$found=true;break;}
        }
        unset($source);
        if(!$found){$sources[]=['url'=>$url,'host'=>$host];}
        self::saveSources($sources,$adminId);
        return ['url'=>$url,'host'=>$host];
    }

    /**
     * Ensure a company website source exists before a browser-driven scan.
     * Existing matching hosts are reused without rewriting settings on every
     * AJAX batch; a new host is saved once and then becomes crawlable.
     * 在浏览器连续扫描前确保网站来源存在；同一 host 已存在时不会在每个 AJAX
     * 批次重复写 Settings，新 host 只保存一次。
     *
     * @return array{url:string,host:string}
     */
    public static function ensureSource(string $url,int $adminId): array
    {
        $url=self::normalizeUrl($url);
        $host=self::host($url);
        foreach(self::sources() as $source){
            if((string)$source['host']!==$host){continue;}
            if((string)$source['url']!==$url){
                return self::addSource($url,$adminId);
            }
            return ['url'=>$url,'host'=>$host];
        }
        return self::addSource($url,$adminId);
    }


    /**
     * Resolve the website source directly from any page/sitemap URL. The admin
     * no longer has to choose a separate Website source in Step 2/3 because
     * the URL already identifies the host unambiguously.
     * 从任意页面 / sitemap URL 自动识别网站来源；Step 2/3 无需再手工选择来源。
     *
     * @return array{url:string,host:string}
     */
    public static function ensureSourceForUrl(string $url,int $adminId): array
    {
        $url=self::normalizeUrl($url);
        $host=self::host($url);
        return self::ensureSource('https://'.$host.'/', $adminId);
    }

    /**
     * Read only enough of an uploaded CSV to determine the website host from
     * its first valid url/page_url row, then ensure that website exists in the
     * Step 1 website list. The actual importer still validates every row and
     * rejects rows from a different host.
     * 仅读取 CSV 的首个有效 URL 来自动确定网站；实际导入仍逐行校验同一 host。
     *
     * @return array{url:string,host:string}
     */
    public static function inferCsvSource(string $path,int $adminId): array
    {
        if(!is_file($path)||filesize($path)>5*1024*1024){
            throw new \DomainException('Upload a CSV file smaller than 5 MB.');
        }
        $file=fopen($path,'rb');
        if(!$file){throw new \DomainException('CSV could not be opened.');}
        try{
            $header=fgetcsv($file,0,',','"','');
            if(!$header){throw new \DomainException('CSV is empty.');}
            $header=array_map(
                static fn($value)=>strtolower(trim(ltrim((string)$value,"\xEF\xBB\xBF"))),
                $header
            );
            $urlKey=in_array('page_url',$header,true)?'page_url':(in_array('url',$header,true)?'url':null);
            if(!$urlKey){throw new \DomainException('CSV must include a url or page_url column.');}
            $urlIndex=array_search($urlKey,$header,true);
            while(($row=fgetcsv($file,0,',','"',''))!==false){
                if($row===[null]||!is_int($urlIndex)||!array_key_exists($urlIndex,$row)){continue;}
                $candidate=trim((string)$row[$urlIndex]);
                if($candidate===''){continue;}
                try{return self::ensureSourceForUrl($candidate,$adminId);}catch(\DomainException $e){continue;}
            }
        }finally{
            fclose($file);
        }
        throw new \DomainException('CSV contains no valid public HTTPS URL to identify its website.');
    }

    /**
     * Remove one configured website source and every indexed URL/product that
     * belongs to that source host. The settings update and reference deletion
     * share one transaction so a partial website removal is never exposed.
     * 删除一个网站来源，并同时删除该 host 关联的全部已扫描 URL / 产品记录；
     * 设置更新和引用删除使用同一事务，避免出现只删一半的状态。
     *
     * @return int Number of related website-reference rows deleted. / 删除的关联 URL / 产品记录数量。
     */
    public static function removeSource(string $host,int $adminId): int
    {
        self::assertReady();
        $host=strtolower(trim($host));
        if($host===''){throw new \DomainException('Choose a website source to delete.');}

        $sources=self::sources();
        $exists=false;
        foreach($sources as $source){
            if((string)$source['host']===$host){$exists=true;break;}
        }
        if(!$exists){throw new \DomainException('Website source was not found.');}

        $remaining=array_values(array_filter(
            $sources,
            static fn($source)=>(string)$source['host']!==$host
        ));

        $pdo=Database::connection();
        $started=!$pdo->inTransaction();
        if($started){$pdo->beginTransaction();}
        try{
            self::saveSources($remaining,$adminId);
            $delete=$pdo->prepare('DELETE FROM cdsp_website_references WHERE source_host=?');
            $delete->execute([$host]);
            $deleted=$delete->rowCount();
            if($started){$pdo->commit();}
            return (int)$deleted;
        }catch(\Throwable $e){
            if($started&&$pdo->inTransaction()){$pdo->rollBack();}
            throw $e;
        }
    }

    /**
     * Return one configured website source by host. / 按 host 返回一个已配置的网站来源。
     *
     * @return array{url:string,host:string}|null
     */
    public static function source(string $host): ?array
    {
        $host=strtolower(trim($host));
        foreach(self::sources() as $source){
            if((string)$source['host']===$host){return $source;}
        }
        return null;
    }

    /**
     * Return product-library counts grouped by source host.
     * 返回按网站 host 分组的产品库统计。
     */
    public static function sourceStats(): array
    {
        self::assertReady();$stats=[];
        $rows=Database::connection()->query(
            "SELECT source_host,COUNT(*) total,
                    COALESCE(SUM(image_url IS NOT NULL AND image_url<>''),0) images_found,
                    COALESCE(SUM(sha256 IS NOT NULL),0) indexed,MAX(imported_at) last_imported
             FROM cdsp_website_references GROUP BY source_host"
        )->fetchAll();
        foreach($rows as $row){$stats[(string)$row['source_host']]=$row;}
        return $stats;
    }

    /**
     * Scan a small browser-driven batch of pages and return newly discovered
     * product/category URLs. Repeated AJAX batches let Admin scan large sites
     * without one long-running PHP request.
     * 扫描一小批页面并返回新发现的产品/分类 URL；浏览器分批调用，避免单个 PHP 请求超时。
     *
     * @param string $website Configured company website root. / 已配置公司网站根地址。
     * @param array $urls URLs to inspect in this batch. / 本批次需要检查的 URL。
     * @return array Structured batch result. / 批次扫描结果。
     */
    public static function scanProductBatch(string $website,array $urls): array
    {
        self::assertReady();
        if(!class_exists(\DOMDocument::class)){throw new \DomainException('PHP DOM extension is required for website product scanning.');}
        $website=self::normalizeUrl($website);$host=self::host($website);
        $allowed=false;
        foreach(self::sources() as $source){if($source['host']===$host){$allowed=true;break;}}
        if(!$allowed){throw new \DomainException('This website is not in Company Website Sources.');}

        $urls=array_values(array_slice(array_unique(array_filter(array_map('strval',$urls))),0,self::PRODUCT_SCAN_BATCH));
        if(!$urls){$urls=self::productScanSeeds($website);}
        $checked=0;$products=0;$imagesFound=0;$indexed=0;$failed=0;$discovered=[];$results=[];
        foreach($urls as $url){
            try{
                $url=self::normalizeUrl($url);
                if(self::host($url)!==$host){continue;}
                $f=self::fetchRaw($url,$host);$checked++;
                $type=strtolower($f['content_type']);
                if(str_contains($type,'xml')||preg_match('/<\s*(urlset|sitemapindex)\b/i',$f['body'])){
                    $links=self::extractSitemapLinks($f['body'],$host);
                    $discovered=array_merge($discovered,$links);
                    $results[]=['url'=>$url,'kind'=>'sitemap','ok'=>true,'found'=>count($links)];
                    continue;
                }
                $meta=self::extractProductMeta($f['body'],$f['effective_url'],$host);
                if($meta!==null){
                    $id=self::upsertReference($host,$meta['page_url'],$meta['title'],$meta['description'],$meta['image_url']);
                    $products++;
                    $imageIndexed=false;
                    if($meta['image_url']!==''){
                        $imagesFound++;
                        $imageIndexed=self::indexReferenceImage($id,$meta['image_url'],$meta['page_url']);
                        if($imageIndexed){$indexed++;}
                    }
                    $results[]=['url'=>$url,'kind'=>'product','ok'=>true,'id'=>$id,'title'=>$meta['title'],'image_url'=>$meta['image_url'],'image_indexed'=>$imageIndexed];
                }else{
                    $results[]=['url'=>$url,'kind'=>'navigation','ok'=>true];
                }
                $discovered=array_merge($discovered,self::discoverProductLinksFromHtml($f['body'],$f['effective_url'],$host));
            }catch(\Throwable $e){
                $failed++;
                \App\Core\Logger::exception($e,'website-catalog',['event'=>'Product website batch page failed','url'=>(string)$url],'warning');
                $results[]=['url'=>(string)$url,'kind'=>'error','ok'=>false,'message'=>$e->getMessage()];
            }
        }
        $discovered=array_values(array_unique($discovered));
        if(count($discovered)>self::PRODUCT_SCAN_LINK_LIMIT){$discovered=array_slice($discovered,0,self::PRODUCT_SCAN_LINK_LIMIT);}
        return ['checked'=>$checked,'products'=>$products,'images_found'=>$imagesFound,'indexed'=>$indexed,'failed'=>$failed,'discovered'=>$discovered,'results'=>$results];
    }


    /**
     * Return true when this exact normalized page URL is already stored for the website.
     * Used by repeat scans to avoid re-downloading product pages that are already indexed.
     */
    public static function referenceUrlExists(string $host,string $pageUrl): bool
    {
        self::assertReady();
        $host=strtolower(trim($host));
        if($host===''){return false;}
        try{$pageUrl=self::normalizeUrl($pageUrl);}catch(\Throwable $e){return false;}
        $q=Database::connection()->prepare(
            'SELECT 1 FROM cdsp_website_references WHERE source_host=? AND page_url_hash=? LIMIT 1'
        );
        $q->execute([$host,hash('sha256',$pageUrl)]);
        return (bool)$q->fetchColumn();
    }

    /**
     * Return true only for URLs that are clearly product-detail pages.
     * Category/listing/navigation URLs must always be fetched on repeat scans so
     * newly added products can still be discovered.
     */
    public static function isProductDetailUrl(string $url): bool
    {
        $path=strtolower((string)(parse_url($url,PHP_URL_PATH)??'/'));
        if(preg_match('~/(?:products?/details?|product|item)/[^/]+~',$path)){return true;}
        // CoolerDepot-style detail route: /products/details/<id>/...
        if(str_contains($path,'/products/details/')){return true;}
        return false;
    }

    /**
     * Stable crawl priority used by the persisted queue.
     * Higher numbers are consumed first so discovered product details do not
     * sit behind hundreds of category/listing pages.
     */
    public static function crawlPriority(string $url): int
    {
        return self::productUrlPriority($url);
    }

    /** Persist multi-site configuration while keeping the legacy first URL. */
    private static function saveSources(array $sources,int $adminId): void
    {
        $urls=[];
        foreach($sources as $source){if(!empty($source['url'])){$urls[]=(string)$source['url'];}}
        Setting::set('company_website_sources_json',json_encode(array_values($urls),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:'[]',$adminId);
        if($urls){Setting::set('company_website_url',$urls[0],$adminId);}else{Setting::delete('company_website_url');}
    }

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
    public static function search(string $query='',int $limit=100,string $sourceHost=''): array
    {
        self::assertReady();
        $limit=max(1,min(200,$limit));
        $pdo=Database::connection();
        $query=trim($query);
        $sourceHost=strtolower(trim($sourceHost));

        $where=[];$params=[];
        if($sourceHost!==''){
            $where[]='source_host=?';
            $params[]=$sourceHost;
        }
        if($query!==''){
            $like='%'.$query.'%';
            $where[]='(title LIKE ? OR description LIKE ? OR page_url LIKE ? OR image_url LIKE ?)';
            array_push($params,$like,$like,$like,$like);
        }

        $sql="SELECT id,source_host,page_url,title,description,image_url,sha256,checked_at,imported_at
              FROM cdsp_website_references";
        if($where){$sql.=' WHERE '.implode(' AND ',$where);}
        $sql.=" ORDER BY imported_at DESC,id DESC LIMIT {$limit}";

        if(!$params){return $pdo->query($sql)->fetchAll();}
        $q=$pdo->prepare($sql);$q->execute($params);return $q->fetchAll();
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
    /** Build conservative starting URLs for a product crawl. / 构建产品扫描的保守起始 URL。 */
    public static function productScanSeeds(string $website): array
    {
        $p=parse_url($website);$origin='https://'.($p['host']??'');
        return array_values(array_unique([
            $website,
            $origin.'/products',
            $origin.'/shop',
            $origin.'/collections',
            $origin.'/sitemap.xml',
            $origin.'/sitemap_index.xml',
            $origin.'/sitemap-index.xml',
            $origin.'/wp-sitemap.xml'
        ]));
    }

    /** Extract product or navigation links from one HTML page. / 从一个 HTML 页面发现产品或分页链接。 */
    private static function discoverProductLinksFromHtml(string $html,string $base,string $host): array
    {
        $links=[];libxml_use_internal_errors(true);$doc=new \DOMDocument();
        if(@$doc->loadHTML($html)){
            $xp=new \DOMXPath($doc);
            foreach($xp->query('//a[@href]/@href')?:[] as $node){
                $absolute=self::absoluteUrl($base,trim((string)$node->nodeValue));
                if(!$absolute){continue;}
                $absolute=preg_replace('/#.*$/','',$absolute)??$absolute;
                try{if(self::host($absolute)!==$host){continue;}}catch(\Throwable $e){continue;}
                if(!self::shouldCrawlProductUrl($absolute)){continue;}
                $links[]=$absolute;
                if(count($links)>=self::PRODUCT_SCAN_LINK_LIMIT){break;}
            }
        }
        libxml_clear_errors();
        usort($links,static fn($a,$b)=>self::productUrlPriority($b)<=>self::productUrlPriority($a));
        return array_values(array_unique($links));
    }

    /** Decide whether a same-host URL is relevant to product discovery. / 判断 URL 是否与产品发现相关。 */
    private static function shouldCrawlProductUrl(string $url): bool
    {
        $path=strtolower((string)(parse_url($url,PHP_URL_PATH)??'/'));
        $query=strtolower((string)(parse_url($url,PHP_URL_QUERY)??''));
        if(preg_match('/\.(?:jpe?g|png|gif|webp|svg|pdf|zip|css|js|ico|woff2?|ttf)$/i',$path)){return false;}
        if(preg_match('~/(?:cart|checkout|account|login|logout|privacy|terms|contact)(?:/|$)~',$path)){return false;}
        if(preg_match('~/(?:products?|product|shop|collections?|catalog|categories?|category|items?)(?:/|$)~',$path)){return true;}
        if(preg_match('~/(?:product-category|product-tag)(?:/|$)~',$path)){return true;}
        // Common pagination routes used by product/category listings.
        if(preg_match('~/(?:products?|shop|collections?|catalog|product-category|category)(?:/[^?]*)?/page/\d+/?$~',$path)){return true;}
        return (bool)preg_match('/(?:^|&)(?:page|p|pg|pageno)=\d+/',$query);
    }

    /** Prioritize detail URLs before navigation pages. / 优先处理产品详情 URL。 */
    private static function productUrlPriority(string $url): int
    {
        $path=strtolower((string)(parse_url($url,PHP_URL_PATH)??''));
        if(str_contains($path,'/products/details/')){return 120;}
        if(preg_match('~/(?:products?/details?|product|item)/[^/]+~',$path)){return 110;}
        if(preg_match('~/(?:products?|shop|collections?|catalog|product-category|category)(?:/[^?]*)?/page/\d+/?$~',$path)){return 85;}
        if(preg_match('~/(?:products?|shop|collections?|catalog|product-category|category)/~',$path)){return 70;}
        if(str_ends_with($path,'.xml')){return 60;}
        return 40;
    }

    /** Parse one product page and return title + first image only. / 解析产品页，只返回标题与第一张产品图。 */
    private static function extractProductMeta(string $html,string $effectiveUrl,string $host): ?array
    {
        libxml_use_internal_errors(true);$doc=new \DOMDocument();
        if(!@$doc->loadHTML($html)){libxml_clear_errors();return null;}
        $xp=new \DOMXPath($doc);$product=null;
        foreach($xp->query('//script[@type="application/ld+json"]')?:[] as $script){
            $decoded=json_decode(trim((string)$script->textContent),true);
            if($decoded===null){continue;}
            $product=self::findProductNode($decoded);
            if($product!==null){break;}
        }
        $read=function(array $queries)use($xp):string{
            foreach($queries as $query){$nodes=$xp->query($query);if($nodes&&$nodes->length){$v=trim(html_entity_decode((string)$nodes->item(0)->nodeValue,ENT_QUOTES|ENT_HTML5,'UTF-8'));if($v!==''){return preg_replace('/\s+/u',' ',$v)??$v;}}}
            return '';
        };
        $path=strtolower((string)(parse_url($effectiveUrl,PHP_URL_PATH)??''));
        $ogType=strtolower($read(["//meta[@property='og:type']/@content"]));
        $isProduct=$product!==null||str_contains($ogType,'product')||preg_match('~/(?:products?/details?|product|item)/[^/]+~',$path);
        if(!$isProduct){libxml_clear_errors();return null;}

        $title='';$description='';$image='';$canonical=$effectiveUrl;
        if(is_array($product)){
            $title=trim((string)($product['name']??''));
            $description=trim((string)($product['description']??''));
            $image=self::firstJsonImage($product['image']??null);
            $candidate=$product['url']??null;if(is_string($candidate)&&trim($candidate)!==''){$canonical=self::absoluteUrl($effectiveUrl,$candidate)?:$canonical;}
        }
        if($title===''){$title=$read(["//meta[@property='og:title']/@content","//main//h1[1]/text()","//h1[1]/text()","//title/text()"]);}
        if($description===''){$description=$read(["//meta[@property='og:description']/@content","//meta[@name='description']/@content"]);}
        if($image===''){$image=$read(["//meta[@property='og:image:secure_url']/@content","//meta[@property='og:image']/@content","//meta[@name='twitter:image']/@content"]);}
        if($image===''){$image=self::firstDomProductImage($xp,$effectiveUrl,$title);}
        if($image===''){$image=self::firstInlineImage($html);}
        $canonicalFound=$read(["//link[@rel='canonical']/@href","//meta[@property='og:url']/@content"]);
        libxml_clear_errors();
        if($canonicalFound!==''){$candidate=self::absoluteUrl($effectiveUrl,$canonicalFound);if($candidate){try{if(self::host($candidate)===$host){$canonical=$candidate;}}catch(\Throwable $e){}}}
        try{$canonical=self::normalizeUrl($canonical);}catch(\Throwable $e){$canonical=$effectiveUrl;}
        if(self::host($canonical)!==$host){$canonical=$effectiveUrl;}
        if($image!==''){$image=self::absoluteUrl($effectiveUrl,$image)?:$image;try{$image=self::normalizeUrl($image);}catch(\Throwable $e){$image='';}}
        if($title===''||mb_strlen($title)>500){return null;}
        return ['page_url'=>$canonical,'title'=>$title,'description'=>$description,'image_url'=>$image];
    }

    /** Recursively locate a schema.org Product node. / 递归查找 schema.org Product 节点。 */
    private static function findProductNode($value): ?array
    {
        if(!is_array($value)){return null;}
        $type=$value['@type']??null;$types=is_array($type)?$type:[$type];
        foreach($types as $candidate){if(is_string($candidate)&&strcasecmp($candidate,'Product')===0){return $value;}}
        foreach($value as $child){if(is_array($child)){if($found=self::findProductNode($child)){return $found;}}}
        return null;
    }

    /** Return only the first image from JSON-LD image structures. / 仅取 JSON-LD 中第一张图片。 */
    private static function firstJsonImage($value): string
    {
        if(is_string($value)){return trim($value);}
        if(!is_array($value)){return '';}
        if(isset($value['url'])&&is_string($value['url'])){return trim($value['url']);}
        if(isset($value['contentUrl'])&&is_string($value['contentUrl'])){return trim($value['contentUrl']);}
        foreach($value as $child){$found=self::firstJsonImage($child);if($found!==''){return $found;}}
        return '';
    }


    /**
     * Locate the first real product image from common lazy-load/gallery attributes.
     * 从常见 lazy-load / gallery 属性中寻找第一张真实产品图。
     */
    private static function firstDomProductImage(\DOMXPath $xp,string $base,string $title=''): string
    {
        $queries=[
            "//main//*[contains(translate(@class,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),'product')]//img",
            "//*[contains(translate(@class,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),'product-image')]//img",
            "//*[contains(translate(@class,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),'gallery')]//img",
            "//main//img",
            "//img",
        ];
        $attrs=['data-zoom-image','data-original','data-src','data-lazy-src','data-large','data-image','src'];
        foreach($queries as $query){
            $nodes=$xp->query($query);if(!$nodes){continue;}
            foreach($nodes as $node){
                foreach($attrs as $attr){
                    $candidate=trim((string)$node->attributes?->getNamedItem($attr)?->nodeValue);
                    if($candidate===''){continue;}
                    $candidate=self::absoluteUrl($base,$candidate)?:$candidate;
                    if(self::looksLikeProductImage($candidate)){return $candidate;}
                }
                $srcset=trim((string)$node->attributes?->getNamedItem('srcset')?->nodeValue);
                if($srcset!==''){
                    $parts=array_map('trim',explode(',',$srcset));
                    for($i=count($parts)-1;$i>=0;$i--){
                        $candidate=preg_split('/\s+/',trim($parts[$i]))[0]??'';
                        $candidate=self::absoluteUrl($base,$candidate)?:$candidate;
                        if(self::looksLikeProductImage($candidate)){return $candidate;}
                    }
                }
            }
        }
        return '';
    }

    /** Read one likely product-image URL from serialized page scripts. / 从页面脚本中读取一个可能的产品图片 URL。 */
    private static function firstInlineImage(string $html): string
    {
        if(preg_match_all('~https?:\\?/\\?/[^\"\'<>\s]+?\.(?:jpe?g|png|webp)(?:\?[^\"\'<>\s]*)?~i',$html,$m)){
            foreach($m[0] as $candidate){
                $candidate=str_replace('\/','/',$candidate);
                if(self::looksLikeProductImage($candidate)){return $candidate;}
            }
        }
        return '';
    }

    /** Reject logos/placeholders/icons before fingerprinting. / 在建立指纹前排除 logo、占位图和图标。 */
    private static function looksLikeProductImage(string $url): bool
    {
        $url=trim(html_entity_decode($url,ENT_QUOTES|ENT_HTML5,'UTF-8'));
        if($url===''||str_starts_with($url,'data:')){return false;}
        $lower=strtolower($url);
        if(preg_match('~(?:logo|placeholder|loading|spinner|sprite|favicon|icon)[-_/.]~',$lower)){return false;}
        return (bool)preg_match('~^https://~i',$url);
    }

    /** Extract crawlable URLs from a sitemap or sitemap index. / 从 sitemap 或 sitemap index 提取可扫描 URL。 */
    private static function extractSitemapLinks(string $xml,string $host): array
    {
        $links=[];libxml_use_internal_errors(true);$doc=new \DOMDocument();
        if(@$doc->loadXML($xml,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING)){
            $xp=new \DOMXPath($doc);
            foreach($xp->query('//*[local-name()="loc"]')?:[] as $node){
                $loc=trim((string)$node->textContent);if($loc===''){continue;}
                try{$loc=self::normalizeUrl($loc);if(self::host($loc)!==$host){continue;}}catch(\Throwable $e){continue;}
                $path=strtolower((string)(parse_url($loc,PHP_URL_PATH)??''));
                if(str_ends_with($path,'.xml')||self::shouldCrawlProductUrl($loc)){$links[]=$loc;}
                if(count($links)>=self::PRODUCT_SCAN_LINK_LIMIT){break;}
            }
        }
        libxml_clear_errors();
        return array_values(array_unique($links));
    }

    /** Download/index the first product image for exact SHA-256 comparison. / 下载并索引第一张产品图，用于精确 SHA-256 查重。 */
    private static function indexReferenceImage(int $id,string $imageUrl,string $pageUrl=''): bool
    {
        try{
            $fp=ImageFingerprint::fromUrl($imageUrl,$pageUrl);
            $q=Database::connection()->prepare('UPDATE cdsp_website_references SET sha256=?,dhash=?,checked_at=NOW() WHERE id=?');
            $q->execute([$fp['sha256'],$fp['dhash']??null,$id]);
            return true;
        }catch(\Throwable $e){
            \App\Core\Logger::exception($e,'website-catalog',['event'=>'Website product image indexing failed','reference_id'=>$id],'warning');
            return false;
        }
    }

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
