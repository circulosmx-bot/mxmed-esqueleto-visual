<?php
declare(strict_types=1);
require_once __DIR__.'/../../modules/identity/http/IdentityHttpComposition.php';
Identity\Http\IdentityHttpComposition::registerAutoloader();
try {Identity\Accreditation\PhysicianOnboardingPage::show(Identity\Http\IdentityHttpComposition::fromProcessEnvironment());}
catch (Throwable) {http_response_code(503);header('Cache-Control: no-store');echo '<!doctype html><html lang="es"><meta charset="utf-8"><title>Servicio no disponible</title><p>No pudimos consultar tu solicitud. Intenta de nuevo más tarde.</p></html>';}
