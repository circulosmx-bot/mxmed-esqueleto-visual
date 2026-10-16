<?php
declare(strict_types=1);
// PHP built-in router ALWAYS runs, even for existing PHP/static files.
$root=dirname(__DIR__);
require_once $root.'/modules/identity/http/IdentityHttpComposition.php';
require_once $root.'/modules/identity/http/PrivateApplicationAccessResolver.php';
Identity\Http\IdentityHttpComposition::registerAutoloader();
use Identity\Operators\{OperatorQaConfiguration,OperatorQaHttp};
try{
 OperatorQaConfiguration::assertEnabled();
 if(($_SERVER['HTTP_HOST']??'')!==parse_url(OperatorQaConfiguration::origin(),PHP_URL_HOST).':'.parse_url(OperatorQaConfiguration::origin(),PHP_URL_PORT)||!in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true))OperatorQaHttp::deny();
 $raw=$_SERVER['REQUEST_URI']??'';$path=parse_url($raw,PHP_URL_PATH);
 if(!is_string($path)||rawurldecode($path)!==$path||str_contains($path,'..')||str_contains($path,'\\')||str_contains($path,'//'))OperatorQaHttp::deny();
 define('MXMED_OP02_ROUTED',true);
 header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');header('Cache-Control: no-store');
 $static=['/assets/css/style.css','/assets/css/next-available-modal.css','/assets/css/profile-theme-admin.css','/assets/css/profile-photo.css','/assets/css/professional-logo.css','/assets/css/owner-media-review.css','/assets/css/datos-generales-buttons.css','/assets/css/professional-information-layout.css','/assets/css/activity-gallery-layout.css','/assets/css/consultorio-visual-normalization.css','/assets/css/expediente-paciente-visual-normalization.css','/assets/css/patient-workspace-navigation-guard.css','/assets/css/clinical/dental-location-v1.css','/assets/css/clinical/dental-odontogram-v2.css','/assets/css/clinical/pathology-parameters-v1.css','/assets/css/clinical/imaging-parameters-v1.css','/assets/css/clinical/functional-parameters-v1.css','/assets/css/clinical/lab-cat02a-navigation-v1.css','/assets/css/clinical/order-composition-v1.css','/assets/css/clinical/study-navigation-hierarchy-v2.css','/assets/css/clinical/diagnostic-family-color-trial.css','/assets/css/clinical/diagnostic-selector-documents-v1.css','/assets/css/billing-patient-profiles.css','/assets/css/billing-invoices.css','/assets/css/billing-issuance.css','/assets/css/platform-shell-color-modes.css','/assets/css/expediente-color-trial.css','/assets/css/vis25-adaptive-sidebar.css','/assets/css/clinical/expediente-panel-header-alignment.css','/assets/css/clinical/expediente-panel-geometry.css','/assets/css/clinical/expediente-panel-title-typography.css','/assets/css/clinical/floating-qa-widget-visibility.css','/assets/css/clinical-doc-base.css','/assets/css/clinical/responsiva-document.css','/assets/css/clinical/example-field-help.css','/assets/css/clinical/document-select-affordance.css','/public/identity/identity-access.js','/public/identity/identity-access.css','/assets/css/style.css','/assets/js/agenda/operators-panel.js','/assets/js/agenda/canonical-operators.js','/assets/js/agenda/agenda-workspace.js','/assets/js/core/navigation.js','/assets/js/core/sidebar.js','/assets/js/core/private-session.js','/assets/css/vis25-adaptive-sidebar.css','/assets/css/platform-shell-color-modes.css','/assets/css/next-available-modal.css','/assets/css/profile-theme-admin.css','/assets/fonts/mxmed-admin/asap-latin.woff2','/assets/mexico-medico.svg'];
 if(in_array($path,$static,true)&&$_SERVER['REQUEST_METHOD']==='GET')return false;
 if(in_array($path,['/public/identity/acceso.php','/public/identity/crear-cuenta.php','/public/identity/verificar-correo.php','/public/identity/recuperar-acceso.php','/public/identity/restablecer-acceso.php'],true)&&$_SERVER['REQUEST_METHOD']==='GET'){require $root.$path;return true;}
 if(in_array($path,['/','/index.html','/private-app.php'],true)&&$_SERVER['REQUEST_METHOD']==='GET'){
  $http=new OperatorQaHttp();$token=(string)($_COOKIE['__Host-mxmed_session']??'');
  if(!$http->identity->sessions()->validate($token)->allowed()){header('Location: /public/identity/acceso.php',true,302);exit;}
  $http->session();require $root.'/private-app.php';return true;
 }
 if($path==='/api/identity/index.php/current-session'){(new OperatorQaHttp())->route($path);}
 $identity=['csrf-token','login','logout','logout-all','sessions','session-revoke','session-rotate','registration-request','email-verification','recovery-request','password-reset'];
 if(preg_match('~^/api/identity/index.php/([a-z-]+)$~D',$path,$m)&&in_array($m[1],$identity,true)){
   if($_SERVER['REQUEST_METHOD']==='POST'){
     $body=json_decode(file_get_contents('php://input'),true);
     if(isset($body['email'])&&!str_ends_with(strtolower((string)$body['email']),'@example.invalid'))OperatorQaHttp::deny();
   }
   require $root.'/api/identity/index.php';return true;
 }
 if(str_starts_with($path,'/api/profile-operators/index.php/')||str_starts_with($path,'/api/agenda/index.php/')){require $root.'/api/profile-operators/index.php';return true;}
 // All aliases, alternate controllers, downloads, clinical/media/governance/static
 // patient files and unsupported HTTP verbs meet this server authorization gate.
 $http=new OperatorQaHttp();$http->session();OperatorQaHttp::deny();
}catch(Throwable $e){error_log('OP02_BOUNDARY_DENIED:'.get_class($e));OperatorQaHttp::deny();}
