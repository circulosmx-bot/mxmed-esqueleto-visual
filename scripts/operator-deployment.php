<?php
declare(strict_types=1);
/** Explicit operator-deployment CLI. Never invoked by a web route or dev startup. */
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/../modules/identity/http/IdentityHttpComposition.php';
Identity\Http\IdentityHttpComposition::registerAutoloader();
function privateJson(string $file):array{
    if(!is_file($file)||is_link($file)||(fileperms($file)&0077)!==0||str_starts_with(realpath($file),realpath(dirname(__DIR__)).'/'))throw new RuntimeException('private_configuration_required');
    return json_decode(file_get_contents($file),true,64,JSON_THROW_ON_ERROR);
}
try{
    $args=getopt('',['mode:','connection:','approval:','journal:']);$mode=$args['mode']??'inspect';$v=privateJson($args['connection']??'');
    if(($v['MXMED_DB_HOST']??'')!=='127.0.0.1'||!preg_match('/^mxmed(?:_operator_deploy_[a-f0-9]{10})?$/D',$v['MXMED_DB_NAME']??''))throw new RuntimeException('deployment_connection_denied');
    $pdo=new PDO('mysql:host=127.0.0.1;port='.($v['MXMED_DB_PORT']??3306).';dbname='.$v['MXMED_DB_NAME'].';charset=utf8mb4',$v['MXMED_DB_USER'],$v['MXMED_DB_PASS'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$package=new Identity\Operators\OperatorDeploymentPackage($pdo);
    if($mode==='inspect')$result=$package->inspect();
    elseif($mode==='apply')$result=$package->apply(privateJson($args['approval']??''),$args['journal']??'');
    elseif($mode==='recover-empty'){$package->recoverEmpty(privateJson($args['approval']??''));$result=['state'=>'recovered_empty_only'];}
    else throw new RuntimeException('deployment_mode_denied');
    echo json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){fwrite(STDERR,'DEPLOYMENT_REFUSED:'.get_class($e).':'.(preg_match('/^(operator_|deployment_|private_)[a-z0-9_:.-]+$/D',$e->getMessage())?$e->getMessage():'inspect_private_journal_and_schema')."\n");exit(1);}
