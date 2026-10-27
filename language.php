<?php
require __DIR__.'/app/bootstrap.php';
if(!post()){header('Allow: POST');http_response_code(405);exit;}
$language=val('language');if(!in_array($language,['en','ur'],true))fail(400,'Unsupported language.');
$_SESSION['language']=$language;
setcookie('zayqah_language',$language,['expires'=>time()+31536000,'path'=>parse_url(cfg('url'),PHP_URL_PATH)?:'/','secure'=>str_starts_with(cfg('url'),'https://'),'httponly'=>true,'samesite'=>'Lax']);
$return=val('return_to');$parts=parse_url($return);$path=$parts['path']??'index.php';$query=$parts['query']??'';
if($parts===false||isset($parts['scheme'])||isset($parts['host'])||str_contains($return,'\\')||preg_match('/[\r\n]/',$return)||!preg_match('~^(?:seller/|admin/|customer/)?[a-z0-9-]+\.php$~i',$path)||!is_file(ROOT.'/'.$path)){$path='index.php';$query='';}
if($path==='language.php'){$path='index.php';$query='';}
go($path.($query!==''?'?'.$query:''));
