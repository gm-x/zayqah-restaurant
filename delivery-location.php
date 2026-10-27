<?php
require __DIR__.'/app/bootstrap.php';require_experience();$token=param('token');
if(!preg_match('/^[a-f0-9]{64}$/',$token))fail(404,t('Rider link not found.'));
$order=one("SELECT o.* FROM delivery_links l JOIN orders o ON o.id=l.order_id WHERE l.token_hash=? AND l.expires_at>? AND o.fulfillment='delivery' AND o.status NOT IN ('completed','cancelled')",[hash('sha256',$token),now()]);
if(!$order)fail(404,t('This rider link has expired, was revoked, or the order is closed.'));$err=[];$title=t('Delivery location');
if(post()){
 $point=coordinates(val('latitude'),val('longitude'));
 if(!$point)$err[]=t('Use GPS or the map to choose a valid location.');
 if(!limit('rider-pin:'.$order['id'],60,3600))$err[]=t('Location sharing is limited to 60 updates per hour.');
 if(!$err){db()->beginTransaction();q('UPDATE orders SET status=status WHERE id=?',[$order['id']]);
  $valid=one("SELECT l.order_id FROM delivery_links l JOIN orders o ON o.id=l.order_id WHERE l.order_id=? AND l.token_hash=? AND l.expires_at>? AND o.status NOT IN ('completed','cancelled')",[$order['id'],hash('sha256',$token),now()]);
  if(!$valid){db()->rollBack();fail(403,t('Rider access is no longer available.'));}
  $n=q('UPDATE order_locations SET rider_latitude=?,rider_longitude=?,rider_updated_at=? WHERE order_id=?',[...$point,now(),$order['id']])->rowCount();
  if(!one('SELECT order_id FROM order_locations WHERE order_id=?',[$order['id']])){db()->rollBack();$err[]=t('This order has no saved destination pin. Ask the seller for delivery directions.');}
  else{notify_user((int)$order['customer_id'],(int)$order['id'],'location',t('Rider location shared'),$order['reference'].' · Open tracking to see the shared pin.','track-order.php?reference='.$order['reference'],'rider-first-pin:'.$order['id']);db()->commit();flash(t('Your location pin has been shared with the customer and seller.'));go('delivery-location.php?token='.$token);}
 }
}
$loc=one('SELECT * FROM order_locations WHERE order_id=?',[$order['id']]);require ROOT.'/app/header.php'; ?>
<main class="page shell medium" id="main"><div class="page-heading"><span class="heading-kicker"><?=e(t('DELIVERY DESTINATION'))?></span><h1><?=e($order['reference'])?></h1><p><?=e($order['shop_name'])?> · <?=e(status_label($order))?></p></div><?php errors($err);order_map($order); ?>
<section class="panel padded space-top"><h2><?=e(t('Share your location pin'))?></h2><p><?=e(t('This shares the pin you choose now. It does not continuously track you.'))?></p><form method="post"><?=csrf()?><?php location_picker($_SERVER['REQUEST_METHOD']==='POST'?val('latitude'):(string)($loc['rider_latitude']??''),$_SERVER['REQUEST_METHOD']==='POST'?val('longitude'):(string)($loc['rider_longitude']??''),t('Rider’s current location')); ?><button class="pill primary space-top"><?=e(t('Share my location'))?></button></form></section></main><?php require ROOT.'/app/footer.php'; ?>
