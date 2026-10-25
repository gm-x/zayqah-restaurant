<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../app/bootstrap.php';require ROOT.'/app/upgrade.php';require ROOT.'/app/commerce-upgrade.php';
install_update02();install_update04();install_update05();echo "Updates installed. Existing accounts, dishes and reviews were preserved.\n";
