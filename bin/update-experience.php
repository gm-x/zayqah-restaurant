<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../app/bootstrap.php';require ROOT.'/app/experience-upgrade.php';
install_experience();echo "Order tracking, notifications, locations and contact forms installed. Existing data preserved.\n";
