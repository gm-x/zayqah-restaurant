<?php
require __DIR__.'/../app/bootstrap.php';$u=require_admin();$title=t('Install website update');$err=[];
if(post()){try{require ROOT.'/app/experience-upgrade.php';install_experience();flash(t('Website update installed. Your existing data was preserved.'));go('admin/update-experience.php');}catch(Throwable $ex){error_log((string)$ex);$err[]=t('Installation failed. Check the PHP error log. No database reset is needed.');}}
require ROOT.'/app/workspace-start.php'; ?>
<div class="page-heading"><h1><?=e(t('Website update'))?></h1><p><?=e(t('Install order tracking, notifications, location pins and contact messages.'))?></p></div><?php errors($err); ?><section class="panel"><p><?=experience_ready()?t('Update is installed.'):t('A one-time database update is required.')?></p><form method="post"><?=csrf()?><button class="pill primary"><?=e(t('Install / check update'))?></button></form></section><?php require ROOT.'/app/workspace-end.php'; ?>
