<?php
require __DIR__.'/../app/bootstrap.php';$u=require_admin();$title='Food categories';$err=[];
if(post()){
 $action=val('action');$id=(int)val('id');$name=val('name');$icon=val('icon')?:'🍽️';
 if($action==='save' && (!text_valid($name,2,80)||!text_valid($icon,1,8)))$err[]='Use a category name (2–80 characters) and a short icon (up to 8 characters).';
 if(!$err){try{db()->beginTransaction();
  if($action==='toggle'){q('UPDATE categories SET active=1-active WHERE id=?',[$id]);audit((int)$u['id'],'Category visibility changed',$id);}
  elseif($action==='save'){
   if($id)q('UPDATE categories SET name=?,icon=? WHERE id=?',[$name,$icon,$id]);
   else{q('INSERT INTO categories (name,icon,active) VALUES (?,?,1)',[$name,$icon]);$id=(int)db()->lastInsertId();}audit((int)$u['id'],'Category saved',$id);
  }else throw new InvalidArgumentException('Unknown action.');
  db()->commit();flash('Category updated.');go('admin/categories.php');
 }catch(PDOException $ex){if(db()->inTransaction())db()->rollBack();if(in_array((string)$ex->getCode(),['23000','23505']))$err[]='A category with this name already exists.';else throw $ex;}}
}
$categories=all('SELECT * FROM categories ORDER BY name');$edit=(int)param('edit');$editing=$edit?one('SELECT * FROM categories WHERE id=?',[$edit]):null;require ROOT.'/app/workspace-start.php'; ?>
<div class="page-heading"><h1>Food categories</h1><p>You decide how customers discover dishes.</p></div><?php errors($err); ?><div class="two-columns"><section class="panel"><h2><?=$editing?'Edit category':'Add category'?></h2><form method="post"><?=csrf()?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=e($editing['id']??0)?>"><?php field('name','Category name','text','required minlength="2" maxlength="80"',$editing['name']??'');field('icon','Emoji or short icon','text','required maxlength="8"',$editing['icon']??'🍽️'); ?><button class="pill primary space-top">Save category</button><?php if($editing): ?><a class="pill" href="<?=e(url('admin/categories.php'))?>">Cancel</a><?php endif ?></form></section><section class="panel"><h2>Category directory</h2><p class="hint">Hiding a category also hides its dishes from customers. Sellers cannot select hidden categories.</p><div class="table-wrap"><table><thead><tr><th>Category</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach($categories as $c): ?><tr><td><?=e($c['icon'].' '.$c['name'])?></td><td><?=$c['active']?'Visible':'Hidden'?></td><td><div class="row-actions"><a class="pill compact" href="?edit=<?=$c['id']?>">Edit</a><form method="post"><?=csrf()?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=$c['id']?>"><button class="pill compact"><?=$c['active']?'Hide':'Show'?></button></form></div></td></tr><?php endforeach ?></tbody></table></div></section></div><?php require ROOT.'/app/workspace-end.php'; ?>
