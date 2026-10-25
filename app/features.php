<?php
function update02_ready(): bool {
 static $ready;
 if($ready!==null)return $ready;
 try{q('SELECT user_id FROM email_changes LIMIT 1');q('SELECT id FROM seller_reviews LIMIT 1');return $ready=true;}
 catch(PDOException $e){if(in_array((string)$e->getCode(),['42S02','HY000']))return $ready=false;throw $e;}
}
function feature_required(): void {
 if(!update02_ready())fail(503,'An account update is being installed. Please try again shortly.');
}
function current_dish(int $id): ?array {
 return one("SELECT m.*,s.business_name FROM menu_items m JOIN seller_applications s ON s.user_id=m.seller_id JOIN categories c ON c.id=m.category_id WHERE m.id=? AND ".live_sql('m')." AND s.status='approved' AND c.active=1",[$id]);
}
function cart_data(): array {
 $raw=$_SESSION['cart']??[];if(!is_array($raw))$raw=[];
 $raw=array_slice($raw,0,50,true);$ids=[];
 foreach($raw as $id=>$qty)if(preg_match('/^[0-9]+$/D',(string)$id) && (int)$id>0 && (int)$qty>0)$ids[]=(int)$id;
 $cart=['items'=>[],'count'=>0,'total'=>0];$_SESSION['cart']=[];
 if(!$ids)return $cart;
 $marks=implode(',',array_fill(0,count($ids),'?'));
 $items=all("SELECT m.*,s.business_name FROM menu_items m JOIN seller_applications s ON s.user_id=m.seller_id JOIN categories c ON c.id=m.category_id WHERE m.id IN ($marks) AND ".live_sql('m')." AND s.status='approved' AND c.active=1",[...$ids]);
 foreach($items as $m){$qty=max(1,min(20,(int)$raw[$m['id']]));$m['quantity']=$qty;$m['line_total']=$m['price_paisa']*$qty;$_SESSION['cart'][$m['id']]=$qty;$cart['items'][]=$m;$cart['count']+=$qty;$cart['total']+=$m['line_total'];}
 return $cart;
}
function cart_html(array $cart): string {ob_start();require ROOT.'/app/cart-content.php';return ob_get_clean();}
function review_stats(int $seller): array {
 if(!update02_ready())return ['average'=>0,'count'=>0];
 $r=one('SELECT AVG(rating) average,COUNT(*) count FROM seller_reviews WHERE seller_id=?',[$seller]);return ['average'=>(float)($r['average']??0),'count'=>(int)($r['count']??0)];
}
function rating_html(float $average,int $count): string {
 $score=$count?number_format($average,1).' / 5 · '.$count.' '.($count===1?'review':'reviews'):'No reviews yet';
 return '<div class="rating-line"><span class="review-stars" aria-hidden="true">★★★★★<span style="width:'.($count?round($average/5*100):0).'%">★★★★★</span></span><span>'.e($score).'</span></div>';
}
function change_record(string $token): ?array {
 if(!preg_match('/^[a-f0-9]{64}$/',$token))return null;
 return one('SELECT ec.* FROM email_changes ec JOIN users u ON u.id=ec.user_id WHERE ec.token_hash=? AND ec.expires_at>? AND ec.session_version=u.session_version AND ec.old_email=u.email',[hash('sha256',$token),now()]);
}

require_once ROOT.'/app/commerce.php';
