<?php
function commerce_ready(): bool {
 static $ready;if($ready!==null)return $ready;
 try{q('SELECT seller_id FROM seller_fulfillment LIMIT 1');q('SELECT id FROM product_reviews LIMIT 1');q('SELECT product_key FROM favourite_food LIMIT 1');q('SELECT id FROM orders LIMIT 1');return $ready=true;}
 catch(PDOException $e){if(in_array((string)$e->getCode(),['42S02','HY000']))return $ready=false;throw $e;}
}
function require_commerce(): void {if(!commerce_ready())fail(503,'This update is being installed. Please try again shortly.');}
function money(int $paisa): string {return site_language()==='ur'?number_format($paisa/100,2).' '.t('Rs'):'Rs '.number_format($paisa/100,2);}
function fulfillment(int $seller): array {
 return (commerce_ready()?one('SELECT * FROM seller_fulfillment WHERE seller_id=?',[$seller]):null)??['seller_id'=>$seller,'pickup_enabled'=>1,'delivery_enabled'=>0,'delivery_fee_paisa'=>0];
}
function checkout_groups(array $cart): array {
 $groups=[];
 foreach($cart['items'] as $item){$id=(int)$item['seller_id'];if(!isset($groups[$id])){$shop=one("SELECT id,user_id,business_name,address,business_phone FROM seller_applications WHERE user_id=? AND status='approved'",[$id]);if(!$shop)continue;$groups[$id]=['shop'=>$shop,'options'=>fulfillment($id),'items'=>[],'subtotal'=>0];}$groups[$id]['items'][]=$item;$groups[$id]['subtotal']+=$item['line_total'];}
 ksort($groups);return $groups;
}
function checkout_fingerprint(array $groups): string {
 $rows=[];foreach($groups as $id=>$group){$items=[];foreach($group['items'] as $item)$items[]=[(int)$item['id'],(int)$item['quantity'],(int)$item['price_paisa']];usort($items,fn($a,$b)=>$a[0]<=>$b[0]);$rows[]=[$id,$group['shop']['business_name'],$group['shop']['address'],$group['shop']['business_phone'],(int)$group['options']['pickup_enabled'],(int)$group['options']['delivery_enabled'],(int)$group['options']['delivery_fee_paisa'],$items];}
 return hash('sha256',json_encode($rows));
}
function checkout_quote(array $groups): string {
 $_SESSION['checkout_quotes']??=[];foreach($_SESSION['checkout_quotes'] as $k=>$q)if($q['expires']<time())unset($_SESSION['checkout_quotes'][$k]);
 if(count($_SESSION['checkout_quotes'])>=5)array_shift($_SESSION['checkout_quotes']);
 $token=bin2hex(random_bytes(24));$_SESSION['checkout_quotes'][$token]=['fingerprint'=>checkout_fingerprint($groups),'expires'=>time()+1800];return $token;
}
function local_return(string $value,string $fallback='index.php'): string {
 $parsed=parse_url($value);if($parsed===false||isset($parsed['scheme'])||isset($parsed['host']))return $fallback;
 $path=$parsed['path']??'';$allow=['index.php','restaurants.php','restaurant.php','dish.php','favourites.php','reviews.php','order.php','category.php'];
 return in_array($path,$allow,true)?$path.(isset($parsed['query'])?'?'.$parsed['query']:'').(isset($parsed['fragment'])?'#'.$parsed['fragment']:''):$fallback;
}
function favourite_state(string $kind,int $id): bool {
 static $sets=[];$u=user();if(!$u||!commerce_ready())return false;$key=$u['id'].'_'.$kind;
 if(!isset($sets[$key])){$column=$kind==='shops'?'seller_id':'product_key';$table=$kind==='shops'?'favourite_shops':'favourite_food';$sets[$key]=array_fill_keys(array_column(all("SELECT $column FROM $table WHERE user_id=?",[$u['id']]),$column),true);}
 return isset($sets[$key][$id]);
}
function favourite_button(string $kind,int $id,string $returnTo,string $name=''): string {
 $u=user();$saved=favourite_state($kind,$id);$label=$saved?t('Remove from favourites'):t('Add to favourites');$class='favourite-button'.($saved?' is-saved':'');
 $heart='<svg width="19" height="19" viewBox="0 0 24 24" fill="'.($saved?'currentColor':'none').'" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/></svg>';
 if(!$u)return '<a class="'.$class.'" href="'.e(url('login.php')).'" aria-label="'.e($label.($name?' · '.$name:'')).'">'.$heart.'</a>';
 return '<form method="post" action="'.e(url('favourite-action.php')).'" class="favourite-form">'.csrf().'<input type="hidden" name="kind" value="'.e($kind).'"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="return_to" value="'.e($returnTo).'"><button class="'.$class.'" aria-label="'.e($label.($name?' · '.$name:'')).'" aria-pressed="'.($saved?'true':'false').'">'.$heart.'</button></form>';
}
function product_stats(int $key): array {
 if(!commerce_ready())return ['average'=>0,'count'=>0];$r=one('SELECT AVG(rating) average,COUNT(*) count FROM product_reviews WHERE product_key=?',[$key]);return ['average'=>(float)($r['average']??0),'count'=>(int)($r['count']??0)];
}
function order_record(int $id,array $u): array {
 $order=one('SELECT * FROM orders WHERE id=?',[$id]);if(!$order||($u['role']!=='admin' && (int)$u['id']!==(int)$order['customer_id'] && (int)$u['id']!==(int)$order['seller_id']))fail(404,t('Order not found.'));return $order;
}
function status_label(array $order,bool $translate=true): string {
 $label=ucfirst($order['status']);
 if($order['status']==='ready')$label=$order['fulfillment']==='pickup'?'Ready for pickup':'Out for delivery';
 if($order['status']==='completed')$label=$order['fulfillment']==='pickup'?'Collected':'Delivered';
 return $translate?t($label):$label;
}
function render_order_summary(array $order,array $items): void {require ROOT.'/app/order-summary.php';}

require_once ROOT.'/app/scheduling.php';
