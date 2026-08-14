<?php
declare(strict_types=1);

$platformContracts = dirname(__DIR__, 2) . '/platform/contracts/';
foreach (glob($platformContracts . '*.php') as $file) require_once $file;
require_once dirname(__DIR__, 2) . '/platform/services/UuidV4ContextIdPolicy.php';
require_once dirname(__DIR__, 2) . '/platform/services/AuditWriterContextBridge.php';
require_once __DIR__ . '/../contracts/SessionId.php';
$audit = __DIR__ . '/../audit/';
foreach (['AuthIdentifierAuditSecretProvider','AuditProducerFailureSignalPort','CanonicalAuditAppendPort','IdentityAuditProducer','SessionAuditProducer'] as $name) require_once $audit . 'contracts/' . $name . '.php';
foreach (['AuthIdentifierAuditTarget','TrustedIdentityId','VerifiedAccountId','PasswordChangedFieldSet','AuditProducerFailureSignal','AuditProducerEmissionResult','HmacSha256AuthIdentifierAuditHasher','CanonicalAuditWriterAdapter','Mp01eEventScopePolicy','PreauthActorOptionalContext','BoundedBestEffortAuditEmitter','IdentityAuditReasonResolver','CanonicalIdentityAuditProducer','CanonicalSessionAuditProducer'] as $name) require_once $audit . $name . '.php';

use Identity\Audit\{AuditProducerEmissionResult,AuditProducerFailureSignal,BoundedBestEffortAuditEmitter,CanonicalIdentityAuditProducer,CanonicalSessionAuditProducer,HmacSha256AuthIdentifierAuditHasher,IdentityAuditReasonResolver,Mp01eEventScopePolicy,PasswordChangedFieldSet,PreauthActorOptionalContext,TrustedIdentityId,VerifiedAccountId};
use Identity\Audit\Contracts\{AuditProducerFailureSignalPort,AuthIdentifierAuditSecretProvider,CanonicalAuditAppendPort};
use Identity\Contracts\SessionId;
use Platform\Contracts\{CanonicalAuditEventInput,TrustedActorContext,TrustedAuditContext,TrustedRequestContext};
use Platform\Services\{AuditWriterContextBridge,UuidV4ContextIdPolicy};

$unitTotal=0;$unitPass=0;$negativeTotal=0;$negativePass=0;$focusedTotal=0;$focusedPass=0;
function mp01eOk(bool $condition,string $name):void{global $unitTotal,$unitPass;$unitTotal++;if(!$condition)throw new RuntimeException('unit:'.$name);$unitPass++;}
function mp01eFocusedOk(bool $condition,string $name):void{global $focusedTotal,$focusedPass;$focusedTotal++;if(!$condition)throw new RuntimeException('focused:'.$name);$focusedPass++;mp01eOk(true,'focused_'.$name);}
function mp01eNo(callable $callable,string $name):void{global $negativeTotal,$negativePass;$negativeTotal++;try{$callable();}catch(Throwable){$negativePass++;return;}throw new RuntimeException('negative:'.$name);}

final class Mp01eSecret implements AuthIdentifierAuditSecretProvider
{
    public function __construct(private string $namespace='audit-auth-identifier',private string $version='auth-id-v1',private string $secret='0123456789abcdef0123456789abcdef'){}
    public function currentAuthIdentifierAuditKey():array{return ['namespace'=>$this->namespace,'version'=>$this->version,'secret'=>$this->secret];}
}
final class Mp01eAppend implements CanonicalAuditAppendPort
{
    public array $inputs=[];public array $contexts=[];public bool $fail=false;public int $attempts=0;
    public function append(CanonicalAuditEventInput $input,TrustedAuditContext $context):void{$this->attempts++;if($this->fail)throw new RuntimeException('sensitive failure detail must not escape');$this->inputs[]=$input;$this->contexts[]=$context;}
}
final class Mp01eSignal implements AuditProducerFailureSignalPort
{
    public array $signals=[];public bool $fail=false;
    public function signal(AuditProducerFailureSignal $signal):void{if($this->fail)throw new RuntimeException('signal failed');$this->signals[]=$signal;}
}
function mp01eRequest(string $operation,string $module,?string $sessionId=null):TrustedRequestContext
{
    static $i=0;$i++;$hex=str_pad(dechex($i),12,'0',STR_PAD_LEFT);
    return TrustedRequestContext::fromTrustedBoundary('11111111-1111-4111-8111-'.$hex,'22222222-2222-4222-8222-'.$hex,$operation,$sessionId,$module,'POST /api/identity/index.php/test',null,null,'HTTP','server',new UuidV4ContextIdPolicy());
}
function mp01eActor(string $targetId,string $targetType='ACCOUNT',string $identity='account_42',string $role='ACCOUNT_OWNER',string $scope='SELF'):TrustedActorContext
{
    return TrustedActorContext::fromTrustedBackend(['authenticated_identity_id'=>$identity,'real_actor_type'=>'ACCOUNT','real_actor_id'=>$identity,'actor_role'=>$role,'actor_scope'=>$scope,'target_type'=>$targetType,'target_id'=>$targetId,'authorization_provenance'=>'authoritative_domain_outcome','trust_source'=>'backend_trusted']);
}
function mp01eSystemActor(string $targetId,string $targetType):TrustedActorContext
{
    return TrustedActorContext::fromTrustedBackend(['actor_role'=>'SYSTEM','actor_scope'=>'SYSTEM','target_type'=>$targetType,'target_id'=>$targetId,'authorization_provenance'=>'trusted_system_policy','trust_source'=>'system']);
}

$append=new Mp01eAppend();$signal=new Mp01eSignal();$scope=new Mp01eEventScopePolicy();$emitter=new BoundedBestEffortAuditEmitter($append,new AuditWriterContextBridge(),$signal,$scope);$hasher=new HmacSha256AuthIdentifierAuditHasher(new Mp01eSecret());$identity=new CanonicalIdentityAuditProducer($emitter,$hasher,new IdentityAuditReasonResolver());$sessions=new CanonicalSessionAuditProducer($emitter);
$account=TrustedIdentityId::fromAuthoritativeOutcome('account_42');$accountActor=mp01eActor('account_42');
$targetSession=new SessionId('session_target_b_0000000000000001');$requestSessionA='session_actor_a_0000000000000001';
$sessionActor=mp01eActor($targetSession->value(),'SESSION','account_42');
$adminActor=mp01eActor($targetSession->value(),'SESSION','admin_7','ADMIN','TENANT');
$userOtherSessionActor=mp01eActor($targetSession->value(),'SESSION','account_42','ACCOUNT_OWNER','SELF');

$identity->registrationRequested(mp01eRequest('AUTH_REGISTRATION','AUTH'),'registration@example.invalid','SUCCESS','USER_REQUEST');
$identity->emailVerificationSent(mp01eRequest('AUTH_EMAIL_VERIFICATION','AUTH'),$account,true,'USER_REQUEST');
$identity->emailVerified(mp01eRequest('AUTH_EMAIL_VERIFICATION','AUTH'),VerifiedAccountId::fromValidatedTokenResolution('account_42'),'USER_REQUEST');
$loginReq=mp01eRequest('AUTH_LOGIN','AUTH',$requestSessionA);
$identity->loginSucceeded($loginReq,$accountActor,$account,'USER_REQUEST');
$identity->loginFailed(mp01eRequest('AUTH_LOGIN','AUTH'),'normalized@example.invalid','invalid_credentials');
$identity->passwordRecoveryRequested(mp01eRequest('AUTH_PASSWORD_RECOVERY','AUTH'),'existing@example.invalid','SUCCESS','USER_REQUEST');
$identity->passwordResetSucceeded(mp01eRequest('AUTH_PASSWORD_RECOVERY','AUTH'),$accountActor,$account,'USER_REQUEST');
$identity->passwordChanged(mp01eRequest('AUTH_PASSWORD_CHANGE','AUTH',$requestSessionA),$accountActor,$account,PasswordChangedFieldSet::passwordOnly(),'USER_REQUEST');
$sessionReq=mp01eRequest('AUTH_SESSION_LIFECYCLE','SESSION',$requestSessionA);
$sessions->created($sessionReq,$sessionActor,$targetSession,'USER_REQUEST');
$sessions->rotated($sessionReq,$sessionActor,$targetSession,'SYSTEM_POLICY');
$sessions->revoked($sessionReq,$adminActor,$targetSession,'SUCCESS','SECURITY_RESPONSE');
$sessions->logout($sessionReq,$userOtherSessionActor,$targetSession,'SUCCESS','USER_REQUEST');
$beforeLogoutAll=count($append->inputs);$sessions->logoutAll(mp01eRequest('AUTH_SESSION_LIFECYCLE','SESSION',$requestSessionA),$accountActor,$account,'SUCCESS','USER_REQUEST');

$expected=['AUTH_REGISTRATION_REQUESTED','AUTH_EMAIL_VERIFICATION_SENT','AUTH_EMAIL_VERIFIED','AUTH_LOGIN_SUCCEEDED','AUTH_LOGIN_FAILED','AUTH_PASSWORD_RECOVERY_REQUESTED','AUTH_PASSWORD_RESET_SUCCEEDED','AUTH_PASSWORD_CHANGED','AUTH_SESSION_CREATED','AUTH_SESSION_ROTATED','AUTH_SESSION_REVOKED','AUTH_LOGOUT','AUTH_LOGOUT_ALL'];
mp01eOk(array_map(fn($i)=>$i->eventType,$append->inputs)===$expected,'exact_13_events');
mp01eOk(count($scope->map())===13,'scope_count');
mp01eOk($append->inputs[6]->eventType==='AUTH_PASSWORD_RESET_SUCCEEDED'&&$append->inputs[7]->eventType==='AUTH_PASSWORD_CHANGED','password_semantics_separate');
mp01eOk($append->inputs[7]->metadata===['changed_field_names'=>['password']],'password_changed_metadata_allowlist');
mp01eOk($append->contexts[2]->actorIdentityId==='account_42'&&$append->contexts[2]->sessionId===null,'verified_backend_actor_no_session');
mp01eOk($append->contexts[4]->actorIdentityId==='UNKNOWN'&&$append->contexts[4]->actorType==='UNKNOWN','failed_login_unknown_actor');
mp01eOk($append->inputs[4]->targetType==='AUTH_IDENTIFIER_HMAC','failed_login_hmac_target_type');
mp01eOk(!str_contains($append->inputs[4]->targetId,'normalized@example.invalid'),'failed_login_raw_identifier_absent');
mp01eOk(preg_match('/^auth-id-v1:[a-f0-9]{64}$/D',$append->inputs[4]->targetId)===1,'hmac_version_and_hex');
mp01eOk($hasher->hashCanonicalIdentifier('same@example.invalid')->targetId()===$hasher->hashCanonicalIdentifier('same@example.invalid')->targetId(),'hmac_deterministic');
mp01eOk($append->attempts===13,'one_writer_attempt_per_event');
mp01eOk($signal->signals===[],'no_failure_signal_on_success');

$caseAppend=new Mp01eAppend();$caseSignal=new Mp01eSignal();$caseEmitter=new BoundedBestEffortAuditEmitter($caseAppend,new AuditWriterContextBridge(),$caseSignal,$scope);$caseIdentity=new CanonicalIdentityAuditProducer($caseEmitter,$hasher,new IdentityAuditReasonResolver());$caseSessions=new CanonicalSessionAuditProducer($caseEmitter);
$caseIdentity->passwordRecoveryRequested(mp01eRequest('AUTH_PASSWORD_RECOVERY','AUTH'),'missing@example.invalid','SUCCESS','USER_REQUEST');
$caseSessions->revoked(mp01eRequest('AUTH_SESSION_LIFECYCLE','SESSION',null),mp01eSystemActor($targetSession->value(),'SESSION'),$targetSession,'SUCCESS','SYSTEM_POLICY');
$typedPreauth=PreauthActorOptionalContext::normalUnknown($append->inputs[0]->targetType,$append->inputs[0]->targetId);

mp01eFocusedOk($append->contexts[0]->actorIdentityId==='UNKNOWN'&&$append->contexts[0]->actorType==='UNKNOWN'&&!$typedPreauth->authenticationFailure,'registration_preauth_unknown_not_auth_failure');
mp01eFocusedOk($append->inputs[5]->targetType==='AUTH_IDENTIFIER_HMAC','recovery_existing_hmac_target');
mp01eFocusedOk($caseAppend->inputs[0]->targetType==='AUTH_IDENTIFIER_HMAC','recovery_nonexistent_hmac_target');
mp01eFocusedOk(preg_match('/^auth-id-v1:[a-f0-9]{64}$/D',$append->inputs[5]->targetId)===1&&preg_match('/^auth-id-v1:[a-f0-9]{64}$/D',$caseAppend->inputs[0]->targetId)===1,'recovery_same_versioned_hmac_scheme');
mp01eFocusedOk(!str_contains(json_encode([$append->inputs[0],$append->inputs[5],$caseAppend->inputs[0]],JSON_THROW_ON_ERROR),'@example.invalid'),'raw_auth_identifier_absent');
mp01eFocusedOk($append->contexts[10]->actorIdentityId==='admin_7'&&$append->inputs[10]->targetId===$targetSession->value()&&$sessionReq->sessionId!==$targetSession->value(),'admin_session_a_revokes_session_b');
mp01eFocusedOk($caseAppend->contexts[1]->actorType==='SYSTEM'&&$caseAppend->contexts[1]->sessionId===null&&$caseAppend->inputs[1]->targetId===$targetSession->value(),'system_null_request_session_revokes_b');
mp01eFocusedOk($append->contexts[11]->actorIdentityId==='account_42'&&$append->inputs[11]->targetId===$targetSession->value()&&$sessionReq->sessionId!==$targetSession->value(),'user_session_a_revokes_other_session_b');
mp01eFocusedOk(array_reduce(array_slice($append->inputs,8,4),fn($ok,$input)=>$ok&&$input->targetType==='SESSION'&&$input->targetId===$targetSession->value(),true),'single_session_targets_session');
mp01eFocusedOk($append->inputs[12]->targetType==='ACCOUNT'&&$append->inputs[12]->targetId==='account_42','logout_all_targets_account');
mp01eFocusedOk(count($append->inputs)-$beforeLogoutAll===1&&$append->inputs[12]->metadata===[],'logout_all_one_event_no_fanout_or_count');

mp01eOk($append->contexts[1]->actorIdentityId==='UNKNOWN'&&$append->contexts[1]->actorType==='UNKNOWN','email_sent_actor_optional_unknown');
mp01eOk($append->contexts[5]->actorIdentityId==='UNKNOWN'&&$append->contexts[5]->actorType==='UNKNOWN','recovery_actor_optional_unknown');
mp01eOk($typedPreauth->actorIdentityId==='UNKNOWN'&&$typedPreauth->actorType==='UNKNOWN'&&$typedPreauth->actorRole==='UNKNOWN'&&$typedPreauth->actorScope==='PRE_AUTH','typed_preauth_unknown_projection');

$failingAppend=new Mp01eAppend();$failingAppend->fail=true;$failureSignal=new Mp01eSignal();$failureEmitter=new BoundedBestEffortAuditEmitter($failingAppend,new AuditWriterContextBridge(),$failureSignal,$scope);$failureProducer=new CanonicalIdentityAuditProducer($failureEmitter,$hasher,new IdentityAuditReasonResolver());
$failed=$failureProducer->loginFailed(mp01eRequest('AUTH_LOGIN','AUTH'),'hidden@example.invalid','account_not_active');
mp01eOk($failed->status===AuditProducerEmissionResult::AUDIT_FAILED_SIGNALLED,'bounded_best_effort_status');
mp01eOk($failed->domainOutcomePreserved&&!$failed->auditSucceeded&&$failed->hardFailureSignalSucceeded,'domain_truth_preserved');
mp01eOk($failingAppend->attempts===1,'no_automatic_retry');
mp01eOk(count($failureSignal->signals)===1,'hard_signal_once');
mp01eOk(array_keys($failureSignal->signals[0]->safePayload())===['request_id','correlation_id','event_type','failure_classification'],'failure_signal_safe_fields_only');
mp01eOk(!str_contains(json_encode($failureSignal->signals[0]->safePayload(),JSON_THROW_ON_ERROR),'hidden@example.invalid'),'failure_signal_no_identifier');
$failureSignal->fail=true;$failedAgain=$failureProducer->loginFailed(mp01eRequest('AUTH_LOGIN','AUTH'),'hidden2@example.invalid','invalid_credentials');
mp01eOk($failedAgain->status===AuditProducerEmissionResult::AUDIT_AND_SIGNAL_FAILED&&$failedAgain->domainOutcomePreserved,'signal_failure_explicit');
mp01eOk($failingAppend->attempts===2,'signal_failure_no_retry_loop');

mp01eNo(fn()=>$identity->emailVerificationSent(mp01eRequest('AUTH_EMAIL_VERIFICATION','AUTH'),$account,false,'USER_REQUEST'),'sent_before_adapter_acceptance');
mp01eNo(fn()=>(new HmacSha256AuthIdentifierAuditHasher(new Mp01eSecret('audit-ip')))->hashCanonicalIdentifier('x'),'ip_hmac_namespace_reuse');
mp01eNo(fn()=>(new HmacSha256AuthIdentifierAuditHasher(new Mp01eSecret('audit-auth-identifier','auth-id-v1','short')))->hashCanonicalIdentifier('x'),'short_hmac_secret');
mp01eNo(fn()=>(new HmacSha256AuthIdentifierAuditHasher(new Mp01eSecret('audit-auth-identifier','')))->hashCanonicalIdentifier('x'),'missing_hmac_version');
mp01eNo(fn()=>$hasher->hashCanonicalIdentifier(' raw@example.invalid '),'noncanonical_identifier');
mp01eNo(fn()=>TrustedIdentityId::fromAuthoritativeOutcome('raw@example.invalid'),'raw_email_as_identity_target');
mp01eNo(fn()=>VerifiedAccountId::fromValidatedTokenResolution('token:secret'),'token_as_verified_account');
mp01eNo(fn()=>PasswordChangedFieldSet::passwordOnly()->names=['other'],'immutable_field_set');
mp01eNo(fn()=>new PasswordChangedFieldSet(['password','token']),'arbitrary_changed_fields');
mp01eNo(fn()=>$identity->loginFailed(mp01eRequest('AUTH_LOGIN','AUTH'),'x','unmapped'),'unknown_login_reason');
mp01eNo(fn()=>$identity->loginFailed(mp01eRequest('AUTH_PASSWORD_RECOVERY','AUTH'),'x','invalid_credentials'),'operation_mismatch');
mp01eNo(fn()=>$identity->loginFailed(mp01eRequest('AUTH_LOGIN','SESSION'),'x','invalid_credentials'),'source_module_mismatch');
mp01eNo(fn()=>$emitter->emit(new CanonicalAuditEventInput('PROFILE_CLAIM_REQUESTED','SUCCESS','USER_REQUEST',null,null,'ACCOUNT','account_42',[]),mp01eRequest('PROFILE_CLAIM','PROFILE'),$accountActor),'event_14_or_other_scope');
mp01eNo(fn()=>new SessionId('raw-cookie-secret'),'raw_cookie_not_safe_session_id');
mp01eNo(fn()=>new AuditProducerFailureSignal('r','c','AUTH_LOGIN_FAILED','raw failure message'),'unsafe_failure_classification');
mp01eNo(fn()=>$emitter->emit(new CanonicalAuditEventInput('AUTH_LOGIN_SUCCEEDED','SUCCESS','USER_REQUEST',null,null,'ACCOUNT','account_42',[]),mp01eRequest('AUTH_LOGIN','AUTH'),mp01eActor('different_account')),'actor_target_mismatch');
mp01eNo(fn()=>TrustedIdentityId::fromAuthoritativeOutcome('account 42'),'invalid_identity_whitespace');
mp01eNo(fn()=>VerifiedAccountId::fromValidatedTokenResolution(''),'missing_verified_account');
mp01eNo(fn()=>$sessions->created($sessionReq,$accountActor,$targetSession,'USER_REQUEST'),'single_session_account_target_actor_rejected');
mp01eNo(fn()=>$sessions->logoutAll($sessionReq,$sessionActor,$account,'SUCCESS','USER_REQUEST'),'logout_all_session_target_actor_rejected');
mp01eNo(fn()=>PreauthActorOptionalContext::normalUnknown('SESSION',$targetSession->value()),'preauth_session_target_rejected');
mp01eNo(fn()=>$emitter->emitActorOptionalPreauth(new CanonicalAuditEventInput('AUTH_LOGIN_FAILED','FAILURE','INVALID_CREDENTIALS',null,null,'AUTH_IDENTIFIER_HMAC',$append->inputs[4]->targetId,[]),mp01eRequest('AUTH_LOGIN','AUTH'),PreauthActorOptionalContext::normalUnknown('AUTH_IDENTIFIER_HMAC',$append->inputs[4]->targetId)),'login_failed_not_normal_preauth');
mp01eNo(fn()=>PreauthActorOptionalContext::normalUnknown('ACCOUNT',''),'preauth_missing_target');
mp01eNo(fn()=>$emitter->emitActorOptionalPreauth(new CanonicalAuditEventInput('AUTH_REGISTRATION_REQUESTED','SUCCESS','USER_REQUEST','ACCOUNT','account_42','AUTH_IDENTIFIER_HMAC',$append->inputs[0]->targetId,[]),mp01eRequest('AUTH_REGISTRATION','AUTH'),$typedPreauth),'preauth_effective_entity_rejected');
mp01eNo(fn()=>$emitter->emitActorOptionalPreauth(new CanonicalAuditEventInput('AUTH_REGISTRATION_REQUESTED','SUCCESS','USER_REQUEST',null,null,'AUTH_IDENTIFIER_HMAC',$append->inputs[5]->targetId,[]),mp01eRequest('AUTH_REGISTRATION','AUTH'),$typedPreauth),'preauth_target_mismatch');
mp01eNo(fn()=>$identity->registrationRequested(mp01eRequest('AUTH_REGISTRATION','AUTH'),' raw@example.invalid ','SUCCESS','USER_REQUEST'),'registration_noncanonical_identifier');
mp01eNo(fn()=>$identity->passwordRecoveryRequested(mp01eRequest('AUTH_PASSWORD_RECOVERY','AUTH'),' raw@example.invalid ','SUCCESS','USER_REQUEST'),'recovery_noncanonical_identifier');

echo 'FOCUSED_REQUIRED_CASES_TOTAL='.$focusedTotal."\n";
echo 'FOCUSED_REQUIRED_CASES_PASS='.$focusedPass.'/'.$focusedTotal."\n";
echo 'UNIT_TESTS_TOTAL='.$unitTotal."\n";
echo 'UNIT_TESTS_PASS='.$unitPass.'/'.$unitTotal."\n";
echo 'UNIT_TESTS_FAIL='.($unitTotal-$unitPass)."\n";
echo 'NEGATIVES_TOTAL='.$negativeTotal."\n";
echo 'NEGATIVES_BLOCKED='.$negativePass.'/'.$negativeTotal."\n";
