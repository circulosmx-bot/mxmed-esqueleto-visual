<?php
declare(strict_types=1);
// Only the new owned synthetic server uses this router. It never mounts the UI or other writers.
$path=parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH);
if(in_array($path,['/api/internal/team/read.php','/api/internal/team/csrf.php','/api/internal/team/capability.php'],true))return false;
http_response_code(404);header('Cache-Control: no-store');echo 'Not available in isolated QA';
