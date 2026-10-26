<?php
declare(strict_types=1);
$config = require __DIR__.'/config.php';
date_default_timezone_set('UTC');
if (($config['env']??'local')==='production') {ini_set('display_errors','0');ini_set('log_errors','1');}
define('ROOT', dirname(__DIR__));
function cfg(string $key): mixed { global $config; return $config[$key] ?? null; }
if (PHP_SAPI !== 'cli') {
 ini_set('session.use_strict_mode', '1'); ini_set('session.use_only_cookies', '1');
 session_name('crave_session');
 session_set_cookie_params(['httponly'=>true, 'secure'=>str_starts_with(cfg('url'),'https://'), 'samesite'=>'Lax','path'=>parse_url(cfg('url'),PHP_URL_PATH) ?: '/']);
 session_start();
 header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: same-origin');
 header("Content-Security-Policy: default-src 'self'; img-src 'self' https://images.unsplash.com data:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
 header('Cache-Control: no-store');
}
function db(): PDO {
 static $pdo; if ($pdo) return $pdo;
 $dsn=cfg('db_driver')==='mysql' ? 'mysql:host='.cfg('db_host').';dbname='.cfg('db_name').';charset=utf8mb4' : 'sqlite:'.ROOT.'/storage/app.sqlite';
 $pdo=new PDO($dsn, cfg('db_driver')==='mysql'?cfg('db_user'):null, cfg('db_driver')==='mysql'?cfg('db_password'):null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
 if (cfg('db_driver')==='sqlite') {$pdo->exec('PRAGMA foreign_keys=ON'); $pdo->exec('PRAGMA busy_timeout=5000');}
 return $pdo;
}
function q(string $sql,array $params=[]): PDOStatement { $s=db()->prepare($sql);$s->execute($params);return $s; }
function one(string $sql,array $params=[]): ?array {return q($sql,$params)->fetch() ?: null;}
function all(string $sql,array $params=[]): array {return q($sql,$params)->fetchAll();}
function now(): string {return gmdate('Y-m-d H:i:s');}
function e(mixed $v): string {return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function url(string $p=''): string {return cfg('url').'/'.ltrim($p,'/');}
function go(string $p): never {header('Location: '.url($p));exit;}
function csrf(): string {$_SESSION['csrf'] ??= bin2hex(random_bytes(32));return '<input type="hidden" name="csrf" value="'.e($_SESSION['csrf']).'">';}
function post(): bool {
 if ($_SERVER['REQUEST_METHOD']!=='POST') return false;
 if (empty($_SESSION['csrf']) || !is_string($_POST['csrf']??null) || !hash_equals($_SESSION['csrf']??'',$_POST['csrf'])) {http_response_code(419);exit('Your session expired. Reload the page and try again.');}
 return true;
}
function val(string $k): string {return is_string($_POST[$k]??null) ? trim($_POST[$k]) : '';}
function raw(string $k): string {return is_string($_POST[$k]??null) ? $_POST[$k] : '';}
function param(string $k): string {return is_string($_GET[$k]??null) ? trim($_GET[$k]) : '';}
function flash(string $s): void {$_SESSION['flash']=$s;}
function user(): ?array {
 if (empty($_SESSION['user_id'])) return null;
 $u=one('SELECT * FROM users WHERE id=?',[$_SESSION['user_id']]);
 if (!$u || (int)$u['session_version']!==(int)($_SESSION['version']??-1)) {unset($_SESSION['user_id'],$_SESSION['version']);return null;}
 return $u;
}
function require_user(bool $verified=true): array {
 $u=user(); if (!$u) go('login.php');
 if ($verified && !$u['verified_at']) go('verify-email.php');
 return $u;
}
function require_admin(): array { $u=require_user(); if($u['role']!=='admin') fail(403,'This page is for the platform administrator.');return $u; }
function application(int $id): ?array {return one('SELECT * FROM seller_applications WHERE user_id=?',[$id]);}
function require_seller(): array {
 $u=require_user();$a=application((int)$u['id']);
 if ($u['role']!=='seller' || !$a || $a['status']!=='approved') go('seller/status.php');
 return $u;
}
function fail(int $status,string $message): never {
 http_response_code($status);$title='Something needs attention';require ROOT.'/app/header.php';
 echo '<main class="page narrow"><div class="panel"><h1>'.e($status).'</h1><p>'.e($message).'</p><a class="pill primary" href="'.e(url()).'">Back to home</a></div></main>';
 require ROOT.'/app/footer.php';exit;
}
function password_errors(string $a,string $b): array {
 $err=[];if (strlen($a)<8 || strlen($a)>72) $err[]='Use a password between 8 and 72 characters.';
 if ($a!==$b) $err[]='The passwords do not match.';return $err;
}
function email_valid(string $s): bool {return strlen($s)<=254 && filter_var($s,FILTER_VALIDATE_EMAIL)!==false;}
function text_valid(string $s,int $min,int $max): bool {return mb_strlen($s)>=$min && mb_strlen($s)<=$max;}
function errors(array $errors): void {if($errors) echo '<div role="alert" class="alert error"><ul><li>'.implode('</li><li>',array_map('e',$errors)).'</li></ul></div>';}
function field(string $name,string $label,string $type='text',string $extra='',?string $value=null): void {
 if ($value===null) $value=$type==='password'?'':val($name);
 echo '<div class="field"><label for="'.e($name).'">'.e($label).'</label><input id="'.e($name).'" name="'.e($name).'" type="'.e($type).'" value="'.e($value).'" '.$extra.'></div>';
}
function area(string $name,string $label,string $value='',string $extra=''):void {
 echo '<div class="field"><label for="'.e($name).'">'.e($label).'</label><textarea id="'.e($name).'" name="'.e($name).'" '.$extra.'>'.e($value).'</textarea></div>';
}
function limit(string $scope,int $max=10,int $seconds=900): bool {
 // Database-backed attempts survive new browser sessions. Do not trust forwarded client IP headers.
 $key=hash('sha256',$scope.'|'.($_SERVER['REMOTE_ADDR']??'cli'));
 $cut=gmdate('Y-m-d H:i:s',time()-$seconds);
 q('DELETE FROM attempts WHERE created_at < ?', [gmdate('Y-m-d H:i:s',time()-86400)]);
 if ((int)q('SELECT COUNT(*) FROM attempts WHERE rate_key=? AND created_at>?',[$key,$cut])->fetchColumn()>=$max) return false;
 q('INSERT INTO attempts (rate_key,created_at) VALUES (?,?)',[$key,now()]);return true;
}
function token_issue(int $id,string $purpose): string {
 $token=bin2hex(random_bytes(32));
 q('DELETE FROM tokens WHERE user_id=? AND purpose=?',[$id,$purpose]);
 q('INSERT INTO tokens (user_id,purpose,token_hash,expires_at,created_at) VALUES (?,?,?,?,?)',[$id,$purpose,hash('sha256',$token),gmdate('Y-m-d H:i:s',time()+($purpose==='verify'?86400:3600)),now()]);return $token;
}
function token_record(string $token,string $purpose): ?array {
 if (!preg_match('/^[a-f0-9]{64}$/',$token)) return null;
 return one('SELECT * FROM tokens WHERE token_hash=? AND purpose=? AND expires_at>?',[hash('sha256',$token),$purpose,now()]);
}
function consume_token(string $token,string $purpose): ?array {
 $t=token_record($token,$purpose);if(!$t)return null;
 // Conditional delete makes concurrent consumption one-time too.
 $n=q('DELETE FROM tokens WHERE id=? AND expires_at>?',[$t['id'],now()])->rowCount();return $n===1?$t:null;
}
function queue_mail(string $to,string $subject,string $body): void {
 q('INSERT INTO outbox (recipient,subject,body,status,created_at) VALUES (?,?,?,?,?)',[$to,$subject,$body,'pending',now()]);
}
function send_outbox(): void {
 if (cfg('env')==='production' && cfg('mail_transport')!=='mail') throw new RuntimeException('Production requires MAIL_TRANSPORT=mail and a configured server mail service.');
 foreach(all("SELECT * FROM outbox WHERE status='pending' ORDER BY id LIMIT 30") as $m){
  // Claim before external delivery; failures are retryable from CLI. Delivery acceptance is not inbox confirmation.
  if(q("UPDATE outbox SET status='sending' WHERE id=? AND status='pending'",[$m['id']])->rowCount()!==1)continue;
  if(cfg('mail_transport')==='log'){$ok=file_put_contents(ROOT.'/storage/mail.log',"\nTO: {$m['recipient']}\n{$m['subject']}\n{$m['body']}\n",FILE_APPEND|LOCK_EX)!==false; $status=$ok?'logged':'failed';}
  else {$ok=mail($m['recipient'],$m['subject'],$m['body'],['From'=>cfg('mail_from'),'Content-Type'=>'text/plain; charset=UTF-8']);$status=$ok?'sent':'failed';}
  q('UPDATE outbox SET status=? WHERE id=?',[$status,$m['id']]);
 }
}
function verification_mail(array $u): void {
 $t=token_issue((int)$u['id'],'verify');queue_mail($u['email'],'Verify your Zayqah email',"Hello {$u['full_name']},\nConfirm your email within 24 hours:\n".url('verify-email.php?token='.$t)."\nIf this was not you, ignore this email.");
}
function mail_after_commit(): void {try {send_outbox();}catch(Throwable $ex){error_log('Mail delivery failed: '.$ex->getMessage());}}
function encrypt_cnic(string $cnic): string {
 $key=base64_decode(cfg('key'),true);if($key===false || strlen($key)!==32)throw new RuntimeException('Run php bin/setup.php to generate APP_KEY.');
 $iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($cnic,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag);
 if($cipher===false)throw new RuntimeException('Encryption failed.');return base64_encode($iv.$tag.$cipher);
}
function decrypt_cnic(string $encrypted): string {
 $v=base64_decode($encrypted,true);$key=base64_decode(cfg('key'),true);
 if(!$v || strlen($v)<29 || !$key)return '[unavailable]';
 return openssl_decrypt(substr($v,28),'aes-256-gcm',$key,OPENSSL_RAW_DATA,substr($v,0,12),substr($v,12,16)) ?: '[unavailable]';
}
function upload(string $field,string $kind,int $owner,bool $required=true): ?string {
 $f=$_FILES[$field]??null;
 if(!$f || ($f['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE){if($required)throw new InvalidArgumentException('Please upload '.str_replace('_',' ',$field).'.');return null;}
 if($f['error']!==UPLOAD_ERR_OK || $f['size']>5*1024*1024)throw new InvalidArgumentException('Images must upload successfully and be under 5 MB.');
 $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);$ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime]??null;
 $size=@getimagesize($f['tmp_name']);
 if(!$ext || !$size || $size[0]>8000 || $size[1]>8000)throw new InvalidArgumentException('Choose a JPG, PNG or WebP image up to 8000 × 8000 pixels.');
 $id=bin2hex(random_bytes(24));$path=$id.'.'.$ext;
 if(!move_uploaded_file($f['tmp_name'],ROOT.'/storage/uploads/'.$path))throw new RuntimeException('Upload could not be saved.');
 chmod(ROOT.'/storage/uploads/'.$path,0600);
 q('INSERT INTO files (id,owner_id,kind,path,mime,created_at) VALUES (?,?,?,?,?,?)',[$id,$owner,$kind,$path,$mime,now()]);return $id;
}
function image_url(?string $id): string {return $id?url('media.php?id='.$id):url('assets/placeholder.svg');}
function local_time(?string $time,string $format='d M Y, g:i a'): string {return $time?(new DateTimeImmutable($time,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone(cfg('timezone')))->format($format):'—';}
function utc_expiry(string $v): ?string {
 $tz=new DateTimeZone(cfg('timezone'));$d=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$v,$tz);
 if(!$d || $d->format('Y-m-d\TH:i')!==$v || $d->getTimestamp()<=time())return null;
 return $d->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}
function audit(int $admin,string $action,int $target): void {q('INSERT INTO audit_logs (admin_id,action,target_id,created_at) VALUES (?,?,?,?)',[$admin,$action,$target,now()]);}
set_exception_handler(function(Throwable $e){
 try {if(db()->inTransaction())db()->rollBack();}catch(Throwable $ignored){} error_log((string)$e);
 if(PHP_SAPI==='cli'){fwrite(STDERR,$e->getMessage()."\n");exit(1);}fail(500,'The request could not be completed. Check server configuration or try again.');
});

require_once ROOT.'/app/icons.php';
require_once ROOT.'/app/features.php';
