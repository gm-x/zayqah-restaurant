<?php
require __DIR__.'/app/bootstrap.php';header('Content-Type: application/json; charset=utf-8');
$u=user();if(!$u){http_response_code(401);echo json_encode(['error'=>t('Sign in to view updates.')]);exit;}
if(!experience_ready()){$prompt=order_review_prompt($u);if($prompt){$prompt['title']=t($prompt['title']);$prompt['body']=t($prompt['body']);}
echo json_encode(['unread'=>0,'latest'=>0,'notifications'=>[],'prompt'=>null]);exit;}
if(post()){
 $action=val('action');$id=(int)val('id');
 if($action==='read')q('UPDATE notifications SET read_at=? WHERE id=? AND user_id=? AND read_at IS NULL',[now(),$id,$u['id']]);
 elseif($action==='read-all')q('UPDATE notifications SET read_at=? WHERE user_id=? AND id<=? AND read_at IS NULL',[now(),$u['id'],$id]);
 elseif($action==='dismiss')q('UPDATE review_prompts SET dismissed_at=? WHERE id=? AND user_id=?',[now(),$id,$u['id']]);
 else {http_response_code(400);echo json_encode(['error'=>'Unknown update action.']);exit;}
}
$rows=all('SELECT id,title,body,path,read_at,created_at FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 20',[$u['id']]);
foreach($rows as &$row){$row['id']=(int)$row['id'];$row['title']=t($row['title']);$row['body']=t($row['body']);$row['href']=url($row['path']);unset($row['path']);}unset($row);
$prompt=order_review_prompt($u);if($prompt){$prompt['title']=t($prompt['title']);$prompt['body']=t($prompt['body']);}
echo json_encode(['unread'=>(int)q('SELECT COUNT(*) FROM notifications WHERE user_id=? AND read_at IS NULL',[$u['id']])->fetchColumn(),'latest'=>(int)($rows[0]['id']??0),'notifications'=>$rows,'prompt'=>$prompt],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);exit;
