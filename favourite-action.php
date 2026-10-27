<?php
require __DIR__.'/app/bootstrap.php';$u=require_user(false);require_commerce();
if(!post()){header('Allow: POST');fail(405,t('Use a favourite button to save or remove an item.'));}
$kind=val('kind');$id=(int)val('id');$return=local_return(val('return_to'),'favourites.php?type='.($kind==='shops'?'shops':'food'));
if(!in_array($kind,['shops','food'])||$id<1)fail(422,t('Choose a valid shop or dish.'));
db()->beginTransaction();q('UPDATE users SET full_name=full_name WHERE id=?',[$u['id']]);
if($kind==='shops'){
 $existing=one('SELECT seller_id FROM favourite_shops WHERE user_id=? AND seller_id=?',[$u['id'],$id]);
 if($existing)q('DELETE FROM favourite_shops WHERE user_id=? AND seller_id=?',[$u['id'],$id]);
 else{$shop=one("SELECT business_name FROM seller_applications WHERE user_id=? AND status='approved'",[$id]);if(!$shop){db()->rollBack();fail(404,t('Shop not found.'));}q('INSERT INTO favourite_shops (user_id,seller_id,shop_name,created_at) VALUES (?,?,?,?)',[$u['id'],$id,$shop['business_name'],now()]);}
}else{
 $existing=one('SELECT product_key FROM favourite_food WHERE user_id=? AND product_key=?',[$u['id'],$id]);
 if($existing)q('DELETE FROM favourite_food WHERE user_id=? AND product_key=?',[$u['id'],$id]);
 else{$m=one("SELECT m.*,s.business_name FROM menu_items m JOIN seller_applications s ON s.user_id=m.seller_id WHERE m.id=? AND s.status='approved' AND m.status<>'archived'",[$id]);if(!$m){db()->rollBack();fail(404,t('Dish not found.'));}q('INSERT INTO favourite_food (user_id,product_id,product_key,seller_id,product_name,shop_name,created_at) VALUES (?,?,?,?,?,?,?)',[$u['id'],$id,$id,$m['seller_id'],$m['name'],$m['business_name'],now()]);}
}
db()->commit();flash($existing?t('Removed from your favourites.'):t('Saved to your favourites.'));go($return);
