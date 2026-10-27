<?php
require __DIR__.'/app/bootstrap.php';$title=t('Forgot password');$done=false;$err=[];
if(post()){
 if(!limit('forgot',5,3600))$err[]=t('Too many requests. Please try again in an hour.');
 elseif(!email_valid(val('email')))$err[]=t('Enter a valid email address.');
 else{$u=one('SELECT * FROM users WHERE email=?',[strtolower(val('email'))]);
  if($u){db()->beginTransaction();$t=token_issue((int)$u['id'],'reset');queue_mail($u['email'],t('Reset your Zayqah password'),t("Reset your password within one hour:\n").url('reset-password.php?token='.$t)."\nIgnore this email if you did not request it.");db()->commit();mail_after_commit();}$done=true;
 }
}
require ROOT.'/app/header.php'; ?>
<main class="page narrow" id="main"><section class="auth-card"><span class="heading-kicker"><?=e(t('LET’S GET YOU BACK IN'))?></span><h1><?=e(t('Forgot password?'))?></h1><?php errors($err); ?><?php if($done): ?><div class="alert"><?=e(t('If an account exists for this email, a password reset link has been queued.'))?></div><?php else: ?><p><?=e(t('Enter your email and we’ll send a secure reset link.'))?></p><form method="post"><?=csrf()?><?php field('email',t('Email address'),'email','required autocomplete="email" maxlength="254"'); ?><button class="pill primary wide space-top"><?=e(t('Send reset link'))?></button></form><?php endif ?><p><a href="<?=e(url('login.php'))?>"><?=e(t('← Back to sign in'))?></a></p></section></main><?php require ROOT.'/app/footer.php'; ?>
