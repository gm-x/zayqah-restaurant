<?php
require __DIR__.'/app/bootstrap.php';$u=require_user(false);require_experience();$title=t('Contact us');$err=[];
$type=$_SERVER['REQUEST_METHOD']==='POST'?val('account_type'):($u['role']==='seller'?'seller':'customer');
if(post()){
 if(!in_array($type,['seller','customer'],true))$err[]=t('Choose Seller or Customer.');
 if(!text_valid(val('description'),10,4000))$err[]=t('Describe your request in 10–4000 characters.');
 if(!limit('contact:'.$u['id'],5,3600))$err[]=t('You’ve sent several requests. Please try again in an hour.');
 if(!$err){db()->beginTransaction();q('INSERT INTO contact_messages (user_id,full_name,email,phone,account_type,description,created_at) VALUES (?,?,?,?,?,?,?)',[$u['id'],$u['full_name'],$u['email'],$u['phone'],$type,val('description'),now()]);$id=(int)db()->lastInsertId();
 foreach(all("SELECT id FROM users WHERE role='admin'") as $admin)notify_user((int)$admin['id'],null,'contact',t('New contact request'),'Request #'.$id.' · '.ucfirst($type),'admin/contact-messages.php?id='.$id,'contact:'.$id.':'.$admin['id']);db()->commit();flash(t('Your request has been sent. The team can contact you using your account details.'));go('contact.php');}
}
require ROOT.'/app/header.php'; ?>
<main class="page narrow" id="main"><section class="auth-card"><span class="heading-kicker"><?=e(t('WE’RE HERE TO HELP'))?></span><h1><?=e(t('Contact us'))?></h1><p><?=e(t('Your account details are filled in automatically.'))?></p><?php errors($err); ?><form method="post"><?=csrf()?><div class="form-grid"><?php field('contact_name',t('Name'),'text','readonly',$u['full_name']);field('contact_email',t('Email'),'email','readonly',$u['email']);field('contact_phone',t('Phone'),'tel','readonly',$u['phone']); ?></div><p class="hint"><?=e(t('Need to change these details?'))?> <a href="<?=e(url('settings.php'))?>"><?=e(t('Open account settings'))?></a>.</p>
<fieldset class="contact-type"><legend><?=e(t('I’m contacting you as a'))?></legend><label><input name="account_type" type="radio" value="customer" required <?=$type==='customer'?'checked':''?>> <?=e(t('Customer'))?></label><label><input name="account_type" type="radio" value="seller" required <?=$type==='seller'?'checked':''?>> <?=e(t('Seller'))?></label></fieldset>
<?php area('description',t('How can we help?'),val('description'),'required minlength="10" maxlength="4000" rows="6"'); ?><button class="pill primary wide space-top"><?=e(t('Send request'))?></button></form></section></main><?php require ROOT.'/app/footer.php'; ?>
