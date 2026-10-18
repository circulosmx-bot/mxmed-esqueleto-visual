<?php
declare(strict_types=1);
// Pure source export: no autoloader/composition, environment, database or Identity session.
require_once __DIR__.'/../../modules/identity/services/InternalCapabilityCatalog.php';
echo "// Generated from InternalCapabilityCatalog::all(); visual snapshot, never authorization.\n";
echo 'window.ASE_VIS01_CATALOG = '.json_encode(\Identity\Services\InternalCapabilityCatalog::all(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).';'.PHP_EOL;
