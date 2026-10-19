<?php
declare(strict_types=1);
namespace Identity\PhysicianRequests;
use Identity\Http\CanonicalHttpSessionResolver;
/** Continuation in the existing Identity applicant route; injected owned composition only. */
final class ApplicantPage
{
    public static function show(array $services):void
    {
        header('Content-Type: text/html; charset=utf-8');header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');header('Cross-Origin-Resource-Policy: same-origin');
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
        $id=$_GET['application']??'';
        if(($_SERVER['REQUEST_METHOD']??'')!=='GET'||array_keys($_GET)!==['application']||!is_string($id)||!preg_match('/^[a-f0-9-]{36}$/D',$id)){http_response_code(400);echo 'Solicitud no válida';return;}
        $actor=(new CanonicalHttpSessionResolver($services['identity']->sessions()))->resolve($_COOKIE,true);
        if($actor===null){header('Location: /public/identity/acceso.php?next='.rawurlencode('/public/identity/perfil-medico.php?application='.$id),true,302);return;}
        try{$state=$services['requests']->applicantRead($actor,$id);}catch(\Throwable){http_response_code(403);echo '<!doctype html><html lang="es"><meta charset="utf-8"><title>Solicitud no disponible</title><main><h1>Solicitud no disponible para esta cuenta</h1><a href="/public/identity/acceso.php">Volver a acceso</a></main></html>';return;}
        $safe=htmlspecialchars($id,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Mi solicitud de perfil — México Médico</title><link rel="stylesheet" href="/public/identity/identity-access.css"></head><body><div class="prototype-shell"><header class="prototype-header"><a href="/public/identity/perfil-medico.php">México Médico</a><button id="request-logout" type="button">Cerrar sesión</button></header><main class="prototype-main"><article class="prototype-card auth-card" data-request-id="'.$safe.'"><p class="auth-card__eyebrow">Solicitud de perfil</p><h1>Mi solicitud</h1><p id="request-account"></p><p id="request-state" role="status">Consultando…</p><p id="request-outcome"></p><section id="request-information" aria-label="Información solicitada"></section><form id="request-response" class="auth-form" hidden><div class="field"><label for="request-response-text">Tu respuesta</label><textarea id="request-response-text" maxlength="4000" required></textarea></div><button type="submit">Enviar información</button></form><section id="request-ownership" hidden><h2>Aceptar titularidad</h2><p id="request-prerequisite"></p><button id="request-prepare" type="button" hidden>Preparar mi vínculo de titularidad</button><form id="request-accept" class="auth-form" hidden><p>La aceptación vincula tu cuenta al perfil indicado. No publica el perfil ni activa un plan.</p><div class="field"><label for="request-password">Confirma tu contraseña</label><input id="request-password" type="password" autocomplete="current-password" required></div><label><input id="request-confirm" type="checkbox" required> Acepto la titularidad del perfil de esta solicitud.</label><button type="submit">Aceptar titularidad</button></form></section><button id="request-refresh" type="button">Actualizar estado</button><p id="request-message" role="status"></p></article></main></div><script src="/assets/js/identity/physician-request-continuation.js" defer></script></body></html>';
    }
}
