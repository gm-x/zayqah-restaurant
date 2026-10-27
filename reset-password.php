<?php
require __DIR__.'/app/bootstrap.php';$title=t('Set a new password');$token=param('token');$err=[];$done=false;
if(post()){
 $token=val('token');$err=password_errors(raw('password'),raw('password_confirm'));
 if(!limit('reset',10))$err[]=t('Too many attempts. Try again later.');
 if(!$err){db()->beginTransaction();$t=consume_token($token,'reset');
  if(!$t){db()->rollBack();$err[]=t('This link is invalid, expired or already used.');}
  else{q('UPDATE users SET password_hash=?,session_version=session_version+1 WHERE id=?',[password_hash(raw('password'),PASSWORD_DEFAULT),$t['user_id']]);q('DELETE FROM tokens WHERE user_id=? AND purpose=?',[$t['user_id'],'reset']);db()->commit();$done=true;}
 }
}
require ROOT.'/app/header.php'; ?>
<main class="page narrow" id="main"><section class="auth-card"><h1><?=e(t('Set a new password'))?></h1><?php errors($err); ?><?php if($done): ?><div class="alert"><?=e(t('Password updated. Sign in with your new password.'))?></div><a class="pill primary" href="<?=e(url('login.php'))?>"><?=e(t('Sign in'))?></a><?php elseif(token_record($token,'reset')): ?><p><?=e(t('Choose a unique password between 8 and 72 characters.'))?></p><form method="post"><?=csrf()?><input type="hidden" name="token" value="<?=e($token)?>"><?php field('password',t('New password'),'password','required minlength="8" maxlength="72" autocomplete="new-password"');field('password_confirm',t('Confirm password'),'password','required minlength="8" maxlength="72" autocomplete="new-password"'); ?><button class="pill primary wide space-top"><?=e(t('Update password'))?></button></form><?php else: ?><p><?=e(t('This reset link is invalid or expired.'))?></p><a class="pill primary" href="<?=e(url('forgot-password.php'))?>"><?=e(t('Request another link'))?></a><?php endif ?></section></main><?php require ROOT.'/app/footer.php'; ?>
