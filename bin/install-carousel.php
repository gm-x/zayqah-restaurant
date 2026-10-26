<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require __DIR__.'/../app/bootstrap.php';
require_once ROOT.'/app/carousel.php';
install_carousel();
echo "Homepage carousel installed. Existing data was preserved. Open Admin > Carousel to add slides.\n";
