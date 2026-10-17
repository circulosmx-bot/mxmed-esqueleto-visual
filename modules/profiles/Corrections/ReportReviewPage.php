<?php
declare(strict_types=1);
namespace Profiles\Corrections;
final class ReportReviewPage
{
    public static function show():never
    {
        header('Content-Type: text/html; charset=UTF-8');header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');header('Referrer-Policy: same-origin');
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'");
        try{
            if(($_SERVER['REQUEST_METHOD']??'')!=='GET')throw new ReportDenied(405);
            $runtime=ReportRuntime::current();if($runtime===null)throw new ReportDenied(503);
            $actor=$runtime->staffPage($_SERVER,$_COOKIE);
            $correctionReviewPage=['csrf'=>$runtime->identity->csrf()->issueAuthenticated($actor->session()->tokenDigest())];
            require dirname(__DIR__,3).'/partials/profiles/correction-review.php';
        }catch(\Throwable $e){
            $status=$e instanceof ReportDenied?$e->status:503;http_response_code($status);
            if($status===401){header('Location: /public/identity/acceso.php?next='.rawurlencode('/internal/media-review/index.php?section=correcciones'),true,302);exit;}
            echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Informes de perfiles · México Médico</title><link rel="stylesheet" href="/assets/css/media-review-inbox.css"><main class="inbox"><header class="inbox-header"><p class="eyebrow">México Médico · Equipo interno</p><h1>Informes de perfiles</h1></header><p>'.($status===503?'La revisión de informes está pendiente de habilitación.':'Esta sección requiere acceso autorizado del equipo interno.').'</p></main></html>';
        }
        exit;
    }
}
