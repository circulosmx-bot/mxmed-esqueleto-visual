<?php
declare(strict_types=1);
require_once __DIR__.'/../../modules/identity/http/IdentityHttpComposition.php';
Identity\Http\IdentityHttpComposition::registerAutoloader();
try{
    if(Identity\Operators\OperatorQaConfiguration::requested())$http=new Identity\Operators\OperatorQaHttp();
    else{
        if(!Identity\Operators\OperatorDeploymentConfiguration::requested())Identity\Operators\OperatorHttp::json(['ok'=>false,'message'=>'La administración de operadores está pendiente de habilitación.'],503);
        $identity=Identity\Http\IdentityHttpComposition::fromProcessEnvironment();$http=Identity\Operators\OperatorRuntimeComposition::fromIdentity($identity)->http;
    }
    $http->route(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH));
}
catch(DomainException $e){Identity\Operators\OperatorHttp::json(['ok'=>false,'message'=>'No se guardó el cambio. Revisa el horario, la disponibilidad y la versión actual.'],409);}
catch(Throwable $e){
    if(in_array($e->getMessage(),['operator_alias_collision','operator_presentation_revision_conflict','operator_alias_offer_conflict'],true))
        Identity\Operators\OperatorHttp::json(['ok'=>false,'error'=>$e->getMessage(),'message'=>$e->getMessage()==='operator_alias_collision'?'Ese alias ya está reservado en este equipo. Elige otro.':'La presentación cambió. Actualiza el equipo antes de continuar.'],409);
    if(in_array($e->getMessage(),['operator_alias_invalid','operator_alias_required'],true))
        Identity\Operators\OperatorHttp::json(['ok'=>false,'error'=>$e->getMessage(),'message'=>'Usa una sola palabra con letras, de hasta 24 caracteres, respetando sus acentos.'],422);
    $controlled=preg_match('/^(operator_|op02_)[a-z_]+$/D',$e->getMessage())===1;
    error_log('OP02_REQUEST_FAILED:'.get_class($e).':'.($controlled?$e->getMessage():'service_failure'));
    if($controlled)Identity\Operators\OperatorHttp::deny();
    if($e instanceof InvalidArgumentException)Identity\Operators\OperatorHttp::json(['ok'=>false,'message'=>'Revisa los datos enviados.'],400);
    Identity\Operators\OperatorHttp::json(['ok'=>false,'message'=>'No se pudo completar la acción. Intenta de nuevo; tus datos siguen en el formulario.'],503);
}
