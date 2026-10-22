<?php
declare(strict_types=1);
umask(0077);
$path=parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH);
try{
 require_once __DIR__.'/ase-adm02-workflow-runtime.php';
 $services=staffWorkflowOwnedServices();
 if($path==='/api/internal/staff-admission/workflow.php'){\Identity\StaffWorkflow\Http::handle($services);return true;}
 if(in_array($path,['/api/identity/index.php/logout','/api/identity/index.php/login','/api/identity/index.php/current-session'],true)){require __DIR__.'/../api/identity/index.php';return true;}
 if($path==='/public/identity/acceso.php'){
  $workspace=getenv('MXMED_ADVISOR_WORKSPACE_REVIEW')==='1';
  $workspace02=getenv('MXMED_ADVISOR_WORKSPACE02_REVIEW')==='1';
  $workspace03=$workspace02&&is_file(__DIR__.'/ase-advisor-workspace03-review.py');
  $control=getenv('MXMED_STAFF_CONTROL05')==='1'?'/staff-control05-review.py':(getenv('MXMED_STAFF_CONTROL04')==='1'?'/staff-control04-review.py':($workspace03?'/ase-advisor-workspace03-review.py':($workspace02?'/ase-advisor-workspace02-review.py':($workspace?'/ase-advisor-workspace-review.py':(getenv('MXMED_OPER09_MEDIA_REVIEW')==='1'?'/ase-adm02-oper09-review.py':'/ase-adm02-panel08-review.py')))));
  if(preg_match('/^mxmed_op01_[a-f0-9]{10}$/D',(string)getenv('SUPPORT_FIXTURE_DB')))$control=(getenv('MXMED_LINK03_REVIEW')==='1'?'/cross-surface-link03-review.py':'/advisor-operator-support01-review.py');
  $owned=realpath(getenv('ASE_INT02A_OWNED'));$command='python3 '.realpath(__DIR__.$control).' '.$owned;
  $staffSyntheticLoginGuide=['credentials'=>$command.($workspace02?' credentials-director':' credentials'),'mfa'=>$command.($workspace02?' mfa-director':' mfa')];
  if($workspace&&($_GET['next']??'')==='/internal/staff-workspace/index.php'){
   $record=json_decode(file_get_contents($owned.'/oper09-advisor.json'),true,512,JSON_THROW_ON_ERROR);
   $staffSyntheticLoginGuide=['email'=>$record['email'],'destination'=>'Gestión de perfiles médicos','credentials'=>$command.' credentials-advisor','mfa'=>$workspace02?$command.' mfa-advisor':null];
  }
  $staffSyntheticLoginGuide['staff_control04']=getenv('MXMED_STAFF_CONTROL04')==='1';
  require __DIR__.'/../public/identity/acceso.php';return true;
 }
 if(in_array($path,['/public/identity/identity-access.js','/public/identity/identity-access.css','/assets/mexico-medico.svg'],true))return false;
 if(preg_match('~^/assets/(css|js)/[a-z0-9-]+\.(css|js)$~D',$path))return false;
 if($path==='/api/internal/team/read.php'){
  \Identity\Team\Http::headers(true);if(!\Identity\Team\Http::method())return true;
  echo json_encode($services[1]->team($_COOKIE,$_GET),JSON_THROW_ON_ERROR|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);return true;
 }
 if(preg_match('~^/api/internal/team/(read|csrf|capability|lifecycle)\.php$~D',$path))return false;
 // R2 opts in only inside the owner-verified disposable composition. Both routes
 // enforce canonical staff authority independently of this transport allowlist.
 if(getenv('MXMED_ADVISOR_WORKSPACE_REVIEW')==='1' && in_array($path,['/internal/staff-workspace/index.php','/api/internal/workspace/read.php','/api/internal/workspace/media.php'],true)){
  require __DIR__.'/..'.$path;return true;
 }
 if(getenv('MXMED_ADVISOR_WORKSPACE_REVIEW')==='1' && preg_match('~^/api/media/index\.php/public/[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$~D',$path)){
  require __DIR__.'/../api/media/index.php';return true;
 }
 // Existing consumer, opt-in only in the owned OPER09 composition. Its own Identity/grant
 // boundary runs unchanged; Equipo retains its independent authority.
 if(getenv('MXMED_OPER09_MEDIA_REVIEW')==='1' && (($path==='/internal/media-review/index.php' && !isset($_GET['section'])) || preg_match('~^/api/internal/media-review/(pending|batches|batch|submission|review-image|source-download|approval-options)\.php$~D',$path))){
  require __DIR__.'/..'.$path;return true;
 }
 if($path==='/internal/media-review/index.php'){
  try{$teamAuthority=\Identity\Team\Http::service()->authority($_COOKIE);}catch(\Identity\Team\ReadFailure $e){header('Cache-Control: no-store');if($e->status===401){header('Location: /public/identity/acceso.php?next=%2Finternal%2Fmedia-review%2Findex.php%3Fsection%3Dequipo',true,303);}else{http_response_code($e->status);echo 'Esta cuenta no tiene acceso al equipo interno.';}return true;}\Identity\Team\Http::headers();header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; object-src 'none'; frame-ancestors 'none'");$teamAuthorized=true;$staffWorkflowReady=true;
  require __DIR__.'/../partials/identity/internal-team.php';return true;
 }
 if($path==='/staff-admission.php'){
  $a=(new \Identity\Http\CanonicalHttpSessionResolver($services[0]->sessions()))->resolve($_COOKIE,true);if(!$a)throw new RuntimeException('authentication_required');
  \Identity\Team\Http::headers();header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; object-src 'none'; frame-ancestors 'none'");
  $staffWorkflowReady=true;$staffWorkflowApplicant=true;
  echo '<!doctype html><html lang="es"><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Mi incorporación · México Médico</title><link rel="stylesheet" href="/assets/css/internal-team.css"><link rel="stylesheet" href="/assets/css/internal-team-admission.css"><script src="/assets/js/staff-applicant.js" defer></script></head><body><main class="advisor-workspace"><h1>Mi incorporación</h1>';
  require __DIR__.'/../partials/identity/staff-workflow.php';echo '</main></body></html>';return true;
 }
}catch(\Identity\Team\ReadFailure $e){
 \Identity\Team\Http::headers(true);http_response_code($e->status);
 if(($_SERVER['REQUEST_METHOD']??'')!=='HEAD')echo json_encode(['error'=>match($e->status){401=>'authentication_required',403=>'access_denied',400=>'invalid_request',404=>'member_not_available',default=>'service_unavailable'}]);return true;
}catch(Throwable $e){http_response_code(503);header('Cache-Control: no-store');echo '{"error":"isolated_service_unavailable"}';return true;}
http_response_code(404);header('Cache-Control: no-store');echo '{}';
