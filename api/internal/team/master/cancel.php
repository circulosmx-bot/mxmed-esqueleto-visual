<?php
declare(strict_types=1);
require_once dirname(__DIR__,4).'/modules/identity/http/IdentityHttpComposition.php';
\Identity\Http\IdentityHttpComposition::registerAutoloader();
\Identity\MasterGovernance\Http::handle('cancel');
