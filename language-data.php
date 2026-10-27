<?php
// Static, public UI dictionary. No account details or database access.
header('Content-Type: application/javascript; charset=utf-8');header('X-Content-Type-Options: nosniff');header('Cache-Control: public, max-age=86400');
$language=($_GET['lang']??'en')==='ur'?'ur':'en';$messages=$language==='ur'?require __DIR__.'/app/lang/ur.php':[];
echo 'window.ZAYQAH_LANGUAGE='.json_encode($language).';window.ZAYQAH_MESSAGES='.json_encode($messages,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE).';';
