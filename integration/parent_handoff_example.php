<?php
/**
 * File / 文件：integration/parent_handoff_example.php
 * EN: Parent-portal integration example for generating authenticated Sales Post Tracker handoff URLs.
 * 中文：父级 Portal 集成示例，用于生成进入 Sales Post Tracker 的认证 handoff URL。
 * Maintenance / 维护：Keep the signature field order synchronized with the tracker verifier.
 * 维护要求：签名字段顺序必须与 Tracker 端验证逻辑保持一致。
 */
/**
 * EN: `salesPostTrackerUrl` builds a signed, short-lived handoff URL for an Admin or Sales user.
 * 中文：`salesPostTrackerUrl` 为 Admin 或 Sales 用户生成带签名且短时有效的 handoff URL。
 */
function salesPostTrackerUrl(array$user,string$trackerBaseUrl,string$secret):string{
 $role=$user['role'];
 $p=['uid'=>(string)$user['id'],'sales_id'=>$role==='sales'?(string)$user['sales_id']:'','name'=>(string)$user['display_name'],'role'=>$role,'ts'=>time(),'nonce'=>bin2hex(random_bytes(16))];
 $payload=implode("\n",[$p['uid'],$p['sales_id'],$p['name'],$p['role'],(string)$p['ts'],$p['nonce']]);$p['sig']=hash_hmac('sha256',$payload,$secret);
 return rtrim($trackerBaseUrl,'/').'/auth/handoff?'.http_build_query($p);
}
// Admin example: role=admin, sales_id empty.
// Sales example: role=sales, sales_id=100006.
