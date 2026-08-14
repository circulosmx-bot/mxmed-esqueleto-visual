<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
foreach (glob($root . '/modules/platform/contracts/*.php') as $file) require_once $file;
foreach (['AuditWriterContextBridge','UuidV4ContextIdPolicy'] as $name) {
    require_once $root . '/modules/platform/services/' . $name . '.php';
}
require_once $root . '/modules/identity/audit/contracts/AuditProducerFailureSignalPort.php';
require_once $root . '/modules/identity/audit/contracts/CanonicalAuditAppendPort.php';
foreach (['AuditProducerFailureSignal','AuditProducerEmissionResult','Mp01eEventScopePolicy','PreauthActorOptionalContext','BoundedBestEffortAuditEmitter'] as $name) {
    require_once $root . '/modules/identity/audit/' . $name . '.php';
}

use Identity\Audit\AuditProducerEmissionResult;
use Identity\Audit\AuditProducerFailureSignal;
use Identity\Audit\BoundedBestEffortAuditEmitter;
use Identity\Audit\Mp01eEventScopePolicy;
use Identity\Audit\PreauthActorOptionalContext;
use Identity\Audit\Contracts\AuditProducerFailureSignalPort;
use Identity\Audit\Contracts\CanonicalAuditAppendPort;
use Platform\Contracts\AuditEventScopePolicy;
use Platform\Contracts\CanonicalAuditEventInput;
use Platform\Contracts\TrustedActorContext;
use Platform\Contracts\TrustedAuditContext;
use Platform\Contracts\TrustedRequestContext;
use Platform\Services\AuditWriterContextBridge;
use Platform\Services\UuidV4ContextIdPolicy;

$total=0;$pass=0;$negativeTotal=0;$negativeBlocked=0;
function scopeOk(bool $condition,string $name):void{global $total,$pass;$total++;if(!$condition)throw new RuntimeException('semantic:'.$name);$pass++;}
function scopeBlocked(callable $probe,string $name):void{global $negativeTotal,$negativeBlocked;$negativeTotal++;try{$probe();}catch(Throwable){$negativeBlocked++;return;}throw new RuntimeException('negative_escaped:'.$name);}

final class ScopeAppend implements CanonicalAuditAppendPort
{
    public array $inputs=[];public array $contexts=[];public int $attempts=0;public bool $fail=false;
    public function append(CanonicalAuditEventInput $input,TrustedAuditContext $context):void{$this->attempts++;if($this->fail)throw new RuntimeException('private append failure');$this->inputs[]=$input;$this->contexts[]=$context;}
}
final class ScopeSignal implements AuditProducerFailureSignalPort
{
    public array $signals=[];
    public function signal(AuditProducerFailureSignal $signal):void{$this->signals[]=$signal;}
}
final class FutureMp01fScopeProof implements AuditEventScopePolicy
{
    public int $calls=0;
    public function assertRequestMatches(string $eventType,TrustedRequestContext $request):void
    {
        $this->calls++;
        if($eventType!=='ROLE_ASSIGNED')throw new InvalidArgumentException('future_scope_event_rejected');
        if($request->operationKey!=='ROLE_LIFECYCLE')throw new InvalidArgumentException('future_scope_operation_rejected');
        if($request->sourceModule!=='ROLE')throw new InvalidArgumentException('future_scope_module_rejected');
    }
}
function scopeRequest(string $operation,string $module,string $suffix):TrustedRequestContext
{
    return TrustedRequestContext::fromTrustedBoundary('11111111-1111-4111-8111-'.$suffix,'22222222-2222-4222-8222-'.$suffix,$operation,'session_'.$suffix,$module,'POST /internal/audit/scope-proof',null,null,'INTERNAL','backend',new UuidV4ContextIdPolicy());
}
function scopeActor(string $targetId,?string $effectiveId=null):TrustedActorContext
{
    $data=['authenticated_identity_id'=>'admin_1','real_actor_type'=>'ACCOUNT','real_actor_id'=>'admin_1','actor_role'=>'ADMIN','actor_scope'=>'TENANT','target_type'=>'ACCOUNT_MEMBERSHIP','target_id'=>$targetId,'authorization_provenance'=>'committed_domain_outcome','trust_source'=>'backend_trusted'];
    if($effectiveId!==null){$data['effective_entity_type']='ACCOUNT';$data['effective_entity_id']=$effectiveId;}
    return TrustedActorContext::fromTrustedBackend($data);
}

$expected=[
 'AUTH_REGISTRATION_REQUESTED'=>['AUTH_REGISTRATION','AUTH'],
 'AUTH_EMAIL_VERIFICATION_SENT'=>['AUTH_EMAIL_VERIFICATION','AUTH'],
 'AUTH_EMAIL_VERIFIED'=>['AUTH_EMAIL_VERIFICATION','AUTH'],
 'AUTH_LOGIN_SUCCEEDED'=>['AUTH_LOGIN','AUTH'],
 'AUTH_LOGIN_FAILED'=>['AUTH_LOGIN','AUTH'],
 'AUTH_PASSWORD_RECOVERY_REQUESTED'=>['AUTH_PASSWORD_RECOVERY','AUTH'],
 'AUTH_PASSWORD_RESET_SUCCEEDED'=>['AUTH_PASSWORD_RECOVERY','AUTH'],
 'AUTH_PASSWORD_CHANGED'=>['AUTH_PASSWORD_CHANGE','AUTH'],
 'AUTH_SESSION_CREATED'=>['AUTH_SESSION_LIFECYCLE','SESSION'],
 'AUTH_SESSION_ROTATED'=>['AUTH_SESSION_LIFECYCLE','SESSION'],
 'AUTH_SESSION_REVOKED'=>['AUTH_SESSION_LIFECYCLE','SESSION'],
 'AUTH_LOGOUT'=>['AUTH_SESSION_LIFECYCLE','SESSION'],
 'AUTH_LOGOUT_ALL'=>['AUTH_SESSION_LIFECYCLE','SESSION'],
];
$mp01e=new Mp01eEventScopePolicy();
scopeOk($mp01e instanceof AuditEventScopePolicy,'mp01e_implements_shared_contract');
scopeOk($mp01e->map()===$expected&&count($mp01e->map())===13,'mp01e_exact_13_event_scope');
$i=0;foreach($expected as $event=>$mapping){$i++;$mp01e->assertRequestMatches($event,scopeRequest($mapping[0],$mapping[1],str_pad((string)$i,12,'0',STR_PAD_LEFT)));}scopeOk(true,'mp01e_mapping_13_of_13');
scopeBlocked(fn()=>$mp01e->assertRequestMatches('ROLE_ASSIGNED',scopeRequest('ROLE_LIFECYCLE','ROLE','000000000014')),'mp01e_rejects_mp01f');

$reflection=new ReflectionMethod(BoundedBestEffortAuditEmitter::class,'__construct');$scopeType=$reflection->getParameters()[3]->getType();
scopeOk($scopeType instanceof ReflectionNamedType&&$scopeType->getName()===AuditEventScopePolicy::class,'emitter_depends_on_shared_contract');
$alternate=new FutureMp01fScopeProof();$append=new ScopeAppend();$signal=new ScopeSignal();$emitter=new BoundedBestEffortAuditEmitter($append,new AuditWriterContextBridge(),$signal,$alternate);
$target='membership_42';$input=new CanonicalAuditEventInput('ROLE_ASSIGNED','SUCCESS','ADMIN_DECISION',null,null,'ACCOUNT_MEMBERSHIP',$target,['changed_field_names'=>['role']]);$request=scopeRequest('ROLE_LIFECYCLE','ROLE','000000000101');$actor=scopeActor($target);
$result=$emitter->emit($input,$request,$actor);
scopeOk($result->status===AuditProducerEmissionResult::WRITTEN&&$append->attempts===1&&$alternate->calls===1,'alternate_scope_accepted_and_executed');
$before=$append->attempts;scopeBlocked(fn()=>$emitter->emit($input,scopeRequest('PROFILE_CLAIM','ROLE','000000000102'),$actor),'alternate_scope_operation_rejected');scopeOk($append->attempts===$before&&$alternate->calls===2,'scope_no_bypass_before_append');
scopeBlocked(fn()=>$emitter->emit($input,$request,scopeActor('different_target')),'actor_target_validation_preserved');
$effectiveActor=scopeActor($target,'effective_1');$effectiveMismatch=new CanonicalAuditEventInput('ROLE_ASSIGNED','SUCCESS','ADMIN_DECISION','ACCOUNT','effective_2','ACCOUNT_MEMBERSHIP',$target,['changed_field_names'=>['role']]);scopeBlocked(fn()=>$emitter->emit($effectiveMismatch,$request,$effectiveActor),'effective_entity_validation_preserved');
$failingAppend=new ScopeAppend();$failingAppend->fail=true;$failureSignal=new ScopeSignal();$failingEmitter=new BoundedBestEffortAuditEmitter($failingAppend,new AuditWriterContextBridge(),$failureSignal,$alternate);$failed=$failingEmitter->emit($input,$request,$actor);scopeOk($failed->status===AuditProducerEmissionResult::AUDIT_FAILED_SIGNALLED&&$failed->domainOutcomePreserved&&$failingAppend->attempts===1&&count($failureSignal->signals)===1,'failure_signal_and_no_retry_preserved');

$preauthAppend=new ScopeAppend();$preauthEmitter=new BoundedBestEffortAuditEmitter($preauthAppend,new AuditWriterContextBridge(),new ScopeSignal(),$mp01e);$preauthEvents=[
 ['AUTH_REGISTRATION_REQUESTED','AUTH_REGISTRATION'],
 ['AUTH_EMAIL_VERIFICATION_SENT','AUTH_EMAIL_VERIFICATION'],
 ['AUTH_PASSWORD_RECOVERY_REQUESTED','AUTH_PASSWORD_RECOVERY'],
];
$j=200;foreach($preauthEvents as [$event,$operation]){$j++;$id='auth-id-v1:'.str_repeat(dechex($j%16),64);$preauthEmitter->emitActorOptionalPreauth(new CanonicalAuditEventInput($event,'SUCCESS','USER_REQUEST',null,null,'AUTH_IDENTIFIER_HMAC',$id,[]),scopeRequest($operation,'AUTH',str_pad((string)$j,12,'0',STR_PAD_LEFT)),PreauthActorOptionalContext::normalUnknown('AUTH_IDENTIFIER_HMAC',$id));}
scopeOk($preauthAppend->attempts===3,'preauth_exact_three_allowed');
$badId='auth-id-v1:'.str_repeat('a',64);scopeBlocked(fn()=>$preauthEmitter->emitActorOptionalPreauth(new CanonicalAuditEventInput('AUTH_LOGIN_FAILED','FAILURE','INVALID_CREDENTIALS',null,null,'AUTH_IDENTIFIER_HMAC',$badId,[]),scopeRequest('AUTH_LOGIN','AUTH','000000000299'),PreauthActorOptionalContext::normalUnknown('AUTH_IDENTIFIER_HMAC',$badId)),'preauth_fourth_event_rejected');

echo 'MP01E_EVENT_TYPE_COUNT=13'.PHP_EOL;
echo 'MP01E_EVENT_SCOPE_MATCH=13/13'.PHP_EOL;
echo 'MP01E_UNAUTHORIZED_EVENT_TYPES=0'.PHP_EOL;
echo 'MP01E_PREAUTH_EVENT_COUNT=3'.PHP_EOL;
echo 'MP01E_PREAUTH_SCOPE_UNCHANGED=true'.PHP_EOL;
echo 'MP01E_POLICY_REJECTS_MP01F_EVENT=true'.PHP_EOL;
echo 'EMITTER_ACCEPTS_ALTERNATE_SCOPE_IMPLEMENTATION=true'.PHP_EOL;
echo 'EMITTER_SCOPE_BYPASS=false'.PHP_EOL;
echo 'MP01F_SCOPE_IMPLEMENTATION_COMPATIBLE=true'.PHP_EOL;
echo 'MP01F_REQUIRES_SECOND_EMITTER=false'.PHP_EOL;
echo 'SEMANTIC_TESTS_PASS='.$pass.'/'.$total.PHP_EOL;
echo 'NEGATIVES_BLOCKED='.$negativeBlocked.'/'.$negativeTotal.PHP_EOL;
