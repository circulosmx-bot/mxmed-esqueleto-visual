<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
foreach (glob($root . '/modules/platform/contracts/*.php') as $file) require_once $file;
foreach (['CorrelatableOperationCatalog','SourceModuleCatalog','CanonicalAuditPolicyRegistry','AuditWriterContextBridge','UuidV4ContextIdPolicy'] as $name) {
    require_once $root . '/modules/platform/services/' . $name . '.php';
}
foreach (['AuditProducerFailureSignal','AuditProducerEmissionResult','PreauthActorOptionalContext','Mp01eEventScopePolicy','BoundedBestEffortAuditEmitter'] as $name) {
    require_once $root . '/modules/identity/audit/' . $name . '.php';
}
require_once $root . '/modules/identity/audit/contracts/AuditProducerFailureSignalPort.php';
require_once $root . '/modules/identity/audit/contracts/CanonicalAuditAppendPort.php';
$audit = $root . '/modules/platform/audit/';
foreach (['Mp01fEventScopePolicy','AuthoritativeAuditTarget','ChangedFieldNames','SensitiveAdminActionCatalog','SensitiveAdminActionKey','AuthoritativeAuditOutcome'] as $name) {
    require_once $audit . $name . '.php';
}
require_once $audit . 'contracts/Mp01fAuditProducer.php';
require_once $audit . 'CanonicalMp01fAuditProducer.php';

use Identity\Audit\AuditProducerEmissionResult;
use Identity\Audit\AuditProducerFailureSignal;
use Identity\Audit\BoundedBestEffortAuditEmitter;
use Identity\Audit\Mp01eEventScopePolicy;
use Identity\Audit\PreauthActorOptionalContext;
use Identity\Audit\Contracts\AuditProducerFailureSignalPort;
use Identity\Audit\Contracts\CanonicalAuditAppendPort;
use Platform\Audit\AuthoritativeAuditOutcome;
use Platform\Audit\AuthoritativeAuditTarget;
use Platform\Audit\CanonicalMp01fAuditProducer;
use Platform\Audit\ChangedFieldNames;
use Platform\Audit\Mp01fEventScopePolicy;
use Platform\Audit\SensitiveAdminActionCatalog;
use Platform\Audit\SensitiveAdminActionKey;
use Platform\Contracts\AuditEventScopePolicy;
use Platform\Contracts\CanonicalAuditEventInput;
use Platform\Contracts\CanonicalAuditEventType;
use Platform\Contracts\TrustedActorContext;
use Platform\Contracts\TrustedAuditContext;
use Platform\Contracts\TrustedRequestContext;
use Platform\Services\AuditWriterContextBridge;
use Platform\Services\CanonicalAuditPolicyRegistry;
use Platform\Services\CorrelatableOperationCatalog;
use Platform\Services\SourceModuleCatalog;
use Platform\Services\UuidV4ContextIdPolicy;

$total=0;$passed=0;$negativeTotal=0;$negativeBlocked=0;
function mp01fOk(bool $condition,string $name):void{global $total,$passed;$total++;if(!$condition)throw new RuntimeException('semantic:'.$name);$passed++;}
function mp01fBlocked(callable $probe,string $name):void{global $negativeTotal,$negativeBlocked;$negativeTotal++;try{$probe();}catch(Throwable){$negativeBlocked++;return;}throw new RuntimeException('negative_escaped:'.$name);}

final class Mp01fAppend implements CanonicalAuditAppendPort
{
    public array $inputs=[];public array $contexts=[];public int $attempts=0;public bool $fail=false;
    public function append(CanonicalAuditEventInput $input,TrustedAuditContext $context):void{$this->attempts++;if($this->fail)throw new RuntimeException('private backend error');$this->inputs[]=$input;$this->contexts[]=$context;}
}
final class Mp01fSignal implements AuditProducerFailureSignalPort
{
    public array $signals=[];public bool $fail=false;
    public function signal(AuditProducerFailureSignal $signal):void{if($this->fail)throw new RuntimeException('signal unavailable');$this->signals[]=$signal;}
}
function mp01fRequest(string $event,Mp01fEventScopePolicy $scope,int $i):TrustedRequestContext
{
    $map=$scope->projection()[$event];$hex=str_pad(dechex($i),12,'0',STR_PAD_LEFT);
    return TrustedRequestContext::fromTrustedBoundary('11111111-1111-4111-8111-'.$hex,'22222222-2222-4222-8222-'.$hex,$map['operation'],'session_'.$hex,$map['source_module'],'POST /internal/mp01f/outcome',null,null,'INTERNAL','backend_continuation',new UuidV4ContextIdPolicy());
}
function mp01fActor(AuthoritativeAuditTarget $target):TrustedActorContext
{
    return TrustedActorContext::fromTrustedBackend(['authenticated_identity_id'=>'admin_1','real_actor_type'=>'ACCOUNT','real_actor_id'=>'admin_1','actor_role'=>'ADMIN','actor_scope'=>'TENANT','target_type'=>$target->type,'target_id'=>$target->id,'authorization_provenance'=>'committed_domain_outcome','trust_source'=>'backend_trusted']);
}

$expectedEvents=['PROFILE_CLAIM_REQUESTED','PROFILE_CLAIM_APPROVED','PROFILE_CLAIM_REJECTED','PROFILE_OWNERSHIP_ASSIGNED','PROFILE_OWNERSHIP_TRANSFERRED','INVITATION_CREATED','INVITATION_ACCEPTED','INVITATION_REVOKED','ROLE_ASSIGNED','ROLE_REVOKED','STEP_UP_CHALLENGE_SUCCEEDED','STEP_UP_CHALLENGE_FAILED','BREAK_GLASS_STARTED','BREAK_GLASS_ENDED','SENSITIVE_ADMIN_ACTION'];
$expectedOperations=['PROFILE_CLAIM','PROFILE_CLAIM','PROFILE_CLAIM','PROFILE_OWNERSHIP_TRANSFER','PROFILE_OWNERSHIP_TRANSFER','INVITATION_LIFECYCLE','INVITATION_LIFECYCLE','INVITATION_LIFECYCLE','ROLE_LIFECYCLE','ROLE_LIFECYCLE','STEP_UP_CHALLENGE','STEP_UP_CHALLENGE','BREAK_GLASS_LIFECYCLE','BREAK_GLASS_LIFECYCLE','SENSITIVE_ADMIN_OPERATION'];
$expectedModules=['PROFILE','PROFILE','PROFILE','OWNERSHIP','OWNERSHIP','INVITATION','INVITATION','INVITATION','ROLE','ROLE','SECURITY','SECURITY','SECURITY','SECURITY','ADMIN'];
$operations=new CorrelatableOperationCatalog();$modules=new SourceModuleCatalog();$scope=new Mp01fEventScopePolicy($operations,$modules);$policies=CanonicalAuditPolicyRegistry::canonical();$catalog=new SensitiveAdminActionCatalog();
$append=new Mp01fAppend();$signal=new Mp01fSignal();$emitter=new BoundedBestEffortAuditEmitter($append,new AuditWriterContextBridge(),$signal,$scope);$producer=new CanonicalMp01fAuditProducer($emitter,$policies,$catalog);

mp01fOk($scope instanceof AuditEventScopePolicy,'mp01f_scope_implements_shared_contract');
mp01fOk($scope->eventTypes()===$expectedEvents,'event_scope_15_of_15');
$projection=$scope->projection();
mp01fOk(array_map(fn($e)=>$projection[$e]['operation'],$expectedEvents)===$expectedOperations,'event_operation_15_of_15');
mp01fOk(array_map(fn($e)=>$projection[$e]['source_module'],$expectedEvents)===$expectedModules,'event_source_module_15_of_15');
$compatible=0;foreach($expectedEvents as $event){$row=$policies->policyFor($event);if($row['authority_status']==='DIRECTOR_RATIFIED_COMPLETE'&&$row['actor_required']&&$row['target_required'])$compatible++;}
mp01fOk($compatible===15,'policy_compatibility_15_of_15');

$i=0;
foreach(array_slice($expectedEvents,0,14) as $event){
    $i++;$target=AuthoritativeAuditTarget::fromCommittedBackendOutcome('RESOURCE',strtolower($event).'_'.$i);$pair=$policies->policyFor($event)['allowed_result_reason_pairs'][0];
    $outcome=AuthoritativeAuditOutcome::afterCommitted($scope,$event,$pair['result'],$pair['reason_code'],$target,true);
    $result=$producer->emit($outcome,mp01fRequest($event,$scope,$i),mp01fActor($target));
    mp01fOk($result->status===AuditProducerEmissionResult::WRITTEN,'emitted_after_authoritative_outcome_'.$event);
}
mp01fOk(count($append->inputs)===14&&$append->attempts===14,'one_append_per_specific_event');
mp01fOk(array_reduce($append->inputs,fn($ok,$input)=>$ok&&!preg_match('/(?:token|otp|password|credential|secret)/i',$input->targetId.' '.json_encode($input->metadata,JSON_THROW_ON_ERROR)),true),'secrets_absent');
mp01fOk(array_reduce($append->contexts,fn($ok,$context)=>$ok&&$context->actorIdentityId==='admin_1',true),'trusted_actor_authority');
mp01fOk(array_reduce($append->inputs,fn($ok,$input)=>$ok&&$input->targetType==='RESOURCE'&&$input->targetId!=='',true),'target_authority');
mp01fOk($signal->signals===[],'no_failure_signal_on_success');

mp01fOk($catalog->all()===[],'initial_sensitive_admin_catalog_empty');
mp01fOk(SensitiveAdminActionCatalog::VERSION==='mp01f-sensitive-admin-v1'&&!SensitiveAdminActionCatalog::FREE_FORM_ALLOWED&&!SensitiveAdminActionCatalog::UNKNOWN_KEY_ALLOWED&&!SensitiveAdminActionCatalog::DUPLICATE_EMISSION,'finite_catalog_policy');
$catalog->assertPolicyCompatibility($policies);mp01fOk(true,'empty_catalog_policy_compatible');
mp01fBlocked(fn()=>SensitiveAdminActionKey::fromBackendCatalog('free form action',$catalog),'free_form_admin_action');
mp01fBlocked(fn()=>SensitiveAdminActionKey::fromBackendCatalog('UNKNOWN_ADMIN_ACTION',$catalog),'unknown_admin_action');
foreach(array_slice($expectedEvents,0,14) as $event)mp01fBlocked(fn()=>SensitiveAdminActionKey::fromBackendCatalog($event,$catalog),'specific_event_not_duplicated_'.$event);
mp01fBlocked(fn()=>AuthoritativeAuditTarget::fromCommittedBackendOutcome('INVITATION','token:raw-secret'),'invitation_token');
mp01fBlocked(fn()=>AuthoritativeAuditTarget::fromCommittedBackendOutcome('CHALLENGE','otp-123456'),'otp_challenge_secret');
mp01fBlocked(fn()=>AuthoritativeAuditTarget::fromCommittedBackendOutcome('ACCOUNT','password-secret'),'credential_payload');
$target=AuthoritativeAuditTarget::fromCommittedBackendOutcome('RESOURCE','profile_claim_1');$pair=$policies->policyFor('PROFILE_CLAIM_REQUESTED')['allowed_result_reason_pairs'][0];
mp01fBlocked(fn()=>AuthoritativeAuditOutcome::afterCommitted($scope,'PROFILE_CLAIM_REQUESTED',$pair['result'],$pair['reason_code'],$target,false),'before_authoritative_outcome');
mp01fBlocked(fn()=>AuthoritativeAuditOutcome::afterCommitted($scope,'AUTH_LOGIN_SUCCEEDED','SUCCESS','USER_REQUEST',$target,true),'event_outside_scope');
mp01fBlocked(fn()=>AuthoritativeAuditOutcome::afterCommitted($scope,'SENSITIVE_ADMIN_ACTION','SUCCESS','ADMIN_DECISION',$target,true),'sensitive_admin_catalog_bypass');
$valid=AuthoritativeAuditOutcome::afterCommitted($scope,'PROFILE_CLAIM_REQUESTED',$pair['result'],$pair['reason_code'],$target,true);
mp01fBlocked(fn()=>$producer->emit($valid,TrustedRequestContext::fromTrustedBoundary('33333333-3333-4333-8333-333333333333','44444444-4444-4444-8444-444444444444','ROLE_LIFECYCLE','s','PROFILE','POST /x',null,null,'INTERNAL','backend',new UuidV4ContextIdPolicy()),mp01fActor($target)),'operation_mismatch');
mp01fBlocked(fn()=>$producer->emit($valid,TrustedRequestContext::fromTrustedBoundary('55555555-5555-4555-8555-555555555555','66666666-6666-4666-8666-666666666666','PROFILE_CLAIM','s','ROLE','POST /x',null,null,'INTERNAL','backend',new UuidV4ContextIdPolicy()),mp01fActor($target)),'source_module_mismatch');
$badPair=AuthoritativeAuditOutcome::afterCommitted($scope,'PROFILE_CLAIM_REQUESTED','SUCCESS','INTERNAL_ERROR',$target,true);
mp01fBlocked(fn()=>$producer->emit($badPair,mp01fRequest('PROFILE_CLAIM_REQUESTED',$scope,98),mp01fActor($target)),'policy_pair_mismatch');
$wrongActor=TrustedActorContext::fromTrustedBackend(['authenticated_identity_id'=>'admin_1','real_actor_type'=>'ACCOUNT','real_actor_id'=>'admin_1','actor_role'=>'ADMIN','actor_scope'=>'TENANT','target_type'=>'RESOURCE','target_id'=>'different','authorization_provenance'=>'committed_domain_outcome','trust_source'=>'backend_trusted']);
mp01fBlocked(fn()=>$producer->emit($valid,mp01fRequest('PROFILE_CLAIM_REQUESTED',$scope,99),$wrongActor),'actor_target_mismatch');
mp01fBlocked(fn()=>ChangedFieldNames::fromBackendAllowlist(['password']),'sensitive_changed_field');

$failAppend=new Mp01fAppend();$failAppend->fail=true;$failSignal=new Mp01fSignal();$failEmitter=new BoundedBestEffortAuditEmitter($failAppend,new AuditWriterContextBridge(),$failSignal,$scope);$failProducer=new CanonicalMp01fAuditProducer($failEmitter,$policies,$catalog);
$failed=$failProducer->emit($valid,mp01fRequest('PROFILE_CLAIM_REQUESTED',$scope,100),mp01fActor($target));
mp01fOk($failed->status===AuditProducerEmissionResult::AUDIT_FAILED_SIGNALLED&&$failAppend->attempts===1&&count($failSignal->signals)===1,'shared_bounded_failure_no_retry');

$mp01eScope=new Mp01eEventScopePolicy();mp01fOk(count($mp01eScope->map())===13,'mp01e_scope_remains_13');
mp01fBlocked(fn()=>$mp01eScope->assertRequestMatches('ROLE_ASSIGNED',mp01fRequest('ROLE_ASSIGNED',$scope,101)),'mp01e_rejects_mp01f');
$preauthAppend=new Mp01fAppend();$preauthEmitter=new BoundedBestEffortAuditEmitter($preauthAppend,new AuditWriterContextBridge(),new Mp01fSignal(),$mp01eScope);$preauthActor=PreauthActorOptionalContext::normalUnknown('ACCOUNT','account_1');$preauthAccepted=0;
foreach(CanonicalAuditEventType::all() as $index=>$event){
    $map=$mp01eScope->map()[$event]??['UNRELATED_OPERATION','UNRELATED'];$hex=str_pad(dechex(200+$index),12,'0',STR_PAD_LEFT);
    $request=TrustedRequestContext::fromTrustedBoundary('77777777-7777-4777-8777-'.$hex,'88888888-8888-4888-8888-'.$hex,$map[0],'session_'.$hex,$map[1],'POST /internal/preauth',null,null,'INTERNAL','backend',new UuidV4ContextIdPolicy());
    $input=new CanonicalAuditEventInput($event,'SUCCESS','USER_REQUEST',null,null,'ACCOUNT','account_1',[]);
    try{$preauthEmitter->emitActorOptionalPreauth($input,$request,$preauthActor);$preauthAccepted++;}catch(Throwable){}
}
mp01fOk($preauthAccepted===3&&$preauthAppend->attempts===3,'mp01e_preauth_remains_3');

echo 'MP01F_EVENT_SCOPE=15/15'.PHP_EOL;
echo 'MP01F_OPERATION_MAPPING=15/15'.PHP_EOL;
echo 'MP01F_SOURCE_MODULE_MAPPING=15/15'.PHP_EOL;
echo 'MP01F_POLICY_COMPATIBILITY=15/15'.PHP_EOL;
echo 'MP01F_SCOPE_IMPLEMENTS_SHARED_CONTRACT=true'.PHP_EOL;
echo 'MP01F_USES_SHARED_EMITTER=true'.PHP_EOL;
echo 'MP01F_REQUIRES_SECOND_EMITTER=false'.PHP_EOL;
echo 'MP01E_SCOPE_REMAINS=13/13'.PHP_EOL;
echo 'MP01E_PREAUTH_REMAINS=3/3'.PHP_EOL;
echo 'SENSITIVE_ADMIN_ACTION_INITIAL_CATALOG=[]'.PHP_EOL;
echo 'SEMANTIC_TESTS_PASS='.$passed.'/'.$total.PHP_EOL;
echo 'NEGATIVES_BLOCKED='.$negativeBlocked.'/'.$negativeTotal.PHP_EOL;
