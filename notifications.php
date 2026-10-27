<?php
require __DIR__.'/app/bootstrap.php';$u=require_user(false);require_experience();$title=t('Notifications');
if(post()){
 if(val('action')==='all')q('UPDATE notifications SET read_at=? WHERE user_id=? AND id<=? AND read_at IS NULL',[now(),$u['id'],(int)val('last_id')]);
 elseif(val('action')==='open'){$n=one('SELECT * FROM notifications WHERE id=? AND user_id=?',[(int)val('id'),$u['id']]);if($n){q('UPDATE notifications SET read_at=? WHERE id=? AND user_id=?',[now(),$n['id'],$u['id']]);go($n['path']);}}
 go('notifications.php');
}
$limit=max(1,min(10,(int)param('page')?:1));$offset=($limit-1)*30;$rows=all('SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 31 OFFSET '.$offset,[$u['id']]);$more=count($rows)>30;$rows=array_slice($rows,0,30);
require ROOT.'/app/header.php'; ?>
<main class="page shell medium" id="main"><div class="dash-header"><div><span class="heading-kicker"><?=e(t('STAY IN THE LOOP'))?></span><h1><?=e(t('Notifications'))?></h1><p><?=e(t('Order progress and new reviews, in one place.'))?></p></div><form method="post"><?=csrf()?><input type="hidden" name="action" value="all"><input type="hidden" name="last_id" value="<?=e($rows[0]['id']??0)?>"><button class="pill"><?=e(t('Mark all as read'))?></button></form></div>
<div class="notification-list"><?php if(!$rows): ?><section class="panel padded"><h2><?=e(t('You’re all caught up.'))?></h2><p><?=e(t('Updates will appear here when you place or receive orders and reviews.'))?></p></section><?php endif ?>
<?php foreach($rows as $n): ?><article class="panel padded notification-row <?=$n['read_at']?'':'is-unread'?>"><div><small><?=e(local_time($n['created_at']))?></small><h2><?=e(t($n['title']))?></h2><p><?=e(t($n['body']))?></p></div><form method="post"><?=csrf()?><input type="hidden" name="action" value="open"><input type="hidden" name="id" value="<?=$n['id']?>"><button class="pill compact"><?=e(t('View update →'))?></button></form></article><?php endforeach ?></div>
<nav class="pagination" aria-label="<?=e(t('Notification pages'))?>"><?php if($limit>1): ?><a class="pill" href="?page=<?=$limit-1?>"><?=e(t('Newer'))?></a><?php endif ?><?php if($more&&$limit<10): ?><a class="pill" href="?page=<?=$limit+1?>"><?=e(t('Older'))?></a><?php endif ?></nav></main><?php require ROOT.'/app/footer.php'; ?>
