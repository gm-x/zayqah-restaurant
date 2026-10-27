<?php
require __DIR__.'/../app/bootstrap.php';$u=require_seller();require_commerce();require_experience();$order=order_record((int)param('id'),$u);
if((int)$order['seller_id']!==(int)$u['id']||$order['status']!=='completed'||(int)$order['customer_id']===(int)$u['id'])fail(403,t('Feedback is available for your completed customer orders.'));
$review=one('SELECT * FROM order_customer_reviews WHERE order_id=?',[$order['id']]);$err=[];
if(post()){
 $rating=val('rating');$text=val('review_text');if(!in_array($rating,['1','2','3','4','5'],true))$err[]=t('Choose a rating from 1 to 5.');if(!text_valid($text,0,2000))$err[]=t('Keep feedback under 2000 characters.');
 if(!limit('customer-feedback:'.$u['id'],20,3600))$err[]=t('Too many feedback updates. Try again later.');
 if(!$err){db()->beginTransaction();q('UPDATE orders SET status=status WHERE id=?',[$order['id']]);$exists=one('SELECT id FROM order_customer_reviews WHERE order_id=?',[$order['id']]);
 if($exists)q('UPDATE order_customer_reviews SET rating=?,review_text=?,updated_at=? WHERE id=?',[(int)$rating,$text,now(),$exists['id']]);
 else q('INSERT INTO order_customer_reviews (order_id,seller_id,customer_id,rating,review_text,created_at,updated_at) VALUES (?,?,?,?,?,?,?)',[$order['id'],$u['id'],$order['customer_id'],(int)$rating,$text,now(),now()]);
 dismiss_order_prompt((int)$order['id'],(int)$u['id']);notify_review((int)$order['customer_id'],(int)$order['id'],t('Seller feedback received'),$order['reference'].' · '.$order['shop_name'],'order.php?id='.$order['id'].'#seller-feedback');db()->commit();flash(t('Customer feedback saved.'));go('seller/order.php?id='.$order['id']);}
}
$title=t('Order feedback');require ROOT.'/app/workspace-start.php'; ?>
<section class="panel padded"><h1><?=e(t('How did this order go?'))?></h1><p><?=e($order['reference'])?> · <?=e($order['customer_name'])?></p><p class="hint"><?=e(t('This feedback is visible to you and this customer. It is not a public customer profile.'))?></p><?php errors($err); ?><form method="post"><?=csrf()?><fieldset class="rating-picker"><legend><?=e(t('Your rating'))?></legend><?php for($i=1;$i<=5;$i++): ?><label><input type="radio" name="rating" value="<?=$i?>" required <?=($_SERVER['REQUEST_METHOD']==='POST'?val('rating'):($review['rating']??''))==$i?'checked':''?>><span><?=$i?> ★</span></label><?php endfor ?></fieldset><?php area('review_text',t('Feedback (optional)'),$_SERVER['REQUEST_METHOD']==='POST'?val('review_text'):($review['review_text']??''),'maxlength="2000"'); ?><button class="pill primary space-top"><?=e(t('Save feedback'))?></button></form></section><?php require ROOT.'/app/workspace-end.php'; ?>
