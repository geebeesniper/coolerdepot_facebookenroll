<?php use App\Core\Csrf;use App\Core\Util;?>
<div class="page-head"><div><div class="eyebrow">Post Review</div><h1><?=Util::e($post['display_name'])?> — <?=Util::e(ucfirst($post['platform']))?></h1></div><a class="btn" target="_blank" rel="noopener" href="<?=Util::e($post['canonical_url'])?>">Open Original ↗</a></div>
<div class="two"><div class="panel"><dl class="details"><dt>Title</dt><dd><?=Util::e($post['title'])?></dd><dt>Published</dt><dd><?=Util::e($post['published_at'])?></dd><dt>Submitted</dt><dd><?=Util::e($post['created_at'])?></dd><dt>URL</dt><dd><?=Util::e($post['canonical_url'])?></dd><dt>Post ID</dt><dd><?=Util::e($post['external_post_id'])?></dd><dt>Description</dt><dd><?=nl2br(Util::e($post['description']))?></dd></dl></div>
<div class="panel"><h2>Admin Review</h2><form method="post" enctype="multipart/form-data" action="<?=$config['app']['base_path']?>/admin/post/review"><input type="hidden" name="_csrf" value="<?=Util::e(Csrf::token())?>"><input type="hidden" name="post_id" value="<?=(int)$post['id']?>">
<label>Decision</label><div class="radios"><label><input type="radio" name="decision" value="good" <?=($review['decision']??'')==='good'?'checked':''?>> Good</label><label><input type="radio" name="decision" value="bad" <?=($review['decision']??'')==='bad'?'checked':''?>> Bad</label></div>
<?php
$fieldName = 'note';
$fieldId = 'admin-note';
$noteValue = (string)($review['note'] ?? '');
require __DIR__ . '/_html_note_editor.php';
?>
<label>Images</label><input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple><button class="btn primary">Save Review</button></form>
<?php foreach($attachments as$a):?><a target="_blank" href="<?=$config['app']['base_path']?>/attachment?id=<?=(int)$a['id']?>"><?=Util::e($a['original_name'])?></a> <?php endforeach;?></div></div>
