<?php
require __DIR__.'/../app/bootstrap.php';$u=require_user();if($u['role']==='admin')go('admin/index.php');$existing=application((int)$u['id']);
if($existing && $existing['status']!=='rejected')go('seller/status.php');$title='Become a seller';$err=[];
if(post()){
 $name=val('business_name');$address=val('address');$phone=val('business_phone');$cnic=preg_replace('/\D/','',val('cnic'));
 if(!limit('apply',5,3600))$err[]='Too many application attempts. Try again in an hour.';
 if(!text_valid($name,2,120))$err[]='Enter a business name (2–120 characters).';
 if(!text_valid($address,10,1500))$err[]='Enter a complete business address (10–1500 characters).';
 if(!preg_match('/^\+?[0-9 ()-]{8,25}$/',$phone) || strlen(preg_replace('/\D/','',$phone))<8)$err[]='Enter a valid business phone number.';
 if(strlen($cnic)!==13)$err[]='Enter a 13-digit CNIC number.';
 if(val('consent')!=='yes')$err[]='Please confirm your business and identity details are accurate and consent to admin review.';
 if(!$err){try{db()->beginTransaction();
  // Lock the account so repeat submissions cannot overwrite an approved application.
  q('UPDATE users SET full_name=full_name WHERE id=?',[$u['id']]);$latest=application((int)$u['id']);
  if($latest && $latest['status']!=='rejected')throw new InvalidArgumentException('An application is already pending or approved.');
  $logo=upload('business_logo','logo',(int)$u['id']);$front=upload('cnic_front','cnic',(int)$u['id']);$back=upload('cnic_back','cnic',(int)$u['id']);
  $values=[$name,$address,$phone,$logo,encrypt_cnic($cnic),$front,$back,'pending',now()];
  if($latest)q('UPDATE seller_applications SET business_name=?,address=?,business_phone=?,logo_id=?,cnic_encrypted=?,cnic_front=?,cnic_back=?,status=?,submitted_at=?,review_note=NULL,reviewed_by=NULL,reviewed_at=NULL WHERE user_id=?',[...$values,$u['id']]);
  else q('INSERT INTO seller_applications (business_name,address,business_phone,logo_id,cnic_encrypted,cnic_front,cnic_back,status,submitted_at,user_id) VALUES (?,?,?,?,?,?,?,?,?,?)',[...$values,$u['id']]);
  queue_mail($u['email'],'Your seller profile is under review',"Hello {$u['full_name']},\nYour application for {$name} is under review. We will email you when the administrator makes a decision.\n".url('seller/status.php'));
  db()->commit();mail_after_commit();flash('Application submitted. Your profile is under review; a notification email has been queued.');go('seller/status.php');
 }catch(InvalidArgumentException $ex){if(db()->inTransaction())db()->rollBack();$err[]=$ex->getMessage();}}
}
require ROOT.'/app/header.php'; ?>
<main class="page shell medium" id="main"><div class="page-heading"><span class="heading-kicker">FROM YOUR KITCHEN TO THE COMMUNITY</span><h1>Let’s meet your business.</h1><p>Verify your details, submit for review, then start sharing your menu.</p></div><ol class="steps"><li class="done">01 · Verified account</li><li class="active">02 · Business details</li><li>03 · Admin review</li><li>04 · Start selling</li></ol><section class="panel padded"><?php errors($err); ?><form method="post" enctype="multipart/form-data"><?=csrf()?><h2>Business information</h2><div class="form-grid"><?php field('business_name','Business name','text','required minlength="2" maxlength="120"');field('business_phone','Business phone','tel','required maxlength="25"',val('business_phone')?:$u['phone']); ?></div><?php area('address','Full business address',val('address'),'required minlength="10" maxlength="1500" placeholder="Street, area, city and postal code"');field('business_logo','Business logo','file','required accept="image/jpeg,image/png,image/webp"'); ?><p class="hint">JPG, PNG or WebP · Maximum 5 MB per image.</p><hr><h2>Identity verification</h2><p class="hint">Your CNIC number is encrypted. CNIC images are private and accessible only to the administrator.</p><?php field('cnic','CNIC number','text','required inputmode="numeric" maxlength="15" placeholder="12345-1234567-1"'); ?><div class="form-grid"><?php field('cnic_front','CNIC front image','file','required accept="image/jpeg,image/png,image/webp"');field('cnic_back','CNIC back image','file','required accept="image/jpeg,image/png,image/webp"'); ?></div><label class="check"><input type="checkbox" name="consent" value="yes" required><span>I confirm these details are accurate and consent to identity review as described in the <a href="<?=e(url('privacy.php'))?>">privacy notice</a>.</span></label><button class="pill primary">Submit for review →</button></form></section></main><?php require ROOT.'/app/footer.php'; ?>
