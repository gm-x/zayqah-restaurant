<?php
require __DIR__.'/app/bootstrap.php';$id=param('id');if(!preg_match('/^[a-f0-9]{48}$/',$id))fail(404,t('Image not found.'));$f=one('SELECT * FROM files WHERE id=?',[$id]);if(!$f)fail(404,t('Image not found.'));$u=user();$admin=$u && $u['role']==='admin' && $u['verified_at'];$owner=$u && (int)$u['id']===(int)$f['owner_id'];
$allowed=false;
if($f['kind']==='cnic')$allowed=(bool)$admin;
elseif($admin || $owner)$allowed=true;
elseif($f['kind']==='logo')$allowed=(bool)one("SELECT id FROM seller_applications WHERE logo_id=? AND status='approved'",[$id]);
elseif($f['kind']==='menu')$allowed=(bool)one("SELECT m.id FROM menu_items m JOIN categories c ON c.id=m.category_id JOIN seller_applications s ON s.user_id=m.seller_id WHERE m.photo_id=? AND ".live_sql('m')." AND c.active=1 AND s.status='approved'",[$id]);
if(!$allowed)fail(404,t('Image not found.'));$path=ROOT.'/storage/uploads/'.basename($f['path']);if(!is_file($path))fail(404,t('Image not found.'));
header('Content-Type: '.$f['mime']);header('Content-Disposition: inline; filename="image.'.pathinfo($path,PATHINFO_EXTENSION).'"');header('Content-Length: '.filesize($path));header('Cache-Control: private, no-store');readfile($path);
