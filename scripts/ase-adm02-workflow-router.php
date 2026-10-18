<?php
declare(strict_types=1);
umask(0077);
$path=parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH);
try{
 require_once __DIR__.'/ase-adm02-workflow-runtime.php';
 $services=staffWorkflowOwnedServices();
 if($path==='/api/internal/staff-admission/workflow.php'){\Identity\StaffWorkflow\Http::handle($services);return true;}
 if(preg_match('~^/assets/(css|js)/[a-z0-9-]+\.(css|js)$~D',$path))return false;
 if($path==='/api/internal/team/read.php'){
  \Identity\Team\Http::headers(true);if(!\Identity\Team\Http::method())return true;
  echo json_encode($services[1]->team($_COOKIE,$_GET),JSON_THROW_ON_ERROR|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);return true;
 }
 if(preg_match('~^/api/internal/team/(read|csrf|capability|lifecycle)\.php$~D',$path))return false;
 if($path==='/internal/media-review/index.php'){
  \Identity\Team\Http::service()->authority($_COOKIE);\Identity\Team\Http::headers();header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; object-src 'none'; frame-ancestors 'none'");$teamAuthorized=true;$staffWorkflowReady=true;
  require __DIR__.'/../partials/identity/internal-team.php';return true;
 }
 if($path==='/staff-admission.php'){
  $a=(new \Identity\Http\CanonicalHttpSessionResolver($services[0]->sessions()))->resolve($_COOKIE,true);if(!$a)throw new RuntimeException('authentication_required');
  \Identity\Team\Http::headers();header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; object-src 'none'; frame-ancestors 'none'");
  $staffWorkflowReady=true;$staffWorkflowApplicant=true;
  echo '<!doctype html><html lang="es"><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Mi incorporación · México Médico</title><link rel="stylesheet" href="/assets/css/internal-team.css"><link rel="stylesheet" href="/assets/css/internal-team-admission.css"><script src="/assets/js/staff-workflow.js" defer></script></head><body><main class="advisor-workspace"><h1>Mi incorporación</h1>';
  require __DIR__.'/../partials/identity/staff-workflow.php';echo '</main></body></html>';return true;
 }
}catch(Throwable $e){http_response_code(503);header('Cache-Control: no-store');echo '{"error":"isolated_service_unavailable"}';return true;}
http_response_code(404);header('Cache-Control: no-store');echo '{}';
