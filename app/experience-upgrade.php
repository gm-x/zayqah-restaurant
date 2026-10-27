<?php
function install_experience(): void {
 if(!commerce_ready())throw new RuntimeException('Install the existing order tables before this update. Do not reset your database.');
 db()->exec(file_get_contents(ROOT.'/database/update_06_'.(cfg('db_driver')==='mysql'?'mysql':'sqlite').'.sql'));
 if(!one('SELECT version FROM experience_updates WHERE version=6'))q('INSERT INTO experience_updates (version,installed_at) VALUES (6,?)',[now()]);
}
