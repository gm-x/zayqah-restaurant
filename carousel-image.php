<?php
require __DIR__.'/app/bootstrap.php';
require_once ROOT.'/app/carousel.php';
$id=filter_var(param('id'),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if (!$id || !carousel_ready()) fail(404,'Image not found.');
$slide=one("SELECT s.active,f.path,f.mime FROM homepage_slides s JOIN files f ON f.id=s.photo_id WHERE s.id=? AND f.kind='carousel'",[$id]);
if (!$slide) fail(404,'Image not found.');
if (!(int)$slide['active']) {
    $u=user();
    if (!$u || $u['role']!=='admin' || !$u['verified_at']) fail(404,'Image not found.');
}
$path=ROOT.'/storage/uploads/'.basename($slide['path']);
if (!is_file($path) || !in_array($slide['mime'],['image/jpeg','image/png','image/webp'],true)) fail(404,'Image not found.');
header('Content-Type: '.$slide['mime']);
header('Content-Disposition: inline; filename="slide.'.pathinfo($path,PATHINFO_EXTENSION).'"');
header('Content-Length: '.filesize($path));
header('Cache-Control: no-store');
readfile($path);
