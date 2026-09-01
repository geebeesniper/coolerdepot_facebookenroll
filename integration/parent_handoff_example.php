<?php
/**
 * File / 文件：integration/parent_handoff_example.php
 * EN: Parent-system integration example for parent handoff example.
 * 中文：用于 parent handoff example 的父系统集成示例。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
/**
 * EN: Perform the sales post tracker url integration helper used by the parent-system example.
 * 中文：执行 父系统集成示例使用的“sales post tracker url”辅助操作。
 *
 * @param array $user User value used by this operation. / 本操作使用的“user”参数值。
 * @param string $trackerBaseUrl Tracker base url value used by this operation. / 本操作使用的“tracker base url”参数值。
 * @param string $secret Whether the value must be handled as encrypted secret data. / 是否将该值作为加密敏感数据处理。
 *
 * @return string String result produced by this operation. / 本操作生成的字符串结果。
 */
function salesPostTrackerUrl(array$user,string$trackerBaseUrl,string$secret):string{
 $role=$user['role'];
 $p=['uid'=>(string)$user['id'],'sales_id'=>$role==='sales'?(string)$user['sales_id']:'','name'=>(string)$user['display_name'],'role'=>$role,'ts'=>time(),'nonce'=>bin2hex(random_bytes(16))];
 $payload=implode("\n",[$p['uid'],$p['sales_id'],$p['name'],$p['role'],(string)$p['ts'],$p['nonce']]);$p['sig']=hash_hmac('sha256',$payload,$secret);
 return rtrim($trackerBaseUrl,'/').'/auth/handoff?'.http_build_query($p);
}
// Admin example: role=admin, sales_id empty.
// Sales example: role=sales, sales_id=100006.
