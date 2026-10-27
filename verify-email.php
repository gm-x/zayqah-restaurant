<?php
require __DIR__.'/app/bootstrap.php';$title=t('Verify your email');$err=[];$success=false;$u=user();$token=param('token');
if(post()){
 if(val('action')==='confirm'){
  db()->beginTransaction();$t=consume_token(val('token'),'verify');
  if($t){q('UPDATE users SET verified_at=? WHERE id=?',[now(),$t['user_id']]);db()->commit();$success=true;$u=user();}
  else{db()->rollBack();$err[]=t('This verification link is invalid or expired. Request a new email.');}
 }elseif($u){
  if(!limit('verification',3,900))$err[]=t('Please wait 15 minutes before requesting another email.');
  elseif(!$u['verified_at']){db()->beginTransaction();verification_mail($u);db()->commit();mail_after_commit();flash(t('A new verification email has been queued. Please check your inbox and spam folder.'));go('verify-email.php');}
 }
}
require ROOT.'/app/header.php'; ?>
<main id="main" class="page narrow"><section class="auth-card"><div class="status-icon"><?=ui_icon('mail')?></div><h1><?=$success || ($u && $u['verified_at'])?t('Email verified'):t('Check your inbox')?></h1><?php errors($err); ?>
<?php if($success || ($u && $u['verified_at'])): ?><p><?=e(t('Your email is confirmed. You’re ready to explore.'))?></p><a class="pill primary" href="<?=e(url($u?'accounts.php':'login.php'))?>"><?=e(t('Continue →'))?></a>
<?php elseif($token && token_record($token,'verify')): ?><p><?=e(t('Confirm that this email address belongs to you.'))?></p><form method="post"><?=csrf()?><input type="hidden" name="action" value="confirm"><input type="hidden" name="token" value="<?=e($token)?>"><button class="pill primary wide"><?=e(t('Verify my email'))?></button></form>
<?php elseif($u): ?><p><?=e(t('We’ve queued a verification link for'))?> <strong><?=e($u['email'])?></strong><?=e(t('. It expires after 24 hours.'))?></p><form method="post"><?=csrf()?><button class="pill primary wide"><?=e(t('Resend verification email'))?></button></form>
<?php else: ?><p><?= $token?t('This verification link has expired or has already been used.'):t('Sign in to request a verification link.')?></p><a class="pill primary" href="<?=e(url('login.php'))?>"><?=e(t('Sign in'))?></a><?php endif ?><p class="hint"><?=e(t('Not seeing an email? Check spam or request another link.'))?></p></section></main><?php require ROOT.'/app/footer.php'; ?>
