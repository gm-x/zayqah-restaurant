<?php
require __DIR__.'/app/bootstrap.php';$u=require_user(false);$title='Account settings';$err=[];$action='';
if(post()){
 $action=val('action');
 if($action==='profile'){
  $name=val('full_name');$phone=val('phone');$dob=val('dob');$address=val('delivery_address');$tz=new DateTimeZone(cfg('timezone'));$d=DateTimeImmutable::createFromFormat('!Y-m-d',$dob,$tz);$today=new DateTimeImmutable('today',$tz);
  if(!text_valid($name,2,120))$err[]='Enter your full name (2–120 characters).';
  if(!preg_match('/^\+?[0-9 ()-]{8,25}$/',$phone)||strlen(preg_replace('/\D/','',$phone))<8)$err[]='Enter a valid phone number with country code.';
  if(!$d||$d->format('Y-m-d')!==$dob||$d>$today||$d<$today->modify('-120 years'))$err[]='Enter a valid date of birth.';
  if(!text_valid($address,0,1000))$err[]='Delivery address must be under 1000 characters.';
  if(!$err){q('UPDATE users SET full_name=?,phone=?,dob=?,delivery_address=? WHERE id=?',[$name,$phone,$dob,$address,$u['id']]);flash('Your profile has been updated.');go('settings.php');}
 }elseif($action==='email'){
  if(!update02_ready())$err[]='The account update is not installed yet. Please ask the administrator to complete the update.';
  elseif(!limit('change-email',5,3600))$err[]='Too many email change requests. Please try again in an hour.';
  $email=strtolower(val('new_email'));
  if(!email_valid($email))$err[]='Enter a valid new email address.';
  if($email===$u['email'])$err[]='Enter a different email address.';
  if(!password_verify(raw('email_password'),$u['password_hash']))$err[]='Your current password is incorrect.';
  if(!$err){try{db()->beginTransaction();
   if(one('SELECT id FROM users WHERE email=?',[$email])){$err[]='This email address is already registered.';db()->rollBack();}
   else{q('DELETE FROM email_changes WHERE expires_at<=?',[now()]);q('DELETE FROM email_changes WHERE user_id=?',[$u['id']]);$token=bin2hex(random_bytes(32));
    q('INSERT INTO email_changes (user_id,new_email,old_email,session_version,token_hash,expires_at,created_at) VALUES (?,?,?,?,?,?,?)',[$u['id'],$email,$u['email'],$u['session_version'],hash('sha256',$token),gmdate('Y-m-d H:i:s',time()+3600),now()]);
    queue_mail($email,'Confirm your new Crave email',"Hello {$u['full_name']},\nConfirm your new email address within one hour:\n".url('confirm-email.php?token='.$token)."\nYour current email will remain active until you confirm this change.");db()->commit();mail_after_commit();flash('Verification queued for your new email. Your current email stays active until you confirm it.');go('settings.php#email');}
  }catch(PDOException $ex){if(db()->inTransaction())db()->rollBack();if(in_array((string)$ex->getCode(),['23000','23505']))$err[]='This email address is already registered or awaiting confirmation.';else throw $ex;}}
 }elseif($action==='cancel-email'){
  if(update02_ready())q('DELETE FROM email_changes WHERE user_id=?',[$u['id']]);flash('Pending email change cancelled.');go('settings.php#email');
 }elseif($action==='password'){
  if(!limit('change-password',5,3600))$err[]='Too many password change attempts. Please try again in an hour.';
  if(!password_verify(raw('current_password'),$u['password_hash']))$err[]='Your current password is incorrect.';
  $err=array_merge($err,password_errors(raw('password'),raw('password_confirm')));
  if(!$err){db()->beginTransaction();$changed=q('UPDATE users SET password_hash=?,session_version=session_version+1 WHERE id=? AND session_version=?',[password_hash(raw('password'),PASSWORD_DEFAULT),$u['id'],$u['session_version']])->rowCount();
   if($changed!==1){db()->rollBack();$err[]='Your account changed in another session. Sign in again before changing your password.';}
   else{q('DELETE FROM tokens WHERE user_id=? AND purpose=?',[$u['id'],'reset']);if(update02_ready())q('DELETE FROM email_changes WHERE user_id=?',[$u['id']]);db()->commit();session_regenerate_id(true);$_SESSION['version']=(int)$u['session_version']+1;flash('Password updated. Other sessions have been signed out and pending email changes cancelled.');go('settings.php#password');}
  }
 }else $err[]='Unknown settings action.';
}
$pending=update02_ready()?one('SELECT new_email,expires_at FROM email_changes WHERE user_id=? AND session_version=? AND expires_at>?',[$u['id'],$u['session_version'],now()]):null;
function profile_value(string $key): string {global $u,$action;return $_SERVER['REQUEST_METHOD']==='POST' && $action==='profile'?val($key):(string)($u[$key]??'');}
require ROOT.'/app/header.php'; ?>
<main class="page shell" id="main"><div class="dash-header"><div><span class="heading-kicker">MAKE YOURSELF AT HOME</span><h1>Account settings</h1><p>Keep your details up to date and your account secure.</p></div><a class="pill" href="<?=e(url('accounts.php'))?>">← My Account</a></div><?php errors($err); ?><div class="settings-layout"><nav class="settings-nav" aria-label="Account settings"><a href="#profile">Personal details</a><a href="#email">Email address</a><a href="#password">Password & security</a><a href="<?=e(url('index.php'))?>">Explore Food →</a></nav><div class="settings-sections">
<section class="panel padded" id="profile"><h2>Personal details</h2><p class="hint">Update your name, phone number and delivery address.</p><form method="post"><?=csrf()?><input type="hidden" name="action" value="profile"><div class="form-grid"><?php field('full_name','Full name','text','required minlength="2" maxlength="120" autocomplete="name"',profile_value('full_name'));field('dob','Date of birth','date','required max="'.e((new DateTimeImmutable('today',new DateTimeZone(cfg('timezone'))))->format('Y-m-d')).'"',profile_value('dob'));field('phone','Phone number','tel','required maxlength="25" autocomplete="tel"',profile_value('phone')); ?></div><?php area('delivery_address','Delivery address (optional)',profile_value('delivery_address'),'maxlength="1000" autocomplete="street-address"'); ?><button class="pill primary space-top">Save personal details</button></form></section>
<section class="panel padded" id="email"><h2>Email address</h2><p>Current email: <strong><?=e($u['email'])?></strong></p><p class="hint">Confirm with your current password. We’ll verify the new address before changing your login email.</p><?php if($pending): ?><div class="alert">Waiting for confirmation: <strong><?=e($pending['new_email'])?></strong><br>Link expires <?=e(local_time($pending['expires_at']))?>.</div><form method="post"><?=csrf()?><input type="hidden" name="action" value="cancel-email"><button class="pill">Cancel pending change</button></form><?php endif ?><form method="post"><?=csrf()?><input type="hidden" name="action" value="email"><div class="form-grid"><?php field('new_email','New email address','email','required maxlength="254" autocomplete="email"');field('email_password','Current password','password','required autocomplete="current-password" maxlength="72"'); ?></div><button class="pill primary space-top">Verify new email →</button></form></section>
<section class="panel padded" id="password"><h2>Password & security</h2><p class="hint">Use a unique password with 8–72 characters. Your other sessions will be signed out.</p><form method="post"><?=csrf()?><input type="hidden" name="action" value="password"><?php field('current_password','Current password','password','required autocomplete="current-password" maxlength="72"'); ?><div class="form-grid"><?php field('password','New password','password','required minlength="8" maxlength="72" autocomplete="new-password"');field('password_confirm','Confirm new password','password','required minlength="8" maxlength="72" autocomplete="new-password"'); ?></div><button class="pill primary space-top">Update password</button></form></section></div></div></main><?php require ROOT.'/app/footer.php'; ?>
