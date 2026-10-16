<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli'||getenv('C4_ISSUER_QA')!=='1') exit(1);
require_once __DIR__.'/../http/IdentityHttpComposition.php';
Identity\Http\IdentityHttpComposition::registerAutoloader();
require_once __DIR__.'/../../media/storage/LocalPersistentPrivateMediaStorage.php';
require_once __DIR__.'/../../profiles/services/VerifiedPhysicianIdentityService.php';
require_once __DIR__.'/../../profiles/services/VerifiedDoctorCredentialService.php';
require_once __DIR__.'/../../agenda/contracts/OtpProviderPort.php';
require_once __DIR__.'/support/C4EvidenceHarness.php';

use Identity\Services\StaffMfaService;

function iaValue(PDO $p,string $sql,array $args=[]):mixed {$q=$p->prepare($sql);$q->execute($args);return $q->fetchColumn();}
function iaApplicant(PDO $p,array $s,EvClock $clock,string $label):array
{
    $notifications=new Identity\Adapters\InMemoryIdentityNotificationAdapter();
    $consents=new Identity\Repositories\AccountConsentRepository($p);$tokens=new Identity\Repositories\OneTimeTokenRepository($p);
    $register=new Identity\Services\RegistrationService($p,$s['accounts'],$s['credentials'],$consents,$tokens,$s['limits'],$notifications,$clock);
    $email='issuer_'.$label.'@example.invalid';
    if (!$register->register(['email'=>$email,'password'=>EV_PASSWORD,'terms_version'=>'synthetic-v1','privacy_notice_version'=>'synthetic-v1',
        'terms_accepted'=>true,'privacy_notice_accepted'=>true],['ip'=>null])->accepted()) throw new RuntimeException('synthetic_registration_failed');
    $verification=new Identity\Services\EmailVerificationService($p,$s['accounts'],$consents,$tokens,$s['limits'],$notifications,$clock);
    if (!$verification->verify($notifications->lastMessage()->token(),['ip'=>'127.0.0.1'])->verified()) throw new RuntimeException('synthetic_email_failed');
    $actor=evLogin($s,$email);evPhone($s,$actor,'+1555'.str_pad((string)(1000000+strlen($label)*37),7,'0',STR_PAD_LEFT));return $actor;
}
function iaConfirmed(PDO $p,array $s,EvClock $clock,array $reviewer,array $actor,array $professional,?string $expiry=null):array
{
    $r=evRequest($s,$actor,$professional);$proof=evProof($s,$clock,$reviewer,$r,'APPROVED');
    foreach($r['files'] as $file)$s['evidence']->readDocument($reviewer['context'],$r['request_id'],$r['version'],$file);
    $r=$s['evidence']->decide($reviewer['context'],$r['request_id'],$r['version'],$proof['proof_id'],$proof['proof'],true,array_fill_keys($r['files'],$expiry));
    $nonce=$s['evidence']->issueConfirmation($actor['context'],$r['request_id'],$r['version'],$actor['email'],EV_PASSWORD);
    $r=$s['evidence']->confirm($actor['context'],$r['request_id'],$r['version'],$nonce['confirmation'],true);
    if (($r['issuance_command_id']??null)!==$r['request_id']) throw new RuntimeException('trusted_confirmation_handoff_missing');return $r;
}
function iaChange(array $s,EvClock $clock,array $staff,string $type,string $id,int $version,string $action):array
{
    $clock->advance('+31 seconds');$reason=match($action){'REJECTED'=>'REVIEW_WITHDRAWN','SUSPENDED'=>'SECURITY_REVIEW','EXPIRED'=>'SOURCE_EXPIRED',default=>'SECURITY_REVOCATION'};
    $case=$s['composition']->issuer->beginChange($staff['context'],$type,$id,$version,$action,$reason,$staff['email'],EV_PASSWORD);
    $proof=$s['mfa']->verifyChallenge($staff['context']->accountId(),(string)$staff['context']->session()->sessionId(),$staff['context']->principal()->credentialVersion(),
        $case['challenge_id'],$case['challenge'],StaffMfaService::totp(str_repeat('e',20),intdiv($clock->now()->getTimestamp(),30)));
    return [$case,$proof];
}
function iaEligibility(PDO $p,array $s,string $account,string $profile):array
{ $p->beginTransaction();try{return $s['composition']->projection->requireCurrent($p,$account,$profile);}finally{$p->rollBack();} }
function iaOwnerStack(PDO $p,array $s,EvClock $clock):array
{
    $gate=$s['composition']->eligibility;$audit=new Identity\Adapters\CanonicalGovernanceAuditAdapter($p);
    $security=new Identity\Services\GovernanceCaseSecurity($p,$s['security'],$gate,null,$audit);
    $authority=new Identity\Adapters\PdoProfileOwnershipApproverAuthorityAdapter($p);
    $cases=new Identity\Services\R3ProfileGovernanceService($p,$s['mfa'],$authority,$clock,security:$security);
    $proofs=new Identity\Repositories\ProfileOwnershipStepUpRepository($p);
    $roles=new Identity\Services\ProfileMembershipRoleChangeService($p,new Identity\Repositories\AccountMembershipRepository($p),
        new Identity\Adapters\PdoProfileOwnershipApprovalAdapter($p,$authority,$clock),new Identity\Adapters\PdoProfileOwnershipStepUpAdapter($p,$proofs,$clock),
        new Identity\Adapters\CanonicalProfileMembershipRoleChangeAuditAdapter($p),$clock,new Identity\Adapters\PdoGovernanceCaseFinalizationAdapter($p,$clock),eligibility:$gate,security:$security);
    $runtime=new Identity\Services\GovernanceRuntime($p,$cases,$s['mfa'],new Identity\Services\ProfileOwnershipStepUpIssuanceService($p,$s['auth'],$proofs,$clock),$roles,$clock);
    return compact('cases','runtime');
}
function iaOwnerApprove(array $s,array $owner,EvClock $clock,array $staff,array $case):void
{
    $clock->advance('+31 seconds');$id=$staff['context']->accountId();
    $challenge=$owner['cases']->issueEndorsementChallenge($staff['context'],$case['case_id'],$staff['email'],EV_PASSWORD,['ip'=>null]);
    $proof=$s['mfa']->verifyChallenge($id,(string)$staff['context']->session()->sessionId(),$staff['context']->principal()->credentialVersion(),
        $challenge['challenge_id'],$challenge['challenge'],StaffMfaService::totp(str_repeat('e',20),intdiv($clock->now()->getTimestamp(),30)));
    $owner['cases']->endorse($staff['context'],$case['case_id'],$proof['proof_id'],$proof['proof']);
}
function iaMember(PDO $p,array $actor,string $profile,string $label):string
{
    $id='issuer_member_'.$label;$p->prepare("INSERT INTO auth_account_memberships(membership_id,account_id,profile_doctor_id,role_code,scope_code,status,assignment_source) VALUES(?,?,?,'collaborator','profile','active','manual_review')")
        ->execute([$id,$actor['context']->accountId(),$profile]);return $id;
}

if (($argv[1]??'')==='--issue-worker') {
    $in=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);$p=evConnect();$clock=new EvClock(new DateTimeImmutable($in['now']));$s=evStack($p,$clock);
    try {$actor=$s['sessions']->validate($in['session'])->context();if ($actor===null)throw new RuntimeException('session_denied');
        echo "READY\n";flush();
        echo json_encode($s['composition']->issuer->issue($actor,$in['command']),JSON_THROW_ON_ERROR);
    }catch(Throwable $e){fwrite(STDERR,get_class($e).':'.$e->getMessage());exit(1);}exit;
}

$p=evConnect();
if (!preg_match('/^mxmed_gate4d_preview_c4issuer_[a-f0-9]{10}$/D',(string)$p->query('SELECT DATABASE()')->fetchColumn())) throw new RuntimeException('issuer_disposable_only');
$clock=new EvClock(new DateTimeImmutable('now',new DateTimeZone('UTC')));putenv('C4_EV_QA_KEY='.base64_encode(random_bytes(32)));$s=evStack($p,$clock);$issuer=$s['composition']->issuer;
foreach(['issuer_director'=>'DIRECTOR','issuer_reviewer'=>'MASTER_ADMIN','issuer_no_grant'=>'MASTER_ADMIN'] as $id=>$class){
    $p->prepare("INSERT INTO auth_accounts(account_id,email_address,email_normalized,status,email_verified_at) VALUES(?,?,?,'active',UTC_TIMESTAMP())")->execute([$id,$id.'@example.invalid',$id.'@example.invalid']);
    $p->prepare('INSERT INTO auth_account_credentials(account_id,password_hash,password_changed_at,credential_version) VALUES(?,?,UTC_TIMESTAMP(),1)')->execute([$id,password_hash(EV_PASSWORD,PASSWORD_ARGON2ID)]);
    $p->prepare("INSERT INTO internal_staff(account_id,governance_class,status) VALUES(?,?,'ACTIVE')")->execute([$id,$class]);
}
foreach(['physician_accreditation_review','profile_initial_ownership_approve'] as $cap)$p->prepare("INSERT INTO internal_operator_grants(grant_id,account_id,capability,status) VALUES(UUID(),'issuer_reviewer',?,'ACTIVE')")->execute([$cap]);
$staff=evLogin($s,'issuer_reviewer@example.invalid');$director=evLogin($s,'issuer_director@example.invalid');$noGrant=evLogin($s,'issuer_no_grant@example.invalid');
$enroll=new Identity\Services\GovernanceStaffMfaCeremony($p,$s['mfa'],$s['security'],new Identity\Adapters\CanonicalGovernanceAuditAdapter($p),$clock,['issuer_director']);
$factor=$enroll->begin($staff['context'],$director['context'],$staff['email'],EV_PASSWORD,$director['email'],EV_PASSWORD);
$enroll->activate($staff['context'],$factor['factor_id'],StaffMfaService::totp(str_repeat('e',20),intdiv($clock->now()->getTimestamp(),30)));
$actor=iaApplicant($p,$s,$clock,'main');$account=$actor['context']->accountId();$professional=evProfessional($p,'issuer_main',$staff['context']->accountId());$second=evProfessional($p,'issuer_other',$staff['context']->accountId());
$clock->time=new DateTimeImmutable('now',new DateTimeZone('UTC'));
$r=iaConfirmed($p,$s,$clock,$staff,$actor,$professional);$command=$r['issuance_command_id'];
evCheck((int)iaValue($p,'SELECT COUNT(*) FROM auth_physician_issuance_workflows')===1&&(int)iaValue($p,'SELECT COUNT(*) FROM profiles_physician_admissions')===0,'confirmation_records_trusted_handoff_without_auto_issuance');
evDeny(fn()=>$issuer->issue($actor['context'],'00000000-0000-4000-8000-000000000000'),'physician_trusted_workflow_required','02_missing_workflow_or_request_denied');
evDeny(fn()=>$issuer->issue($director['context'],$command),'physician_issuer_account_scope_denied','03_wrong_authenticated_account_denied');
evDeny(fn()=>evConsumer($s,$p,$r,$account,$second[0]),'c4_confirmed_scope_required','04_wrong_profile_denied');

// Adversarial changes occur ONLY in this fresh synthetic DB, restored after each probe.
$mutations=[
    ['UPDATE auth_account_phone_verifications SET contact_version=contact_version+1 WHERE account_id=?',[$account],'UPDATE auth_account_phone_verifications SET contact_version=contact_version-1 WHERE account_id=?',[$account],'c4_phone_verification_required','current_phone_revision_required'],
    ["UPDATE auth_account_phone_verifications SET status='REVOKED',verified_at=NULL WHERE account_id=?",[$account],"UPDATE auth_account_phone_verifications SET status='VERIFIED',verified_at=? WHERE account_id=?",[iaValue($p,'SELECT verified_at FROM auth_account_phone_verifications WHERE account_id=?',[$account]),$account],'c4_phone_verification_required','05_unverified_phone_denied'],
    ['UPDATE auth_physician_evidence_requests SET valid_until=? WHERE request_id=?',[$clock->now()->modify('-1 second')->format('Y-m-d H:i:s.u'),$command],'UPDATE auth_physician_evidence_requests SET valid_until=NULL WHERE request_id=?',[$command],'c4_evidence_expired','06_expired_evidence_denied'],
    ["UPDATE internal_staff SET status='SUSPENDED' WHERE account_id='issuer_reviewer'",[],"UPDATE internal_staff SET status='ACTIVE' WHERE account_id='issuer_reviewer'",[],'c4_reviewer_capability_required','07_revoked_reviewer_denied'],
    ["UPDATE internal_operator_grants SET status='REVOKED',revoked_at=UTC_TIMESTAMP() WHERE account_id='issuer_reviewer' AND capability='physician_accreditation_review'",[],"UPDATE internal_operator_grants SET status='ACTIVE',revoked_at=NULL WHERE account_id='issuer_reviewer' AND capability='physician_accreditation_review'",[],'c4_reviewer_capability_required','08_missing_explicit_capability_denied'],
    ["UPDATE profiles_doctor_credentials SET lifecycle_status='REVOKED' WHERE credential_id=?",[$professional[2]],"UPDATE profiles_doctor_credentials SET lifecycle_status='ACTIVE',updated_at=? WHERE credential_id=?",[iaValue($p,'SELECT updated_at FROM profiles_doctor_credentials WHERE credential_id=?',[$professional[2]]),$professional[2]],'c4_professional_sources_denied','10_invalid_professional_credential_denied'],
    ["UPDATE profiles_verified_identities SET source_reference='changed-synthetic-reference' WHERE verified_identity_id=?",[$professional[1]],'UPDATE profiles_verified_identities SET source_reference=? WHERE verified_identity_id=?',[iaValue($p,'SELECT source_reference FROM profiles_verified_identities WHERE verified_identity_id=?',[$professional[1]]),$professional[1]],'c4_professional_revision_changed','11_changed_VID01_denied'],
    ["UPDATE profiles_doctor_credentials SET source_reference='changed-synthetic-reference' WHERE credential_id=?",[$professional[2]],'UPDATE profiles_doctor_credentials SET source_reference=?,updated_at=? WHERE credential_id=?',[iaValue($p,'SELECT source_reference FROM profiles_doctor_credentials WHERE credential_id=?',[$professional[2]]),iaValue($p,'SELECT updated_at FROM profiles_doctor_credentials WHERE credential_id=?',[$professional[2]]),$professional[2]],'c4_professional_revision_changed','12_changed_CRD02_denied'],
    ["UPDATE auth_staff_mfa_factors SET status='REVOKED',revoked_at=UTC_TIMESTAMP() WHERE factor_id=?",[$factor['factor_id']],"UPDATE auth_staff_mfa_factors SET status='ACTIVE',revoked_at=NULL WHERE factor_id=?",[$factor['factor_id']],'c4_review_mfa_evidence_denied','reviewer_MFA_revocation_denied'],
    ["UPDATE auth_account_credentials SET credential_version=2 WHERE account_id='issuer_reviewer'",[],"UPDATE auth_account_credentials SET credential_version=1 WHERE account_id='issuer_reviewer'",[],'c4_account_denied','current_reviewer_credential_revision_required'],
];
foreach($mutations as [$sql,$args,$restore,$restoreArgs,$reason,$label]){try{$p->prepare($sql)->execute($args);evDeny(fn()=>$issuer->issue($actor['context'],$command),$reason,$label);}finally{$p->prepare($restore)->execute($restoreArgs);}}
evSqlDeny(fn()=>$p->prepare("UPDATE auth_physician_issuance_workflows SET profile_doctor_id=? WHERE command_id=?")->execute([$second[0],$command]),'45000','18_changed_command_payload_conflicts');
$foreign=evStack(evConnect(),$clock);
$wrongConnection=new Identity\Accreditation\ProfessionalAuthorityIssuer($s['authority'],$foreign['evidence'],$s['mfa']);
evDeny(fn()=>$wrongConnection->issue($actor['context'],$command),'c4_consumer_transaction_required','same_PDO_port_required');
$p->exec('UPDATE gov03b_qa_faults SET enabled=2');
try {$issuer->issue($actor['context'],$command);throw new LogicException('audit_fault_failed');}
catch(PDOException $e){if ((string)$e->getCode()!=='45000') throw $e;evCheck(true,'19_native_audit_failure_aborts_activation');}
finally{$p->exec('UPDATE gov03b_qa_faults SET enabled=0');}
evCheck((int)iaValue($p,'SELECT COUNT(*) FROM profiles_physician_admissions')===0&&(int)iaValue($p,'SELECT COUNT(*) FROM auth_account_professional_bindings')===0
    &&(int)iaValue($p,'SELECT COUNT(*) FROM auth_physician_authority_history')===0&&iaValue($p,'SELECT staged_result FROM auth_physician_issuance_workflows WHERE command_id=?',[$command])===null,'19_atomic_rollback_includes_rows_history_workflow_audit');

$input=['now'=>$clock->now()->format(DATE_ATOM),'session'=>$actor['token'],'command'=>$command];$workers=[];
$p->beginTransaction();$p->prepare('SELECT command_id FROM auth_physician_issuance_workflows WHERE command_id=? FOR UPDATE')->execute([$command]);
for($i=0;$i<2;$i++){$proc=proc_open([PHP_BINARY,__FILE__,'--issue-worker'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode($input,JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
try {foreach($workers as [$proc,$pipes]){stream_set_timeout($pipes[1],20);if(trim((string)fgets($pipes[1]))!=='READY')throw new RuntimeException('issuer_worker_not_ready');}}finally{$p->commit();}
$responses=[];foreach($workers as [$proc,$pipes]){$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);evCheck(proc_close($proc)===0&&$error==='','16_concurrent_issuer_worker_clean');$responses[]=json_decode($out,true,32,JSON_THROW_ON_ERROR);}
evCheck($responses[0]==$responses[1]&&(int)iaValue($p,'SELECT COUNT(*) FROM profiles_physician_admissions')===1&&(int)iaValue($p,'SELECT COUNT(*) FROM auth_account_professional_bindings')===1,'16_concurrent_issuance_serialized_single_pair');
$issued=$responses[0];$before=(int)iaValue($p,'SELECT COUNT(*) FROM platform_audit_events');
evCheck($issuer->issue($actor['context'],$command)==$issued&&(int)iaValue($p,'SELECT COUNT(*) FROM platform_audit_events')===$before,'17_same_command_is_idempotent_without_duplicate_audit');
$facts=iaEligibility($p,$s,$account,$professional[0]);
evCheck($facts['account_id']===$account&&$facts['profile_doctor_id']===$professional[0]&&$facts['verified_identity_id']===$professional[1]&&$facts['professional_credential_id']===$professional[2]
    &&$facts['admission_id']===$issued['admission_id']&&$facts['evidence_version']===$r['version'],'01_13_confirmed_evidence_issues_exact_account_person_admission_binding');
evCheck((int)iaValue($p,"SELECT COUNT(*) FROM auth_governance_cases WHERE operation='PHYSICIAN_EVIDENCE_REVIEW' AND beneficiary_account_id=?",[$account])===1
    &&(int)iaValue($p,'SELECT COUNT(*) FROM auth_physician_evidence_files WHERE request_id=?',[$command])===2,'26_one_staff_review_no_second_upload');
evCheck(iaValue($p,'SELECT confirmation_hash FROM auth_physician_evidence_requests WHERE request_id=?',[$command])===null
    &&iaValue($p,'SELECT confirmation_audit_reference FROM auth_physician_evidence_requests WHERE request_id=?',[$command])!==null,'27_independent_consumed_confirmation_reused');
foreach(['profiles_physician_admissions'=>'admission_id','auth_account_professional_bindings'=>'binding_id'] as $table=>$key){
    $columns=$p->query('SHOW COLUMNS FROM '.$table)->fetchAll(PDO::FETCH_ASSOC);$names=[];foreach($columns as $col)if(!str_contains($col['Extra'],'GENERATED'))$names[]=$col['Field'];
    $select=array_map(fn($name)=>$name===$key?'UUID()':'`'.$name.'`',$names);$names=array_map(fn($name)=>'`'.$name.'`',$names);
    evSqlDeny(fn()=>$p->exec('INSERT INTO '.$table.'('.implode(',',$names).') SELECT '.implode(',',$select).' FROM '.$table),'23000',$table==='profiles_physician_admissions'?'14_duplicate_effective_admission_rejected':'15_duplicate_effective_binding_rejected');
}
evCheck((int)iaValue($p,"SELECT COUNT(*) FROM auth_account_memberships WHERE role_code='owner'")===0,'issuer_never_promotes_membership');
$owner=iaOwnerStack($p,$s,$clock);$membership=iaMember($p,$actor,$professional[0],'main');
$noBinding=iaApplicant($p,$s,$clock,'no_binding');$noMembership=iaMember($p,$noBinding,$second[0],'no_binding');
evDeny(fn()=>$owner['cases']->openInitialOwnerCase($staff['context'],$noMembership,'Synthetic no binding'),'professional_binding_missing_or_ambiguous','29_owner_assignment_without_binding_denied');
$case=$owner['cases']->openInitialOwnerCase($staff['context'],$membership,'Synthetic issuer ownership acceptance');iaOwnerApprove($s,$owner,$clock,$staff,$case);
$step=$owner['runtime']->issueBeneficiaryStepUp($actor['context'],$case['case_id'],$actor['email'],EV_PASSWORD,['ip'=>null]);
$owner['runtime']->finalizeInitialOwner($actor['context'],$case['case_id'],$step['step_up_proof']);
evCheck(iaValue($p,'SELECT role_code FROM auth_account_memberships WHERE membership_id=?',[$membership])==='owner'
    &&(int)iaValue($p,'SELECT COUNT(*) FROM auth_governance_case_endorsements WHERE case_id=?',[$case['case_id']])===1,'28_actual_existing_owner_writer_one_staff_independent_acceptance');
require_once dirname(__DIR__,3).'/api/_lib/clinical_professional_authority.php';
foreach(['PRESCRIPTION_SIGN','PRESCRIPTION_ISSUE'] as $operation)foreach([$account,$noBinding['context']->accountId(),'synthetic_nonmedical_operator'] as $subject){
    try {clinical_professional_authority_assert(['doctor_id'=>$professional[0],'user_id'=>$subject,'role_code'=>'owner'],$operation);throw new LogicException('signing_activated');}
    catch(RuntimeException){evCheck(true,'30_31_owner_and_operator_closed_'.$operation);}
}
evDeny(fn()=>$issuer->beginChange($actor['context'],'ADMISSION',$issued['admission_id'],2,'SUSPENDED','SECURITY_REVIEW',$actor['email'],EV_PASSWORD),'c4_self_review_denied','09_applicant_cannot_self_review_lifecycle');
evDeny(fn()=>$issuer->beginChange($noGrant['context'],'ADMISSION',$issued['admission_id'],2,'SUSPENDED','SECURITY_REVIEW',$noGrant['email'],EV_PASSWORD),'c4_reviewer_capability_required','lifecycle_requires_explicit_staff_capability');
evDeny(fn()=>$issuer->beginChange($staff['context'],'ADMISSION',$issued['admission_id'],1,'SUSPENDED','SECURITY_REVIEW',$staff['email'],EV_PASSWORD),'physician_authority_version_conflict','lifecycle_expected_revision_required');
evDeny(fn()=>$issuer->beginChange($staff['context'],'ADMISSION',$issued['admission_id'],2,'EXPIRED','SOURCE_EXPIRED',$staff['email'],EV_PASSWORD),'physician_authority_not_expired','no_invented_annual_deadline');
[$suspend,$suspendProof]=iaChange($s,$clock,$staff,'ADMISSION',$issued['admission_id'],2,'SUSPENDED');
$suspended=$issuer->completeChange($staff['context'],$suspend['case_id'],$suspendProof['proof_id'],$suspendProof['proof']);
evDeny(fn()=>iaEligibility($p,$s,$account,$professional[0]),'professional_admission_denied','20_admission_suspension_denies_future_eligibility');
evCheck($issuer->completeChange($staff['context'],$suspend['case_id'],$suspendProof['proof_id'],$suspendProof['proof'])==$suspended,'lifecycle_exact_replay_is_idempotent');
evDeny(fn()=>$issuer->completeChange($staff['context'],$suspend['case_id'],$suspendProof['proof_id'],'changed-payload'),'physician_command_payload_conflict','18_changed_lifecycle_command_conflicts');
[$revoke,$revokeProof]=iaChange($s,$clock,$staff,'BINDING',$issued['binding_id'],2,'REVOKED');
$p->exec('UPDATE gov03b_qa_faults SET enabled=1');
try {$issuer->completeChange($staff['context'],$revoke['case_id'],$revokeProof['proof_id'],$revokeProof['proof']);throw new LogicException('fault_not_raised');}
catch(PDOException $e){evCheck((string)$e->getCode()==='45000','lifecycle_audit_failure_denies');}finally{$p->exec('UPDATE gov03b_qa_faults SET enabled=0');}
evCheck(iaValue($p,'SELECT status FROM auth_staff_mfa_proofs WHERE proof_id=?',[$revokeProof['proof_id']])==='VERIFIED','lifecycle_audit_failure_rolls_back_MFA');
$issuer->completeChange($staff['context'],$revoke['case_id'],$revokeProof['proof_id'],$revokeProof['proof']);
evDeny(fn()=>iaEligibility($p,$s,$account,$professional[0]),'professional_binding_missing_or_ambiguous','21_binding_revocation_denies');
evDeny(fn()=>$issuer->issue($actor['context'],$command),'physician_issued_authority_changed','terminal_binding_cannot_reissue_old_command');
evCheck(iaValue($p,'SELECT role_code FROM auth_account_memberships WHERE membership_id=?',[$membership])==='owner','revocation_preserves_membership_history_without_authority');
[$revoke,$proof]=iaChange($s,$clock,$staff,'ADMISSION',$issued['admission_id'],3,'REVOKED');$issuer->completeChange($staff['context'],$revoke['case_id'],$proof['proof_id'],$proof['proof']);
evSqlDeny(fn()=>$p->exec("UPDATE auth_physician_authority_history SET actor_account_id='issuer_director'"),'45000','22_historical_decisions_cannot_be_overwritten');
evSqlDeny(fn()=>$p->exec('DELETE FROM auth_physician_authority_history'),'45000','22_historical_decisions_cannot_be_deleted');
evSqlDeny(fn()=>$p->prepare("UPDATE profiles_physician_admissions SET reviewed_by_account_id='issuer_director' WHERE admission_id=?")->execute([$issued['admission_id']]),'45000','22_original_reviewer_attribution_immutable');
evSqlDeny(fn()=>$p->prepare("UPDATE auth_account_professional_bindings SET acceptance_reference='invented' WHERE binding_id=?")->execute([$issued['binding_id']]),'45000','22_consumed_acceptance_reference_immutable');

// Pending/rejected terminal history; new re-review allocates a new admission, never overwrites.
$pending=iaApplicant($p,$s,$clock,'pending');$pp=evProfessional($p,'issuer_pending',$staff['context']->accountId());$pr=iaConfirmed($p,$s,$clock,$staff,$pending,$pp);
$staged=$issuer->stage($pending['context'],$pr['issuance_command_id']);
evCheck($issuer->stage($pending['context'],$pr['issuance_command_id'])==$staged,'PENDING_checkpoint_idempotent');
foreach(['ADMISSION'=>'admission_id','BINDING'=>'binding_id'] as $type=>$key){[$c,$proof]=iaChange($s,$clock,$staff,$type,$staged[$key],1,'REJECTED');$issuer->completeChange($staff['context'],$c['case_id'],$proof['proof_id'],$proof['proof']);
    evDeny(fn()=>$issuer->beginChange($staff['context'],$type,$staged[$key],2,'SUSPENDED','SECURITY_REVIEW',$staff['email'],EV_PASSWORD),'physician_authority_transition_denied','terminal_REJECTED_'.$type.'_does_not_reactivate');}
evDeny(fn()=>$issuer->issue($pending['context'],$pr['issuance_command_id']),'physician_pending_authority_changed','rejected_pending_cannot_activate');
$proof=evProof($s,$clock,$staff,$pr,'REVOKED');$s['evidence']->decide($staff['context'],$pr['request_id'],$pr['version'],$proof['proof_id'],$proof['proof'],false);
$newReview=iaConfirmed($p,$s,$clock,$staff,$pending,$pp);$newAdmission=$issuer->issue($pending['context'],$newReview['issuance_command_id']);
evCheck($newAdmission['admission_id']!==$staged['admission_id']&&(int)iaValue($p,'SELECT COUNT(*) FROM profiles_physician_admissions WHERE doctor_id=?',[$pp[0]])===2
    &&iaValue($p,'SELECT status FROM profiles_physician_admissions WHERE admission_id=?',[$staged['admission_id']])==='REJECTED','22_rereview_preserves_prior_admission_in_separate_row');

// Revalidation during the existing ownership writer, after a previously approved owner case.
$pm=iaMember($p,$pending,$pp[0],'pending');$case=$owner['cases']->openInitialOwnerCase($staff['context'],$pm,'Synthetic later revocation');iaOwnerApprove($s,$owner,$clock,$staff,$case);
$step=$owner['runtime']->issueBeneficiaryStepUp($pending['context'],$case['case_id'],$pending['email'],EV_PASSWORD,['ip'=>null]);
[$c,$proof]=iaChange($s,$clock,$staff,'BINDING',$newAdmission['binding_id'],2,'REVOKED');$issuer->completeChange($staff['context'],$c['case_id'],$proof['proof_id'],$proof['proof']);
evDeny(fn()=>$owner['runtime']->finalizeInitialOwner($pending['context'],$case['case_id'],$step['step_up_proof']),'professional_binding_missing_or_ambiguous','28_final_owner_transaction_revalidates_revoked_binding');
evCheck(iaValue($p,'SELECT role_code FROM auth_account_memberships WHERE membership_id=?',[$pm])==='collaborator','failed_final_assignment_preserves_collaborator');
[$c,$proof]=iaChange($s,$clock,$staff,'ADMISSION',$newAdmission['admission_id'],2,'REVOKED');$issuer->completeChange($staff['context'],$c['case_id'],$proof['proof_id'],$proof['proof']);

$expiring=iaApplicant($p,$s,$clock,'expiring');$ep=evProfessional($p,'issuer_expiring',$staff['context']->accountId());$expiry=$clock->now()->modify('+10 minutes')->format('Y-m-d\TH:i:s\Z');
$er=iaConfirmed($p,$s,$clock,$staff,$expiring,$ep,$expiry);$ei=$issuer->issue($expiring['context'],$er['issuance_command_id']);$clock->advance('+601 seconds');
evDeny(fn()=>iaEligibility($p,$s,$expiring['context']->accountId(),$ep[0]),'professional_evidence_expired','source_deadline_denied_before_expiration_recorded');
foreach(['ADMISSION'=>'admission_id','BINDING'=>'binding_id'] as $type=>$key){[$c,$proof]=iaChange($s,$clock,$staff,$type,$ei[$key],2,'EXPIRED');$result=$issuer->completeChange($staff['context'],$c['case_id'],$proof['proof_id'],$proof['proof']);evCheck($result['status']==='EXPIRED','source_driven_EXPIRED_'.$type);}

// Exercise every remaining legal edge with actual protected evidence and MFA decisions.
foreach(['pending_revoke','pending_expire','suspended_revoke','suspended_expire'] as $scenario){
    $candidate=iaApplicant($p,$s,$clock,$scenario);$profile=evProfessional($p,'issuer_'.$scenario,$staff['context']->accountId());
    $deadline=str_contains($scenario,'expire')?$clock->now()->modify('+3 minutes')->format('Y-m-d\TH:i:s\Z'):null;
    $request=iaConfirmed($p,$s,$clock,$staff,$candidate,$profile,$deadline);
    $pair=str_starts_with($scenario,'pending')?$issuer->stage($candidate['context'],$request['issuance_command_id']):$issuer->issue($candidate['context'],$request['issuance_command_id']);
    if (str_starts_with($scenario,'suspended')) foreach(['ADMISSION'=>'admission_id','BINDING'=>'binding_id'] as $type=>$key){
        [$c,$proof]=iaChange($s,$clock,$staff,$type,$pair[$key],2,'SUSPENDED');$result=$issuer->completeChange($staff['context'],$c['case_id'],$proof['proof_id'],$proof['proof']);
        evCheck($result['status']==='SUSPENDED','legal_active_to_suspended_'.$type);
        evDeny(fn()=>$issuer->beginChange($staff['context'],$type,$pair[$key],3,$type==='ADMISSION'?'APPROVED':'ACTIVE','SECURITY_REVIEW',$staff['email'],EV_PASSWORD),
            'physician_authority_transition_denied','suspended_cannot_reactivate_'.$type);
    }
    $action=str_contains($scenario,'expire')?'EXPIRED':'REVOKED';if ($action==='EXPIRED')$clock->advance('+181 seconds');
    foreach(['ADMISSION'=>'admission_id','BINDING'=>'binding_id'] as $type=>$key){
        $version=str_starts_with($scenario,'pending')?1:3;
        [$c,$proof]=iaChange($s,$clock,$staff,$type,$pair[$key],$version,$action);$result=$issuer->completeChange($staff['context'],$c['case_id'],$proof['proof_id'],$proof['proof']);
        evCheck($result['status']===$action,'legal_'.$scenario.'_'.$type);
        evDeny(fn()=>$issuer->beginChange($staff['context'],$type,$pair[$key],$version+1,'SUSPENDED','SECURITY_REVIEW',$staff['email'],EV_PASSWORD),
            'physician_authority_transition_denied','terminal_'.$scenario.'_cannot_reactivate_'.$type);
    }
}

evDeny(fn()=>iaEligibility($p,$s,$noBinding['context']->accountId(),$second[0]),'professional_binding_missing_or_ambiguous','23_existing_legacy_profile_is_not_accreditation');
$conflict=evRequest($s,$noBinding,$second,'CONFLICT_OR_TRANSFER_REVIEW');
evDeny(fn()=>$s['evidence']->beginReview($staff['context'],$conflict['request_id'],$conflict['version'],'APPROVED',$staff['email'],EV_PASSWORD),'c4_exception_or_new_profile_resolution_required','24_conflict_transfer_cannot_autoapprove');
$new=evRequest($s,$noBinding,[],'REQUEST_NEW_PROFILE');
evDeny(fn()=>$s['evidence']->beginReview($staff['context'],$new['request_id'],$new['version'],'APPROVED',$staff['email'],EV_PASSWORD),'c4_exception_or_new_profile_resolution_required','25_new_profile_cannot_be_substituted');
evDeny(fn()=>$issuer->issue($noBinding['context'],$new['request_id']),'physician_trusted_workflow_required','25_unresolved_origin_has_no_issuance_workflow');
evSqlDeny(fn()=>$p->exec("CALL mxmed.audit_mp01c_lock_stream_head_v1('synthetic')"),'42000','34_cross_database_audit_calls_denied');
evCheck((new Identity\Contracts\SessionPolicy())->idleTtlSeconds()===3600&&(new Identity\Contracts\SessionPolicy())->absoluteTtlSeconds()===43200,'33_3600_43200_session_policy_unchanged');
evCheck((int)iaValue($p,'SELECT COUNT(*) FROM platform_audit_stream_heads h LEFT JOIN platform_audit_events e ON e.stream_key=h.stream_key AND e.sequence_number=h.last_sequence_number AND e.event_hash=h.last_event_hash WHERE h.last_sequence_number>0 AND e.event_id IS NULL')===0,'native_audit_chain_heads_coherent');
evCheck(hash('sha256',json_encode(array_slice(Platform\Services\CanonicalAuditPolicyRegistry::canonicalRows(),0,52),JSON_UNESCAPED_SLASHES))==='2444b5ba944db0bc0a29390a073699e1b85efe4e061376bf6375c781df6db581','all_previously_published_52_audit_policies_unchanged');
evCheck(hash('sha256',json_encode(array_slice(Platform\Services\CanonicalAuditPolicyRegistry::canonicalRows(),0,53),JSON_UNESCAPED_SLASHES))==='ad2e8bc54009bebf66b434f3f648b3b1bd0cbca47a16e287608dd729dddd057a','all_53_published_policies_including_C4_EV01_unchanged');
evCheck((new Platform\Services\CorrelatableOperationCatalog())->operationForEvent('PHYSICIAN_AUTHORITY_CHANGED')==='PHYSICIAN_AUTHORITY_LIFECYCLE'
    &&(new Platform\Services\SourceModuleCatalog())->moduleForEvent('PHYSICIAN_AUTHORITY_CHANGED')==='AUTH','finite_authority_audit_catalogue');
$metadata=implode('\n',$p->query("SELECT metadata_json FROM platform_audit_events WHERE action='PHYSICIAN_AUTHORITY_CHANGED'")->fetchAll(PDO::FETCH_COLUMN));
evCheck(!str_contains($metadata,'+1555')&&!str_contains($metadata,'C4EV-SYNTHETIC')&&!str_contains($metadata,'private/physician-evidence/')
    &&!str_contains($metadata,'C4-EV01-SYNTHETIC-DOCUMENT'),'authority_audit_excludes_contacts_credentials_documents_keys');
evCheck((int)iaValue($p,"SELECT COUNT(*) FROM auth_physician_authority_history h JOIN platform_audit_events e ON e.event_id=h.audit_physical_id
    WHERE e.resource_reference<>h.authority_id OR e.action<>'PHYSICIAN_AUTHORITY_CHANGED'")===0,'journal_references_exact_canonical_authority_events');
foreach(['profiles_physician_admissions','auth_account_professional_bindings','auth_physician_issuance_workflows'] as $table)
    evSqlDeny(fn()=>$p->exec('DELETE FROM '.$table),'45000','authority_history_delete_denied_'.$table);
echo "C4_ADM02_IMP02_DISPOSABLE_ISSUER=PASS\n";
