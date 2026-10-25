<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../app/bootstrap.php';
$email=strtolower(trim(getenv('ADMIN_EMAIL')?:''));$password=getenv('ADMIN_PASSWORD')?:'';$name=getenv('ADMIN_NAME')?:'Platform Admin';
if(!email_valid($email)||strlen($password)<12||strlen($password)>72)exit("Set ADMIN_EMAIL, ADMIN_NAME and ADMIN_PASSWORD (12–72 characters).\n");
if(one('SELECT id FROM users WHERE email=?',[$email]))exit("Email is already registered. No account changed.\n");
q('INSERT INTO users (full_name,dob,phone,email,password_hash,role,verified_at,created_at) VALUES (?,?,?,?,?,?,?,?)',[$name,'2000-01-01','Not provided',$email,password_hash($password,PASSWORD_DEFAULT),'admin',now(),now()]);
echo "Administrator created. Sign in at ".url('login.php')."\n";
