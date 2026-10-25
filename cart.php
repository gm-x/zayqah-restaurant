<?php
require __DIR__.'/app/bootstrap.php';$err=[];$json=str_contains($_SERVER['HTTP_ACCEPT']??'','application/json');
if(post()){
 $action=val('action');$id=(int)val('id');$cart=cart_data();
 if($action==='clear')$_SESSION['cart']=[];
 elseif($action==='remove')unset($_SESSION['cart'][$id]);
 elseif(in_array($action,['add','quantity'])){
  $m=current_dish($id);
  $quantity=array_key_exists('quantity',$_POST)?val('quantity'):'1';
  if(!preg_match('/^[0-9]+$/D',$quantity) || (int)$quantity<1 || (int)$quantity>20)$err[]='Choose a quantity between 1 and 20.';
  elseif(!$m){unset($_SESSION['cart'][$id]);$err[]='This dish is no longer available and was removed from your cart.';}
  elseif($action==='add' && !isset($_SESSION['cart'][$id]) && count($_SESSION['cart'])>=50)$err[]='Your cart can contain up to 50 different dishes.';
  elseif($action==='add')$_SESSION['cart'][$id]=min(20,($_SESSION['cart'][$id]??0)+(int)$quantity);
  else $_SESSION['cart'][$id]=(int)$quantity;
 }else $err[]='Unknown cart action.';
 $cart=cart_data();
 if($json){header('Content-Type: application/json');if($err)http_response_code(422);echo json_encode(['html'=>cart_html($cart),'count'=>$cart['count'],'message'=>$err?implode(' ',$err):($action==='add'?'Added to your cart.':'Cart updated.'),'error'=>(bool)$err]);exit;}
 if(!$err){flash($action==='add'?'Added to your cart.':'Cart updated.');go('cart.php');}
}
$title='Your cart';$cart=cart_data();require ROOT.'/app/header.php'; ?>
<main class="page shell medium" id="main"><div class="page-heading"><span class="heading-kicker">SAVE A LITTLE ROOM</span><h1>Your cart</h1><p>A few favorites, all in one place.</p></div><?php errors($err); ?><section class="panel padded cart-full-page"><?=cart_html($cart)?></section><a class="pill space-top" href="<?=e(url('index.php'))?>">← Keep exploring</a></main><?php require ROOT.'/app/footer.php'; ?>
