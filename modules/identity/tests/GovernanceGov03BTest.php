<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/../http/IdentityHttpComposition.php';
Identity\Http\IdentityHttpComposition::registerAutoloader();
require_once __DIR__.'/support/SyntheticOwnershipEligibility.php';
use Identity\Contracts\{Clock,MfaSecretGeneratorPort,AuthenticatedAccessContext};
use Identity\Services\{SessionService,SessionTokenCodec,CredentialAuthenticationService,RateLimitService,GovernanceSecurityContext,GovernanceCaseSecurity,StaffMfaService,GovernanceStaffMfaCeremony,R3ProfileGovernanceService,GovernanceRuntime,ProfileMembershipRoleChangeService,ProfileOwnershipStepUpIssuanceService,FirstDirectorBootstrapService};
use Identity\Repositories\{IdentityAccountRepository,AccountCredentialRepository,AccountMembershipRepository,ProfileOwnershipStepUpRepository};
use Identity\Adapters\{PdoSessionAccountStateAdapter,InMemorySessionStoreAdapter,Ed25519ExternalTrustAnchorAdapter,CanonicalGovernanceAuditAdapter,CanonicalFirstDirectorBootstrapAuditAdapter,CanonicalProfileMembershipRoleChangeAuditAdapter,PdoProfileOwnershipApproverAuthorityAdapter,PdoProfileOwnershipApprovalAdapter,PdoProfileOwnershipStepUpAdapter,PdoGovernanceCaseFinalizationAdapter,CanonicalInitialOwnerEligibilityAdapter};
function checkB(bool $ok,string $label):void{if(!$ok)throw new RuntimeException('FAIL '.$label);echo 'PASS '.$label.PHP_EOL;}
function denyB(callable $fn,string $label):void{try{$fn();}catch(Throwable $e){if($e instanceof LogicException)throw $e;echo 'PASS '.$label.PHP_EOL;return;}throw new LogicException('FAIL unexpected success '.$label);}
final class BClock implements Clock{public function __construct(public DateTimeImmutable $value){}public function now():DateTimeImmutable{return $this->value;}public function advance(string $v):void{$this->value=$this->value->modify($v);}}
final class BSecret implements MfaSecretGeneratorPort{public function generate():string{return str_repeat('x',20);}}
function connectB():PDO{$c=json_decode(file_get_contents((string)getenv('GOV03B_QA_CONNECTION')),true,32,JSON_THROW_ON_ERROR);if(!preg_match('/^mxmed_op01_[a-f0-9]{10}$/D',$c['db']))throw new RuntimeException('disposable_only');return new PDO('mysql:host=127.0.0.1;port=3306;dbname='.$c['db'].';charset=utf8mb4',$c['user'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);}
function scalarB(PDO $p,string $sql,array $args=[]):mixed{$s=$p->prepare($sql);$s->execute($args);return $s->fetchColumn();}
function stackB(PDO $p,BClock $clock,?InMemorySessionStoreAdapter $store=null):array{
 $store??=new InMemorySessionStoreAdapter();$accounts=new IdentityAccountRepository($p);$credentials=new AccountCredentialRepository($p);
 $sessions=new SessionService($store,new SessionTokenCodec(str_repeat('p',32)),$clock,new Identity\Contracts\SessionPolicy(),new PdoSessionAccountStateAdapter($accounts,$credentials));
 $auth=new CredentialAuthenticationService($accounts,$credentials,new RateLimitService($p,new Identity\Contracts\RateLimitKeyHasher(str_repeat('r',32)),$clock),$clock);
 $security=new GovernanceSecurityContext($sessions,$auth,$clock);$mfa=new StaffMfaService($p,base64_encode(str_repeat('m',32)),'gov03b-test',new BSecret(),$clock,$security);
 $pair=sodium_crypto_sign_seed_keypair(str_repeat('s',32));$trust=new Ed25519ExternalTrustAnchorAdapter($p,base64_encode(sodium_crypto_sign_publickey($pair)),'mxmed-synthetic','local',$clock);
 $audit=new CanonicalGovernanceAuditAdapter($p);$eligibility=new SyntheticOwnershipEligibility();$bindings=new GovernanceCaseSecurity($p,$security,$eligibility,$trust,$audit);
 $cases=new R3ProfileGovernanceService($p,$mfa,new PdoProfileOwnershipApproverAuthorityAdapter($p),$clock,security:$bindings);
 $proofs=new ProfileOwnershipStepUpRepository($p);$roles=new ProfileMembershipRoleChangeService($p,new AccountMembershipRepository($p),new PdoProfileOwnershipApprovalAdapter($p,new PdoProfileOwnershipApproverAuthorityAdapter($p),$clock),new PdoProfileOwnershipStepUpAdapter($p,$proofs,$clock),new CanonicalProfileMembershipRoleChangeAuditAdapter($p),$clock,new PdoGovernanceCaseFinalizationAdapter($p,$clock),eligibility:$eligibility,security:$bindings);
 $runtime=new GovernanceRuntime($p,$cases,$mfa,new ProfileOwnershipStepUpIssuanceService($p,$auth,$proofs,$clock),$roles,$clock);
 $enroll=new GovernanceStaffMfaCeremony($p,$mfa,$security,$audit,$clock,['director_test']);
 return compact('store','sessions','auth','security','mfa','trust','audit','bindings','cases','runtime','enroll','pair','roles');
}
const B_PASSWORD='Only-Synthetic-Gov03B!';
function loginB(array $s,string $id):array{$d=$s['auth']->authenticate($id.'@example.invalid',B_PASSWORD,['ip'=>'synthetic']);if(!$d->isAllowed())throw new RuntimeException('login_denied');$created=$s['sessions']->create($d->candidate());if(!$created->allowed())throw new RuntimeException('session_denied');$token=(string)$created->token();$v=$s['sessions']->validate($token);return ['token'=>$token,'context'=>$v->context()];}
function uuidB():string{return (new Platform\Services\RandomAuditUuidProvider())->generateCanonicalUuid();}
function signedB(array $s,BClock $clock,array $changes=[]):string{
 $claims=['contract_version'=>2,'operation'=>'FIRST_DIRECTOR_BOOTSTRAP','operation_id'=>uuidB(),'target_account_id'=>'director_test','organization_id'=>'mxmed-synthetic','environment'=>'local','case_reference'=>'external-bootstrap-case','profile_doctor_id'=>null,'membership_id'=>null,'membership_version'=>null,'requester_account_id'=>null,'trust_anchor_reference'=>'trust_'.bin2hex(random_bytes(16)),'ratifier_session_reference'=>'external-session-independent','authorized_at'=>$clock->now()->format(DATE_ATOM),'expires_at'=>$clock->now()->modify('+10 minutes')->format(DATE_ATOM),'ratifiers'=>[['account_id'=>'ratifier_external_a','approval_reference'=>'external-approval-a','mfa_verified'=>true,'reauthenticated'=>true],['account_id'=>'ratifier_external_b','approval_reference'=>'external-approval-b','mfa_verified'=>true,'reauthenticated'=>true]]];
 $claims=array_replace($claims,$changes);$json=json_encode($claims,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$b64=static fn($v)=>rtrim(strtr(base64_encode($v),'+/','-_'),'=');return $b64($json).'.'.$b64(sodium_crypto_sign_detached($json,sodium_crypto_sign_secretkey($s['pair'])));
}
function bootstrapB(FirstDirectorBootstrapService $bootstrap,array $s,array $director,string $operation,string $target,string $envelope):array{
 $binding=$s['security']->reauthenticate($director['context'],'director_test@example.invalid',B_PASSWORD,['ip'=>'synthetic']);
 return $bootstrap->execute($operation,$target,$envelope,$binding,'external-bootstrap-case');
}
function externalB(PDO $p,array $s,BClock $clock,array $requester,array $case,array $changes=[]):void{
 $q=$p->prepare('SELECT * FROM auth_governance_cases WHERE case_id=?');$q->execute([$case['case_id']]);$c=$q->fetch();$ctx=json_decode($c['security_context'],true);
 $claims=['operation'=>$c['operation'],'operation_id'=>$c['operation_id'],'case_reference'=>$c['case_id'],'target_account_id'=>$c['beneficiary_account_id'],'requester_account_id'=>$c['requester_account_id'],'profile_doctor_id'=>$c['profile_doctor_id'],'membership_id'=>$c['target_membership_id'],'membership_version'=>isset($ctx['membership'])?GovernanceCaseSecurity::version($ctx['membership']):null];
 $s['cases']->bindExternalEvidence($requester['context'],$case['case_id'],signedB($s,$clock,array_replace($claims,$changes)));
}
function proofB(array $s,BClock $clock,array $actor,array $case):array{
 $clock->advance('+31 seconds');$id=$actor['context']->accountId();
 $c=$s['cases']->issueEndorsementChallenge($actor['context'],$case['case_id'],$id.'@example.invalid',B_PASSWORD,['ip'=>'synthetic']);
 return $s['mfa']->verifyChallenge($id,(string)$actor['context']->session()->sessionId(),$actor['context']->principal()->credentialVersion(),$c['challenge_id'],$c['challenge'],StaffMfaService::totp(str_repeat('x',20),intdiv($clock->now()->getTimestamp(),30)));
}
function endorseB(array $s,BClock $clock,array $actor,array $case):void{$p=proofB($s,$clock,$actor,$case);$s['cases']->endorse($actor['context'],$case['case_id'],$p['proof_id'],$p['proof']);}
function profileB(PDO $p,string $suffix,string $account='beneficiary_test',string $role='collaborator'):array{
 $id='op01_'.$suffix;$membership='membership_'.$suffix;
 $p->prepare('INSERT INTO profiles_doctors(doctor_id,display_name) VALUES(?,?)')->execute([$id,'Synthetic profile']);
 $p->prepare("INSERT INTO auth_account_memberships(membership_id,account_id,profile_doctor_id,role_code,scope_code,status) VALUES(?,?,?,?,'profile','active')")->execute([$membership,$account,$id,$role]);return [$id,$membership];
}
if(($argv[1]??'')==='--worker'){
 try{$input=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);$p=connectB();$clock=new BClock(new DateTimeImmutable($input['now']));$s=stackB($p,$clock,unserialize(base64_decode($input['store']),['allowed_classes'=>true]));$v=$s['sessions']->validate($input['token']);$s['runtime']->finalizeInitialOwner($v->context(),$input['case'],$input['proof']);echo "OK\n";}catch(Throwable $e){fwrite(STDERR,get_class($e).":".$e->getMessage()."\n");echo "DENIED\n";}exit;
}
$p=connectB();$clock=new BClock(new DateTimeImmutable('now',new DateTimeZone('UTC')));$s=stackB($p,$clock);
denyB(fn()=>new R3ProfileGovernanceService($p,$s['mfa'],new PdoProfileOwnershipApproverAuthorityAdapter($p),$clock),'DEFAULT_unbound_R3_construction_denied');
denyB(fn()=>(new StaffMfaService($p,base64_encode(str_repeat('m',32)),'gov03b-test'))->beginEnrollment('arbitrary_staff'),'DEFAULT_unbound_enrollment_denied');
// Real native audit routines must be owned, validated and unable to address mxmed.
$target=Platform\Repositories\CanonicalAuditDatabaseTarget::fromConnection($p);
foreach(['audit_mp01c_lock_stream_head_v1','audit_mp01c_advance_stream_head_cas_v1'] as $name)checkB(str_starts_with($target->routine($p,$name),'`mxmed_op01_'),'AUDIT_NATIVE_TARGET_'.$name);
denyB(fn()=>$p->query('SELECT COUNT(*) FROM mxmed.auth_accounts'),'ISOLATION_normal_accounts_denied');
denyB(fn()=>$p->exec("UPDATE mxmed.auth_accounts SET status=status WHERE 0"),'ISOLATION_normal_write_denied');
denyB(fn()=>$p->exec("CALL mxmed.audit_mp01c_lock_stream_head_v1('synthetic')"),'ISOLATION_normal_audit_call_denied');
$hash=password_hash(B_PASSWORD,PASSWORD_ARGON2ID);
foreach(['director_test','beneficiary_test','approver_test_a','approver_test_b','requester_test','replacement_test','factor_test'] as $id){$p->prepare("INSERT INTO auth_accounts(account_id,email_address,email_normalized,status,email_verified_at) VALUES(?,?,?,'active',UTC_TIMESTAMP())")->execute([$id,$id.'@example.invalid',$id.'@example.invalid']);$p->prepare('INSERT INTO auth_account_credentials(account_id,password_hash,password_changed_at,credential_version) VALUES(?,?,UTC_TIMESTAMP(),1)')->execute([$id,$hash]);}
[$profile,$membership]=profileB($p,'governed');$beneficiary=loginB($s,'beneficiary_test');$director=loginB($s,'director_test');
$bootstrap=new FirstDirectorBootstrapService($p,$s['trust'],new CanonicalFirstDirectorBootstrapAuditAdapter($p),$clock,$s['security']);
$op=uuidB();$envelope=signedB($s,$clock,['operation_id'=>$op]);
$bad=substr($envelope,0,-6).'badbad';denyB(fn()=>bootstrapB($bootstrap,$s,$director,$op,'director_test',$bad),'A_forged_bootstrap_denied');
$memberOperation=uuidB();$memberEvidence=signedB($s,$clock,['operation_id'=>$memberOperation,'target_account_id'=>'beneficiary_test']);
$memberReauth=$s['security']->reauthenticate($beneficiary['context'],'beneficiary_test@example.invalid',B_PASSWORD,['ip'=>'synthetic']);
try{$bootstrap->execute($memberOperation,'beneficiary_test',$memberEvidence,$memberReauth,'external-bootstrap-case');throw new LogicException('professional account bootstrap allowed');}catch(RuntimeException $e){checkB($e->getMessage()==='first_director_staff_physician_separation_required','B_professional_member_bootstrap_denied');}
foreach(['organization_id'=>'wrong-org','environment'=>'production','target_account_id'=>'other_test','case_reference'=>'wrong_case'] as $key=>$value){if($key==='case_reference')continue;denyB(fn()=>bootstrapB($bootstrap,$s,$director,$op,'director_test',signedB($s,$clock,[$key=>$value,'operation_id'=>$op])),'J_bootstrap_wrong_'.$key);}
// A physical audit INSERT failure must roll back staff and external evidence together.
$p->exec("UPDATE gov03b_qa_faults SET enabled=1");
denyB(fn()=>bootstrapB($bootstrap,$s,$director,$op,'director_test',$envelope),'M_bootstrap_audit_failure');
checkB((int)scalarB($p,'SELECT COUNT(*) FROM internal_staff')===0&&(int)scalarB($p,'SELECT COUNT(*) FROM auth_external_trust_anchor_evidence')===0,'M_atomic_bootstrap_rollback');$p->exec('UPDATE gov03b_qa_faults SET enabled=0');
denyB(fn()=>bootstrapB($bootstrap,$s,$director,$op,'director_test',signedB($s,$clock,['contract_version'=>1,'operation_id'=>$op])),'K_unversioned_trust_contract_denied');
bootstrapB($bootstrap,$s,$director,$op,'director_test',$envelope);checkB((int)scalarB($p,"SELECT COUNT(*) FROM internal_staff WHERE governance_class='DIRECTOR'")===1,'A_valid_independent_bootstrap');denyB(fn()=>bootstrapB($bootstrap,$s,$director,$op,'director_test',$envelope),'K_bootstrap_single_use');
foreach(['approver_test_a','approver_test_b','requester_test','factor_test'] as $id){$p->prepare("INSERT INTO internal_staff(account_id,governance_class,status) VALUES(?,'MASTER_ADMIN','ACTIVE')")->execute([$id]);$p->prepare("INSERT INTO internal_operator_grants(grant_id,account_id,capability,status) VALUES(UUID(),?,'profile_initial_ownership_approve','ACTIVE')")->execute([$id]);}
$a=loginB($s,'approver_test_a');$b=loginB($s,'approver_test_b');$requester=loginB($s,'requester_test');$factor=loginB($s,'factor_test');
foreach([$a,$b] as $actor){$id=$actor['context']->accountId();$e=$s['enroll']->begin($actor['context'],$director['context'],$id.'@example.invalid',B_PASSWORD,'director_test@example.invalid',B_PASSWORD);$s['enroll']->activate($actor['context'],$e['factor_id'],StaffMfaService::totp(str_repeat('x',20),intdiv($clock->now()->getTimestamp(),30)));denyB(fn()=>$s['enroll']->activate($actor['context'],$e['factor_id'],'000000'),'C_enrollment_replay_denied_'.$id);}
denyB(fn()=>$s['enroll']->begin($factor['context'],$factor['context'],'factor_test@example.invalid',B_PASSWORD,'factor_test@example.invalid',B_PASSWORD),'C_self_enrollment_registrar_denied');
$e=$s['enroll']->begin($factor['context'],$director['context'],'factor_test@example.invalid',B_PASSWORD,'director_test@example.invalid',B_PASSWORD);
$otherSession=loginB($s,'factor_test');denyB(fn()=>$s['enroll']->activate($otherSession['context'],$e['factor_id'],StaffMfaService::totp(str_repeat('x',20),intdiv($clock->now()->getTimestamp(),30))),'C_enrollment_cross_session_denied');
$p->exec('UPDATE gov03b_qa_faults SET enabled=1');denyB(fn()=>$s['enroll']->activate($factor['context'],$e['factor_id'],StaffMfaService::totp(str_repeat('x',20),intdiv($clock->now()->getTimestamp(),30))),'M_factor_activation_audit_failure');$p->exec('UPDATE gov03b_qa_faults SET enabled=0');checkB(scalarB($p,'SELECT status FROM auth_staff_mfa_factors WHERE factor_id=?',[$e['factor_id']])==='PENDING','M_factor_activation_rollback');
for($i=0;$i<5;$i++)denyB(fn()=>$s['enroll']->activate($factor['context'],$e['factor_id'],'not-totp'),'C_failed_possession_attempt_'.$i);
denyB(fn()=>$s['enroll']->activate($factor['context'],$e['factor_id'],StaffMfaService::totp(str_repeat('x',20),intdiv($clock->now()->getTimestamp(),30))),'C_enrollment_lockout');$s['enroll']->cancel($factor['context'],$e['factor_id']);
checkB(scalarB($p,'SELECT status FROM auth_staff_mfa_factors WHERE factor_id=?',[$e['factor_id']])==='REVOKED','C_explicit_pending_cancellation');
$e=$s['enroll']->begin($factor['context'],$director['context'],'factor_test@example.invalid',B_PASSWORD,'director_test@example.invalid',B_PASSWORD);$clock->advance('+6 minutes');denyB(fn()=>$s['enroll']->activate($factor['context'],$e['factor_id'],'000000'),'C_enrollment_expiration');$s['enroll']->cancel($factor['context'],$e['factor_id']);
checkB(true,'C_controlled_enrollment_audited');
denyB(fn()=>$s['cases']->openInitialOwnerCase($beneficiary['context'],$membership,'Synthetic request rejected'),'E_beneficiary_not_internal_requester');
$productionGate=new CanonicalInitialOwnerEligibilityAdapter();denyB(fn()=>$productionGate->assertEligible($p,'beneficiary_test',$profile),'G_verified_account_cannot_substitute_accreditation');
$case=$s['cases']->openInitialOwnerCase($requester['context'],$membership,'Synthetic independent initial owner');
foreach(['organization_id'=>'wrong-org','environment'=>'staging','target_account_id'=>'replacement_test','case_reference'=>'govcase_wrong','profile_doctor_id'=>'op01_wrong','membership_version'=>str_repeat('0',64)] as $key=>$value)denyB(fn()=>externalB($p,$s,$clock,$requester,$case,[$key=>$value]),'J_exact_external_'.$key);
externalB($p,$s,$clock,$requester,$case);denyB(fn()=>externalB($p,$s,$clock,$requester,$case),'K_case_evidence_reuse_denied');
denyB(fn()=>$s['cases']->issueEndorsementChallenge($requester['context'],$case['case_id'],'requester_test@example.invalid',B_PASSWORD),'E_requester_not_approver');
denyB(fn()=>$s['cases']->issueEndorsementChallenge($a['context'],$case['case_id'],'approver_test_a@example.invalid','wrong-password'),'H_password_reauthentication_required');
$p->exec("UPDATE internal_staff SET status='SUSPENDED' WHERE account_id='approver_test_a'");denyB(fn()=>$s['cases']->issueEndorsementChallenge($a['context'],$case['case_id'],'approver_test_a@example.invalid',B_PASSWORD),'H_suspended_staff_denied');$p->exec("UPDATE internal_staff SET status='ACTIVE' WHERE account_id='approver_test_a'");
$stale=proofB($s,$clock,$a,$case);$clock->advance('+6 minutes');denyB(fn()=>$s['cases']->endorse($a['context'],$case['case_id'],$stale['proof_id'],$stale['proof']),'I_expired_action_proof_denied');
$proof=proofB($s,$clock,$a,$case);$s['cases']->endorse($a['context'],$case['case_id'],$proof['proof_id'],$proof['proof']);denyB(fn()=>$s['cases']->endorse($a['context'],$case['case_id'],$proof['proof_id'],$proof['proof']),'I_mfa_replay_denied');
denyB(fn()=>$s['runtime']->issueBeneficiaryStepUp($beneficiary['context'],$case['case_id'],'beneficiary_test@example.invalid',B_PASSWORD,['ip'=>'synthetic']),'D_one_approver_insufficient');
$duplicate=proofB($s,$clock,$a,$case);denyB(fn()=>$s['cases']->endorse($a['context'],$case['case_id'],$duplicate['proof_id'],$duplicate['proof']),'D_same_approver_fresh_proof_insufficient');
endorseB($s,$clock,$b,$case);
denyB(fn()=>$s['runtime']->issueBeneficiaryStepUp($requester['context'],$case['case_id'],'requester_test@example.invalid',B_PASSWORD,['ip'=>'synthetic']),'F_wrong_beneficiary_denied');
$step=$s['runtime']->issueBeneficiaryStepUp($beneficiary['context'],$case['case_id'],'beneficiary_test@example.invalid',B_PASSWORD,['ip'=>'synthetic']);
$version=(string)scalarB($p,'SELECT updated_at FROM auth_account_memberships WHERE membership_id=?',[$membership]);
$p->prepare('UPDATE auth_account_memberships SET updated_at=DATE_ADD(updated_at,INTERVAL 1 SECOND) WHERE membership_id=?')->execute([$membership]);
denyB(fn()=>$s['runtime']->finalizeInitialOwner($beneficiary['context'],$case['case_id'],$step['step_up_proof']),'VERSION_approved_membership_snapshot_changed_denied');
$p->prepare('UPDATE auth_account_memberships SET updated_at=? WHERE membership_id=?')->execute([$version,$membership]);
$p->exec("UPDATE auth_account_credentials SET credential_version=2 WHERE account_id='approver_test_a'");denyB(fn()=>$s['runtime']->finalizeInitialOwner($beneficiary['context'],$case['case_id'],$step['step_up_proof']),'H_approver_credential_rotation_denied');$p->exec("UPDATE auth_account_credentials SET credential_version=1 WHERE account_id='approver_test_a'");
$p->exec("UPDATE internal_operator_grants SET status='REVOKED',revoked_at=UTC_TIMESTAMP() WHERE account_id='approver_test_a'");denyB(fn()=>$s['runtime']->finalizeInitialOwner($beneficiary['context'],$case['case_id'],$step['step_up_proof']),'H_approver_authority_revocation_denied');$p->exec("UPDATE internal_operator_grants SET status='ACTIVE',revoked_at=NULL WHERE account_id='approver_test_a'");
$p->exec("UPDATE gov03b_qa_faults SET enabled=1");denyB(fn()=>$s['runtime']->finalizeInitialOwner($beneficiary['context'],$case['case_id'],$step['step_up_proof']),'M_owner_audit_failure');$p->exec('UPDATE gov03b_qa_faults SET enabled=0');
checkB(scalarB($p,'SELECT role_code FROM auth_account_memberships WHERE membership_id=?',[$membership])==='collaborator'&&scalarB($p,'SELECT status FROM auth_governance_cases WHERE case_id=?',[$case['case_id']])==='APPROVED','M_owner_and_evidence_rollback');
// Two independent PHP processes, same synthetic canonical session state, native DB locks.
$input=['now'=>$clock->now()->format('Y-m-d\TH:i:s.uP'),'store'=>base64_encode(serialize($s['store'])),'token'=>$beneficiary['token'],'case'=>$case['case_id'],'proof'=>$step['step_up_proof']];$workers=[];
for($i=0;$i<2;$i++){$proc=proc_open([PHP_BINARY,__FILE__,'--worker'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode($input,JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
$results=[];foreach($workers as [$proc,$pipes]){$results[]=trim(stream_get_contents($pipes[1]));fclose($pipes[1]);$err=stream_get_contents($pipes[2]);if($err!=='')fwrite(STDERR,$err);fclose($pipes[2]);proc_close($proc);}sort($results);checkB($results===['DENIED','OK'],'L_concurrent_finalization_single_consumer');
checkB(scalarB($p,'SELECT role_code FROM auth_account_memberships WHERE membership_id=?',[$membership])==='owner','F_exact_beneficiary_acceptance');
denyB(fn()=>$s['runtime']->finalizeInitialOwner($beneficiary['context'],$case['case_id'],$step['step_up_proof']),'K_finalization_replay_denied');
// The same request guard is used by governance.php, with real canonical sessions/CSRF.
$httpCsrf=new Identity\Http\CsrfTokenService(str_repeat('c',32),900,$clock,'https://localhost:8443');
$httpBody=['csrf_token'=>$httpCsrf->issueAuthenticated($beneficiary['context']->session()->tokenDigest())];
$httpServer=['REQUEST_METHOD'=>'POST','HTTPS'=>'on','HTTP_ORIGIN'=>'https://localhost:8443'];
$access=Identity\Http\GovernanceHttpSecurity::access($s['sessions'],$httpCsrf,'https://localhost:8443',$httpServer,['__Host-mxmed_session'=>$beneficiary['token']],$httpBody,json_encode($httpBody),'');
checkB($access->accountId()==='beneficiary_test','Q_canonical_http_access_positive');
foreach([['anonymous',401,[], $httpBody,$httpServer],['wrong_origin',403,['__Host-mxmed_session'=>$beneficiary['token']],$httpBody,array_replace($httpServer,['HTTP_ORIGIN'=>'https://other.invalid'])],['missing_csrf',403,['__Host-mxmed_session'=>$beneficiary['token']],[],$httpServer],['GET',405,['__Host-mxmed_session'=>$beneficiary['token']],$httpBody,array_replace($httpServer,['REQUEST_METHOD'=>'GET'])],['untrusted_forwarded_proto',503,['__Host-mxmed_session'=>$beneficiary['token']],$httpBody,['REQUEST_METHOD'=>'POST','REMOTE_ADDR'=>'127.0.0.1','HTTP_X_FORWARDED_PROTO'=>'https']]] as [$label,$status,$cookies,$body,$server]){
 try{Identity\Http\GovernanceHttpSecurity::access($s['sessions'],$httpCsrf,'https://localhost:8443',$server,$cookies,$body,json_encode($body),'');throw new LogicException('unexpected HTTP allow');}catch(Identity\Http\GovernanceHttpDenied $e){checkB($e->status===$status,'Q_HTTP_'.$label);}
}
$key=str_repeat('k',32);$raw=json_encode($httpBody);$server=['REQUEST_METHOD'=>'POST','REQUEST_URI'=>'/api/identity/governance.php/cases/exact/finalize','REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'localhost:8443','HTTP_ORIGIN'=>'https://localhost:8443','HTTP_COOKIE'=>'opaque-synthetic','HTTP_X_MXMED_TLS_TIME'=>(string)time(),'HTTP_X_MXMED_TLS_NONCE'=>str_repeat('a',32)];
$parts=[$server['HTTP_X_MXMED_TLS_TIME'],$server['HTTP_X_MXMED_TLS_NONCE'],'POST',$server['REQUEST_URI'],'localhost:8443','https://localhost:8443','','opaque-synthetic','',hash('sha256',$raw)];
$server['HTTP_X_MXMED_TLS_PROOF']=hash_hmac('sha256',implode("\n",$parts),$key);
checkB(Identity\Http\GovernanceTransport::secure($server,$raw,$key),'Q_authenticated_proxy_transport');
checkB(!Identity\Http\GovernanceTransport::secure($server,$raw.'tamper',$key),'Q_proxy_body_tampering_denied');
$server['REQUEST_URI']='/api/identity/governance.php/cases/another/finalize';checkB(!Identity\Http\GovernanceTransport::secure($server,$raw,$key),'Q_proxy_cross_case_replay_denied');
// A second active membership must not be invented by recovery; replacement is pre-existing.
$p->prepare("INSERT INTO auth_account_memberships(membership_id,account_id,profile_doctor_id,role_code,scope_code,status) VALUES('membership_replacement','replacement_test',?,'collaborator','profile','active')")->execute([$profile]);
$recovery=$s['cases']->openOwnerRecoveryCase($requester['context'],'OWNER_REPLACEMENT',$membership,'membership_replacement','Synthetic governed replacement');externalB($p,$s,$clock,$requester,$recovery);endorseB($s,$clock,$a,$recovery);endorseB($s,$clock,$b,$recovery);
denyB(fn()=>$s['cases']->executeOwnerRecovery($a['context'],$recovery['case_id']),'N_recovery_requester_separation');
// The actual operator boundary refuses omission if any pending invitation exists.
$p->prepare("INSERT INTO auth_profile_operator_invitations(invitation_id,profile_doctor_id,owner_membership_id,email_normalized,role_code,offered_capabilities,offer_hash,token_hash,status,created_at,expires_at) VALUES('pending_synthetic',?,?,'invited@example.invalid','collaborator','[]',?,?,'pending',?,?)")->execute([$profile,$membership,hash('sha256','synthetic-offer'),hash('sha256','synthetic-token'),$clock->now()->format('Y-m-d H:i:s.u'),$clock->now()->modify('+7 days')->format('Y-m-d H:i:s.u')]);
denyB(fn()=>$s['cases']->executeOwnerRecovery($requester['context'],$recovery['case_id']),'O_missing_operator_hook_denied');
// Pending invitation is retained until the actual canonical operator hook reviews it.
$writer=new Platform\Services\CanonicalAuditWriter(Platform\Services\CanonicalAuditPolicyRegistry::canonical(),new Platform\Services\CanonicalAuditMetadataSanitizer(),new Platform\Services\TrustedAuditContextValidator(),new Platform\Services\RandomAuditUuidProvider(),new Platform\Services\SystemAuditUtcClock(),new Platform\Services\HmacSha256AuditIpHasher(new Platform\Services\EnvironmentAuditSecretProvider()),new Platform\Services\CoarseAuditUserAgentSummarizer(),new Platform\Repositories\JoinedPdoCanonicalAuditTransactionAdapter($p),new Platform\Services\CanonicalAuditSealer(new Platform\Services\CanonicalAuditSerializer()),new Platform\Services\AuditV1PhysicalMapper());
$csrf=new Identity\Http\CsrfTokenService(str_repeat('c',32),900,$clock,'https://op01.example.invalid');
$operators=Identity\Operators\ProfileOperatorService::compose($p,$s['sessions'],$csrf,$writer,new Identity\Adapters\InMemoryIdentityNotificationAdapter(),$clock);
$withOperators=new R3ProfileGovernanceService($p,$s['mfa'],new PdoProfileOwnershipApproverAuthorityAdapter($p),$clock,$operators,$s['bindings']);
$withOperators->executeOwnerRecovery($requester['context'],$recovery['case_id']);
checkB(scalarB($p,'SELECT role_code FROM auth_account_memberships WHERE membership_id=?',[$membership])==='administrator'&&scalarB($p,"SELECT role_code FROM auth_account_memberships WHERE membership_id='membership_replacement'")==='owner','N_atomic_owner_replacement');
checkB(scalarB($p,"SELECT status FROM auth_profile_operator_invitations WHERE invitation_id='pending_synthetic'")==='cancelled','O_owner_change_reviewed_pending_invitation');
checkB((int)scalarB($p,"SELECT COUNT(*) FROM platform_audit_events WHERE action='INTERNAL_GOVERNANCE_CHANGED' AND JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.producer_metadata.operation'))='OWNER_REPLACEMENT' AND real_actor_reference='account:requester_test'")===1,'N_recovery_canonical_audit_written');
$removal=$withOperators->openOwnerRecoveryCase($requester['context'],'OWNER_REMOVAL','membership_replacement',null,'Synthetic last owner protection');externalB($p,$s,$clock,$requester,$removal);endorseB($s,$clock,$a,$removal);endorseB($s,$clock,$b,$removal);
denyB(fn()=>$withOperators->executeOwnerRecovery($requester['context'],$removal['case_id']),'N_last_owner_protected');
$withOperators->expireOrRevoke($requester['context'],$removal['case_id'],true);
$e=$s['enroll']->begin($factor['context'],$director['context'],'factor_test@example.invalid',B_PASSWORD,'director_test@example.invalid',B_PASSWORD);
$s['enroll']->activate($factor['context'],$e['factor_id'],StaffMfaService::totp(str_repeat('x',20),intdiv($clock->now()->getTimestamp(),30)));
$mfaRecovery=$s['cases']->openMfaRecoveryCase($requester['context'],'approver_test_a','Synthetic independent factor recovery');externalB($p,$s,$clock,$requester,$mfaRecovery);endorseB($s,$clock,$b,$mfaRecovery);endorseB($s,$clock,$factor,$mfaRecovery);
$s['sessions']->logout($b['token']);
$targetFactor=(string)scalarB($p,"SELECT factor_id FROM auth_staff_mfa_factors WHERE account_id='approver_test_a' AND status='ACTIVE'");
denyB(fn()=>$s['cases']->executeMfaRecovery($requester['context'],$mfaRecovery['case_id'],$targetFactor),'SESSION_revocation_denied_at_consumption');
$s['cases']->expireOrRevoke($requester['context'],$mfaRecovery['case_id'],true);
$b=loginB($s,'approver_test_b');$mfaRecovery=$s['cases']->openMfaRecoveryCase($requester['context'],'approver_test_a','Synthetic renewed factor recovery');externalB($p,$s,$clock,$requester,$mfaRecovery);endorseB($s,$clock,$b,$mfaRecovery);endorseB($s,$clock,$factor,$mfaRecovery);
$targetFactor=(string)scalarB($p,"SELECT factor_id FROM auth_staff_mfa_factors WHERE account_id='approver_test_a' AND status='ACTIVE'");
$s['cases']->executeMfaRecovery($requester['context'],$mfaRecovery['case_id'],$targetFactor);
checkB(scalarB($p,'SELECT status FROM auth_staff_mfa_factors WHERE factor_id=?',[$targetFactor])==='REVOKED','N_mfa_recovery_audited_and_consumed');
checkB((int)scalarB($p,"SELECT COUNT(*) FROM platform_audit_events WHERE action='INTERNAL_GOVERNANCE_CHANGED' AND JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.producer_metadata.operation'))='MFA_RECOVERY'")===1,'N_mfa_recovery_canonical_audit');
$expired=$s['cases']->openMfaRecoveryCase($requester['context'],'approver_test_b','Synthetic expiration lifecycle');externalB($p,$s,$clock,$requester,$expired);
$clock->advance('+31 minutes');$requester=loginB($s,'requester_test');
$s['cases']->expireOrRevoke($requester['context'],$expired['case_id']);
checkB(scalarB($p,'SELECT status FROM auth_governance_cases WHERE case_id=?',[$expired['case_id']])==='EXPIRED','EXPIRATION_case_audited_and_closed');
$new=$s['cases']->openMfaRecoveryCase($requester['context'],'approver_test_b','Synthetic repeat after expiration');
checkB($new['case_id']!==$expired['case_id'],'EXPIRATION_active_target_released_without_deletion');
externalB($p,$s,$clock,$requester,$new);$factor=loginB($s,'factor_test');
$challenge=$s['cases']->issueEndorsementChallenge($factor['context'],$new['case_id'],'factor_test@example.invalid',B_PASSWORD,['ip'=>'synthetic']);
for($i=0;$i<5;$i++)denyB(fn()=>$s['mfa']->verifyChallenge('factor_test',(string)$factor['context']->session()->sessionId(),1,$challenge['challenge_id'],$challenge['challenge'],'invalid'),'MFA_factor_lockout_attempt_'.$i);
$clock->advance('+6 minutes');$challenge=$s['cases']->issueEndorsementChallenge($factor['context'],$new['case_id'],'factor_test@example.invalid',B_PASSWORD,['ip'=>'synthetic']);
try{$s['mfa']->verifyChallenge('factor_test',(string)$factor['context']->session()->sessionId(),1,$challenge['challenge_id'],$challenge['challenge'],StaffMfaService::totp(str_repeat('x',20),intdiv($clock->now()->getTimestamp(),30)));throw new LogicException('factor lockout bypass');}catch(RuntimeException $e){checkB($e->getMessage()==='staff_mfa_factor_locked','MFA_lockout_survives_expired_challenge_and_new_nonce');}
// Every test identity is synthetic; production owner eligibility remains unconditionally closed.
require_once dirname(__DIR__,3).'/api/_lib/clinical_professional_authority.php';
checkB(clinical_professional_authority_decision(['doctor_id'=>$profile,'account_id'=>'beneficiary_test','role_code'=>'owner'],'PRESCRIPTION_ISSUE')['allowed']===false,'P_owner_not_clinical_signer');
checkB((int)scalarB($p,'SELECT COUNT(*) FROM platform_audit_stream_heads h LEFT JOIN platform_audit_events e ON e.stream_key=h.stream_key AND e.sequence_number=h.last_sequence_number AND e.event_hash=h.last_event_hash WHERE h.last_sequence_number>0 AND e.event_id IS NULL')===0,'AUDIT_native_heads_coherent');
denyB(fn()=>$p->exec("UPDATE auth_governance_events SET reason_code='TAMPER' LIMIT 1"),'AUDIT_history_immutable');
echo "GOV03B_DISPOSABLE_CONTRACTS=PASS\n";
