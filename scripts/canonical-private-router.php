<?php
declare(strict_types=1);
/** Original application's server boundary. Default disabled; applies before files AND aliases. */
$root=dirname(__DIR__);
require_once $root.'/modules/identity/http/IdentityHttpComposition.php';
Identity\Http\IdentityHttpComposition::registerAutoloader();
use Identity\Operators\{OperatorDeploymentConfiguration,OperatorRuntimeComposition,OperatorHttp};
use Identity\Http\IdentityHttpComposition;
if(!OperatorDeploymentConfiguration::requested())return false;
define('MXMED_OPERATOR_BOUNDARY_ENFORCED',true);
try{
    $identity=IdentityHttpComposition::fromProcessEnvironment();$runtime=OperatorRuntimeComposition::fromIdentity($identity);
    $path=parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH);
    if(!is_string($path)||rawurldecode($path)!==$path||str_contains($path,'..')||str_contains($path,'\\')||str_contains($path,'//'))OperatorHttp::deny();
    $decision=$identity->sessions()->validate($_COOKIE['__Host-mxmed_session']??null);
    if(!$decision->allowed()||!OperatorRuntimeComposition::accountRequiresBoundedShell($identity,$decision->context()->accountId()))return false;
    $static=['/assets/js/agenda/operators-panel.js','/assets/js/agenda/canonical-operators.js','/assets/js/agenda/agenda-workspace.js','/assets/js/core/navigation.js','/assets/js/core/sidebar.js','/assets/js/core/private-session.js','/assets/mexico-medico.svg','/public/identity/identity-access.js','/public/identity/identity-access.css'];
    if($_SERVER['REQUEST_METHOD']==='GET'&&(in_array($path,$static,true)||preg_match('~^/assets/(?:css|fonts)/[A-Za-z0-9_./-]+$~D',$path)))return false;
    if(in_array($path,['/private-app.php','/','/index.html'],true)&&$_SERVER['REQUEST_METHOD']==='GET'){require $root.'/private-app.php';return true;}
    if(preg_match('~^/public/identity/(?:acceso|crear-cuenta|verificar-correo|recuperar-acceso|restablecer-acceso)\.php$~D',$path)&&$_SERVER['REQUEST_METHOD']==='GET')return false;
    if(preg_match('~^/api/identity/index.php/(?:csrf-token|login|logout|logout-all|sessions|session-revoke|session-rotate|registration-request|email-verification|recovery-request|password-reset)$~D',$path))return false;
    if($path==='/api/identity/index.php/current-session')$runtime->http->route($path);
    if(str_starts_with($path,'/api/profile-operators/index.php/')||str_starts_with($path,'/api/agenda/index.php/')){require $root.'/api/profile-operators/index.php';return true;}
    OperatorHttp::deny(); // Every direct module, alternate PHP entry, media/download or billing/staff path.
}catch(Throwable $e){error_log('OPERATOR_BOUNDARY_UNAVAILABLE:'.get_class($e));OperatorHttp::json(['ok'=>false,'message'=>'La administración de operadores no está disponible.'],503);}
