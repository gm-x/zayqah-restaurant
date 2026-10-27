<?php
require __DIR__.'/../app/bootstrap.php';
$u=require_admin();$title=t('Homepage carousel');$err=[];
require_once ROOT.'/app/carousel.php';
$ready=carousel_ready();
$edit=max(0,(int)param('edit'));
$editing=$ready && $edit?one('SELECT * FROM homepage_slides WHERE id=?',[$edit]):null;
if (post()) {
    if (!$ready) $err[]='Install the carousel database update first (see the included installation instructions).';
    else {
        $action=val('action');$id=(int)val('id');$newPath=null;
        try {
            if (!in_array($action,['save','toggle','delete'],true)) throw new InvalidArgumentException(t('Unknown action.'));
            $existing=$id?one('SELECT * FROM homepage_slides WHERE id=?',[$id]):null;
            if (($id && !$existing) || (!$id && $action!=='save')) throw new InvalidArgumentException(t('This slide no longer exists.'));
            if ($action==='save') {
                $caption=val('caption');$alt=val('alt_text');$order=val('sort_order');
                if (!text_valid($caption,1,160)) throw new InvalidArgumentException(t('Enter a banner heading between 1 and 160 characters.'));
                if (!text_valid($alt,1,180)) throw new InvalidArgumentException(t('Enter a banner description between 1 and 180 characters.'));
                if (!preg_match('/^\d{1,4}$/',$order)) throw new InvalidArgumentException(t('Display order must be a number between 0 and 9999.'));
                if (!$id && (int)q('SELECT COUNT(*) FROM homepage_slides')->fetchColumn()>=20) throw new InvalidArgumentException('You can save up to 20 slides. Edit or delete an existing slide first.');
            }
            db()->beginTransaction();
            if ($action==='save') {
                $photo=upload('slide_image','carousel',(int)$u['id'],!$existing);
                if ($photo) $newPath=ROOT.'/storage/uploads/'.basename(one('SELECT path FROM files WHERE id=?',[$photo])['path']);
                $photo=$photo?:$existing['photo_id'];$active=val('active')==='1'?1:0;
                if ($id) q('UPDATE homepage_slides SET photo_id=?,caption=?,alt_text=?,sort_order=?,active=?,updated_at=? WHERE id=?',[$photo,$caption,$alt,(int)$order,$active,now(),$id]);
                else {
                    q('INSERT INTO homepage_slides (photo_id,caption,alt_text,sort_order,active,created_at,updated_at) VALUES (?,?,?,?,?,?,?)',[$photo,$caption,$alt,(int)$order,$active,now(),now()]);
                    $id=(int)db()->lastInsertId();
                }
                audit((int)$u['id'],t('Homepage slide saved'),$id);
            } elseif ($action==='toggle') {
                q('UPDATE homepage_slides SET active=1-active,updated_at=? WHERE id=?',[now(),$id]);
                audit((int)$u['id'],t('Homepage slide visibility changed'),$id);
            } else {
                q('DELETE FROM homepage_slides WHERE id=?',[$id]);
                audit((int)$u['id'],t('Homepage slide deleted'),$id);
            }
            db()->commit();flash(t('Carousel updated.'));go('admin/carousel.php');
        } catch (Throwable $ex) {
            if (db()->inTransaction()) db()->rollBack();
            if ($newPath && is_file($newPath)) unlink($newPath);
            if ($ex instanceof InvalidArgumentException) $err[]=$ex->getMessage();
            else { error_log((string)$ex);$err[]=t('The slide could not be saved. Check that storage/uploads is writable and try again.'); }
        }
    }
}
$slides=$ready?all('SELECT * FROM homepage_slides ORDER BY sort_order,id'):[];
$values=($_SERVER['REQUEST_METHOD']==='POST' && val('action')==='save')?$_POST:($editing?:['caption'=>t("Delicious food.\nJust around the corner."),'alt_text'=>t('From neighborhood favorites to fresh new flavors — find something worth craving.'),'sort_order'=>'0','active'=>'1','id'=>0]);
require ROOT.'/app/workspace-start.php';
?>
<link rel="stylesheet" href="<?=e(url('assets/carousel.css?v=2'))?>">
<div class="page-heading"><h1><?=e(t('Homepage carousel'))?></h1><p><?=e(t('Each slide controls the banner heading, description and image. The Explore dishes button stays in place.'))?></p></div>
<?php errors($err); if(!$ready): ?>
<section class="panel"><h2><?=e(t('One-time setup required'))?></h2><p><?=e(t('Apply the included carousel database update, then reload this page.'))?></p></section>
<?php else: ?>
<div class="two-columns zq-carousel-editor"><section class="panel"><h2><?=$editing?t('Edit slide'):t('Add slide')?></h2>
<form method="post" enctype="multipart/form-data">
<?=csrf()?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=e($values['id']??0)?>">
<?php area('caption',t('Banner heading'),(string)($values['caption']??''),'required maxlength="160" rows="3"');area('alt_text',t('Banner description'),(string)($values['alt_text']??''),'required maxlength="180" rows="3"'); ?>
<p class="hint"><?=e(t('The heading and description appear on the left of the banner. Use a new line in the heading to control where it breaks. On mobile, the image blends behind this text with a green overlay.'))?></p>
<div class="field"><label for="slide_image"><?=$editing?t('Replace image (optional)'):t('Slide image')?></label><input id="slide_image" type="file" name="slide_image" accept="image/jpeg,image/png,image/webp" <?=$editing?'':'required'?>><p class="hint"><?=e(t('JPG, PNG or WebP, up to 5 MB. Recommended: 1200 × 1000 pixels. Images crop to fill the frame; keep important details near the center.'))?></p></div>
<?php if($editing): ?><img class="zq-admin-preview" src="<?=e(url('carousel-image.php?id='.$editing['id']))?>" alt="<?=e($editing['caption'])?>"><?php endif ?>
<?php field('sort_order',t('Display order'),'number','required min="0" max="9999" step="1"',(string)($values['sort_order']??'0')); ?>
<p class="hint"><?=e(t('Lower numbers appear first. Equal numbers follow creation order.'))?></p>
<label class="zq-checkbox"><input type="checkbox" name="active" value="1" <?=($values['active']??'')==='1' || ($values['active']??null)===1?'checked':''?>> <?=e(t('Show on homepage'))?></label>
<div class="row-actions space-top"><button class="pill primary"><?=e(t('Save slide'))?></button><?php if($editing): ?><a class="pill" href="<?=e(url('admin/carousel.php'))?>"><?=e(t('Cancel'))?></a><?php endif ?></div>
</form></section>
<section class="panel"><h2><?=e(t('Slides ('))?><?=count($slides)?> / 20)</h2><p class="hint"><?=e(t('Visible slides rotate every six seconds. With no visible slides, the current banner image stays in place.'))?></p>
<?php if(!$slides): ?><p><?=e(t('No slides yet. Upload your first image to get started.'))?></p><?php endif ?>
<?php foreach($slides as $slide): ?><article class="zq-admin-slide">
<img class="zq-admin-preview" src="<?=e(url('carousel-image.php?id='.$slide['id']))?>" alt="<?=e($slide['caption'])?>" loading="lazy">
<h3><?=e($slide['caption'])?></h3><p class="hint"><?=e(t('Order'))?> <?=e($slide['sort_order'])?> · <?=$slide['active']?t('Visible'):t('Hidden')?></p>
<div class="row-actions"><a class="pill compact" href="?edit=<?=$slide['id']?>"><?=e(t('Edit'))?></a>
<form method="post"><?=csrf()?><input type="hidden" name="id" value="<?=$slide['id']?>"><button name="action" value="toggle" class="pill compact"><?=$slide['active']?t('Hide'):t('Show')?></button></form>
<details class="zq-delete"><summary><?=e(t('Delete'))?></summary><form method="post"><?=csrf()?><input type="hidden" name="id" value="<?=$slide['id']?>"><p><?=e(t('Remove this slide from the homepage?'))?></p><button name="action" value="delete" class="pill compact"><?=e(t('Confirm delete'))?></button></form></details>
</div></article><?php endforeach ?></section></div>
<?php endif;require ROOT.'/app/workspace-end.php'; ?>
