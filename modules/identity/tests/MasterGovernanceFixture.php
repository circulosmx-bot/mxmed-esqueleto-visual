<?php
declare(strict_types=1);
// TEST ONLY. CLI and fresh owned instance guards precede the synthetic review adapter.
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/../http/IdentityHttpComposition.php';
\Identity\Http\IdentityHttpComposition::registerAutoloader();
use Identity\Http\{IdentityHttpComposition,CanonicalHttpSessionResolver,GovernanceDeploymentAuthorization};
use Identity\Services\{GovernanceSecurityContext,StaffMfaService,GovernanceRuntime,InternalGovernanceService,GovernanceStaffMfaCeremony};
use Identity\MasterGovernance\Composition;
use Identity\Contracts\{Clock,StaffIdentityReviewPort,StaffIdentityReviewSnapshot,MfaSecretGeneratorPort};
$owned=realpath((string)getenv('ASE_INT02A_OWNED'));
if(!$owned||!preg_match('~^/private/tmp/mxmed-ase-int03b(?:ui)?-[a-zA-Z0-9_]+$~D',$owned)||!preg_match('/^mxmed_gate4d_preview_aseint03b(?:ui)?_[a-f0-9]{10}$/D',(string)getenv('MXMED_DB_NAME'))||in_array((int)getenv('MXMED_DB_PORT'),[3306,3309],true))throw new RuntimeException('test_owned_target_required');
$identity=IdentityHttpComposition::fromProcessEnvironment();$p=$identity->pdo();
if(realpath((string)$p->query('SELECT @@datadir')->fetchColumn())!==$owned.'/mysql')throw new RuntimeException('test_datadir_mismatch');
$input=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);
$clock=new class((int)($input['time']??time())) implements Clock{public function __construct(private int $time){}public function now():DateTimeImmutable{return (new DateTimeImmutable('@'.$this->time))->setTimezone(new DateTimeZone('UTC'));}};
// No runtime factory imports this adapter; only this guarded CLI fixture constructs it.
$reviews=new class implements StaffIdentityReviewPort{
 public function currentApproved(PDO $p,string $accountId,int $version):StaffIdentityReviewSnapshot{
  if(!$p->inTransaction()||!str_starts_with($accountId,'qa_'))throw new RuntimeException('test_review_denied');
  $q=$p->prepare('SELECT * FROM ase_master_test_reviews WHERE account_id=? FOR UPDATE');$q->execute([$accountId]);$r=$q->fetch(PDO::FETCH_ASSOC);
  if(!$r||$r['status']!=='APPROVED'||(int)$r['credential_version']!==$version)throw new RuntimeException('test_review_denied');
  return new StaffIdentityReviewSnapshot($r['bound_account_id'],(int)$r['credential_version'],$r['review_version']);
 }
};
$security=new GovernanceSecurityContext($identity->sessions(),$identity->authentication(),$clock);
$secret=file_get_contents($owned.'/mfa-secret');
$generator=new class($secret) implements MfaSecretGeneratorPort{public function __construct(private string $secret){}public function generate():string{return $this->secret;}};
$mfa=new StaffMfaService($p,(string)getenv('MXMED_STAFF_MFA_ENCRYPTION_KEY'),(string)getenv('MXMED_STAFF_MFA_KEY_ID'),$generator,$clock,$security);
$cases=GovernanceRuntime::staff($p,$security,$mfa,($input['product_eligibility']??false)?new \Identity\Adapters\UnavailableStaffIdentityReview():$reviews,$clock);
$resolver=new CanonicalHttpSessionResolver($identity->sessions());$writer=new InternalGovernanceService($p,$resolver,$cases);
$tokens=json_decode(file_get_contents($owned.'/sessions.json'),true,32,JSON_THROW_ON_ERROR);$actorName=$input['actor']??'director';$cookies=['__Host-mxmed_session'=>$tokens[$actorName]??''];
try{
 $actor=$resolver->resolve($cookies,true);if(!$actor)throw new RuntimeException('fixture_session_denied');$action=$input['action'];$password=trim(file_get_contents($owned.'/fixture-password'));
 $directMfa=function(bool $consume)use($p,$mfa,$security,$actor,$input,$password,$actorName){
  $p->beginTransaction();try{
   $case=\Identity\MasterGovernance\Policy::row($p,'SELECT * FROM auth_governance_cases WHERE case_id=?',[$input['case_id']]);
   $ctx=\Identity\MasterGovernance\Policy::context($case);$i=$ctx['intent'];
   if($consume)return $mfa->consumeProof($p,$input['proof_id'],$input['proof'],$actor->accountId(),(string)$actor->session()->sessionId(),$actor->principal()->credentialVersion(),$i['operation'],$case['case_id'],$i['target'],$i['target'],null,$i['operation_id']);
   return $mfa->issueChallenge($actor->accountId(),$i['operation'],$case['case_id'],$i['target'],$i['target'],null,$security->reauthenticate($actor,'qa_'.$actorName.'@example.test',$password,[])+['master_intent_digest'=>$ctx['digest']]);
  }finally{if($p->inTransaction())$p->rollBack();}
 };
 $result=match($action){
  'open'=>$cases->open($actor,$input['operation'],$input['target'],$input['capability']??null,'Synthetic exact review'),
  'confirm'=>$cases->confirm($actor,$input['case_id'],$input['intent_digest'],'qa_'.$actorName.'@example.test',($input['bad_password']??false)?'invalid':$password,[]),
  'verify'=>$cases->verify($actor,$input['case_id'],$input['challenge_id'],$input['challenge'],$input['code']??StaffMfaService::totp($secret,intdiv($clock->now()->getTimestamp(),30))),
  'execute'=>$writer->executePrivileged($cookies,$input['case_id'],$input['proof_id'],$input['proof']),
  'read'=>$cases->read($actor,$input['case_id']),
  'cancel'=>$cases->close($actor,$input['case_id']),
  'expire'=>$cases->close($actor,$input['case_id'],true),
  'direct_issue'=>$directMfa(false),
  'direct_consume'=>$directMfa(true),
  'historical_mfa'=>(function()use($p,$actor,$input,$security,$password,$actorName,$mfa,$clock,$secret){
   $op=$input['operation'];$id='legacy_'.bin2hex(random_bytes(16));$uuid=(new \Platform\Services\RandomAuditUuidProvider())->generateCanonicalUuid();
   $q=$p->prepare("INSERT INTO auth_governance_cases(case_id,operation_id,operation,requester_account_id,beneficiary_account_id,reason,status,created_at,expires_at) VALUES(?,?,?,?,?,'Synthetic primitive regression','OPEN',?,?)");
   $q->execute([$id,$uuid,$op,$actor->accountId(),'qa_master',$clock->now()->format('Y-m-d H:i:s.u'),$clock->now()->modify('+5 minutes')->format('Y-m-d H:i:s.u')]);
   $ra=$security->reauthenticate($actor,'qa_'.$actorName.'@example.test',$password,[]);
   $ch=$mfa->issueChallenge($actor->accountId(),$op,$id,'qa_master','qa_master',null,$ra);
   $proof=$mfa->verifyChallenge($actor->accountId(),(string)$actor->session()->sessionId(),$actor->principal()->credentialVersion(),$ch['challenge_id'],$ch['challenge'],StaffMfaService::totp($secret,intdiv($clock->now()->getTimestamp(),30)));
   $p->beginTransaction();try{$mfa->consumeProof($p,$proof['proof_id'],$proof['proof'],$actor->accountId(),(string)$actor->session()->sessionId(),$actor->principal()->credentialVersion(),$op,$id,'qa_master','qa_master',null,$uuid);$p->commit();}catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
   return ['operation'=>$op,'consumed'=>true];
  })(),
  'logout'=>(function()use($identity,$cookies){$identity->sessions()->logout($cookies['__Host-mxmed_session']);return ['logged_out'=>true];})(),
  'direct_verify'=>$mfa->verifyChallenge($actor->accountId(),(string)$actor->session()->sessionId(),$actor->principal()->credentialVersion(),$input['challenge_id'],$input['challenge'],$input['code']??StaffMfaService::totp($secret,intdiv($clock->now()->getTimestamp(),30))),
  'enroll'=>(function()use($p,$mfa,$security,$clock,$actor,$resolver,$tokens,$password,$secret){
   $ceremony=new GovernanceStaffMfaCeremony($p,$mfa,$security,new \Identity\Adapters\CanonicalGovernanceAuditAdapter($p),$clock,['qa_master']);
   $registrar=$resolver->resolve(['__Host-mxmed_session'=>$tokens['master']]);
   $r=$ceremony->begin($actor,$registrar,'qa_director@example.test',$password,'qa_master@example.test',$password);
   $ceremony->activate($actor,$r['factor_id'],StaffMfaService::totp($secret,intdiv($clock->now()->getTimestamp(),30)));return ['factor_id'=>$r['factor_id']];
  })(),
  'deployment'=>(function()use($identity,$p,$owned){
   $r=['contract_version'=>2,'state'=>'approved_for_governance','authorization_reference'=>'synthetic-master-only','allowed_actions'=>['master_governance'],'target_account_ids'=>[],'expires_at'=>(new DateTimeImmutable('+2 hours'))->format(DATE_ATOM),...\Identity\Operators\OperatorDeploymentSchema::identity($p),'schema_fingerprint'=>\Identity\Operators\OperatorDeploymentSchema::fingerprint($p),'source_hash'=>GovernanceDeploymentAuthorization::sourceHash(),'master_source_sha256'=>Composition::sourceHash(),'organization_id'=>getenv('MXMED_GOVERNANCE_ORGANIZATION_ID'),'environment'=>$identity->environment(),'trust_key_sha256'=>hash('sha256',(string)getenv('MXMED_GOVERNANCE_TRUST_ANCHOR_PUBLIC_KEY')),'mfa_key_id'=>getenv('MXMED_STAFF_MFA_KEY_ID'),'mfa_key_sha256'=>hash('sha256',(string)getenv('MXMED_STAFF_MFA_ENCRYPTION_KEY'))];
   $bytes=json_encode($r,JSON_THROW_ON_ERROR);file_put_contents($owned.'/master-deployment.json',$bytes);chmod($owned.'/master-deployment.json',0600);return ['pin'=>hash('sha256',$bytes)];
  })(),
  default=>throw new RuntimeException('fixture_action_invalid'),
 };
 echo json_encode(['ok'=>true,'data'=>$result],JSON_THROW_ON_ERROR);
}catch(Throwable $e){echo json_encode(['ok'=>false,'error'=>$e instanceof PDOException?'fixture_sql_failure':$e->getMessage()],JSON_THROW_ON_ERROR);if($e instanceof PDOException)file_put_contents($owned.'/master-sql-error.log',$e->getMessage());}
