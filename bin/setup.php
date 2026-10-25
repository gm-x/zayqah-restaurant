<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../app/bootstrap.php';
if(!is_dir(ROOT.'/storage/uploads'))mkdir(ROOT.'/storage/uploads',0700,true);
if(!cfg('key')){$key=base64_encode(random_bytes(32));file_put_contents(ROOT.'/storage/app.key',$key,LOCK_EX);chmod(ROOT.'/storage/app.key',0600);$config['key']=$key;}
$sql=file_get_contents(ROOT.'/database/schema.sql');
if(cfg('db_driver')==='mysql'){
 // Generate dialect-specific DDL from the canonical schema. Foreign keys are installed explicitly below.
 $sql=str_replace('INTEGER PRIMARY KEY AUTOINCREMENT','INTEGER PRIMARY KEY AUTO_INCREMENT',$sql);
 $sql=preg_replace('/ REFERENCES [a-z_]+\(id\)/','',$sql);
 $sql=preg_replace('/CREATE INDEX IF NOT EXISTS .*?;/s','',$sql);
 $sql=str_replace(');',') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',$sql);
}
db()->exec($sql);
if(cfg('db_driver')==='mysql'){
 foreach(['tokens'=>['user_id'=>'users'],'files'=>['owner_id'=>'users'],'seller_applications'=>['user_id'=>'users','logo_id'=>'files','cnic_front'=>'files','cnic_back'=>'files','reviewed_by'=>'users'],'menu_items'=>['seller_id'=>'users','category_id'=>'categories','photo_id'=>'files'],'audit_logs'=>['admin_id'=>'users']] as $table=>$columns){foreach($columns as $col=>$ref){$constraint='fk_'.$table.'_'.$col;
  if(!(int)q('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=? AND CONSTRAINT_NAME=?',[cfg('db_name'),$constraint])->fetchColumn())db()->exec("ALTER TABLE $table ADD CONSTRAINT $constraint FOREIGN KEY ($col) REFERENCES $ref(id)");
 }}
 foreach(['menu_items'=>['menu_expiry'=>'status, expires_at'],'attempts'=>['attempt_lookup'=>'rate_key, created_at']] as $table=>$indexes)foreach($indexes as $name=>$columns){if(!(int)q('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME=?',[cfg('db_name'),$table,$name])->fetchColumn())db()->exec("CREATE INDEX $name ON $table ($columns)");}
}
if(!q('SELECT COUNT(*) FROM categories')->fetchColumn())foreach([['Burgers','🍔'],['Pizza','🍕'],['Healthy','🥗'],['Chicken','🍗'],['Desserts','🍰'],['Pasta','🍝'],['Desi','🍛']] as $c)q('INSERT INTO categories (name,icon) VALUES (?,?)',$c);
echo "Database initialized. No accounts or sample menu records were created.\nNext: php bin/create-admin.php\n";

require ROOT.'/app/upgrade.php';install_update02();

require ROOT.'/app/commerce-upgrade.php';install_update04();install_update05();
