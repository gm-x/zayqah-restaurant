<?php
function install_update02(): void {
 $path=ROOT.'/database/update_02_'.(cfg('db_driver')==='mysql'?'mysql':'sqlite').'.sql';
 db()->exec(file_get_contents($path));
}
