<?php use App\Core\Csrf;use App\Core\Util;
$days=[];foreach($counts as$r){$d=$r['work_date'];if(!isset($days[$d]))$days[$d]=['facebook'=>0,'offerup'=>0,'craigslist'=>0,'total'=>0];$days[$d][$r['platform']]=(int)$r['cnt'];$days[$d]['total']+=(int)$r['cnt'];}
?>
<div class="page-head"><div><div class="eyebrow">Sales Dashboard</div><h1><?=Util::e($user['display_name'])?></h1><p>Saved posts cannot be deleted by Sales. Request deletion from Admin if needed.</p></div><a class="btn primary" href="<?=$config['app']['base_path']?>/sales/submit">+ Submit Post</a></div>
<div class="panel"><form class="filters"><label>From<input type="date" name="from" value="<?=Util::e($from)?>"></label><label>To<input type="date" name="to" value="<?=Util::e($to)?>"></label><button class="btn">Apply</button></form></div>
<div class="metrics"><?php foreach($days as$d=>$c):?><div class="metric"><small><?=Util::e($d)?></small><strong><?=$c['total']?></strong><span>FB <?=$c['facebook']?> · OfferUp <?=$c['offerup']?> · CL <?=$c['craigslist']?></span></div><?php endforeach;?></div>
<div class="panel"><div class="panel-head"><h2>Posts</h2><div><button class="tiny active" data-view="list">List</button><button class="tiny" data-view="grid">Grid</button></div></div>
<div id="postCollection" class="post-list"><?php foreach($posts as$p):?><article class="post">
<span class="platform <?=Util::e($p['platform'])?>"><?=Util::e(ucfirst($p['platform']))?></span><div class="post-main"><b><?=Util::e($p['title'])?></b><a target="_blank" rel="noopener" href="<?=Util::e($p['canonical_url'])?>"><?=Util::e($p['canonical_url'])?></a><small>Published <?=Util::e($p['published_at'])?> · Saved <?=Util::e($p['created_at'])?></small></div>
<span class="status <?=Util::e($p['admin_review_status'])?>"><?=Util::e(ucfirst($p['admin_review_status']))?></span>
<details><summary>Request deletion</summary><form method="post" action="<?=$config['app']['base_path']?>/sales/delete-request"><input type="hidden" name="_csrf" value="<?=Util::e(Csrf::token())?>"><input type="hidden" name="post_id" value="<?=(int)$p['id']?>"><input name="reason" required placeholder="Reason"><button class="tiny badbtn">Send</button></form></details>
</article><?php endforeach;?></div></div>
