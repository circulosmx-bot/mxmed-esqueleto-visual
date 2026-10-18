<?php
declare(strict_types=1);
namespace Identity\Team;
final class Page
{
    public static function show(): void
    {
        Http::headers();if(!Http::method())return;
        try{Http::service()->authority($_COOKIE);}
        catch(\Throwable $e){$status=$e instanceof ReadFailure?$e->status:503;http_response_code($status);if(($_SERVER['REQUEST_METHOD']??'')!=='HEAD')echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Equipo interno · México Médico</title><link rel="stylesheet" href="/assets/css/media-review-inbox.css"><main class="inbox"><h1>Equipo interno y permisos</h1><p>'.($status===401?'Inicia sesión mediante Identity para continuar.':($status===403?'Esta sección requiere autorización de administración del equipo interno.':'La consulta del equipo no está disponible en este momento.')).'</p><a href="/public/identity/acceso.php?next=%2Finternal%2Fmedia-review%2Findex.php%3Fsection%3Dequipo">Iniciar sesión</a></main></html>';return;}
        if(($_SERVER['REQUEST_METHOD']??'')==='HEAD')return;
        $teamAuthorized=true;require dirname(__DIR__,3).'/partials/identity/internal-team.php';
    }
}
