<?php
require __DIR__.'/app/bootstrap.php';feature_required();$title=t('Confirm your new email');$err=[];$done=false;$token=param('token');
if(post()){
 $token=val('token');
 if(!limit('confirm-email',10))$err[]=t('Too many attempts. Please try again later.');
 if(!$err){try{db()->beginTransaction();$change=change_record($token);
  if(!$change){db()->rollBack();$err[]=t('This link has expired, was cancelled, or has already been used.');}
  else{$claimed=q('DELETE FROM email_changes WHERE user_id=? AND token_hash=? AND expires_at>?',[$change['user_id'],hash('sha256',$token),now()])->rowCount();
   if($claimed!==1){db()->rollBack();$err[]=t('This link has already been used.');}
   else{$changed=q('UPDATE users SET email=?,verified_at=?,session_version=session_version+1 WHERE id=? AND email=? AND session_version=?',[$change['new_email'],now(),$change['user_id'],$change['old_email'],$change['session_version']])->rowCount();
    if($changed!==1){db()->rollBack();$err[]=t('Your account has changed. Request a new email verification link.');}
    else{q('DELETE FROM tokens WHERE user_id=?',[$change['user_id']]);queue_mail($change['old_email'],t('Your Zayqah email was changed'),"Your account email has been changed to {$change['new_email']}. If you did not authorize this, contact the platform administrator.");db()->commit();mail_after_commit();if((int)($_SESSION['user_id']??0)===(int)$change['user_id']){session_regenerate_id(true);$_SESSION['version']=(int)$change['session_version']+1;}$done=true;}
   }
  }
 }catch(PDOException $ex){if(db()->inTransaction())db()->rollBack();if(in_array((string)$ex->getCode(),['23000','23505']))$err[]=t('That email is already registered. Choose a different email in account settings.');else throw $ex;}}
}
$change=$done?null:change_record($token);require ROOT.'/app/header.php'; ?>
<main class="page narrow" id="main"><section class="auth-card"><div class="status-icon"><?=ui_icon('mail')?></div><h1><?=$done?t('Your email is updated.'):t('Confirm your new email')?></h1><?php errors($err); ?><?php if($done): ?><p><?=e(t('Your new email is verified. Use it the next time you sign in.'))?></p><a class="pill primary" href="<?=e(url(user()?'accounts.php':'login.php'))?>"><?=e(t('Continue →'))?></a><?php elseif($change): ?><p><?=e(t('Use'))?> <strong><?=e($change['new_email'])?></strong> <?=e(t('as your new account email?'))?></p><form method="post"><?=csrf()?><input type="hidden" name="token" value="<?=e($token)?>"><button class="pill primary wide"><?=e(t('Confirm email change'))?></button></form><?php else: ?><p><?=e(t('This link is invalid, expired or already used.'))?></p><a class="pill" href="<?=e(url('settings.php#email'))?>"><?=e(t('Account settings'))?></a><?php endif ?></section></main><?php require ROOT.'/app/footer.php'; ?>
