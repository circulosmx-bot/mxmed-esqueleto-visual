<?php
declare(strict_types=1);
require_once __DIR__.'/../../../modules/identity/http/IdentityHttpComposition.php';
\Identity\Http\IdentityHttpComposition::registerAutoloader();
\Identity\StaffWorkflow\Http::handle();
