<?php
function site_language(): string {
 $value=$_SESSION['language']??($_COOKIE['zayqah_language']??'en');return $value==='ur'?'ur':'en';
}
function urdu_messages(): array {static $messages;return $messages??=require __DIR__.'/lang/ur.php';}
function source_text(string $text): string {
 static $reverse;if($reverse===null){$reverse=[];foreach(urdu_messages() as $key=>$value)if(!isset($reverse[$value]))$reverse[$value]=$key;}return $reverse[$text]??$text;
}
function t(string $text,array $params=[]): string {
 if(site_language()==='ur'){
  $messages=urdu_messages();$key=trim($text);$translated=$messages[$text]??$messages[$key]??null;
  if($translated===null){static $fold;if($fold===null){$fold=[];foreach($messages as $k=>$v)$fold[strtolower($k)]=$v;}$translated=$fold[strtolower($key)]??null;}
  if($translated!==null)$text=$translated;
  else {
   $patterns=[
    '/^Request #(\d+) · (Customer|Seller)$/u'=>fn($m)=>t('Request #{id} · {type}',['id'=>$m[1],'type'=>t($m[2])]),
    '/^Order (Placed|Confirmed|Preparing|Ready for pickup|Out for delivery|Collected|Delivered|Completed|Cancelled)$/' => fn($m)=>t('Order {status}',['status'=>t($m[1])]),
    '/^(.+) is complete\. Leave a review now or later from order history\.$/u' => fn($m)=>t('{reference} is complete. Leave a review now or later from order history.',['reference'=>$m[1]]),
    '/^(.+) · ([1-5]) stars$/u' => fn($m)=>$m[1].' · '.t('{count} stars',['count'=>$m[2]]),
    '/^(Minimum|Maximum) price must be between Rs 0 and Rs 999,999\.99\.$/' => fn($m)=>t('{label} price must be between Rs 0 and Rs 999,999.99.',['label'=>t($m[1])]),
    '/^Choose an available pickup or delivery option for (.+)\.$/u'=>fn($m)=>t('Choose an available pickup or delivery option for {shop}.',['shop'=>$m[1]]),
    '/^(.+) · Open tracking to see the shared pin\.$/u'=>fn($m)=>t('{reference} · Open tracking to see the shared pin.',['reference'=>$m[1]]),
   ];
   foreach($patterns as $pattern=>$callback)if(preg_match($pattern,$text,$match)){$text=$callback($match);break;}
  }
 }
 foreach($params as $name=>$value)$text=str_replace('{'.$name.'}',(string)$value,$text);return $text;
}
function category_label(string $name): string {
 return in_array($name,['Burgers','Pizza','Healthy','Chicken','Desserts','Pasta','Desi'],true)?t($name):$name;
}
function language_return_path(): string {
 $request=$_SERVER['REQUEST_URI']??'/index.php';$base=rtrim((string)parse_url(cfg('url'),PHP_URL_PATH),'/');
 if($base!==''&&str_starts_with($request,$base.'/'))$request=substr($request,strlen($base));
 return ltrim($request,'/');
}
function language_switch(bool $mobile=false): void { ?>
 <form method="post" action="<?=e(url('language.php'))?>" class="language-switch <?=$mobile?'mobile-language':'desktop-language'?>"><?=csrf()?><input type="hidden" name="language" value="<?=site_language()==='ur'?'en':'ur'?>"><input type="hidden" name="return_to" value="<?=e(language_return_path())?>"><button class="pill language-button" type="submit" lang="<?=site_language()==='ur'?'en':'ur'?>" dir="<?=site_language()==='ur'?'ltr':'rtl'?>" aria-label="<?=e(site_language()==='ur'?'View in English':'اردو میں دیکھیں')?>"><?=site_language()==='ur'?'English':'اردو'?></button></form>
<?php }
