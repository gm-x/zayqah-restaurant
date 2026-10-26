<?php
require __DIR__.'/app/bootstrap.php';if(user())go('accounts.php');$title='Create your account';$err=[];
if(post()){
 if(!limit('signup',5,3600))$err[]='Too many signup attempts. Please try again in an hour.';
 $name=val('full_name');$dob=val('dob');$phone=val('phone');$email=strtolower(val('email'));$address=val('delivery_address');
 if(!text_valid($name,2,120))$err[]='Enter your full name (2–120 characters).';
 $d=DateTimeImmutable::createFromFormat('!Y-m-d',$dob,new DateTimeZone(cfg('timezone')));$today=new DateTimeImmutable('today',new DateTimeZone(cfg('timezone')));
 if(!$d || $d->format('Y-m-d')!==$dob || $d>$today || $d<$today->modify('-120 years'))$err[]='Enter a valid date of birth.';
 if(!preg_match('/^\+?[0-9 ()-]{8,25}$/',$phone) || strlen(preg_replace('/\D/','',$phone))<8)$err[]='Enter a valid phone number with country code.';
 if(!email_valid($email))$err[]='Enter a valid email address.';
 if(!text_valid($address,0,1000))$err[]='Delivery address must be at most 1000 characters.';
 $err=array_merge($err,password_errors(raw('password'),raw('password_confirm')));
 if(val('terms')!=='yes')$err[]='Please accept the terms and privacy notice.';
 if(!$err){
  try{db()->beginTransaction();q('INSERT INTO users (full_name,dob,phone,email,password_hash,role,delivery_address,created_at) VALUES (?,?,?,?,?,?,?,?)',[$name,$dob,$phone,$email,password_hash(raw('password'),PASSWORD_DEFAULT),'customer',$address,now()]);$id=(int)db()->lastInsertId();$u=one('SELECT * FROM users WHERE id=?',[$id]);verification_mail($u);db()->commit();
   session_regenerate_id(true);$_SESSION['user_id']=$id;$_SESSION['version']=0;mail_after_commit();flash('Your account is ready. Check your email to verify it.');go('verify-email.php');
  }catch(PDOException $ex){if(db()->inTransaction())db()->rollBack();if(in_array((string)$ex->getCode(),['23000','23505']))$err[]='This email is already registered. Sign in or reset your password.';else throw $ex;}
 }
}
require ROOT.'/app/header.php'; ?>
<main class="auth-layout shell" id="main"><section class="auth-story"><span class="eyebrow">GOOD FOOD, GREAT COMPANY</span><h1>Your neighborhood.<br><em>A whole new menu.</em></h1><p>Create one account to discover local food. Ready to share your own? Apply to sell whenever you like.</p><div class="story-art" aria-hidden="true"><?=ui_icon('bowl')?></div><small>Local kitchens. Fresh possibilities.</small></section><section class="auth-card"><span class="heading-kicker">LET’S GET YOU STARTED</span><h2>Create an account</h2><p>Already part of the community? <a href="<?=e(url('login.php'))?>">Sign in</a></p><?php errors($err); ?><form method="post"><?=csrf()?><div class="form-grid"><?php field('full_name','Full name','text','required minlength="2" maxlength="120" autocomplete="name"');field('dob','Date of birth','date','required max="'.e((new DateTimeImmutable('today',new DateTimeZone(cfg('timezone'))))->format('Y-m-d')).'" autocomplete="bday"');field('phone','Phone number','tel','required maxlength="25" autocomplete="tel" placeholder="+92 300 1234567"');field('email','Email address','email','required maxlength="254" autocomplete="email"');field('password','Password','password','required minlength="8" maxlength="72" autocomplete="new-password"');field('password_confirm','Confirm password','password','required minlength="8" maxlength="72" autocomplete="new-password"'); ?></div><p class="hint">Use 8–72 characters. A long, unique passphrase works well.</p><?php area('delivery_address','Delivery address (optional)',val('delivery_address'),'maxlength="1000" autocomplete="street-address"'); ?><label class="check"><input name="terms" value="yes" type="checkbox" required <?=val('terms')==='yes'?'checked':''?>> <span>I agree to the <a href="<?=e(url('terms.php'))?>">terms</a> and <a href="<?=e(url('privacy.php'))?>">privacy notice</a>.</span></label><button class="pill primary wide">Create customer account →</button></form></section></main><?php require ROOT.'/app/footer.php'; ?>
