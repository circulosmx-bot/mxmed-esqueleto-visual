<?php
declare(strict_types=1);
$path=parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH);
if(preg_match('~^/api/internal/staff-admission/(csrf|read|result|prepare|update|correct|confirm|revoke)\.php$~D',$path))return false;
http_response_code(404);header('Cache-Control: no-store');echo '{}';
