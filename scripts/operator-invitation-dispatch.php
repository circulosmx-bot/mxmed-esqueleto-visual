<?php
declare(strict_types=1);
/** Default disabled; one bounded dispatch after separate provider authorization. */
if(PHP_SAPI!=='cli')exit(1);
if(getenv('MXMED_OPERATOR_DELIVERY_ENABLED')!=='1'){fwrite(STDERR,"OPERATOR_DELIVERY_DISABLED\n");exit(78);}
require_once __DIR__.'/../modules/identity/http/IdentityHttpComposition.php';
Identity\Http\IdentityHttpComposition::registerAutoloader();
try{
    $identity=Identity\Http\IdentityHttpComposition::fromProcessEnvironment();$runtime=Identity\Operators\OperatorRuntimeComposition::fromIdentity($identity);$config=$runtime->configuration;
    if(!preg_match('/^[A-Za-z0-9._:-]{8,128}$/D',$config->receipt['delivery_authorization_reference']??'')||getenv('MXMED_EMAIL_PROVIDER')!=='ses')throw new RuntimeException('operator_provider_authorization_required');
    $origin=Identity\Operators\OperatorInvitationOrigin::assertPublic($config->receipt['public_invitation_origin']??'');
    if(!class_exists(Aws\SesV2\SesV2Client::class))throw new RuntimeException('operator_provider_sdk_required');
    $client=new Aws\SesV2\SesV2Client(['version'=>'latest','region'=>'us-east-1']);
    $adapter=new Identity\Adapters\SesIdentityNotificationAdapter($client,'us-east-1','no-reply@mexicomedico.com','México Médico',$origin);
    $worker=new Identity\Operators\OperatorInvitationDispatcher($identity->pdo(),new Identity\Operators\SesOperatorInvitationTransport($adapter),$config->deliveryKey(),$config->deliveryKeyId());
    echo json_encode($worker->dispatchOne(),JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){fwrite(STDERR,'OPERATOR_DELIVERY_UNAVAILABLE:'.get_class($e)."\n");exit(1);}
