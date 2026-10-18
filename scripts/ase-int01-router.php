<?php
declare(strict_types=1);
// Transport allowlist belongs only to the isolated preview; product boundaries are tested directly.
$path=parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH);
if($path==='/api/internal/team/read.php'||$path==='/internal/media-review/index.php')return false;
$assets=['/assets/js/internal-team.js','/assets/css/media-review-inbox.css','/assets/css/internal-team.css','/assets/css/internal-team-commercial.css','/assets/css/internal-team-admission.css','/assets/css/internal-team-readonly.css'];
if(in_array($path,$assets,true)&&in_array($_SERVER['REQUEST_METHOD']??'',['GET','HEAD'],true))return false;
http_response_code(404);header('Cache-Control: no-store');echo 'Not available in isolated preview';
