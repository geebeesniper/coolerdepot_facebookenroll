<?php
namespace App\Controllers;

use App\Services\HtmlNoteSanitizer;
use App\Services\FacebookMarketplaceProviderChain;
use App\Core\Controller;use App\Core\Auth;use App\Core\Csrf;use App\Core\Database;use App\Models\Post;use App\Models\User;use App\Services\UploadService;
class AdminController extends Controller{
    public function dashboard():void{
        $admin=Auth::requireRole('admin');
        [
            'date'=>$date,
            'period'=>$period,
            'info'=>$periodInfo,
        ]=$this->dashboardRequestContext($_GET);

        $salesFilter=(int)($_GET['sales_id']??0);
        $sales=User::allSales();
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
            "SELECT d.*,p.title,u.display_name
             FROM cdsp_deletion_requests d
             JOIN cdsp_sales_posts p ON p.id=d.post_id
             JOIN cdsp_users u ON u.id=p.sales_user_id
             WHERE d.status='pending'
             ORDER BY d.created_at"
        );
        $deletionRequests=$s->fetchAll();

        $this->render(
            'admin/dashboard',
            compact(
                'admin',
                'date',
                'period',
                'periodInfo',
                'posts',
                'sales',
                'salesFilter',
                'selectedSalesName',
                'salesProgress',
                'dashboardState',
                'deletionRequests'
            )
        );
    }

    public function dashboardProgress():void{
        Auth::requireRole('admin');

        [
            'date'=>$date,
            'period'=>$period,
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

    public function dashboardSalesPosts():void{
        Auth::requireRole('admin');

        $salesUserId=(int)($_GET['sales_id']??0);

        [
            'date'=>$date,
            'period'=>$period,
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
            'period_label'=>$periodInfo['label'],
            'from'=>$periodInfo['from'],
            'to'=>$periodInfo['to'],
            'review'=>$salesPeriodReview,
            'posts'=>$items,
            'count'=>count($items),
        ]);
    }

    public function dashboardSaveSalesReview():void{
        $admin=Auth::requireRole('admin');
        $this->verifyAjaxCsrf();

        $salesUserId=(int)($_POST['sales_user_id']??0);
        $date=$this->validDashboardDate(
            (string)($_POST['date']??date('Y-m-d'))
        );
        $rawPeriod=(string)($_POST['period']??'day');

        if($rawPeriod==='range'){
            $this->json([
                'ok'=>false,
                'message'=>'Custom date ranges do not have a single period review.',
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
        $pdo->beginTransaction();

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
            }

            $history=$pdo->prepare(
                "INSERT INTO cdsp_sales_review_history(
                    sales_user_id,period_type,period_start,period_end,admin_user_id,rating,note,created_at
                 ) VALUES(?,?,?,?,?,?,?,NOW())"
            );
            $history->execute([$salesUserId,$period,$periodInfo['from'],$periodInfo['to'],(int)$admin['id'],$rating,$note]);
            $pdo->commit();
        }catch(\Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            $this->json([
                'ok'=>false,
                'message'=>'Sales review save failed: '.$e->getMessage(),
            ],422);
        }

        $review=$this->dashboardSalesReviewData(
            $salesUserId,
            $period,
            $periodInfo
        );

        $this->json([
            'ok'=>true,
            'review'=>$review,
            'message'=>$review['label'].' saved.',
        ]);
    }

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
                'published_at'=>(string)$post['published_at'],
                'published_date'=>(string)$post['published_date'],
                'external_post_id'=>(string)$post['external_post_id'],
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

            if($platform!=='facebook'){
                $this->json([
                    'ok'=>false,
                    'message'=>'Get Content currently uses the configured Facebook Marketplace provider chain for Facebook posts.',
                ],422);
            }

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

            // Explicit Admin action: force a real provider request instead of
            // serving the 10-minute provider cache.
            $item=(new FacebookMarketplaceProviderChain())->fetch(
                $url,
                (int)$admin['id'],
                true,
                true
            );

            $title=trim((string)($item['title']??''));
            $description=trim((string)($item['description']??''));

            if($title==='' || $description===''){
                throw new \RuntimeException(
                    'The provider returned the listing but title or description is missing.'
                );
            }

            $content=$this->marketplaceContentPreview($item);
            $firstImage=$content['photos'][0]??null;

            Post::updateFetchedContent(
                $postId,
                $title,
                $description,
                is_string($firstImage) ? $firstImage : null
            );

            $this->json([
                'ok'=>true,
                'post_id'=>$postId,
                'content'=>$content,
                'message'=>empty($content['photos'])
                    ? 'Content fetched, but no configured provider returned an image.'
                    : 'Content and first image fetched successfully.',
            ]);
        }catch(\Throwable $e){
            $this->json([
                'ok'=>false,
                'message'=>$e->getMessage()!=='' 
                    ? $e->getMessage()
                    : 'Could not fetch listing content.',
            ],422);
        }
    }

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

public function dashboardDeleteAttachment():void{
    $admin=Auth::requireRole('admin');
    $this->verifyAjaxCsrf();

    $attachmentId=(int)($_POST['attachment_id']??0);

    $s=Database::connection()->prepare(
        "SELECT *
         FROM cdsp_review_attachments
         WHERE id=?
           AND entity_type IN ('post_comment','post_review')
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
            $this->json(['ok'=>false,'message'=>$e->getMessage()!==''?$e->getMessage():'Could not upload editor image.'],422);
        }
    }

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

        User::setDailyPostTarget($salesUserId,$target);

        $this->json([
            'ok'=>true,
            'target'=>$target,
            'message'=>'Daily target saved.',
        ]);
    }

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

        return [
            'provider'=>$provider!=='' ? $provider : 'Provider',
            'title'=>trim((string)($item['title']??'')),
            'description'=>trim((string)($item['description']??'')),
            'listing_date'=>trim((string)($item['published_raw']??'')),
            'price'=>$price,
            'location'=>$location,
            'photos'=>$photos,
            'fetched_at'=>date('Y-m-d H:i:s'),
            'fallback_used'=>!empty($item['_fallback_used']),
            'fallback_reason'=>$item['_fallback_reason']??null,
        ];
    }

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

private function dashboardSalesReviewData(
    int $salesUserId,
    string $period,
    array $periodInfo
):array{
    if($period==='day'){
        $s=Database::connection()->prepare(
            "SELECT
                r.id,
                r.rating,
                r.note,
                r.reviewed_at,
                r.updated_at,
                u.display_name AS admin_name
             FROM cdsp_daily_sales_reviews r
             JOIN cdsp_users u
               ON u.id=r.admin_user_id
             WHERE r.sales_user_id=?
               AND r.work_date=?
             LIMIT 1"
        );

        $s->execute([
            $salesUserId,
            $periodInfo['from'],
        ]);
    }else{
        $s=Database::connection()->prepare(
            "SELECT
                r.id,
                r.rating,
                r.note,
                r.reviewed_at,
                r.updated_at,
                u.display_name AS admin_name
             FROM cdsp_period_sales_reviews r
             JOIN cdsp_users u
               ON u.id=r.admin_user_id
             WHERE r.sales_user_id=?
               AND r.period_type=?
               AND r.period_start=?
             LIMIT 1"
        );

        $s->execute([
            $salesUserId,
            $period,
            $periodInfo['from'],
        ]);
    }

    $row=$s->fetch()?:null;

    $label=match($period){
        'week'=>'Weekly Review',
        'month'=>'Monthly Review',
        default=>'Daily Review',
    };

    return [
        'id'=>$row ? (int)$row['id'] : null,
        'period'=>$period,
        'label'=>$label,
        'from'=>(string)$periodInfo['from'],
        'to'=>(string)$periodInfo['to'],
        'period_label'=>(string)$periodInfo['label'],
        'rating'=>$row && (int)($row['rating']??0)>0
            ? (int)$row['rating']
            : null,
        'note'=>$row ? (string)$row['note'] : '',
        'reviewed_at'=>$row
            ? (string)($row['reviewed_at']??$row['updated_at'])
            : null,
        'admin_name'=>$row
            ? (string)$row['admin_name']
            : null,
        'history'=>$this->dashboardSalesReviewHistory(
            $salesUserId,
            $period,
            (string)$periodInfo['from']
        ),
        'exists'=>(bool)$row,
    ];
}


    private function dashboardSalesReviewHistory(
        int $salesUserId,
        string $period,
        string $periodStart
    ):array{
        $q=Database::connection()->prepare(
            "SELECT h.id,h.rating,h.note,h.created_at,u.display_name AS admin_name
             FROM cdsp_sales_review_history h
             JOIN cdsp_users u ON u.id=h.admin_user_id
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
            ];
        }
        return $rows;
    }

    private function dashboardRequestContext(array $source):array{
        $rawFrom=trim((string)($source['from']??''));
        $rawTo=trim((string)($source['to']??''));

        $validFrom=preg_match('/^\d{4}-\d{2}-\d{2}$/',$rawFrom)===1;
        $validTo=preg_match('/^\d{4}-\d{2}-\d{2}$/',$rawTo)===1;

        if($validFrom&&$validTo){
            $today=date('Y-m-d');
            $from=$rawFrom;
            $to=$rawTo;

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

            $fromTs=strtotime($from.' 12:00:00');
            $toTs=strtotime($to.' 12:00:00');

            $label=$from===$to
                ? date('F j, Y',$fromTs)
                : date('M j',$fromTs).' — '.date('M j, Y',$toTs);

            return [
                'date'=>$to,
                'period'=>'range',
                'info'=>[
                    'period'=>'range',
                    'from'=>$from,
                    'to'=>$to,
                    'days'=>$days,
                    'label'=>$label,
                    'short_label'=>$days===1
                        ? 'Daily target'
                        : $days.'-day target',
                ],
            ];
        }

        $date=$this->validDashboardDate(
            (string)($source['date']??date('Y-m-d'))
        );
        $period=$this->validDashboardPeriod(
            (string)($source['period']??'day')
        );

        return [
            'date'=>$date,
            'period'=>$period,
            'info'=>$this->dashboardPeriodInfo($date,$period),
        ];
    }

    private function validDashboardDate(string $date):string{
        $today=date('Y-m-d');

        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)){
            return $today;
        }

        return $date>$today
            ? $today
            : $date;
    }

    private function validDashboardPeriod(string $period):string{
        return in_array($period,['day','week','month'],true)
            ? $period
            : 'day';
    }

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

    public function postReview():void{
        $admin=Auth::requireRole('admin');$post=Post::find((int)($_GET['id']??0));if(!$post){http_response_code(404);exit('Post not found');}
        $s=Database::connection()->prepare("SELECT * FROM cdsp_post_reviews WHERE post_id=? LIMIT 1");$s->execute([$post['id']]);$review=$s->fetch()?:null;
        $attachments=$review?$this->attachments('post_review',(int)$review['id']):[];$this->render('admin/post_review',compact('admin','post','review','attachments'));
    }
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

    public function dailyReview():void{
        Auth::requireRole('admin');
        $sid=(int)($_GET['sales_id']??0);
        $date=$this->validDashboardDate((string)($_GET['date']??date('Y-m-d')));
        $salesUser=User::find($sid);
        if(!$salesUser||($salesUser['role']??'')!=='sales'){http_response_code(404);exit('Sales user not found');}
        $this->redirect('/admin?date='.rawurlencode($date).'&period=day&sales_id='.$sid.'&review=1');
    }
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

    public function reports():void{
        $admin=Auth::requireRole('admin');$period=$_GET['period']??'week';$sid=(int)($_GET['sales_id']??0);$sales=User::allSales();
        if($period==='month'){$start=$_GET['start']??date('Y-m-01');$end=date('Y-m-t',strtotime($start));}else{$period='week';$start=$_GET['start']??date('Y-m-d',strtotime('monday this week'));$end=date('Y-m-d',strtotime($start.' +6 days'));}
        $params=[$start.' 00:00:00',$end.' 23:59:59'];$filter='';if($sid>0){$filter=' AND p.sales_user_id=?';$params[]=$sid;}
        $sql="SELECT u.id sales_user_id,u.display_name,COUNT(p.id) total_posts,SUM(p.platform='facebook') facebook_posts,SUM(p.platform='offerup') offerup_posts,SUM(p.platform='craigslist') craigslist_posts,SUM(p.admin_review_status='good') good_posts,SUM(p.admin_review_status='bad') bad_posts
        FROM cdsp_users u LEFT JOIN cdsp_sales_posts p ON p.sales_user_id=u.id AND p.created_at BETWEEN ? AND ? AND p.deleted_at IS NULL WHERE u.role='sales' AND u.active=1 {$filter} GROUP BY u.id,u.display_name ORDER BY total_posts DESC,u.display_name";
        $s=Database::connection()->prepare($sql);$s->execute($params);$rows=$s->fetchAll();$salesUserId=$sid;$this->render('admin/reports',compact('admin','period','start','end','salesUserId','sales','rows'));
    }
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

    public function handleDeleteRequest():void{
        $admin=Auth::requireRole('admin');
        Csrf::verify($_POST['_csrf']??null);
        $id=(int)($_POST['request_id']??0);
        $action=(string)($_POST['action']??'');
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
            $_SESSION['flash_error']=$e->getMessage();
            $this->redirect('/admin');
        }
        $_SESSION['flash_success']=$status==='approved'
            ?'Post permanently deleted.'
            :'Deletion request rejected.';
        $this->redirect('/admin');
    }

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
            error_log('[CDSP hard delete] '.$e->getMessage());
            $this->json(['ok'=>false,'message'=>'Post could not be deleted.'],500);
        }
    }
    private function isAjaxRequest():bool{
        return strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH']??''))==='xmlhttprequest'
            || str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT']??'')),'application/json');
    }

    private function verifyAjaxCsrf():void{
        $token=(string)($_POST['_csrf']??'');$sessionToken=(string)($_SESSION['_csrf']??'');
        if($token===''||$sessionToken===''||!hash_equals($sessionToken,$token)){
            $this->json(['ok'=>false,'message'=>'Security token expired. Refresh the dashboard and try again.'],419);
        }
    }

    private function requestExceedsPostMaxSize():bool{
        $contentLength=(int)($_SERVER['CONTENT_LENGTH']??0);if($contentLength<1)return false;
        $max=$this->iniBytes((string)ini_get('post_max_size'));
        return $max>0&&$contentLength>$max;
    }

    private function iniBytes(string $value):int{
        $value=trim($value);if($value==='')return 0;$unit=strtolower(substr($value,-1));$number=(float)$value;
        return match($unit){'g'=>(int)round($number*1024*1024*1024),'m'=>(int)round($number*1024*1024),'k'=>(int)round($number*1024),default=>(int)$number};
    }

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

private function commentHasContent(string $html):bool{
    if(trim(strip_tags($html))!==''){
        return true;
    }

    return stripos($html,'<img')!==false;
}

private function hasUploadedFiles(string $field):bool{
    if(empty($_FILES[$field])) return false;
    $errors=$_FILES[$field]['error']??UPLOAD_ERR_NO_FILE;
    if(!is_array($errors)) return (int)$errors!==UPLOAD_ERR_NO_FILE;
    foreach($errors as $error){
        if((int)$error!==UPLOAD_ERR_NO_FILE) return true;
    }
    return false;
}

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

private function formatAttachments(array $items):array{
    $result=[];

    foreach($items as $attachment){
        $result[]=$this->formatAttachment($attachment);
    }

    return $result;
}

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
