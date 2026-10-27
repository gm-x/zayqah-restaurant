<?php
require __DIR__.'/../app/bootstrap.php';$u=require_seller();require_experience();$order=order_record((int)param('id'),$u);
if((int)$order['seller_id']!==(int)$u['id']||$order['fulfillment']!=='delivery')fail(404,t('Delivery order not found.'));$err=[];$title=t('Rider link');$sessionKey='delivery_link_'.$order['id'];
if(post()){
 if(in_array($order['status'],['completed','cancelled'],true))$err[]=t('This order is closed. Rider access is disabled.');
 elseif(val('action')==='revoke'){q('DELETE FROM delivery_links WHERE order_id=?',[$order['id']]);unset($_SESSION[$sessionKey]);flash(t('Rider link revoked.'));go('seller/delivery-link.php?id='.$order['id']);}
 elseif(val('action')==='create'){
  $token=bin2hex(random_bytes(32));$expiry=gmdate('Y-m-d H:i:s',time()+8*3600);db()->beginTransaction();q('DELETE FROM delivery_links WHERE order_id=?',[$order['id']]);q('INSERT INTO delivery_links (order_id,token_hash,expires_at) VALUES (?,?,?)',[$order['id'],hash('sha256',$token),$expiry]);db()->commit();$_SESSION[$sessionKey]=$token;go('seller/delivery-link.php?id='.$order['id']);
 }else $err[]=t('Unknown action.');
}
$link=one('SELECT * FROM delivery_links WHERE order_id=? AND expires_at>?',[$order['id'],now()]);$token=$_SESSION[$sessionKey]??'';$canShow=$link&&$token&&hash_equals($link['token_hash'],hash('sha256',$token));
require ROOT.'/app/workspace-start.php'; ?>
<section class="panel padded"><h1><?=e(t('Rider location link'))?></h1><p><?=e(t('Order'))?> <?=e($order['reference'])?></p><p class="hint"><?=e(t('Share only with your assigned rider. The link shows this order’s delivery destination and allows the rider to share a location pin. It expires after eight hours or as soon as the order closes.'))?></p><?php errors($err); ?>
<?php if(!in_array($order['status'],['completed','cancelled'],true)): ?>
<?php if($canShow): ?><label for="rider-link"><?=e(t('Copy and share this link'))?></label><input id="rider-link" class="wide" type="text" readonly value="<?=e(url('delivery-location.php?token='.$token))?>"><a class="pill space-top" href="<?=e(url('delivery-location.php?token='.$token))?>" target="_blank" rel="noopener noreferrer"><?=e(t('Open rider page'))?></a><?php elseif($link): ?><p><?=e(t('A rider link is active. To display a new link here, generate a replacement. The old link will stop working.'))?></p><?php endif ?>
<div class="row-actions space-top"><form method="post"><?=csrf()?><button class="pill primary" name="action" value="create"><?=$link?t('Replace rider link'):t('Create rider link')?></button></form><?php if($link): ?><form method="post"><?=csrf()?><button class="pill" name="action" value="revoke"><?=e(t('Revoke access'))?></button></form><?php endif ?></div>
<?php else: ?><p><?=e(t('This order is closed. Rider access is disabled.'))?></p><?php endif ?><a class="pill space-top" href="<?=e(url('seller/order.php?id='.$order['id']))?>"><?=e(t('Back to order'))?></a></section><?php require ROOT.'/app/workspace-end.php'; ?>
