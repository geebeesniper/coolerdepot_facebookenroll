<?php
namespace App\Controllers;

use App\Services\HtmlNoteSanitizer;
use App\Services\FacebookMarketplaceProviderChain;
use App\Core\Controller;use App\Core\Auth;use App\Core\Csrf;use App\Core\Database;use App\Models\Post;use App\Models\User;use App\Services\UploadService;
class AdminController extends Controller{
    public function dashboard():void{
        $admin=Auth::requireRole('admin');
        $date=$this->validDashboardDate(
            (string)($_GET['date']??date('Y-m-d'))
        );
        $period=$this->validDashboardPeriod(
            (string)($_GET['period']??'day')
        );

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

        $periodInfo=$this->dashboardPeriodInfo($date,$period);
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

        $date=$this->validDashboardDate(
            (string)($_GET['date']??date('Y-m-d'))
        );
        $period=$this->validDashboardPeriod(
            (string)($_GET['period']??'day')
        );
        $periodInfo=$this->dashboardPeriodInfo($date,$period);

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
        $date=$this->validDashboardDate(
            (string)($_GET['date']??date('Y-m-d'))
        );
        $period=$this->validDashboardPeriod(
            (string)($_GET['period']??'day')
        );

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

        $periodInfo=$this->dashboardPeriodInfo($date,$period);

        if (session_status()===PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $posts=Post::adminSalesPostsForPeriod(
            $salesUserId,
            $periodInfo['from'],
            $periodInfo['to']
        );

        $items=[];

        $sequence=0;

        foreach ($posts as $post) {
            $sequence++;

            $status=in_array(
                ($post['admin_review_status']??null),
                ['good','bad'],
                true
            )
                ? (string)$post['admin_review_status']
                : null;

            $items[]=[
                'sequence'=>$sequence,
                'id'=>(int)$post['id'],
                'platform'=>ucfirst((string)$post['platform']),
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
            'posts'=>$items,
            'count'=>count($items),
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
        $attachments=[];

        if($review){
            foreach(
                $this->attachments(
                    'post_review',
                    (int)$review['id']
                ) as $attachment
            ){
                $attachments[]=[
                    'id'=>(int)$attachment['id'],
                    'name'=>(string)$attachment['original_name'],
                    'url'=>$GLOBALS['config']['app']['base_path']
                        .'/attachment?id='
                        .(int)$attachment['id'],
                ];
            }
        }

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
                'decision'=>$review
                    ? (string)$review['decision']
                    : null,
            ],
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
    $this->verifyAjaxCsrf();

    $postId=(int)($_POST['post_id']??0);
    $body=HtmlNoteSanitizer::clean(
        (string)($_POST['comment_body']??'')
    );

    if(!Post::find($postId)){
        $this->json([
            'ok'=>false,
            'message'=>'Post was not found.',
        ],404);
    }

    if(!$this->commentHasContent($body)){
        $this->json([
            'ok'=>false,
            'field'=>'comment_body',
            'message'=>'Write a note before adding it.',
        ],422);
    }

    $s=Database::connection()->prepare(
        "INSERT INTO cdsp_post_review_comments(
            post_id,
            admin_user_id,
            body_html,
            created_at,
            updated_at
         )
         VALUES(?,?,?,NOW(),NOW())"
    );

    $s->execute([
        $postId,
        (int)$admin['id'],
        $body,
    ]);

    $commentId=(int)Database::connection()->lastInsertId();

    $this->json([
        'ok'=>true,
        'comment'=>$this->postReviewComment($commentId),
        'message'=>'Note added.',
    ]);
}

public function dashboardUpdateComment():void{
    $admin=Auth::requireRole('admin');
    $this->verifyAjaxCsrf();

    $commentId=(int)($_POST['comment_id']??0);
    $body=HtmlNoteSanitizer::clean(
        (string)($_POST['comment_body']??'')
    );

    if(!$this->commentHasContent($body)){
        $this->json([
            'ok'=>false,
            'field'=>'comment_body',
            'message'=>'A note cannot be empty.',
        ],422);
    }

    $s=Database::connection()->prepare(
        "UPDATE cdsp_post_review_comments
         SET body_html=?,
             updated_by=?,
             updated_at=NOW()
         WHERE id=?
           AND deleted_at IS NULL"
    );

    $s->execute([
        $body,
        (int)$admin['id'],
        $commentId,
    ]);

    if($s->rowCount()<1){
        $exists=$this->postReviewComment($commentId);

        if(!$exists){
            $this->json([
                'ok'=>false,
                'message'=>'Comment was not found.',
            ],404);
        }
    }

    $this->json([
        'ok'=>true,
        'comment'=>$this->postReviewComment($commentId),
        'message'=>'Note updated.',
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
            'message'=>'Comment was not found or was already deleted.',
        ],404);
    }

    $this->json([
        'ok'=>true,
        'comment_id'=>$commentId,
        'message'=>'Note deleted.',
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

        $date=$this->validDashboardDate(
            (string)($_GET['date']??date('Y-m-d'))
        );
        $period=$this->validDashboardPeriod(
            (string)($_GET['period']??'day')
        );
        $periodInfo=$this->dashboardPeriodInfo($date,$period);

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

    private function validDashboardDate(string $date):string{
        return preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)
            ? $date
            : date('Y-m-d');
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
            $row['daily_review_url']=
                $GLOBALS['config']['app']['base_path']
                .'/admin/daily?sales_id='
                .(int)$row['sales_user_id']
                .'&date='.rawurlencode($from);

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
             WHERE post_id=?"
        );
        $s->execute([$pid]);
        $rid=(int)$s->fetchColumn();

        if($rid<1){
            throw new \RuntimeException(
                'Review row could not be resolved after saving.'
            );
        }

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
        $attachments=[];

        foreach(
            $this->attachments('post_review',$rid)
            as $attachment
        ){
            $attachments[]=[
                'id'=>(int)$attachment['id'],
                'name'=>(string)$attachment['original_name'],
                'url'=>$GLOBALS['config']['app']['base_path']
                    .'/attachment?id='
                    .(int)$attachment['id'],
            ];
        }

        $this->json([
            'ok'=>true,
            'post_id'=>$pid,
            'decision'=>$decision,
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
        $admin=Auth::requireRole('admin');$sid=(int)($_GET['sales_id']??0);$date=$_GET['date']??date('Y-m-d');$salesUser=User::find($sid);
        if(!$salesUser||$salesUser['role']!=='sales'){http_response_code(404);exit('Sales user not found');}
        $posts=Post::forSales($sid,$date,$date);$s=Database::connection()->prepare("SELECT * FROM cdsp_daily_sales_reviews WHERE sales_user_id=? AND work_date=? LIMIT 1");$s->execute([$sid,$date]);$review=$s->fetch()?:null;
        $attachments=$review?$this->attachments('daily_review',(int)$review['id']):[];$this->render('admin/daily_review',compact('admin','salesUser','date','posts','review','attachments'));
    }
    public function saveDailyReview():void{
        $admin=Auth::requireRole('admin');Csrf::verify($_POST['_csrf']??null);$sid=(int)$_POST['sales_user_id'];$date=(string)$_POST['work_date'];$note = HtmlNoteSanitizer::clean((string)($_POST['note'] ?? ''));
        $pdo=Database::connection();$s=$pdo->prepare("INSERT INTO cdsp_daily_sales_reviews(sales_user_id,work_date,admin_user_id,rating,note,reviewed_at,created_at,updated_at) VALUES(?,?,?,NULL,?,NOW(),NOW(),NOW())
        ON DUPLICATE KEY UPDATE admin_user_id=VALUES(admin_user_id),rating=NULL,note=VALUES(note),reviewed_at=NOW(),updated_at=NOW()");
        $s->execute([$sid,$date,(int)$admin['id'],$note]);$s=$pdo->prepare("SELECT id FROM cdsp_daily_sales_reviews WHERE sales_user_id=? AND work_date=?");$s->execute([$sid,$date]);$rid=(int)$s->fetchColumn();(new UploadService())->save('daily_review',$rid,(int)$admin['id']);
        $_SESSION['flash_success']='Daily review saved.';$this->redirect('/admin/daily?sales_id='.$sid.'&date='.urlencode($date));
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
        $admin=Auth::requireRole('admin');Csrf::verify($_POST['_csrf']??null);$sid=(int)$_POST['sales_user_id'];$type=(string)$_POST['period_type'];$start=(string)$_POST['period_start'];$end=(string)$_POST['period_end'];$note = HtmlNoteSanitizer::clean((string)($_POST['note'] ?? ''));
        $s=Database::connection()->prepare("INSERT INTO cdsp_period_sales_reviews(sales_user_id,period_type,period_start,period_end,admin_user_id,rating,note,reviewed_at,created_at,updated_at) VALUES(?,?,?,?,?,NULL,?,NOW(),NOW(),NOW())
        ON DUPLICATE KEY UPDATE period_end=VALUES(period_end),admin_user_id=VALUES(admin_user_id),rating=NULL,note=VALUES(note),reviewed_at=NOW(),updated_at=NOW()");
        $s->execute([$sid,$type,$start,$end,(int)$admin['id'],$note]);$s=Database::connection()->prepare("SELECT id FROM cdsp_period_sales_reviews WHERE sales_user_id=? AND period_type=? AND period_start=?");$s->execute([$sid,$type,$start]);$rid=(int)$s->fetchColumn();(new UploadService())->save('period_review',$rid,(int)$admin['id']);
        $_SESSION['flash_success']=ucfirst($type).' review saved.';$this->redirect('/admin/reports?period='.$type.'&start='.urlencode($start).'&sales_id='.$sid);
    }
    public function handleDeleteRequest():void{
        $admin=Auth::requireRole('admin');Csrf::verify($_POST['_csrf']??null);$id=(int)$_POST['request_id'];$action=(string)$_POST['action'];$pdo=Database::connection();$pdo->beginTransaction();
        try{$s=$pdo->prepare("SELECT * FROM cdsp_deletion_requests WHERE id=? AND status='pending' FOR UPDATE");$s->execute([$id]);$r=$s->fetch();if(!$r)throw new \RuntimeException('Request not found');
            $status=$action==='approve'?'approved':'rejected';if($status==='approved'){$s=$pdo->prepare("UPDATE cdsp_sales_posts SET deleted_at=NOW(),deleted_by=?,updated_at=NOW() WHERE id=?");$s->execute([(int)$admin['id'],$r['post_id']]);}
            $s=$pdo->prepare("UPDATE cdsp_deletion_requests SET status=?,reviewed_by=?,reviewed_at=NOW(),updated_at=NOW() WHERE id=?");$s->execute([$status,(int)$admin['id'],$id]);$pdo->commit();}
        catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$_SESSION['flash_error']=$e->getMessage();$this->redirect('/admin');}
        $_SESSION['flash_success']='Deletion request '.$status.'.';$this->redirect('/admin');
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
            u.display_name AS author_name
         FROM cdsp_post_review_comments c
         JOIN cdsp_users u
           ON u.id=c.admin_user_id
         WHERE c.post_id=?
           AND c.deleted_at IS NULL
         ORDER BY c.created_at ASC,c.id ASC"
    );

    $s->execute([$postId]);

    $rows=[];

    foreach($s->fetchAll() as $row){
        $rows[]=$this->formatPostReviewComment($row);
    }

    return $rows;
}

private function postReviewComment(int $commentId):?array{
    $s=Database::connection()->prepare(
        "SELECT
            c.id,
            c.post_id,
            c.admin_user_id,
            c.body_html,
            c.created_at,
            c.updated_at,
            c.updated_by,
            u.display_name AS author_name
         FROM cdsp_post_review_comments c
         JOIN cdsp_users u
           ON u.id=c.admin_user_id
         WHERE c.id=?
           AND c.deleted_at IS NULL
         LIMIT 1"
    );

    $s->execute([$commentId]);
    $row=$s->fetch();

    return $row
        ? $this->formatPostReviewComment($row)
        : null;
}

private function formatPostReviewComment(array $row):array{
    $created=(string)$row['created_at'];
    $updated=(string)$row['updated_at'];
    $edited=(
        !empty($row['updated_by'])
        || (
            $created!==''
            && $updated!==''
            && $created!==$updated
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
        'edited'=>$edited,
    ];
}

private function commentHasContent(string $html):bool{
    if(trim(strip_tags($html))!==''){
        return true;
    }

    return stripos($html,'<img')!==false;
}

    private function attachments(string $type,int $id):array{$s=Database::connection()->prepare("SELECT * FROM cdsp_review_attachments WHERE entity_type=? AND entity_id=? ORDER BY created_at");$s->execute([$type,$id]);return$s->fetchAll();}
}
