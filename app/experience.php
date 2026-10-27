<?php
function experience_ready(): bool {
 static $ready;if($ready!==null)return $ready;
 try{return $ready=(bool)one('SELECT version FROM experience_updates WHERE version=6');}
 catch(PDOException $e){if($e->getCode()==='42S02'||str_contains($e->getMessage(),'no such table'))return $ready=false;throw $e;}
}
function require_experience(): void {if(!experience_ready())fail(503,t('This feature is being installed. Please try again shortly.'));}
function coordinates(string $lat,string $lng): ?array {
 if($lat===''||$lng===''||!is_numeric($lat)||!is_numeric($lng))return null;
 $a=(float)$lat;$b=(float)$lng;if(!is_finite($a)||!is_finite($b)||abs($a)>90||abs($b)>180)return null;
 return [round($a,7),round($b,7)];
}
function saved_location(int $id): ?array {return experience_ready()?one('SELECT * FROM user_locations WHERE user_id=?',[$id]):null;}
function save_location(int $id,array $point): void {
 if(one('SELECT user_id FROM user_locations WHERE user_id=?',[$id]))q('UPDATE user_locations SET latitude=?,longitude=?,updated_at=? WHERE user_id=?',[...$point,now(),$id]);
 else q('INSERT INTO user_locations (user_id,latitude,longitude,updated_at) VALUES (?,?,?,?)',[$id,...$point,now()]);
}
function location_picker(string $lat='',string $lng='',string $label='Pin your location'): void { ?>
 <section class="location-picker" data-location-picker><h3><?=e(t($label))?></h3><p class="hint"><?=e(t('Use GPS or tap the map to place your pin. You can drag the pin to adjust it.'))?></p>
 <button class="pill compact" type="button" data-location-gps><?=ui_icon('location',16)?> <?=e(t('Use my current location'))?></button>
 <div class="location-map" data-location-map aria-label="<?=e(t('Location map'))?>"></div>
 <div class="form-grid coordinate-fields"><?php field('latitude',t('Latitude'),'number','required min="-90" max="90" step="any" inputmode="decimal"',$lat);field('longitude',t('Longitude'),'number','required min="-180" max="180" step="any" inputmode="decimal"',$lng); ?></div>
 <p class="hint" role="status" data-location-status><?=($lat!==''&&$lng!=='')?t('Saved pin loaded. Adjust it if needed.'):t('Choose a pin to continue. GPS requires HTTPS or localhost; if unavailable, tap the map or enter coordinates.')?></p></section>
<?php }
function notify_user(int $user,?int $order,string $kind,string $title,string $body,string $path,string $key): void {
 $title=source_text($title);
 if(!experience_ready())return;
 if(one('SELECT id FROM notifications WHERE event_key=?',[$key]))return;
 q('INSERT INTO notifications (user_id,order_id,kind,title,body,path,event_key,created_at) VALUES (?,?,?,?,?,?,?,?)',[$user,$order,$kind,$title,$body,$path,$key,now()]);
}
function record_order_event(array $order,string $status,int $actor): void {
 if(!experience_ready())return;
 if(!one('SELECT id FROM order_events WHERE order_id=? AND status=?',[$order['id'],$status]))q('INSERT INTO order_events (order_id,status,actor_id,created_at) VALUES (?,?,?,?)',[$order['id'],$status,$actor,now()]);
 $preview=$order;$preview['status']=$status;$label=status_label($preview,false);
 foreach(array_unique([(int)$order['customer_id'],(int)$order['seller_id']]) as $recipient){
  $isSeller=$recipient===(int)$order['seller_id'];$path=($isSeller?'seller/order.php?id=':'order.php?id=').$order['id'];
  notify_user($recipient,(int)$order['id'],'order','Order '.$label,$order['reference'].' · '.$order['shop_name'],$path,'status:'.$order['id'].':'.$status.':'.$recipient);
  if($status==='completed' && (int)$order['customer_id']!==(int)$order['seller_id'] && !one('SELECT id FROM review_prompts WHERE order_id=? AND user_id=?',[$order['id'],$recipient]))q('INSERT INTO review_prompts (order_id,user_id,kind,created_at) VALUES (?,?,?,?)',[$order['id'],$recipient,$isSeller?'seller':'customer',now()]);
 }
}
function order_review_prompt(array $u): ?array {
 if(!experience_ready())return null;
 foreach(all("SELECT p.*,o.reference,o.shop_name FROM review_prompts p JOIN orders o ON o.id=p.order_id WHERE p.user_id=? AND p.dismissed_at IS NULL AND o.status='completed' ORDER BY p.id LIMIT 15",[$u['id']]) as $p){
  if($p['kind']==='seller'){$remaining=!one('SELECT id FROM order_customer_reviews WHERE order_id=? AND seller_id=?',[$p['order_id'],$u['id']]);$path='seller/review-customer.php?id='.$p['order_id'];}
  else{$remaining=$u['role']!=='admin' && one('SELECT i.id FROM order_items i WHERE i.order_id=? AND NOT EXISTS(SELECT 1 FROM product_reviews r WHERE r.user_id=? AND r.product_key=i.product_key)',[$p['order_id'],$u['id']]);$path='order.php?id='.$p['order_id'].'&review=1#order-reviews';}
  if($remaining)return ['id'=>(int)$p['id'],'title'=>$p['kind']==='seller'?t('How did this order go?'):t('How was your food?'),'body'=>$p['reference'].' is complete. Leave a review now or later from order history.','href'=>url($path)];
 }
 return null;
}
function dismiss_order_prompt(int $order,int $user): void {if(experience_ready())q('UPDATE review_prompts SET dismissed_at=? WHERE order_id=? AND user_id=?',[now(),$order,$user]);}
function notify_review(int $recipient,?int $order,string $title,string $body,string $path): void {notify_user($recipient,$order,'review',$title,$body,$path,'review:'.bin2hex(random_bytes(16)));}
function tracking_html(array $order): string {
 $events=experience_ready()?all('SELECT status,created_at FROM order_events WHERE order_id=? ORDER BY id',[$order['id']]):[];$times=array_column($events,'created_at','status');
 $steps=['placed'=>t('Order placed'),'confirmed'=>t('Confirmed'),'preparing'=>t('Preparing'),'ready'=>$order['fulfillment']==='pickup'?t('Ready for pickup'):t('Out for delivery'),'completed'=>$order['fulfillment']==='pickup'?t('Collected'):t('Delivered')];$keys=array_keys($steps);$position=array_search($order['status'],$keys,true);ob_start(); ?>
 <section class="tracking-card panel padded"><div class="tracking-heading"><div><span class="heading-kicker"><?=e(t('YOUR ORDER, STEP BY STEP'))?></span><h2><?=e($order['reference'])?></h2><p><?=e($order['shop_name'])?></p></div><span class="status-badge"><?=e(status_label($order))?></span></div>
 <?php if($order['status']==='cancelled'): ?><div class="alert"><?=e(t('This order was cancelled. Contact the shop if you need help.'))?></div><?php endif ?>
 <ol class="order-timeline"><?php foreach($steps as $key=>$label):$n=array_search($key,$keys,true);$done=$key==='placed'||isset($times[$key])||($position!==false&&$n<=$position); ?><li class="<?=$done?'done':''?> <?=$order['status']===$key?'current':''?>" <?=$order['status']===$key?'aria-current="step"':''?>><span class="timeline-dot" aria-hidden="true"><?=$done?'✓':($n+1)?></span><div><strong><?=e(t($label))?></strong><small><?=isset($times[$key])?e(local_time($times[$key])):($key==='placed'?e(local_time($order['created_at'])):($order['status']===$key?e(local_time($order['updated_at'])):($done?t('Time not recorded'):t('Waiting'))))?></small></div></li><?php endforeach ?></ol>
 <p class="hint"><?=e(t('Last updated'))?> <?=e(local_time($order['updated_at']))?> <?=e(t('· Status refreshes automatically while this page is open.'))?></p></section>
 <?php return ob_get_clean();
}
function order_map(array $order): void {
 if(!experience_ready())return;$loc=one('SELECT * FROM order_locations WHERE order_id=?',[$order['id']]);if(!$loc)return; ?>
 <section class="panel padded space-top"><h2><?=e(t('Customer location'))?></h2><p><?=e($loc['address'])?></p><div class="location-map" data-view-map data-order-map="<?=e($order['id'])?>" data-lat="<?=e($loc['latitude'])?>" data-lng="<?=e($loc['longitude'])?>" <?php if($loc['rider_latitude']!==null): ?>data-rider-lat="<?=e($loc['rider_latitude'])?>" data-rider-lng="<?=e($loc['rider_longitude'])?>"<?php endif ?>></div><a class="pill compact" target="_blank" rel="noopener noreferrer" href="https://www.google.com/maps/dir/?api=1&amp;destination=<?=e($loc['latitude'].','.$loc['longitude'])?>"><?=e(t('Get directions'))?></a><?php if($loc['rider_updated_at']): ?><p class="hint"><?=e(t('Rider’s last shared pin:'))?> <?=e(local_time($loc['rider_updated_at']))?><?=e(t('. This is a manually shared location, not continuous GPS tracking.'))?></p><?php endif ?></section>
<?php }
function location_required_error(array &$errors,string $address,?array $point): void {
 if(!text_valid($address,10,1000))$errors[]=t('Enter your complete address (10–1000 characters).');
 if(!$point)$errors[]=t('Pin your location on the map or enter valid latitude and longitude.');
}
