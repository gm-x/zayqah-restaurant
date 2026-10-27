<?php
require __DIR__.'/app/bootstrap.php';$u=require_user(false);require_commerce();require_experience();$title=t('Track your order');$reference=strtoupper(param('reference'));$order=null;$err=[];
if($reference!==''){
 if(!limit('tracking:'.$u['id'],120,900))$err[]=t('Too many tracking requests. Please try again later.');
 elseif(!preg_match('/^[A-Z0-9-]{3,32}$/',$reference))$err[]=t('Enter the full order ID from your order confirmation.');
 else{$order=one('SELECT * FROM orders WHERE reference=? AND (customer_id=? OR seller_id=?)',[$reference,$u['id'],$u['id']]);if(!$order)$err[]=t('No matching order was found in your account. Check the full order ID.');}
}
if(param('format')==='json'){
 header('Content-Type: application/json; charset=utf-8');if(!$order){http_response_code(404);echo json_encode(['error'=>$err[0]??t('Order not found.')]);}else echo json_encode(['html'=>tracking_html($order),'status'=>$order['status'],'updated_at'=>$order['updated_at'],'order_id'=>(int)$order['id'],'location'=>one('SELECT * FROM order_locations WHERE order_id=?',[$order['id']])]);exit;
}
require ROOT.'/app/header.php'; ?>
<main class="page shell medium" id="main"><div class="page-heading"><span class="heading-kicker"><?=e(t('FROM THE KITCHEN TO YOU'))?></span><h1><?=e(t('Track your order'))?></h1><p><?=e(t('Paste the order ID from your confirmation. You can track orders linked to your account.'))?></p></div>
<form method="get" class="tracking-search panel"><label for="reference"><?=e(t('Order ID'))?></label><div class="search-line"><input id="reference" name="reference" required maxlength="32" placeholder="CR-XXXXXXXXXX" value="<?=e($reference)?>"><button class="pill primary"><?=e(t('Track order'))?></button></div></form><?php errors($err); ?>
<?php if($order): ?><div data-track-panel data-track-url="<?=e(url('track-order.php?format=json&reference='.rawurlencode($reference)))?>"><?=tracking_html($order)?></div><?php order_map($order); ?><a class="pill space-top" href="<?=e(url(((int)$order['seller_id']===(int)$u['id']?'seller/':'').'order.php?id='.$order['id']))?>"><?=e(t('View order details →'))?></a><?php elseif($reference===''): ?><section class="panel padded"><h2><?=e(t('Need your order ID?'))?></h2><p><?=e(t('You’ll find it in order history and on your confirmation page.'))?></p><a class="pill" href="<?=e(url($u['role']==='seller'?'seller/orders.php':'orders.php'))?>"><?=e(t('Open order history'))?></a></section><?php endif ?></main><?php require ROOT.'/app/footer.php'; ?>
