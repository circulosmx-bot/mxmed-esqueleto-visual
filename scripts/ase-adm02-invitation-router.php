<?php
declare(strict_types=1);
$path=parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH);
if(preg_match('~^/api/internal/staff-admission/invitation-(csrf|result|issue|reissue|revoke|accept)\.php$~D',$path))return false;
return require __DIR__.'/ase-adm02-router.php';
