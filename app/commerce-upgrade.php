<?php
function install_update04(): void {
 db()->exec(file_get_contents(ROOT.'/database/update_04_'.(cfg('db_driver')==='mysql'?'mysql':'sqlite').'.sql'));
}

function install_update05(): void {db()->exec(file_get_contents(ROOT.'/database/update_05_'.(cfg('db_driver')==='mysql'?'mysql':'sqlite').'.sql'));}
