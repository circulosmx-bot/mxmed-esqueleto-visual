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
 if(($_SERVER['HTTP_HOST']??'')!=='127.0.0.1:8445'||!in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true))OperatorQaHttp::deny();
 $raw=$_SERVER['REQUEST_URI']??'';$path=parse_url($raw,PHP_URL_PATH);
 if(!is_string($path)||rawurldecode($path)!==$path||str_contains($path,'..')||str_contains($path,'\\')||str_contains($path,'//'))OperatorQaHttp::deny();
 define('MXMED_OP02_ROUTED',true);
 header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');header('Cache-Control: no-store');
 $static=['/public/identity/identity-access.js','/public/identity/identity-access.css','/assets/css/style.css','/assets/css/operator-workspace.css','/assets/js/operator-workspace.js','/assets/fonts/mxmed-admin/asap-latin.woff2','/assets/mexico-medico.svg'];
 if(in_array($path,$static,true)&&$_SERVER['REQUEST_METHOD']==='GET')return false;
 if(in_array($path,['/public/identity/acceso.php','/public/identity/crear-cuenta.php','/public/identity/verificar-correo.php','/public/identity/recuperar-acceso.php','/public/identity/restablecer-acceso.php'],true)&&$_SERVER['REQUEST_METHOD']==='GET'){require $root.$path;return true;}
 if(in_array($path,['/','/index.html','/private-app.php'],true)&&$_SERVER['REQUEST_METHOD']==='GET'){
  $http=new OperatorQaHttp();$token=(string)($_COOKIE['__Host-mxmed_session']??'');
  if(!$http->identity->sessions()->validate($token)->allowed()){header('Location: /public/identity/acceso.php',true,302);exit;}
  $http->session();require $root.'/modules/identity/operators/operator-workspace.php';return true;
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
