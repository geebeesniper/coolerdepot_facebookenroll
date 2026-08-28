<?php
/** Put this logic in the parent CoolerDepot Admin/Sales portal. */
function salesPostTrackerUrl(array$user,string$trackerBaseUrl,string$secret):string{
 $role=$user['role'];
 $p=['uid'=>(string)$user['id'],'sales_id'=>$role==='sales'?(string)$user['sales_id']:'','name'=>(string)$user['display_name'],'role'=>$role,'ts'=>time(),'nonce'=>bin2hex(random_bytes(16))];
 $payload=implode("\n",[$p['uid'],$p['sales_id'],$p['name'],$p['role'],(string)$p['ts'],$p['nonce']]);$p['sig']=hash_hmac('sha256',$payload,$secret);
 return rtrim($trackerBaseUrl,'/').'/auth/handoff?'.http_build_query($p);
}
// Admin example: role=admin, sales_id empty.
// Sales example: role=sales, sales_id=100006.
