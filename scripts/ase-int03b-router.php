<?php
declare(strict_types=1);
// Allowlist for the new disposable synthetic QA process only.
$path=parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH);
if(in_array($path,[
    '/internal/media-review/index.php',
    '/api/internal/team/master/open.php',
    '/api/internal/team/master/read.php',
    '/api/internal/team/master/csrf.php',
    '/api/internal/team/master/confirm.php',
    '/api/internal/team/master/verify.php',
    '/api/internal/team/master/execute.php',
    '/api/internal/team/master/cancel.php',
    '/api/internal/team/master/expire.php',

    '/api/internal/team/lifecycle.php','/api/internal/team/read.php','/api/internal/team/csrf.php','/api/internal/team/capability.php',
    '/assets/js/internal-team.js','/assets/js/internal-team-editor.js',
    '/assets/css/media-review-inbox.css','/assets/css/internal-team.css',
    '/assets/css/internal-team-commercial.css','/assets/css/internal-team-admission.css','/assets/css/internal-team-readonly.css',
],true))return false;
http_response_code(404);header('Cache-Control: no-store');echo 'Not available in isolated QA';
