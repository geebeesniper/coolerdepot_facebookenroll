<?php
/**
 * File / 文件：app/Controllers/AdminController.php
 * EN: Defines the AdminController HTTP controller and request/response actions.
 * 中文：定义 AdminController HTTP Controller 及其请求/响应操作。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Controllers;

use App\Services\HtmlNoteSanitizer;
use App\Services\PostInspector;
use App\Services\MarketplaceAccount;
use App\Core\Controller;use App\Core\Auth;use App\Core\Csrf;use App\Core\Database;use App\Models\Post;use App\Models\User;use App\Models\Location;use App\Services\UploadService;
/**
 * EN: HTTP controller for admin requests, responses, and server-side authorization.
 * 中文：负责 admin 请求、响应及服务器端权限控制的 HTTP Controller。
 */
class AdminController extends Controller{
    /**
     * EN: Handle the dashboard HTTP action for admin controller and return the appropriate response.
     * 中文：处理 admin controller 的“dashboard”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function dashboard():void{
        $admin=Auth::requireRole('admin');
        [
            'date'=>$date,
            'period'=>$period,
            'preset'=>$preset,
            'info'=>$periodInfo,
        ]=$this->dashboardRequestContext($_GET);

        $salesFilter=(int)($_GET['sales_id']??0);
        $sales=User::allSales();
        $locations=Location::allWithSalesCounts();
        $unassignedSalesCount=Location::unassignedSalesCount();
        $validSalesIds=array_map(
            static fn(array $row): int => (int)$row['id'],
            $sales
        );

        if ($salesFilter > 0
            && !in_array($salesFilter,$validSalesIds,true)) {
            $salesFilter=0;
        }

        $salesProgress=$this->formatProgressRows(
            Post::adminSalesProgress(
                $periodInfo['from'],
                $periodInfo['to']
            ),
            $periodInfo
        );
        $dashboardState=Post::adminDashboardStateRange(
            $periodInfo['from'],
            $periodInfo['to']
        );

        // The detailed table remains the selected calendar day.
        $posts=Post::adminQueue($date,$salesFilter);

        $selectedSalesName='All Sales';

        if ($salesFilter > 0) {
            foreach ($sales as $salesUser) {
                if ((int)$salesUser['id']===$salesFilter) {
                    $selectedSalesName=(string)$salesUser['display_name'];
                    break;
                }
            }
        }

        $s=Database::connection()->query(
            "SELECT
                d.*,
                p.title,
                p.canonical_url,
                p.platform,
                p.external_post_id,
                u.display_name
             FROM cdsp_deletion_requests d
             JOIN cdsp_sales_posts p ON p.id=d.post_id
             JOIN cdsp_users u ON u.id=p.sales_user_id
             WHERE d.status='pending'
             ORDER BY d.created_at DESC,d.id DESC"
        );
        $deletionRequests=$s->fetchAll();

        $this->render(
            'admin/dashboard',
            compact(
                'admin',
                'date',
                'period',
                'preset',
                'periodInfo',
                'posts',
                'sales',
                'locations',
                'unassignedSalesCount',
                'salesFilter',
                'selectedSalesName',
                'salesProgress',
                'dashboardState',
                'deletionRequests'
            )
        );
    }

    /**
     * EN: Handle the dashboard progress HTTP action for admin controller and return the appropriate response.
     * 中文：处理 admin controller 的“dashboard progress”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function dashboardProgress():void{
        Auth::requireRole('admin');

        [
            'date'=>$date,
            'period'=>$period,
            'preset'=>$preset,
            'info'=>$periodInfo,
        ]=$this->dashboardRequestContext($_GET);

        if (session_status()===PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $rows=$this->formatProgressRows(
            Post::adminSalesProgress(
                $periodInfo['from'],
                $periodInfo['to']
            ),
            $periodInfo
        );
        $state=Post::adminDashboardStateRange(
            $periodInfo['from'],
            $periodInfo['to']
        );

        header(
            'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
        );

        $this->json([
            'ok'=>true,
            'date'=>$date,
            'period'=>$period,
            'preset'=>$preset,
            'period_label'=>$periodInfo['label'],
            'period_short_label'=>$periodInfo['short_label'],
            'from'=>$periodInfo['from'],
            'to'=>$periodInfo['to'],
            'days'=>$periodInfo['days'],
            'post_count'=>$state['post_count'],
            'max_post_id'=>$state['max_post_id'],
            'rows'=>$rows,
        ]);
    }

    /**
     * EN: Handle the dashboard sales posts HTTP action for admin controller and return the appropriate response.
     * 中文：处理 admin controller 的“dashboard sales posts”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function dashboardSalesPosts():void{
        Auth::requireRole('admin');

        $salesUserId=(int)($_GET['sales_id']??0);

        [
            'date'=>$date,
            'period'=>$period,
            'preset'=>$preset,
            'info'=>$periodInfo,
        ]=$this->dashboardRequestContext($_GET);

        if ($salesUserId < 1) {
            $this->json([
                'ok'=>false,
                'message'=>'Sales user is required.',
            ],422);
        }

        $salesUser=User::find($salesUserId);

        if (!$salesUser
            || ($salesUser['role']??'')!=='sales'
            || !(int)($salesUser['active']??0)) {
            $this->json([
                'ok'=>false,
                'message'=>'Sales user was not found.',
            ],404);
        }

        $salesPeriodReview=$period==='range'
            ? null
            : $this->dashboardSalesReviewData(
                $salesUserId,
                $period,
                $periodInfo
            );

        if (session_status()===PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $posts=Post::adminSalesPostsForPeriod(
            $salesUserId,
            $periodInfo['from'],
            $periodInfo['to']
        );
        $chartRows=Post::salesChartRows(
            $salesUserId,
            $periodInfo['from'],
            $periodInfo['to']
        );
        $dailyTarget=max(
            1,
            (int)($salesUser['daily_post_target']??10)
        );

        $items=[];

        foreach ($posts as $post) {
            $status=in_array(
                ($post['current_review_status']??null),
                ['good','bad'],
                true
            )
                ? (string)$post['current_review_status']
                : null;

            $items[]=[
                'id'=>(int)$post['id'],
                'platform'=>ucfirst((string)$post['platform']),
                'title'=>(string)$post['title'],
                'description'=>(string)($post['description']??''),
                'thumbnail_url'=>!empty($post['fetched_image_url'])
                    ? (string)$post['fetched_image_url']
                    : null,
                'published_at'=>(string)$post['published_at'],
                'published_date'=>(string)$post['published_date'],
                'status'=>$status,
                'review_url'=>$GLOBALS['config']['app']['base_path']
                    .'/admin/post?id='.(int)$post['id'],
            ];
        }

        header(
            'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
        );

        $this->json([
            'ok'=>true,
            'sales'=>[
                'id'=>(int)$salesUser['id'],
                'name'=>(string)$salesUser['display_name'],
                'sales_id'=>(string)$salesUser['sales_id'],
            ],
            'period'=>$period,
            'preset'=>$preset,
            'period_label'=>$periodInfo['label'],
            'from'=>$periodInfo['from'],
            'to'=>$periodInfo['to'],
            'review'=>$salesPeriodReview,
            'posts'=>$items,
            'count'=>count($items),
            'chart_rows'=>$chartRows,
            'daily_target'=>$dailyTarget,
        ]);
    }

    /**
     * EN: Handle the dashboard save sales review HTTP action for admin controller and return the appropriate response.
     * 中文：处理 admin controller 的“dashboard save sales review”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     *
     * @throws \RuntimeException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    public function dashboardSaveSalesReview():void{
        $admin=Auth::requireRole('admin');

        if($this->requestExceedsPostMaxSize()){
            $this->json([
                'ok'=>false,
                'message'=>'Upload request exceeds PHP post_max_size ('.ini_get('post_max_size').').',
            ],413);
        }

        $this->verifyAjaxCsrf();

        $salesUserId=(int)($_POST['sales_user_id']??0);
        $date=$this->validDashboardDate(
            (string)($_POST['date']??date('Y-m-d'))
        );
        $rawPeriod=(string)($_POST['period']??'day');

        if($rawPeriod==='range'){
            $this->json([
                'ok'=>false,
                'message'=>'Custom date ranges do not have a single Sales Review.',
            ],422);
        }

        $period=$this->validDashboardPeriod($rawPeriod);
        $note=HtmlNoteSanitizer::clean(
            (string)($_POST['note']??'')
        );
        $rating=(int)($_POST['rating']??0);

        if($rating<1||$rating>5){
            $this->json([
                'ok'=>false,
                'field'=>'rating',
                'message'=>'Choose a rating from 1 to 5 stars.',
            ],422);
        }

        $salesUser=User::find($salesUserId);

        if(
            !$salesUser
            || ($salesUser['role']??'')!=='sales'
            || !(int)($salesUser['active']??0)
        ){
            $this->json([
                'ok'=>false,
                'message'=>'Sales user was not found.',
            ],404);
        }

        $periodInfo=$this->dashboardPeriodInfo(
            $date,
            $period
        );

        $pdo=Database::connection();
        $attachmentType=$period==='day'?'daily_review':'period_review';
        $hasNewAttachments=$this->hasUploadedFiles('images');

        // Save Review is versioning, not a generic submit button. If the
        // current form is identical to the latest active Sales Review and no
        // new attachment is being uploaded, do not manufacture another
        // history row. This is especially important after an Admin marks a
        // history entry as deleted: the delete is already committed by its
        // own endpoint and must not be followed by an accidental duplicate
        // save of the fallback review.
        $currentHistory=$pdo->prepare(
            "SELECT id,rating,note
             FROM cdsp_sales_review_history
             WHERE sales_user_id=?
               AND period_type=?
               AND period_start=?
               AND deleted_at IS NULL
             ORDER BY created_at DESC,id DESC
             LIMIT 1"
        );
        $currentHistory->execute([
            $salesUserId,
            $period,
            $periodInfo['from'],
        ]);
        $currentHistoryRow=$currentHistory->fetch()?:null;

        if(
            $currentHistoryRow
            && (int)($currentHistoryRow['rating']??0)===$rating
            && trim((string)($currentHistoryRow['note']??''))===trim($note)
            && !$hasNewAttachments
        ){
            $review=$this->dashboardSalesReviewData(
                $salesUserId,
                $period,
                $periodInfo
            );
            $this->json([
                'ok'=>true,
                'unchanged'=>true,
                'review'=>$review,
                'upload_warning'=>null,
                'message'=>'No Sales Review changes to save.',
            ]);
        }

        $pdo->beginTransaction();
        $reviewId=0;
        $historyId=0;

        try{
            if($period==='day'){
                $q=$pdo->prepare(
                    "INSERT INTO cdsp_daily_sales_reviews(
                        sales_user_id,work_date,admin_user_id,rating,note,reviewed_at,created_at,updated_at
                     ) VALUES(?,?,?,?,?,NOW(),NOW(),NOW())
                     ON DUPLICATE KEY UPDATE
                        admin_user_id=VALUES(admin_user_id),
                        rating=VALUES(rating),
                        note=VALUES(note),
                        reviewed_at=NOW(),
                        updated_at=NOW()"
                );
                $q->execute([$salesUserId,$periodInfo['from'],(int)$admin['id'],$rating,$note]);
                $idQuery=$pdo->prepare(
                    "SELECT id FROM cdsp_daily_sales_reviews WHERE sales_user_id=? AND work_date=? LIMIT 1"
                );
                $idQuery->execute([$salesUserId,$periodInfo['from']]);
            }else{
                $q=$pdo->prepare(
                    "INSERT INTO cdsp_period_sales_reviews(
                        sales_user_id,period_type,period_start,period_end,admin_user_id,rating,note,reviewed_at,created_at,updated_at
                     ) VALUES(?,?,?,?,?,?,?,NOW(),NOW(),NOW())
                     ON DUPLICATE KEY UPDATE
                        period_end=VALUES(period_end),
                        admin_user_id=VALUES(admin_user_id),
                        rating=VALUES(rating),
                        note=VALUES(note),
                        reviewed_at=NOW(),
                        updated_at=NOW()"
                );
                $q->execute([$salesUserId,$period,$periodInfo['from'],$periodInfo['to'],(int)$admin['id'],$rating,$note]);
                $idQuery=$pdo->prepare(
                    "SELECT id FROM cdsp_period_sales_reviews WHERE sales_user_id=? AND period_type=? AND period_start=? LIMIT 1"
                );
                $idQuery->execute([$salesUserId,$period,$periodInfo['from']]);
            }

            $reviewId=(int)$idQuery->fetchColumn();
            if($reviewId<1){
                throw new \RuntimeException('Sales Review row could not be resolved after saving.');
            }

            $history=$pdo->prepare(
                "INSERT INTO cdsp_sales_review_history(
                    sales_user_id,period_type,period_start,period_end,admin_user_id,rating,note,created_at
                 ) VALUES(?,?,?,?,?,?,?,NOW())"
            );
            $history->execute([$salesUserId,$period,$periodInfo['from'],$periodInfo['to'],(int)$admin['id'],$rating,$note]);
            $historyId=(int)$pdo->lastInsertId();
            $pdo->commit();
        }catch(\Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            \App\Core\Logger::exception(
                $e,
                'sales-review',
                ['event' => 'Sales Review database save failed'],
                'error'
            );
            $this->json([
                'ok'=>false,
                'message'=>'Sales Review save failed: '.$e->getMessage(),
            ],422);
        }

        $uploadWarning=null;
        if($hasNewAttachments){
            try{
                (new UploadService())->save(
                    $attachmentType,
                    $reviewId,
                    (int)$admin['id'],
                    'images',
                    $historyId
                );
            }catch(\Throwable $e){
                \App\Core\Logger::exception($e, 'upload', ['event' => 'Sales Review attachment upload failed'], 'warning');
                $uploadWarning=$e->getMessage();
            }
        }

        $review=$this->dashboardSalesReviewData(
            $salesUserId,
            $period,
            $periodInfo
        );

        $this->json([
            'ok'=>true,
            'review'=>$review,
            'upload_warning'=>$uploadWarning,
            'message'=>$uploadWarning
                ?$review['label'].' saved, but an attachment could not be uploaded.'
                :$review['label'].' saved.',
        ]);
    }

    /**
     * EN: Handle the dashboard delete sales review history HTTP action for admin controller and return the appropriate response.
     * 中文：处理 admin controller 的“dashboard delete sales review history”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function dashboardDeleteSalesReviewHistory():void{
        $admin=Auth::requireRole('admin');
        $this->verifyAjaxCsrf();

        $historyId=(int)($_POST['history_id']??0);

        if($historyId<1){
            $this->json([
                'ok'=>false,
                'message'=>'Sales Review history entry was not found.',
            ],404);
        }

        $pdo=Database::connection();
        $q=$pdo->prepare(
            "SELECT h.id,h.sales_user_id,h.period_type,h.period_start,h.deleted_at
             FROM cdsp_sales_review_history h
             WHERE h.id=?
             LIMIT 1"
        );
        $q->execute([$historyId]);
        $history=$q->fetch();

        if(!$history){
            $this->json([
                'ok'=>false,
                'message'=>'Sales Review history entry was not found.',
            ],404);
        }

        if(!empty($history['deleted_at'])){
            $this->json([
                'ok'=>false,
                'message'=>'This Sales Review history entry is already marked as deleted.',
            ],409);
        }

        $d=$pdo->prepare(
            "UPDATE cdsp_sales_review_history
             SET deleted_at=NOW(),deleted_by=?
             WHERE id=? AND deleted_at IS NULL"
        );
        $d->execute([(int)$admin['id'],$historyId]);

        if($d->rowCount()<1){
            $this->json([
                'ok'=>false,
                'message'=>'Sales Review history entry could not be marked as deleted.',
            ],409);
        }

        $fresh=$pdo->prepare(
            "SELECT
                h.id,h.rating,h.note,h.created_at,h.deleted_at,h.deleted_by,
                u.display_name AS admin_name,
                du.display_name AS deleted_by_name
             FROM cdsp_sales_review_history h
             JOIN cdsp_users u ON u.id=h.admin_user_id
             LEFT JOIN cdsp_users du ON du.id=h.deleted_by
             WHERE h.id=?
             LIMIT 1"
        );
        $fresh->execute([$historyId]);
        $row=$fresh->fetch()?:[];

        $period=(string)$history['period_type'];
        $periodInfo=$this->dashboardPeriodInfo(
            (string)$history['period_start'],
            $period
        );
        $review=$this->dashboardSalesReviewData(
            (int)$history['sales_user_id'],
            $period,
            $periodInfo
        );

        $this->json([
            'ok'=>true,
            'history_id'=>$historyId,
            'deleted_at'=>(string)($row['deleted_at']??''),
            'deleted_by_name'=>(string)($row['deleted_by_name']??$admin['display_name']??'Administrator'),
            'review'=>$review,
            'message'=>'Sales Review history entry marked as deleted.',
        ]);
    }

    /**
     * EN: Handle the dashboard post review HTTP action for admin controller and return the appropriate response.
     * 中文：处理 admin controller 的“dashboard post review”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function dashboardPostReview():void{
        Auth::requireRole('admin');

        $postId=(int)($_GET['id']??0);
        $post=Post::find($postId);

        if(!$post){
            $this->json([
                'ok'=>false,
                'message'=>'Post was not found.',
            ],404);
        }

        $s=Database::connection()->prepare(
            "SELECT *
             FROM cdsp_post_reviews
             WHERE post_id=?
             LIMIT 1"
        );
        $s->execute([$postId]);
        $review=$s->fetch()?:null;

        $comments=$this->postReviewComments($postId);
        $reviewHistory=$this->postReviewHistory($postId);

        $latestReviewHistory=$reviewHistory
            ? $reviewHistory[count($reviewHistory)-1]
            : null;

        $historyDecision=$latestReviewHistory
            && in_array(
                (string)($latestReviewHistory['decision']??''),
                ['good','bad'],
                true
            )
                ? (string)$latestReviewHistory['decision']
                : null;

        $attachments=$review
            ? $this->formatAttachments(
                $this->attachments(
                    'post_review',
                    (int)$review['id'],
                    true
                )
            )
            : [];

        if(session_status()===PHP_SESSION_ACTIVE){
            session_write_close();
        }

        header(
            'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
        );

        $this->json([
            'ok'=>true,
            'post'=>[
                'id'=>(int)$post['id'],
                'sales_name'=>(string)$post['display_name'],
                'sales_id'=>(string)$post['sales_id'],
                'platform'=>ucfirst((string)$post['platform']),
                'verification_status'=>(string)($post['verification_status']??'verified'),
                'published_at'=>(string)$post['published_at'],
                'published_date'=>(string)$post['published_date'],
                'external_post_id'=>(string)$post['external_post_id'],
                'platform_account_id'=>(string)($post['platform_account_id']??''),
                'platform_account_name'=>(string)($post['platform_account_name']??''),
                'platform_account_url'=>(string)($post['platform_account_url']??''),
                'canonical_url'=>(string)$post['canonical_url'],
            ],
'review'=>[
    // The last immutable Save Review event is authoritative
    // when the popup is reopened.
    'decision'=>$historyDecision
        ?: (
            in_array(
                (string)($post['admin_review_status']??''),
                ['good','bad'],
                true
            )
                ? (string)$post['admin_review_status']
                : (
                    $review
                    && in_array(
                        (string)$review['decision'],
                        ['good','bad'],
                        true
                    )
                        ? (string)$review['decision']
                        : null
                )
        ),
    'last_saved_at'=>$latestReviewHistory
        ? (string)$latestReviewHistory['created_at']
        : (
            $review
                ? (string)($review['reviewed_at']??'')
                : null
        ),
    'last_saved_by'=>$latestReviewHistory
        ? (string)$latestReviewHistory['author_name']
        : null,
    'source'=>$historyDecision
        ? 'history'
        : (
            !empty($post['admin_review_status'])
                ? 'post'
                : ($review ? 'review' : null)
        ),
],
            'review_history'=>$reviewHistory,
            'comments'=>$comments,
            'content'=>[
                'provider'=>'Saved post',
                'title'=>(string)$post['title'],
                'description'=>(string)$post['description'],
                'listing_date'=>(string)$post['published_at'],
                'price'=>null,
                'location'=>null,
                'photos'=>!empty($post['fetched_image_url'])
                    ? [(string)$post['fetched_image_url']]
                    : [],
                'fetched_at'=>(string)$post['fetched_at'],
            ],
            'attachments'=>$attachments,
        ]);
    }

    /**
     * EN: Handle the dashboard get content HTTP action for admin controller and return the appropriate response.
     * 中文：处理 admin controller 的“dashboard get content”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     *
     * @throws \RuntimeException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    public function dashboardGetContent():void{
        $admin=Auth::requireRole('admin');

        try{
            Csrf::verify($_POST['_csrf']??null);

            $postId=(int)($_POST['post_id']??0);
            $post=Post::find($postId);

            if(!$post){
                $this->json([
                    'ok'=>false,
                    'message'=>'Post was not found.',
                ],404);
            }

            $platform=strtolower((string)$post['platform']);

            $url=trim((string)(
                $post['canonical_url']
                ?: $post['submitted_url']
            ));

            if($url===''){
                $this->json([
                    'ok'=>false,
                    'message'=>'This post does not have a source URL.',
                ],422);
            }

            if(session_status()===PHP_SESSION_ACTIVE){
                session_write_close();
            }

            // Explicit Admin action: fetch the newest source content. Facebook
            // bypasses the provider cache; OfferUp/Craigslist re-fetch the live page.
            $item=(new PostInspector())->refreshExistingContent(
                (int)$admin['id'],
                $platform,
                $url
            );

            $title=trim((string)($item['title']??''));
            $description=trim((string)($item['description']??''));

            if($title==='' || $description===''){
                throw new \RuntimeException(
                    'The provider returned the listing but title or description is missing.'
                );
            }

            $platformAccount=is_array($item['platform_account']??null)
                ? $item['platform_account']
                : MarketplaceAccount::safeFromProviderResult($platform,$item,['operation'=>'admin_refresh_content']);
            if($platformAccount!==null){
                $item['platform_account']=$platformAccount;
            }

            $content=$this->marketplaceContentPreview($item);
            $firstImage=$content['photos'][0]??null;
            $imageUrl=is_string($firstImage) ? trim($firstImage) : '';
            $refreshedAssets=[];
            $imageIndexWarning=null;
            if($imageUrl!==''){
                try{
                    $refreshedAssets[]=\App\Services\ImageFingerprint::fromUrl($imageUrl);
                }catch(\Throwable $imageError){
                    // Refresh Content itself remains usable even when the remote CDN
                    // refuses the fingerprint download. The old fingerprint is still
                    // removed below so stale image data cannot create false duplicates.
                    \App\Core\Logger::exception(
                        $imageError,
                        'post-content',
                        ['event'=>'Admin refreshed image could not be fingerprinted','post_id'=>$postId],
                        'warning'
                    );
                    $imageIndexWarning='Content fetched, but the refreshed image could not be indexed for duplicate checking.';
                }
            }

            $pdo=Database::connection();
            $ownsTransaction=!$pdo->inTransaction();
            if($ownsTransaction){$pdo->beginTransaction();}
            try{
                Post::updateFetchedContent(
                    $postId,
                    $title,
                    $description,
                    $imageUrl!=='' ? $imageUrl : null,
                    $platformAccount
                );
                \App\Services\DuplicateIndex::replacePostFingerprints($postId,$refreshedAssets);
                if($ownsTransaction){$pdo->commit();}
            }catch(\Throwable $updateError){
                if($ownsTransaction&&$pdo->inTransaction()){$pdo->rollBack();}
                throw $updateError;
            }

            $this->json([
                'ok'=>true,
                'post_id'=>$postId,
                'verification_status'=>'verified',
                'content'=>$content,
                'image_indexed'=>!empty($refreshedAssets),
                'message'=>empty($content['photos'])
                    ? 'Content fetched, but no configured provider returned an image.'
                    : ($imageIndexWarning ?? 'Content and first image fetched successfully.'),
            ]);
        }catch(\Throwable $e){
            \App\Core\Logger::exception(
                $e,
                'post-content',
                ['event' => 'Admin listing content refresh failed'],
                'warning'
            );
            $this->json([
                'ok'=>false,
                'message'=>$e->getMessage()!=='' 
                    ? $e->getMessage()
                    : 'Could not fetch listing content.',
            ],422);
        }
    }

/**
 * EN: Handle the dashboard add comment HTTP action for admin controller and return the appropriate response.
 * 中文：处理 admin controller 的“dashboard add comment”HTTP 操作并返回相应响应。
 *
 * @return void No value is returned. / 无返回值。
 */
public function dashboardAddComment():void{
    $admin=Auth::requireRole('admin');

    if($this->requestExceedsPostMaxSize()){
        $this->json([
            'ok'=>false,
            'message'=>'Upload request exceeds PHP post_max_size ('
                .ini_get('post_max_size').').',
        ],413);
    }

    $this->verifyAjaxCsrf();
    $postId=(int)($_POST['post_id']??0);
    $body=HtmlNoteSanitizer::clean((string)($_POST['comment_body']??''));
    $hasImages=$this->hasUploadedFiles('comment_images');

    if(!Post::find($postId)){
        $this->json(['ok'=>false,'message'=>'Post was not found.'],404);
    }

    if(!$this->commentHasContent($body)&&!$hasImages){
        $this->json([
            'ok'=>false,
            'field'=>'comment_body',
            'message'=>'Write a note or attach an image before adding it.',
        ],422);
    }

    $pdo=Database::connection();
    $s=$pdo->prepare(
        "INSERT INTO cdsp_post_review_comments(
            post_id,admin_user_id,body_html,created_at,updated_at
         ) VALUES(?,?,?,NOW(),NOW())"
    );
    $s->execute([$postId,(int)$admin['id'],$body]);
    $commentId=(int)$pdo->lastInsertId();
    $uploadWarning=null;

    try{
        (new UploadService())->save(
            'post_comment',$commentId,(int)$admin['id'],'comment_images'
        );
    }catch(\Throwable $e){
        \App\Core\Logger::exception($e, 'upload', ['event' => 'Review comment attachment upload failed'], 'warning');
        $uploadWarning=$e->getMessage();
    }

    $this->json([
        'ok'=>true,
        'comment'=>$this->postReviewComment($commentId),
        'upload_warning'=>$uploadWarning,
        'message'=>$uploadWarning
            ? 'Note saved, but an image could not be uploaded.'
            : 'Note added.',
    ]);
}

/**
 * EN: Handle the dashboard update comment HTTP action for admin controller and return the appropriate response.
 * 中文：处理 admin controller 的“dashboard update comment”HTTP 操作并返回相应响应。
 *
 * @return void No value is returned. / 无返回值。
 */
public function dashboardUpdateComment():void{
    $admin=Auth::requireRole('admin');

    if($this->requestExceedsPostMaxSize()){
        $this->json([
            'ok'=>false,
            'message'=>'Upload request exceeds PHP post_max_size ('
                .ini_get('post_max_size').').',
        ],413);
    }

    $this->verifyAjaxCsrf();

    $commentId=(int)($_POST['comment_id']??0);
    $body=HtmlNoteSanitizer::clean(
        (string)($_POST['comment_body']??'')
    );
    $hasImages=$this->hasUploadedFiles('comment_images');

    // Include soft-deleted comments: their wording may still need correction,
    // but editing never clears deleted_at/deleted_by.
    $existing=$this->postReviewComment(
        $commentId,
        true
    );

    if(!$existing){
        $this->json([
            'ok'=>false,
            'message'=>'Comment was not found.',
        ],404);
    }

    if(
        !$this->commentHasContent($body)
        && !$hasImages
        && (int)($existing['active_attachment_count']??0)<1
    ){
        $this->json([
            'ok'=>false,
            'field'=>'comment_body',
            'message'=>'A note cannot be empty unless it has an image.',
        ],422);
    }

    $s=Database::connection()->prepare(
        "UPDATE cdsp_post_review_comments
         SET body_html=?,
             updated_by=?,
             updated_at=NOW()
         WHERE id=?"
    );

    $s->execute([
        $body,
        (int)$admin['id'],
        $commentId,
    ]);

    $uploadWarning=null;

    // A deleted comment remains deleted. Do not attach new images to a
    // deleted record; only permit text correction for audit clarity.
    if(!empty($existing['deleted'])){
        if($hasImages){
            $uploadWarning=
                'This comment is marked as deleted. Its text was updated, '
                .'but new images were not attached.';
        }
    }else{
        try{
            (new UploadService())->save(
                'post_comment',
                $commentId,
                (int)$admin['id'],
                'comment_images'
            );
        }catch(\Throwable $e){
            \App\Core\Logger::exception($e, 'upload', ['event' => 'Review attachment upload failed'], 'warning');
            $uploadWarning=$e->getMessage();
        }
    }

    $updated=$this->postReviewComment(
        $commentId,
        true
    );

    $this->json([
        'ok'=>true,
        'comment'=>$updated,
        'upload_warning'=>$uploadWarning,
        'message'=>!empty($existing['deleted'])
            ? (
                $uploadWarning
                    ? 'Deleted comment text updated. '.$uploadWarning
                    : 'Deleted comment text updated; it remains marked as deleted.'
            )
            : (
                $uploadWarning
                    ? 'Note updated, but an image could not be uploaded.'
                    : 'Note updated.'
            ),
    ]);
}

/**
 * EN: Handle the dashboard delete comment HTTP action for admin controller and return the appropriate response.
 * 中文：处理 admin controller 的“dashboard delete comment”HTTP 操作并返回相应响应。
 *
 * @return void No value is returned. / 无返回值。
 */
public function dashboardDeleteComment():void{
    $admin=Auth::requireRole('admin');
    $this->verifyAjaxCsrf();

    $commentId=(int)($_POST['comment_id']??0);

    $s=Database::connection()->prepare(
        "UPDATE cdsp_post_review_comments
         SET deleted_at=NOW(),
             deleted_by=?,
             updated_at=NOW()
         WHERE id=?
           AND deleted_at IS NULL"
    );

    $s->execute([
        (int)$admin['id'],
        $commentId,
    ]);

    if($s->rowCount()<1){
        $this->json([
            'ok'=>false,
            'message'=>'Comment was not found or was already marked as deleted.',
        ],404);
    }

    $comment=$this->postReviewComment(
        $commentId,
        true
    );

    $this->json([
        'ok'=>true,
        'comment_id'=>$commentId,
        'comment'=>$comment,
        'message'=>'Comment marked as deleted.',
    ]);
}

/**
 * EN: Handle the dashboard delete attachment HTTP action for admin controller and return the appropriate response.
 * 中文：处理 admin controller 的“dashboard delete attachment”HTTP 操作并返回相应响应。
 *
 * @return void No value is returned. / 无返回值。
 */
public function dashboardDeleteAttachment():void{
    $admin=Auth::requireRole('admin');
    $this->verifyAjaxCsrf();

    $attachmentId=(int)($_POST['attachment_id']??0);

    $s=Database::connection()->prepare(
        "SELECT *
         FROM cdsp_review_attachments
         WHERE id=?
           AND entity_type IN ('post_comment','post_review','daily_review','period_review')
         LIMIT 1"
    );
    $s->execute([$attachmentId]);
    $attachment=$s->fetch();

    if(!$attachment){
        $this->json([
            'ok'=>false,
            'message'=>'Image was not found.',
        ],404);
    }

    $base=dirname(__DIR__,2).'/storage/uploads';
    $baseReal=realpath($base);
    $storedPath=ltrim(
        str_replace('\\','/',(string)$attachment['stored_path']),
        '/'
    );
    $file=$base.'/'.$storedPath;
    $fileReal=is_file($file)
        ? realpath($file)
        : false;

    if(
        $fileReal!==false
        && (
            $baseReal===false
            || !str_starts_with(
                $fileReal,
                rtrim($baseReal,DIRECTORY_SEPARATOR)
                    .DIRECTORY_SEPARATOR
            )
        )
    ){
        $this->json([
            'ok'=>false,
            'message'=>'Image storage path failed the safety check.',
        ],422);
    }

    if(
        $fileReal!==false
        && !@unlink($fileReal)
    ){
        $this->json([
            'ok'=>false,
            'message'=>'Image file could not be deleted from storage.',
        ],500);
    }

    $d=Database::connection()->prepare(
        "DELETE FROM cdsp_review_attachments
         WHERE id=?"
    );
    $d->execute([$attachmentId]);

    if($d->rowCount()<1){
        $this->json([
            'ok'=>false,
            'message'=>'Image database record could not be deleted.',
        ],500);
    }

    $this->json([
        'ok'=>true,
        'attachment_id'=>$attachmentId,
        'entity_type'=>(string)$attachment['entity_type'],
        'entity_id'=>(int)$attachment['entity_id'],
        'message'=>'Image permanently deleted.',
    ]);
}

    /**
     * EN: Handle the dashboard editor image HTTP action for admin controller and return the appropriate response.
     * 中文：处理 admin controller 的“dashboard editor image”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     *
     * @throws \RuntimeException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    public function dashboardEditorImage():void{
        $admin=Auth::requireRole('admin');
        if($this->requestExceedsPostMaxSize()){
            $this->json(['ok'=>false,'message'=>'Upload request exceeds PHP post_max_size ('.ini_get('post_max_size').').'],413);
        }
        $this->verifyAjaxCsrf();
        $postId=(int)($_POST['post_id']??0);
        $post=Post::find($postId);
        if(!$post){$this->json(['ok'=>false,'message'=>'Post was not found.'],404);}
        try{
            $saved=(new UploadService())->save('post_note',$postId,(int)$admin['id'],'editor_image');
            if(!$saved) throw new \RuntimeException('Choose an image before uploading.');
            $image=$saved[0];
            $this->json(['ok'=>true,'image'=>[
                'id'=>(int)$image['id'],
                'name'=>(string)$image['name'],
                'url'=>$GLOBALS['config']['app']['base_path'].'/attachment?id='.(int)$image['id'],
            ]]);
        }catch(\Throwable $e){
            \App\Core\Logger::exception(
                $e,
                'upload',
                ['event' => 'Editor image upload failed'],
                'warning'
            );
            $this->json(['ok'=>false,'message'=>$e->getMessage()!==''?$e->getMessage():'Could not upload editor image.'],422);
        }
    }

    /**
     * EN: Handle the save sales target HTTP action for admin controller and return the appropriate response.
     * 中文：处理 admin controller 的“save sales target”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function saveSalesTarget():void{
        $admin=Auth::requireRole('admin');
        Csrf::verify($_POST['_csrf']??null);

        $salesUserId=(int)($_POST['sales_user_id']??0);
        $target=(int)($_POST['target']??0);

        if ($salesUserId < 1) {
            $this->json([
                'ok'=>false,
                'message'=>'Sales user is required.',
            ],422);
        }

        if ($target < 1 || $target > 999) {
            $this->json([
                'ok'=>false,
                'field'=>'target',
                'message'=>'Target must be between 1 and 999.',
            ],422);
        }

        $salesUser=User::find($salesUserId);

        if (!$salesUser
            || ($salesUser['role']??'')!=='sales'
            || !(int)($salesUser['active']??0)) {
            $this->json([
                'ok'=>false,
                'message'=>'Sales user was not found.',
            ],404);
        }

        $locationId=array_key_exists('location_id',$_POST)
            ? (int)$_POST['location_id']
            : (int)($salesUser['location_id']??0);
        $locationId=$locationId > 0 ? $locationId : null;

        if ($locationId !== null) {
            $location=Location::find($locationId);
            if (!$location || !(int)($location['active']??0)) {
                $this->json([
                    'ok'=>false,
                    'field'=>'location_id',
                    'message'=>'Selected location is not available.',
                ],422);
            }
        }

        User::setSalesSettings($salesUserId,$target,$locationId);
        $updated=User::find($salesUserId) ?: $salesUser;
        $locationCounts=Location::allWithSalesCounts();

        $this->json([
            'ok'=>true,
            'target'=>$target,
            'location_id'=>(int)($updated['location_id']??0),
            'location_name'=>(string)($updated['location_name']??''),
            'location_counts'=>array_map(
                static fn(array $row): array => [
                    'id'=>(int)$row['id'],
                    'count'=>(int)$row['sales_count'],
                ],
                $locationCounts
            ),
            'unassigned_count'=>Location::unassignedSalesCount(),
            'message'=>'Sales settings saved.',
        ]);
    }

    /**
     * EN: Handle the dashboard updates HTTP action for admin controller and return the appropriate response.
     * 中文：处理 admin controller 的“dashboard updates”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function dashboardUpdates():void{
        Auth::requireRole('admin');

        [
            'date'=>$date,
            'period'=>$period,
            'info'=>$periodInfo,
        ]=$this->dashboardRequestContext($_GET);

        if (session_status()===PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $state=Post::adminDashboardStateRange(
            $periodInfo['from'],
            $periodInfo['to']
        );

        header(
            'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
        );

        $this->json([
            'ok'=>true,
            'date'=>$date,
            'period'=>$period,
            'from'=>$periodInfo['from'],
            'to'=>$periodInfo['to'],
            'post_count'=>$state['post_count'],
            'max_post_id'=>$state['max_post_id'],
        ]);
    }

    /**
     * EN: Update the marketplace content preview operation.
     * 中文：更新“marketplace content preview”操作。
     *
     * @param array $item Current item being processed. / 当前正在处理的数据项。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
    private function marketplaceContentPreview(array $item):array{
        $raw=is_array($item['raw']??null)
            ? $item['raw']
            : [];

        $provider=trim((string)(
            $item['_provider_profile_name']
            ?? $item['provider_name']
            ?? $item['provider']
            ?? 'Provider'
        ));

        $price=$this->firstScalar([
            $raw['listingPrice']['formatted_amount_zeros_stripped']??null,
            $raw['listingPrice']['formatted_amount']??null,
            $raw['listingPrice']['amount']??null,
            $raw['price']['formatted']??null,
            $raw['price']['text']??null,
            $raw['price']??null,
            $raw['listing_price']??null,
        ]);

        $location=$this->firstScalar([
            $raw['locationText']['text']??null,
            $raw['location_name']??null,
            $raw['location']['name']??null,
            $raw['location']['text']??null,
            $raw['location']??null,
        ]);

        $photos=$this->extractMarketplacePhotos($raw);

        $platformAccount=is_array($item['platform_account']??null)
            ? $item['platform_account']
            : null;

        return [
            'provider'=>$provider!=='' ? $provider : 'Provider',
            'title'=>trim((string)($item['title']??'')),
            'description'=>trim((string)($item['description']??'')),
            'listing_date'=>trim((string)($item['published_raw']??'')),
            'price'=>$price,
            'location'=>$location,
            'photos'=>$photos,
            'platform_account'=>$platformAccount,
            'fetched_at'=>date('Y-m-d H:i:s'),
            'fallback_used'=>!empty($item['_fallback_used']),
            'fallback_reason'=>$item['_fallback_reason']??null,
        ];
    }

    /**
     * EN: Perform the first scalar operation.
     * 中文：执行“first scalar”操作。
     *
     * @param array $values Values value used by this operation. / 本操作使用的“values”参数值。
     *
     * @return ?string String result produced by this operation, or null when no value is available. / 本操作生成的字符串结果；无可用值时返回 null。
     */
    private function firstScalar(array $values):?string{
        foreach($values as $value){
            if(is_string($value)||is_numeric($value)){
                $text=trim((string)$value);

                if($text!==''){
                    return $text;
                }
            }
        }

        return null;
    }


/**
 * EN: Parse or extract the extract marketplace photos operation.
 * 中文：解析或提取“extract marketplace photos”操作。
 *
 * @param array $raw Raw value used by this operation. / 本操作使用的“raw”参数值。
 *
 * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
 */
private function extractMarketplacePhotos(array $raw):array{
    $urls=[];

    $push=function($value)use(&$urls):void{
        if(!is_string($value)){
            return;
        }

        $value=trim($value);

        if(
            $value!==''
            && str_starts_with($value,'https://')
            && !in_array($value,$urls,true)
        ){
            $urls[]=$value;
        }
    };

    $walk=function($value, ?string $key=null)use(&$walk,$push):void{
        if(is_string($value)){
            if(
                $key!==null
                && in_array(
                    strtolower($key),
                    [
                        'url',
                        'uri',
                        'src',
                        'image_url',
                        'thumbnail_url',
                        'photo_url',
                        'image',
                        'thumbnail',
                    ],
                    true
                )
            ){
                $push($value);
            }

            return;
        }

        if(!is_array($value)){
            return;
        }

        foreach($value as $childKey=>$child){
            $walk(
                $child,
                is_string($childKey) ? $childKey : null
            );
        }
    };

    foreach([
        $raw['listingPhotos']??null,
        $raw['photos']??null,
        $raw['images']??null,
        $raw['image']??null,
        $raw['image_url']??null,
        $raw['thumbnail']??null,
        $raw['thumbnail_url']??null,
        $raw,
    ] as $candidate){
        $walk($candidate);
    }

    return array_slice($urls,0,8);
}

/**
 * EN: Perform the dashboard sales review data operation.
 * 中文：执行“dashboard sales review data”操作。
 *
 * @param int $salesUserId Application or external user identifier. / 应用或外部用户 ID。
 * @param string $period Period value used by this operation. / 本操作使用的“period”参数值。
 * @param array $periodInfo Period info value used by this operation. / 本操作使用的“period info”参数值。
 *
 * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
 */
private function dashboardSalesReviewData(
    int $salesUserId,
    string $period,
    array $periodInfo
):array{
    $pdo=Database::connection();

    // Sales Review is a separate employee-performance record. The latest
    // non-deleted history save is authoritative for the visible/current
    // Sales Rating. This keeps "mark as deleted" meaningful: deleting the
    // latest save rolls back to the previous active save, and deleting every
    // save leaves the period unrated without touching Post Good/Bad data.
    $active=$pdo->prepare(
        "SELECT
            h.id AS history_id,
            h.rating,
            h.note,
            h.created_at AS reviewed_at,
            u.display_name AS admin_name
         FROM cdsp_sales_review_history h
         JOIN cdsp_users u ON u.id=h.admin_user_id
         WHERE h.sales_user_id=?
           AND h.period_type=?
           AND h.period_start=?
           AND h.deleted_at IS NULL
         ORDER BY h.created_at DESC,h.id DESC
         LIMIT 1"
    );
    $active->execute([
        $salesUserId,
        $period,
        $periodInfo['from'],
    ]);
    $row=$active->fetch()?:null;

    $label=match($period){
        'week'=>'Weekly Sales Review',
        'month'=>'Monthly Sales Review',
        default=>'Sales Review',
    };

    return [
        'id'=>$row ? (int)$row['history_id'] : null,
        'period'=>$period,
        'label'=>$label,
        'from'=>(string)$periodInfo['from'],
        'to'=>(string)$periodInfo['to'],
        'period_label'=>(string)$periodInfo['label'],
        'rating'=>$row && (int)($row['rating']??0)>0
            ? (int)$row['rating']
            : null,
        'note'=>$row ? (string)$row['note'] : '',
        'reviewed_at'=>$row ? (string)$row['reviewed_at'] : null,
        'admin_name'=>$row ? (string)$row['admin_name'] : null,
        'attachments'=>$row
            ? $this->salesReviewHistoryAttachments((int)$row['history_id'])
            : [],
        'history'=>$this->dashboardSalesReviewHistory(
            $salesUserId,
            $period,
            (string)$periodInfo['from']
        ),
        'exists'=>(bool)$row,
    ];
}


    /**
     * EN: Perform the dashboard sales review history operation.
     * 中文：执行“dashboard sales review history”操作。
     *
     * @param int $salesUserId Application or external user identifier. / 应用或外部用户 ID。
     * @param string $period Period value used by this operation. / 本操作使用的“period”参数值。
     * @param string $periodStart Period start value used by this operation. / 本操作使用的“period start”参数值。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
    private function dashboardSalesReviewHistory(
        int $salesUserId,
        string $period,
        string $periodStart
    ):array{
        $q=Database::connection()->prepare(
            "SELECT
                h.id,h.rating,h.note,h.created_at,h.deleted_at,h.deleted_by,
                u.display_name AS admin_name,
                du.display_name AS deleted_by_name
             FROM cdsp_sales_review_history h
             JOIN cdsp_users u ON u.id=h.admin_user_id
             LEFT JOIN cdsp_users du ON du.id=h.deleted_by
             WHERE h.sales_user_id=? AND h.period_type=? AND h.period_start=?
             ORDER BY h.created_at DESC,h.id DESC"
        );
        $q->execute([$salesUserId,$period,$periodStart]);
        $rows=[];
        foreach($q->fetchAll() as $row){
            $rows[]=[
                'id'=>(int)$row['id'],
                'rating'=>(int)($row['rating']??0)>0 ? (int)$row['rating'] : null,
                'note'=>(string)($row['note']??''),
                'admin_name'=>(string)$row['admin_name'],
                'created_at'=>(string)$row['created_at'],
                'deleted'=>!empty($row['deleted_at']),
                'deleted_at'=>!empty($row['deleted_at']) ? (string)$row['deleted_at'] : null,
                'deleted_by_name'=>(string)($row['deleted_by_name']??''),
                'attachments'=>$this->salesReviewHistoryAttachments((int)$row['id']),
            ];
        }
        return $rows;
    }

    /**
     * EN: Perform the sales review history attachments operation.
     * 中文：执行“sales review history attachments”操作。
     *
     * @param int $historyId Identifier of the history record or entity. / history 记录或实体的标识 ID。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
    private function salesReviewHistoryAttachments(int $historyId):array{
        $q=Database::connection()->prepare(
            "SELECT
                a.*,
                up.display_name AS uploaded_by_name,
                du.display_name AS deleted_by_name
             FROM cdsp_review_attachments a
             LEFT JOIN cdsp_users up ON up.id=a.uploaded_by
             LEFT JOIN cdsp_users du ON du.id=a.deleted_by
             WHERE a.history_id=?
               AND a.deleted_at IS NULL
             ORDER BY a.created_at,a.id"
        );
        $q->execute([$historyId]);
        return $this->formatAttachments($q->fetchAll());
    }

    /**
     * EN: Perform the dashboard request context operation.
     * 中文：执行“dashboard request context”操作。
     *
     * @param array $source Source value used by this operation. / 本操作使用的“source”参数值。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
    private function dashboardRequestContext(array $source):array{
        $rawFrom=trim((string)($source['from']??''));
        $rawTo=trim((string)($source['to']??''));
        $requestedPreset=strtolower(trim((string)($source['preset']??'')));
        $allowedPresets=['single','day','week','month','custom'];

        if(!in_array($requestedPreset,$allowedPresets,true)){
            $requestedPreset='';
        }

        $validFrom=preg_match('/^\d{4}-\d{2}-\d{2}$/',$rawFrom)===1;
        $validTo=preg_match('/^\d{4}-\d{2}-\d{2}$/',$rawTo)===1;

        if($validFrom&&$validTo){
            $today=date('Y-m-d');
            $from=$rawFrom;
            $to=$rawTo;

            if($to>$today){$to=$today;}
            if($from>$today){$from=$today;}
            if($from>$to){$from=$to;}

            $days=max(
                1,
                (int)floor(
                    (
                        strtotime($to.' 12:00:00')
                        -strtotime($from.' 12:00:00')
                    )/86400
                )+1
            );

            $fromTs=strtotime($from.' 12:00:00');
            $toTs=strtotime($to.' 12:00:00');
            $label=$from===$to
                ?date('F j, Y',$fromTs)
                :date('M j',$fromTs).' — '.date('M j, Y',$toTs);
            $preset=$requestedPreset!==''
                ?$requestedPreset
                :$this->dashboardDetectPreset($from,$to);

            // Any exact one-day selection is a day-level Sales Review context,
            // even when the user reached it through Custom. Preset controls the
            // range UI; period controls review semantics. This keeps Sales Review
            // addressable by the real Sales + work date pair.
            $period=($from===$to)
                ?'day'
                :'range';

            return [
                'date'=>$to,
                'period'=>$period,
                'preset'=>$preset,
                'info'=>[
                    'period'=>$period,
                    'from'=>$from,
                    'to'=>$to,
                    'days'=>$days,
                    'label'=>$label,
                    'short_label'=>$days===1
                        ?'Daily target'
                        :$days.'-day target',
                ],
            ];
        }

        $date=$this->validDashboardDate(
            (string)($source['date']??date('Y-m-d'))
        );
        $legacyPeriod=$this->validDashboardPeriod(
            (string)($source['period']??'day')
        );
        $preset=$requestedPreset!==''
            ?$requestedPreset
            :($legacyPeriod==='day'?'single':$legacyPeriod);

        return [
            'date'=>$date,
            'period'=>$legacyPeriod,
            'preset'=>$preset,
            'info'=>$this->dashboardPeriodInfo($date,$legacyPeriod),
        ];
    }

    /**
     * EN: Perform the dashboard detect preset operation.
     * 中文：执行“dashboard detect preset”操作。
     *
     * @param string $from From value used by this operation. / 本操作使用的“from”参数值。
     * @param string $to To value used by this operation. / 本操作使用的“to”参数值。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
     */
    private function dashboardDetectPreset(string $from,string $to):string{
        if($from===$to){return 'single';}

        foreach(['day','week','month'] as $preset){
            [$expectedFrom,$expectedTo]=$this->rollingPresetRange(
                $preset,
                $to
            );
            if($expectedFrom===$from&&$expectedTo===$to){
                return $preset;
            }
        }

        return 'custom';
    }

    /**
     * EN: Perform the rolling preset range operation.
     * 中文：执行“rolling preset range”操作。
     *
     * @param string $preset Preset value used by this operation. / 本操作使用的“preset”参数值。
     * @param string $to To value used by this operation. / 本操作使用的“to”参数值。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
    private function rollingPresetRange(string $preset,string $to):array{
        $today=date('Y-m-d');
        $to=$this->validDashboardDate($to);
        $anchor=new \DateTimeImmutable($to.' 12:00:00');

        if($preset==='single'){
            $from=$anchor;
        }elseif($preset==='day'){
            $from=$anchor->modify('-2 days');
        }elseif($preset==='week'){
            $from=$anchor->modify('-6 days');
        }elseif($preset==='month'){
            $anchorDay=(int)$anchor->format('j');
            $previousMonthStart=$anchor->modify('first day of previous month');
            $previousMonthLast=$anchor->modify('last day of previous month');
            $previousDay=min($anchorDay,(int)$previousMonthLast->format('j'));
            $from=$previousMonthStart
                ->setDate(
                    (int)$previousMonthStart->format('Y'),
                    (int)$previousMonthStart->format('m'),
                    $previousDay
                )
                ->modify('+1 day');
        }else{
            $from=$anchor;
        }

        $fromValue=$from->format('Y-m-d');
        if($fromValue>$today){$fromValue=$today;}

        return [$fromValue,$anchor->format('Y-m-d')];
    }

    /**
     * EN: Perform the valid dashboard date operation.
     * 中文：执行“valid dashboard date”操作。
     *
     * @param string $date Date value used to scope the operation. / 用于限定本操作范围的日期值。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
     */
    private function validDashboardDate(string $date):string{
        $today=date('Y-m-d');

        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)){
            return $today;
        }

        return $date>$today
            ? $today
            : $date;
    }

    /**
     * EN: Perform the valid dashboard period operation.
     * 中文：执行“valid dashboard period”操作。
     *
     * @param string $period Period value used by this operation. / 本操作使用的“period”参数值。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
     */
    private function validDashboardPeriod(string $period):string{
        return in_array($period,['day','week','month'],true)
            ? $period
            : 'day';
    }

    /**
     * EN: Perform the dashboard period info operation.
     * 中文：执行“dashboard period info”操作。
     *
     * @param string $date Date value used to scope the operation. / 用于限定本操作范围的日期值。
     * @param string $period Period value used by this operation. / 本操作使用的“period”参数值。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
    private function dashboardPeriodInfo(
        string $date,
        string $period
    ):array{
        $anchor=strtotime($date.' 12:00:00');

        if ($period==='week') {
            $daysFromMonday=(int)date('N',$anchor)-1;
            $from=date(
                'Y-m-d',
                strtotime('-'.$daysFromMonday.' days',$anchor)
            );
            $to=date(
                'Y-m-d',
                strtotime($from.' +6 days')
            );
            $days=7;
            $label=date('M j',strtotime($from))
                .' — '
                .date('M j, Y',strtotime($to));
            $shortLabel='7-day target';
        } elseif ($period==='month') {
            $from=date('Y-m-01',$anchor);
            $to=date('Y-m-t',$anchor);
            $days=(int)date('t',$anchor);
            $label=date('F Y',$anchor);
            $shortLabel=$days.'-day target';
        } else {
            $period='day';
            $from=$date;
            $to=$date;
            $days=1;
            $label=date('F j, Y',$anchor);
            $shortLabel='Daily target';
        }

        $today=date('Y-m-d');

        if($to>$today){
            $to=$today;
        }

        if($from>$today){
            $from=$today;
        }

        if($from>$to){
            $from=$to;
        }

        $days=max(
            1,
            (int)floor(
                (
                    strtotime($to.' 12:00:00')
                    -strtotime($from.' 12:00:00')
                )/86400
            )+1
        );

        if($period==='week'){
            $label=$from===$to
                ?date('M j, Y',strtotime($from))
                :date('M j',strtotime($from))
                    .' — '
                    .date('M j, Y',strtotime($to));
            $shortLabel=$days===1
                ?'Daily target'
                :$days.'-day target';
        }elseif($period==='month'){
            $shortLabel=$days===1
                ?'Daily target'
                :$days.'-day target';
        }

        return [
            'period'=>$period,
            'from'=>$from,
            'to'=>$to,
            'days'=>$days,
            'label'=>$label,
            'short_label'=>$shortLabel,
        ];
    }

    /**
     * EN: Retrieve the format progress rows operation.
     * 中文：读取“format progress rows”操作。
     *
     * @param array $rows Rows value used by this operation. / 本操作使用的“rows”参数值。
     * @param array $periodInfo Period info value used by this operation. / 本操作使用的“period info”参数值。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
    private function formatProgressRows(
        array $rows,
        array $periodInfo
    ):array{
        $days=max(1,(int)$periodInfo['days']);
        $period=(string)$periodInfo['period'];
        $from=(string)$periodInfo['from'];

        foreach ($rows as &$row) {
            $dailyTarget=max(1,(int)($row['daily_target']??10));
            $periodTarget=$dailyTarget*$days;
            $postCount=(int)($row['post_count']??0);
            $good=(int)($row['good_count']??0);
            $bad=(int)($row['bad_count']??0);

            $row['daily_target']=$dailyTarget;
            $row['period_target']=$periodTarget;
            $row['unreviewed_count']=max(
                0,
                $postCount-$good-$bad
            );
            $row['percent']=$periodTarget > 0
                ? min(
                    100,
                    (int)round(
                        ($postCount/$periodTarget)*100
                    )
                )
                : 0;
            $row['target_met']=$postCount >= $periodTarget;

            if ($period==='day') {
                $row['view_url']=
                    $GLOBALS['config']['app']['base_path']
                    .'/admin?date='.rawurlencode($from)
                    .'&sales_id='.(int)$row['sales_user_id']
                    .'#daily-posts';
            } else {
                $row['view_url']=
                    $GLOBALS['config']['app']['base_path']
                    .'/admin/reports?period='
                    .rawurlencode($period)
                    .'&start='.rawurlencode($from)
                    .'&sales_id='.(int)$row['sales_user_id'];
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * EN: Handle the post review HTTP action for admin controller and return the appropriate response.
     * 中文：处理 admin controller 的“post review”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function postReview():void{
        $admin=Auth::requireRole('admin');$post=Post::find((int)($_GET['id']??0));if(!$post){
            \App\Core\Logger::httpStatus(404,['event'=>'admin_post_review_not_found','post_id'=>(int)($_GET['id']??0)]);
            http_response_code(404);exit('Post not found');
        }
        $s=Database::connection()->prepare("SELECT * FROM cdsp_post_reviews WHERE post_id=? LIMIT 1");$s->execute([$post['id']]);$review=$s->fetch()?:null;
        $attachments=$review?$this->attachments('post_review',(int)$review['id']):[];$this->render('admin/post_review',compact('admin','post','review','attachments'));
    }
/**
 * EN: Handle the save post review HTTP action for admin controller and return the appropriate response.
 * 中文：处理 admin controller 的“save post review”HTTP 操作并返回相应响应。
 *
 * @return void No value is returned. / 无返回值。
 *
 * @throws \RuntimeException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
 * @throws \Throwable When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
 */
public function savePostReview():void{
    $admin=Auth::requireRole('admin');
    $isAjax=$this->isAjaxRequest();

    if($isAjax&&$this->requestExceedsPostMaxSize()){
        $this->json([
            'ok'=>false,
            'message'=>'Upload request exceeds PHP post_max_size ('
                .ini_get('post_max_size')
                .'). Choose a smaller image.',
        ],413);
    }

    if($isAjax){
        $this->verifyAjaxCsrf();
    }else{
        Csrf::verify($_POST['_csrf']??null);
    }

    $pid=(int)($_POST['post_id']??0);
    $decision=(string)($_POST['decision']??'');

    if(!in_array($decision,['good','bad'],true)){
        if($isAjax){
            $this->json([
                'ok'=>false,
                'field'=>'decision',
                'message'=>'Choose Good or Bad.',
            ],422);
        }

        $_SESSION['flash_error']='Choose Good or Bad.';
        $this->redirect('/admin/post?id='.$pid);
    }

    $post=Post::find($pid);

    if(!$post){
        if($isAjax){
            $this->json([
                'ok'=>false,
                'message'=>'Post was not found.',
            ],404);
        }

        \App\Core\Logger::httpStatus(404,['event'=>'admin_post_review_save_not_found','post_id'=>$pid]);
        http_response_code(404);
        exit('Post not found');
    }

    $pdo=Database::connection();
    $pdo->beginTransaction();

    try{
        $s=$pdo->prepare(
            "INSERT INTO cdsp_post_reviews(
                post_id,
                admin_user_id,
                decision,
                rating,
                note,
                reviewed_at,
                created_at,
                updated_at
             )
             VALUES(?,?,?,NULL,NULL,NOW(),NOW(),NOW())
             ON DUPLICATE KEY UPDATE
                admin_user_id=VALUES(admin_user_id),
                decision=VALUES(decision),
                rating=NULL,
                reviewed_at=NOW(),
                updated_at=NOW()"
        );

        $s->execute([
            $pid,
            (int)$admin['id'],
            $decision,
        ]);

        $s=$pdo->prepare(
            "UPDATE cdsp_sales_posts
             SET admin_review_status=?,
                 updated_at=NOW()
             WHERE id=?"
        );
        $s->execute([$decision,$pid]);

        $s=$pdo->prepare(
            "SELECT id
             FROM cdsp_post_reviews
             WHERE post_id=?
             ORDER BY updated_at DESC,id DESC
             LIMIT 1"
        );
        $s->execute([$pid]);
        $rid=(int)$s->fetchColumn();

        if($rid<1){
            throw new \RuntimeException(
                'Review row could not be resolved after saving.'
            );
        }

        $history=$pdo->prepare(
            "INSERT INTO cdsp_post_review_history(
                post_id,
                admin_user_id,
                decision,
                created_at
             )
             VALUES(?,?,?,NOW())"
        );

        $history->execute([
            $pid,
            (int)$admin['id'],
            $decision,
        ]);

        $historyId=(int)$pdo->lastInsertId();

        $pdo->commit();
    }catch(\Throwable $e){
        if($pdo->inTransaction()){
            $pdo->rollBack();
        }
        \App\Core\Logger::exception(
            $e,
            'post-review',
            ['event' => 'Post Review database save failed'],
            'error'
        );

        if($isAjax){
            $this->json([
                'ok'=>false,
                'message'=>'Review database save failed: '
                    .$e->getMessage(),
            ],422);
        }

        throw $e;
    }

    $uploadWarning=null;

    try{
        (new UploadService())->save(
            'post_review',
            $rid,
            (int)$admin['id']
        );
    }catch(\Throwable $e){
        \App\Core\Logger::exception($e, 'upload', ['event' => 'Post Review attachment upload failed'], 'warning');
        $uploadWarning=$e->getMessage();
    }

    if($isAjax){
        $attachments=$this->formatAttachments(
            $this->attachments(
                'post_review',
                $rid,
                true
            )
        );

        $this->json([
            'ok'=>true,
            'post_id'=>$pid,
            'decision'=>$decision,
            'history_event'=>$this->postReviewHistoryEvent(
                $historyId
            ),
            'attachments'=>$attachments,
            'upload_warning'=>$uploadWarning,
            'message'=>$uploadWarning
                ? 'Review saved, but an image could not be uploaded.'
                : 'Review saved.',
        ]);
    }

    $_SESSION['flash_success']=$uploadWarning
        ? 'Review saved. Image upload warning: '.$uploadWarning
        : 'Post review saved.';

    $this->redirect('/admin/post?id='.$pid);
}

    /**
     * EN: Handle the daily review HTTP action for admin controller and return the appropriate response.
     * 中文：处理 admin controller 的“daily review”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function dailyReview():void{
        Auth::requireRole('admin');
        $sid=(int)($_GET['sales_id']??0);
        $date=$this->validDashboardDate((string)($_GET['date']??date('Y-m-d')));
        $salesUser=User::find($sid);
        if(!$salesUser||($salesUser['role']??'')!=='sales'){
            \App\Core\Logger::httpStatus(404,['event'=>'admin_daily_sales_user_not_found','sales_user_id'=>$sid]);
            http_response_code(404);exit('Sales user not found');
        }
        $this->redirect('/admin?date='.rawurlencode($date).'&period=day&sales_id='.$sid.'&review=1');
    }
/**
 * EN: Handle the save daily review HTTP action for admin controller and return the appropriate response.
 * 中文：处理 admin controller 的“save daily review”HTTP 操作并返回相应响应。
 *
 * @return void No value is returned. / 无返回值。
 */
public function saveDailyReview():void{
    Auth::requireRole('admin');
    Csrf::verify($_POST['_csrf']??null);

    $sid=(int)($_POST['sales_user_id']??0);
    $date=$this->validDashboardDate(
        (string)($_POST['work_date']??date('Y-m-d'))
    );

    $_SESSION['flash_error']=
        'Daily Review now uses the unified Dashboard review. '
        .'Choose a 1–5 star rating and save it there.';

    $this->redirect(
        '/admin?date='.rawurlencode($date)
        .'&period=day'
        .'&sales_id='.$sid
        .'&review=1'
    );
}

    /**
     * EN: Handle the reports HTTP action for admin controller and return the appropriate response.
     * 中文：处理 admin controller 的“reports”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function reports():void{
        $admin=Auth::requireRole('admin');
        $sales=User::allSales();
        $context=$this->managementReportContext($_GET);
        $rows=$this->managementReportRows(
            $context['from'],
            $context['to'],
            $context['sales_user_id']
        );
        $period=$context['preset'];
        $start=$context['from'];
        $end=$context['to'];
        $salesUserId=$context['sales_user_id'];

        $this->render(
            'admin/reports',
            compact(
                'admin','period','start','end',
                'salesUserId','sales','rows'
            )
        );
    }

    /**
     * EN: Handle the reports download HTTP action for admin controller and return the appropriate response.
     * 中文：处理 admin controller 的“reports download”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function reportsDownload():void{
        Auth::requireRole('admin');
        $context=$this->managementReportContext($_GET);
        $rows=$this->managementReportRows(
            $context['from'],
            $context['to'],
            $context['sales_user_id']
        );

        $scope=$context['sales_user_id']>0
            ?'sales-'.$context['sales_user_id']
            :'all-sales';
        $filename='sales-report-'
            .$scope.'-'
            .$context['from'].'-to-'.$context['to'].'.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $out=fopen('php://output','wb');
        if($out===false){
            \App\Core\Logger::httpStatus(500,['event'=>'report_output_open_failed']);
            http_response_code(500);
            exit('Could not create report download.');
        }

        fwrite($out,"\xEF\xBB\xBF");
        fputcsv($out,[
            'Date','Sales','Total','Facebook','OfferUp','Craigslist',
            'Post Review - Good','Post Review - Bad','Post Review - Good %','Sales Review - Sales Rating'
        ]);

        foreach($rows as $row){
            $reviewed=(int)$row['good_posts']+(int)$row['bad_posts'];
            $pct=$reviewed
                ?round(((int)$row['good_posts']/$reviewed)*100,1)
                :0;
            $rating=(int)($row['daily_rating']??0);
            fputcsv($out,[
                (string)$row['work_date'],
                (string)$row['display_name'],
                (int)$row['total_posts'],
                (int)$row['facebook_posts'],
                (int)$row['offerup_posts'],
                (int)$row['craigslist_posts'],
                (int)$row['good_posts'],
                (int)$row['bad_posts'],
                $pct.'%',
                $rating>0 ? $rating.'/5' : '',
            ]);
        }

        fclose($out);
        exit;
    }

    /**
     * EN: Perform the management report context operation.
     * 中文：执行“management report context”操作。
     *
     * @param array $source Source value used by this operation. / 本操作使用的“source”参数值。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
    private function managementReportContext(array $source):array{
        $today=date('Y-m-d');
        $preset=strtolower(trim((string)($source['period']??'week')));
        if(!in_array($preset,['single','day','week','month','custom'],true)){
            $preset='week';
        }

        $to=(string)($source['to']??$source['start']??$today);
        $from=(string)($source['from']??'');
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)){$to=$today;}
        if($to>$today){$to=$today;}

        if($preset==='custom'){
            if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)){$from=$to;}
            if($from>$today){$from=$today;}
            if($from>$to){$from=$to;}
        }else{
            [$from,$to]=$this->rollingPresetRange($preset,$to);
        }

        $sid=(int)($source['sales_id']??0);
        if($sid<1){$sid=0;}

        return [
            'preset'=>$preset,
            'from'=>$from,
            'to'=>$to,
            'sales_user_id'=>$sid,
        ];
    }

    /**
     * EN: Perform the management report rows operation.
     * 中文：执行“management report rows”操作。
     *
     * @param string $from From value used by this operation. / 本操作使用的“from”参数值。
     * @param string $to To value used by this operation. / 本操作使用的“to”参数值。
     * @param int $salesUserId Application or external user identifier. / 应用或外部用户 ID。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
    private function managementReportRows(
        string $from,
        string $to,
        int $salesUserId
    ):array{
        $pdo=Database::connection();

        $salesSql="SELECT id,display_name FROM cdsp_users WHERE role='sales' AND active=1";
        $salesParams=[];
        if($salesUserId>0){
            $salesSql.=' AND id=?';
            $salesParams[]=$salesUserId;
        }
        $salesSql.=' ORDER BY display_name';
        $salesStmt=$pdo->prepare($salesSql);
        $salesStmt->execute($salesParams);
        $salesRows=$salesStmt->fetchAll();

        if(!$salesRows){
            return [];
        }

        $postParams=[$from,$to];
        $postFilter='';
        if($salesUserId>0){
            $postFilter=' AND p.sales_user_id=?';
            $postParams[]=$salesUserId;
        }

        $postSql="SELECT
                    p.sales_user_id,
                    p.published_date AS work_date,
                    COUNT(p.id) total_posts,
                    COALESCE(SUM(p.platform='facebook'),0) facebook_posts,
                    COALESCE(SUM(p.platform='offerup'),0) offerup_posts,
                    COALESCE(SUM(p.platform='craigslist'),0) craigslist_posts,
                    COALESCE(SUM(
                        COALESCE(rh.decision,p.admin_review_status,pr.decision)='good'
                    ),0) good_posts,
                    COALESCE(SUM(
                        COALESCE(rh.decision,p.admin_review_status,pr.decision)='bad'
                    ),0) bad_posts
                  FROM cdsp_sales_posts p
                  LEFT JOIN cdsp_post_reviews pr ON pr.post_id=p.id
                  LEFT JOIN (
                    SELECT h.post_id,h.decision
                    FROM cdsp_post_review_history h
                    INNER JOIN (
                        SELECT post_id,MAX(id) max_id
                        FROM cdsp_post_review_history
                        GROUP BY post_id
                    ) latest ON latest.max_id=h.id
                  ) rh ON rh.post_id=p.id
                  WHERE p.deleted_at IS NULL
                    AND p.published_date BETWEEN ? AND ?{$postFilter}
                  GROUP BY p.sales_user_id,p.published_date";
        $postStmt=$pdo->prepare($postSql);
        $postStmt->execute($postParams);
        $postMap=[];
        foreach($postStmt->fetchAll() as $row){
            $postMap[(int)$row['sales_user_id'].'|'.(string)$row['work_date']]=$row;
        }

        $reviewParams=[$from,$to];
        $reviewFilter='';
        if($salesUserId>0){
            $reviewFilter=' AND sales_user_id=?';
            $reviewParams[]=$salesUserId;
        }

        // Sales Rating is intentionally independent from Post Review.
        // Reports use the latest NON-DELETED day-level Sales Review history
        // for the exact Sales + date key. Legacy/current-row values cannot
        // leak into the report after an Admin marks the Sales Review deleted.
        $reviewStmt=$pdo->prepare(
            "SELECT
                h.id,
                h.sales_user_id,
                h.period_start AS work_date,
                h.rating,
                h.note,
                h.created_at AS reviewed_at
             FROM cdsp_sales_review_history h
             INNER JOIN (
                SELECT sales_user_id,period_start,MAX(id) AS max_id
                FROM cdsp_sales_review_history
                WHERE period_type='day'
                  AND deleted_at IS NULL
                  AND period_start BETWEEN ? AND ?{$reviewFilter}
                GROUP BY sales_user_id,period_start
             ) latest ON latest.max_id=h.id"
        );
        $reviewStmt->execute($reviewParams);
        $reviewMap=[];
        foreach($reviewStmt->fetchAll() as $row){
            $reviewMap[(int)$row['sales_user_id'].'|'.(string)$row['work_date']]=$row;
        }

        $rows=[];
        $cursor=new \DateTimeImmutable($to);
        $first=new \DateTimeImmutable($from);
        while($cursor >= $first){
            $date=$cursor->format('Y-m-d');
            foreach($salesRows as $sales){
                $sid=(int)$sales['id'];
                $key=$sid.'|'.$date;
                $stats=$postMap[$key]??[];
                $review=$reviewMap[$key]??[];
                $rows[]=[
                    'work_date'=>$date,
                    'sales_user_id'=>$sid,
                    'display_name'=>(string)$sales['display_name'],
                    'total_posts'=>(int)($stats['total_posts']??0),
                    'facebook_posts'=>(int)($stats['facebook_posts']??0),
                    'offerup_posts'=>(int)($stats['offerup_posts']??0),
                    'craigslist_posts'=>(int)($stats['craigslist_posts']??0),
                    'good_posts'=>(int)($stats['good_posts']??0),
                    'bad_posts'=>(int)($stats['bad_posts']??0),
                    'daily_rating'=>(int)($review['rating']??0),
                    'daily_review_note'=>(string)($review['note']??''),
                    'daily_reviewed_at'=>(string)($review['reviewed_at']??''),
                ];
            }
            $cursor=$cursor->modify('-1 day');
        }

        return $rows;
    }

/**
 * EN: Handle the save period review HTTP action for admin controller and return the appropriate response.
 * 中文：处理 admin controller 的“save period review”HTTP 操作并返回相应响应。
 *
 * @return void No value is returned. / 无返回值。
 */
public function savePeriodReview():void{
    Auth::requireRole('admin');
    Csrf::verify($_POST['_csrf']??null);

    $sid=(int)($_POST['sales_user_id']??0);
    $type=(string)($_POST['period_type']??'week');

    if(!in_array($type,['week','month'],true)){
        $type='week';
    }

    $start=$this->validDashboardDate(
        (string)($_POST['period_start']??date('Y-m-d'))
    );

    $_SESSION['flash_error']=
        ucfirst($type)
        .' Review now uses the unified Dashboard review. '
        .'Choose a 1–5 star rating and save it there.';

    $this->redirect(
        '/admin?date='.rawurlencode($start)
        .'&period='.rawurlencode($type)
        .'&sales_id='.$sid
        .'&review=1'
    );
}

    /**
     * EN: Handle the handle delete request HTTP action for admin controller and return the appropriate response.
     * 中文：处理 admin controller 的“handle delete request”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     *
     * @throws \RuntimeException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    public function handleDeleteRequest():void{
        $admin=Auth::requireRole('admin');
        $isAjax=$this->isAjaxRequest();
        if($isAjax){
            $this->verifyAjaxCsrf();
        }else{
            Csrf::verify($_POST['_csrf']??null);
        }

        $id=(int)($_POST['request_id']??0);
        $action=(string)($_POST['action']??'');
        if(!in_array($action,['approve','reject'],true)){
            if($isAjax){
                $this->json(['ok'=>false,'message'=>'Choose Approve delete or Reject.'],422);
            }
            $_SESSION['flash_error']='Choose Approve delete or Reject.';
            $this->redirect('/admin');
        }

        $pdo=Database::connection();
        $pdo->beginTransaction();
        try{
            $s=$pdo->prepare("SELECT * FROM cdsp_deletion_requests WHERE id=? AND status='pending' FOR UPDATE");
            $s->execute([$id]);
            $r=$s->fetch();
            if(!$r)throw new \RuntimeException('Request not found');

            if($action==='approve'){
                // Approval is a permanent delete. Remove the post and its
                // post-level dependent records so deleted content cannot
                // participate in later duplicate checks or searches.
                Post::hardDelete((int)$r['post_id']);
                $status='approved';
            }else{
                $status='rejected';
                $s=$pdo->prepare("UPDATE cdsp_deletion_requests SET status='rejected',reviewed_by=?,reviewed_at=NOW(),updated_at=NOW() WHERE id=?");
                $s->execute([(int)$admin['id'],$id]);
            }
            $pdo->commit();
        }catch(\Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            \App\Core\Logger::exception(
                $e,
                'delete-request',
                ['event' => 'Admin deletion-request action failed'],
                'error'
            );
            if($isAjax){
                $this->json(['ok'=>false,'message'=>$e->getMessage()],422);
            }
            $_SESSION['flash_error']=$e->getMessage();
            $this->redirect('/admin');
        }

        $message=$status==='approved'
            ?'Post permanently deleted.'
            :'Deletion request rejected.';

        if($isAjax){
            $this->json([
                'ok'=>true,
                'request_id'=>$id,
                'status'=>$status,
                'message'=>$message,
            ]);
        }

        $_SESSION['flash_success']=$message;
        $this->redirect('/admin');
    }

    /**
     * EN: Handle the delete post HTTP action for admin controller and return the appropriate response.
     * 中文：处理 admin controller 的“delete post”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function deletePost():void{
        Auth::requireRole('admin');
        $this->verifyAjaxCsrf();
        $postId=(int)($_POST['post_id']??0);
        try{
            Post::hardDelete($postId);
            $this->json([
                'ok'=>true,
                'post_id'=>$postId,
                'message'=>'Post permanently deleted.',
            ]);
        }catch(\DomainException $e){
            $this->json(['ok'=>false,'message'=>$e->getMessage()],404);
        }catch(\Throwable $e){
            \App\Core\Logger::exception($e, 'admin', ['event' => 'Hard delete failed'], 'error');
            $this->json(['ok'=>false,'message'=>'Post could not be deleted.'],500);
        }
    }
    /**
     * EN: Check or validate the is ajax request operation.
     * 中文：检查或验证“is ajax request”操作。
     *
     * @return bool True when the requested condition is satisfied; otherwise false. / 请求条件满足时返回 true，否则返回 false。
     */
    private function isAjaxRequest():bool{
        return strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH']??''))==='xmlhttprequest'
            || str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT']??'')),'application/json');
    }

    /**
     * EN: Check or validate the verify ajax csrf operation.
     * 中文：检查或验证“verify ajax csrf”操作。
     *
     * @return void No value is returned. / 无返回值。
     */
    private function verifyAjaxCsrf():void{
        $token=(string)($_POST['_csrf']??'');$sessionToken=(string)($_SESSION['_csrf']??'');
        if($token===''||$sessionToken===''||!hash_equals($sessionToken,$token)){
            $this->json(['ok'=>false,'message'=>'Security token expired. Refresh the dashboard and try again.'],419);
        }
    }

    /**
     * EN: Send or process the request exceeds post max size operation.
     * 中文：发送或处理“request exceeds post max size”操作。
     *
     * @return bool True when the requested condition is satisfied; otherwise false. / 请求条件满足时返回 true，否则返回 false。
     */
    private function requestExceedsPostMaxSize():bool{
        $contentLength=(int)($_SERVER['CONTENT_LENGTH']??0);if($contentLength<1)return false;
        $max=$this->iniBytes((string)ini_get('post_max_size'));
        return $max>0&&$contentLength>$max;
    }

    /**
     * EN: Perform the ini bytes operation.
     * 中文：执行“ini bytes”操作。
     *
     * @param string $value Value processed or stored by this operation. / 本操作处理或保存的值。
     *
     * @return int Numeric result produced by this operation. / 本操作生成的数字结果。
     */
    private function iniBytes(string $value):int{
        $value=trim($value);if($value==='')return 0;$unit=strtolower(substr($value,-1));$number=(float)$value;
        return match($unit){'g'=>(int)round($number*1024*1024*1024),'m'=>(int)round($number*1024*1024),'k'=>(int)round($number*1024),default=>(int)$number};
    }

/**
 * EN: Perform the post review history operation.
 * 中文：执行“post review history”操作。
 *
 * @param int $postId Sales post identifier. / 销售 Post ID。
 *
 * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
 */
private function postReviewHistory(int $postId):array{
    $s=Database::connection()->prepare(
        "SELECT
            h.id,
            h.post_id,
            h.admin_user_id,
            h.decision,
            h.created_at,
            u.display_name AS author_name
         FROM cdsp_post_review_history h
         JOIN cdsp_users u
           ON u.id=h.admin_user_id
         WHERE h.post_id=?
         ORDER BY h.created_at ASC,h.id ASC"
    );

    $s->execute([$postId]);

    $rows=[];

    foreach($s->fetchAll() as $row){
        $rows[]=$this->formatPostReviewHistoryEvent($row);
    }

    return $rows;
}

/**
 * EN: Perform the post review history event operation.
 * 中文：执行“post review history event”操作。
 *
 * @param int $historyId Identifier of the history record or entity. / history 记录或实体的标识 ID。
 *
 * @return ?array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
 */
private function postReviewHistoryEvent(int $historyId):?array{
    if($historyId<1){
        return null;
    }

    $s=Database::connection()->prepare(
        "SELECT
            h.id,
            h.post_id,
            h.admin_user_id,
            h.decision,
            h.created_at,
            u.display_name AS author_name
         FROM cdsp_post_review_history h
         JOIN cdsp_users u
           ON u.id=h.admin_user_id
         WHERE h.id=?
         LIMIT 1"
    );

    $s->execute([$historyId]);
    $row=$s->fetch();

    return $row
        ? $this->formatPostReviewHistoryEvent($row)
        : null;
}

/**
 * EN: Retrieve the format post review history event operation.
 * 中文：读取“format post review history event”操作。
 *
 * @param array $row Database or structured data row being processed. / 正在处理的数据库或结构化数据行。
 *
 * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
 */
private function formatPostReviewHistoryEvent(array $row):array{
    return [
        'id'=>(int)$row['id'],
        'post_id'=>(int)$row['post_id'],
        'author_id'=>(int)$row['admin_user_id'],
        'author_name'=>(string)$row['author_name'],
        'decision'=>(string)$row['decision'],
        'created_at'=>(string)$row['created_at'],
        'activity_type'=>'review_saved',
        'decision_only'=>true,
    ];
}

/**
 * EN: Perform the post review comments operation.
 * 中文：执行“post review comments”操作。
 *
 * @param int $postId Sales post identifier. / 销售 Post ID。
 *
 * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
 */
private function postReviewComments(int $postId):array{
    $s=Database::connection()->prepare(
        "SELECT
            c.id,
            c.post_id,
            c.admin_user_id,
            c.body_html,
            c.created_at,
            c.updated_at,
            c.updated_by,
            c.deleted_at,
            c.deleted_by,
            u.display_name AS author_name,
            uu.display_name AS updated_by_name,
            du.display_name AS deleted_by_name
         FROM cdsp_post_review_comments c
         JOIN cdsp_users u
           ON u.id=c.admin_user_id
         LEFT JOIN cdsp_users uu
           ON uu.id=c.updated_by
         LEFT JOIN cdsp_users du
           ON du.id=c.deleted_by
         WHERE c.post_id=?
         ORDER BY c.created_at ASC,c.id ASC"
    );

    $s->execute([$postId]);

    $rows=[];

    foreach($s->fetchAll() as $row){
        $rows[]=$this->formatPostReviewComment($row);
    }

    return $rows;
}

/**
 * EN: Perform the post review comment operation.
 * 中文：执行“post review comment”操作。
 *
 * @param int $commentId Identifier of the comment record or entity. / comment 记录或实体的标识 ID。
 * @param bool $includeDeleted Include deleted value used by this operation. / 本操作使用的“include deleted”参数值。
 *
 * @return ?array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
 */
private function postReviewComment(
    int $commentId,
    bool $includeDeleted=false
):?array{
    $sql=
        "SELECT
            c.id,
            c.post_id,
            c.admin_user_id,
            c.body_html,
            c.created_at,
            c.updated_at,
            c.updated_by,
            c.deleted_at,
            c.deleted_by,
            u.display_name AS author_name,
            uu.display_name AS updated_by_name,
            du.display_name AS deleted_by_name
         FROM cdsp_post_review_comments c
         JOIN cdsp_users u
           ON u.id=c.admin_user_id
         LEFT JOIN cdsp_users uu
           ON uu.id=c.updated_by
         LEFT JOIN cdsp_users du
           ON du.id=c.deleted_by
         WHERE c.id=?";

    if(!$includeDeleted){
        $sql.=" AND c.deleted_at IS NULL";
    }

    $sql.=" LIMIT 1";

    $s=Database::connection()->prepare($sql);
    $s->execute([$commentId]);
    $row=$s->fetch();

    return $row
        ? $this->formatPostReviewComment($row)
        : null;
}

/**
 * EN: Retrieve the format post review comment operation.
 * 中文：读取“format post review comment”操作。
 *
 * @param array $row Database or structured data row being processed. / 正在处理的数据库或结构化数据行。
 *
 * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
 */
private function formatPostReviewComment(array $row):array{
    $created=(string)$row['created_at'];
    $updated=(string)$row['updated_at'];
    $deletedAt=(string)($row['deleted_at']??'');
    $attachments=$this->formatAttachments(
        $this->attachments(
            'post_comment',
            (int)$row['id'],
            true
        )
    );

    $activeAttachmentCount=0;

    foreach($attachments as $attachment){
        if(empty($attachment['deleted'])){
            $activeAttachmentCount++;
        }
    }

    $edited=(
        !empty($row['updated_by'])
        || (
            $created!==''
            && $updated!==''
            && $created!==$updated
            && $deletedAt===''
        )
    );

    return [
        'id'=>(int)$row['id'],
        'post_id'=>(int)$row['post_id'],
        'author_id'=>(int)$row['admin_user_id'],
        'author_name'=>(string)$row['author_name'],
        'body_html'=>(string)$row['body_html'],
        'created_at'=>$created,
        'updated_at'=>$updated,
        'updated_by_name'=>(string)($row['updated_by_name']??''),
        'edited'=>$edited,
        'deleted'=>$deletedAt!=='',
        'deleted_at'=>$deletedAt!=='' ? $deletedAt : null,
        'deleted_by_name'=>(string)($row['deleted_by_name']??''),
        'attachments'=>$attachments,
        'active_attachment_count'=>$activeAttachmentCount,
        'activity_type'=>'comment',
    ];
}

/**
 * EN: Perform the comment has content operation.
 * 中文：执行“comment has content”操作。
 *
 * @param string $html HTML content processed by the operation. / 本操作处理的 HTML 内容。
 *
 * @return bool True when the requested condition is satisfied; otherwise false. / 请求条件满足时返回 true，否则返回 false。
 */
private function commentHasContent(string $html):bool{
    if(trim(strip_tags($html))!==''){
        return true;
    }

    return stripos($html,'<img')!==false;
}

/**
 * EN: Check or validate the has uploaded files operation.
 * 中文：检查或验证“has uploaded files”操作。
 *
 * @param string $field Field value used by this operation. / 本操作使用的“field”参数值。
 *
 * @return bool True when the requested condition is satisfied; otherwise false. / 请求条件满足时返回 true，否则返回 false。
 */
private function hasUploadedFiles(string $field):bool{
    if(empty($_FILES[$field])) return false;
    $errors=$_FILES[$field]['error']??UPLOAD_ERR_NO_FILE;
    if(!is_array($errors)) return (int)$errors!==UPLOAD_ERR_NO_FILE;
    foreach($errors as $error){
        if((int)$error!==UPLOAD_ERR_NO_FILE) return true;
    }
    return false;
}

/**
 * EN: Retrieve the format attachment operation.
 * 中文：读取“format attachment”操作。
 *
 * @param array $attachment Attachment value used by this operation. / 本操作使用的“attachment”参数值。
 *
 * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
 */
private function formatAttachment(array $attachment):array{
    return [
        'id'=>(int)$attachment['id'],
        'name'=>(string)$attachment['original_name'],
        'mime'=>(string)$attachment['mime_type'],
        'size'=>(int)$attachment['size_bytes'],
        'url'=>$GLOBALS['config']['app']['base_path']
            .'/attachment?id='
            .(int)$attachment['id'],
        'uploaded_at'=>(string)$attachment['created_at'],
        'uploaded_by_name'=>(string)($attachment['uploaded_by_name']??''),
        'deleted'=>!empty($attachment['deleted_at']),
        'deleted_at'=>!empty($attachment['deleted_at'])
            ? (string)$attachment['deleted_at']
            : null,
        'deleted_by_name'=>(string)($attachment['deleted_by_name']??''),
    ];
}

/**
 * EN: Retrieve the format attachments operation.
 * 中文：读取“format attachments”操作。
 *
 * @param array $items Items value used by this operation. / 本操作使用的“items”参数值。
 *
 * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
 */
private function formatAttachments(array $items):array{
    $result=[];

    foreach($items as $attachment){
        $result[]=$this->formatAttachment($attachment);
    }

    return $result;
}

/**
 * EN: Perform the attachment by id operation.
 * 中文：执行“attachment by id”操作。
 *
 * @param int $id Identifier of the record record or entity. / record 记录或实体的标识 ID。
 *
 * @return ?array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
 */
private function attachmentById(int $id):?array{
    $s=Database::connection()->prepare(
        "SELECT
            a.*,
            up.display_name AS uploaded_by_name,
            du.display_name AS deleted_by_name
         FROM cdsp_review_attachments a
         LEFT JOIN cdsp_users up
           ON up.id=a.uploaded_by
         LEFT JOIN cdsp_users du
           ON du.id=a.deleted_by
         WHERE a.id=?
         LIMIT 1"
    );

    $s->execute([$id]);
    $row=$s->fetch();

    return $row ?: null;
}

/**
 * EN: Perform the attachments operation.
 * 中文：执行“attachments”操作。
 *
 * @param string $type Type value used by this operation. / 本操作使用的“type”参数值。
 * @param int $id Identifier of the record record or entity. / record 记录或实体的标识 ID。
 * @param bool $includeDeleted Include deleted value used by this operation. / 本操作使用的“include deleted”参数值。
 *
 * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
 */
private function attachments(
    string $type,
    int $id,
    bool $includeDeleted=false
):array{
    $sql=
        "SELECT
            a.*,
            up.display_name AS uploaded_by_name,
            du.display_name AS deleted_by_name
         FROM cdsp_review_attachments a
         LEFT JOIN cdsp_users up
           ON up.id=a.uploaded_by
         LEFT JOIN cdsp_users du
           ON du.id=a.deleted_by
         WHERE a.entity_type=?
           AND a.entity_id=?";

    if(!$includeDeleted){
        $sql.=" AND a.deleted_at IS NULL";
    }

    $sql.=" ORDER BY a.created_at,a.id";

    $s=Database::connection()->prepare($sql);
    $s->execute([$type,$id]);

    return $s->fetchAll();
}

}
