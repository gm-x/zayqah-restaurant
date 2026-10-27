<?php
require __DIR__.'/app/bootstrap.php';if(user())go('accounts.php');$title=t('Welcome back');$err=[];
if(post()){
 $email=strtolower(val('email'));
 if(!limit('login',10))$err[]=t('Too many sign-in attempts. Try again in 15 minutes.');
 if(!$err){$u=one('SELECT * FROM users WHERE email=?',[$email]);
  $valid=password_verify(raw('password'),$u['password_hash']??'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
  if(!$u || !$valid)$err[]=t('Email or password is incorrect.');
  else{session_regenerate_id(true);$_SESSION['user_id']=$u['id'];$_SESSION['version']=$u['session_version'];unset($_SESSION['csrf']);go(!$u['verified_at']?'verify-email.php':($u['role']==='admin'?'admin/index.php':($u['role']==='seller'?'seller/index.php':'index.php')));}
 }
}
require ROOT.'/app/header.php'; ?>
<main class="auth-layout shell" id="main"><section class="auth-story"><span class="eyebrow"><?=e(t('WELCOME TO YOUR FOOD COMMUNITY'))?></span><h1><?=e(t('A little hungry?'))?><br><em><?=e(t('You’re in the right place.'))?></em></h1><p><?=e(t('Your favorite kitchens and your next great meal, all in one place.'))?></p><div class="story-art" aria-hidden="true"><?=ui_icon('bowl')?></div></section><section class="auth-card"><span class="heading-kicker"><?=e(t('GOOD TO SEE YOU AGAIN'))?></span><h2><?=e(t('Welcome back'))?></h2><p><?=e(t('New here?'))?> <a href="<?=e(url('signup.php'))?>"><?=e(t('Create an account'))?></a></p><?php errors($err); ?><form method="post"><?=csrf()?><?php field('email',t('Email address'),'email','required autocomplete="email" maxlength="254"');field('password',t('Password'),'password','required autocomplete="current-password" maxlength="72"'); ?><div class="form-links"><span><?=e(t('One sign-in for every role'))?></span><a href="<?=e(url('forgot-password.php'))?>"><?=e(t('Forgot password?'))?></a></div><button class="pill primary wide"><?=e(t('Sign in →'))?></button></form></section></main><?php require ROOT.'/app/footer.php'; ?>
