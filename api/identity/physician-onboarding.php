<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/modules/identity/http/IdentityHttpComposition.php';
Identity\Http\IdentityHttpComposition::registerAutoloader();
try {
    // No operational composition: safe status only until separately authorized deployment.
    Identity\Accreditation\PhysicianOnboardingHttp::serve(Identity\Operational\OperationalRuntime::onboarding()??new Identity\Accreditation\PhysicianOnboardingHttp(Identity\Http\IdentityHttpComposition::fromProcessEnvironment()));
} catch (Throwable) {http_response_code(503);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo '{"ok":false,"message":"El servicio no está disponible. Intenta de nuevo más tarde."}';}
