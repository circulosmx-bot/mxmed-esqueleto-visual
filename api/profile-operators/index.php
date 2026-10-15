<?php
declare(strict_types=1);
require_once __DIR__.'/../../modules/identity/http/IdentityHttpComposition.php';
Identity\Http\IdentityHttpComposition::registerAutoloader();
if(!Identity\Operators\OperatorQaConfiguration::requested()){http_response_code(404);exit;}
try{(new Identity\Operators\OperatorQaHttp())->route(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH));}
catch(DomainException $e){Identity\Operators\OperatorQaHttp::json(['ok'=>false,'message'=>'No se guardó el cambio. Revisa el horario, la disponibilidad y la versión actual.'],409);}
catch(Throwable $e){
    $controlled=preg_match('/^(operator_|op02_)[a-z_]+$/D',$e->getMessage())===1;
    error_log('OP02_REQUEST_FAILED:'.get_class($e).':'.($controlled?$e->getMessage():'service_failure'));
    if($controlled)Identity\Operators\OperatorQaHttp::deny();
    if($e instanceof InvalidArgumentException)Identity\Operators\OperatorQaHttp::json(['ok'=>false,'message'=>'Revisa los datos enviados.'],400);
    Identity\Operators\OperatorQaHttp::json(['ok'=>false,'message'=>'No se pudo completar la acción. Intenta de nuevo; tus datos siguen en el formulario.'],503);
}
