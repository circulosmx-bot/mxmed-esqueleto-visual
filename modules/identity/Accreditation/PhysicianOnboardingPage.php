<?php
declare(strict_types=1);
namespace Identity\Accreditation;

use Identity\Http\{IdentityHttpComposition,GovernanceHttpDenied};

/** Same renderer for the original Identity journey and the existing staff workspace section. */
final class PhysicianOnboardingPage
{
    public static function show(IdentityHttpComposition $identity,bool $staff=false,?PhysicianOnboardingHttp $http=null):void
    {
        header('Content-Type: text/html; charset=utf-8');header('Cache-Control: no-store, private');header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');header('Cross-Origin-Resource-Policy: same-origin');
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' blob:; frame-src blob:; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
        if (($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){http_response_code(405);header('Allow: GET');return;}
        if (!$staff && array_key_exists('entry', $_GET)) {
            require_once dirname(__DIR__,2).'/profiles/services/PublicEnrollmentPresentation.php';
            require_once dirname(__DIR__,2).'/profiles/controllers/PublicProfileController.php';
            $controller=new \Profiles\Controllers\PublicProfileController(new \Profiles\Repositories\PublicProfileRepository($identity->pdo()));
            // Public presentation only: no session read/touch, request creation, store or source attestation.
            $entry=\Profiles\Services\PublicEnrollmentPresentation::resolve($_GET, $controller->showByDoctorId(...));
            $state=['enabled'=>false,'staff'=>false,'email_verified'=>false,'phone_verified'=>false,
                'requests'=>[],'request'=>null,'message'=>$entry['message'],'public_entry'=>$entry,
                'csrf_token'=>'','expires_at'=>0];
            $title=$entry['title'];
            require dirname(__DIR__,3).'/partials/identity/physician-onboarding.php';
            return;
        }
        try {$state=($http??new PhysicianOnboardingHttp($identity))->page($_COOKIE,$staff);}
        catch (GovernanceHttpDenied $e) {
            if ($e->status===401){$next=$staff?'/internal/media-review/index.php?section=acreditacion':'/public/identity/perfil-medico.php';header('Location: /public/identity/acceso.php?next='.rawurlencode($next),true,302);return;}
            http_response_code(403);echo '<!doctype html><html lang="es"><meta charset="utf-8"><title>Acceso restringido</title><main><h1>Acceso restringido</h1><p>Esta revisión requiere autorización del equipo interno.</p><a href="/public/identity/perfil-medico.php">Volver a mi acreditación</a></main></html>';return;
        } catch (\Throwable) {http_response_code(503);echo '<!doctype html><html lang="es"><meta charset="utf-8"><title>Servicio no disponible</title><main><h1>No pudimos consultar tu solicitud</h1><p>Intenta de nuevo más tarde.</p></main></html>';return;}
        $title=$staff?'Revisión de acreditación profesional':'Mi acreditación profesional';
        require dirname(__DIR__,3).'/partials/identity/physician-onboarding.php';
    }
}
