<?php
declare(strict_types=1);
// Only the owning disposable runner provides credentials, Valkey and a private temp root.
if (PHP_SAPI!=='cli') exit(1);
require_once __DIR__.'/../http/IdentityHttpComposition.php';
Identity\Http\IdentityHttpComposition::registerAutoloader();
require_once __DIR__.'/../../media/storage/LocalPersistentPrivateMediaStorage.php';
require_once __DIR__.'/../../profiles/services/VerifiedPhysicianIdentityService.php';
require_once __DIR__.'/../../profiles/services/VerifiedDoctorCredentialService.php';
require_once __DIR__.'/../../agenda/contracts/OtpProviderPort.php';

use Identity\Accreditation\{EvidenceAuthority,EvidenceCipher,EvidenceAudit,PhoneVerificationService,PhysicianEvidenceService,ProfileRequestMatcher};
use Identity\Contracts\{Clock,MfaSecretGeneratorPort};
use Identity\Repositories\{IdentityAccountRepository,AccountCredentialRepository,AccountConsentRepository,OneTimeTokenRepository};
use Identity\Services\{SessionService,SessionTokenCodec,RateLimitService,CredentialAuthenticationService,GovernanceSecurityContext,StaffMfaService};

require_once __DIR__.'/support/C4EvidenceHarness.php';

if (($argv[1]??'')==='--confirm-worker') {
    $input=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);$p=evConnect();$clock=new EvClock(new DateTimeImmutable($input['now']));$s=evStack($p,$clock);
    try {
        $actor=$s['sessions']->validate($input['session'])->context();if($actor===null)throw new RuntimeException('worker_session_denied');
        $s['evidence']->confirm($actor,$input['request_id'],$input['version'],$input['confirmation'],true);echo 'OK';
    } catch (RuntimeException $e) {
        if (!in_array($e->getMessage(),['c4_request_missing_or_stale','c4_confirmation_denied'],true)) {fwrite(STDERR,'unexpected_worker_failure');exit(1);}echo 'DENIED';
    }
    exit;
}

$p=evConnect();$clock=new EvClock(new DateTimeImmutable('now',new DateTimeZone('UTC')));putenv('C4_EV_QA_KEY='.base64_encode(random_bytes(32)));$s=evStack($p,$clock);
$otherConnection=evConnect();
evDeny(fn()=>new PhoneVerificationService($s['authority'],$s['cipher'],new EvidenceAudit($otherConnection)),'c4_audit_connection_mismatch','phone_audit_requires_same_transaction_connection');
$foreignMfa=new StaffMfaService($otherConnection,base64_encode(str_repeat('m',32)),'c4-qa-mfa',new EvMfaSecret(),$clock,$s['security']);
evDeny(fn()=>new PhysicianEvidenceService($s['authority'],$s['cipher'],$s['storage'],$foreignMfa),'c4_mfa_connection_mismatch','evidence_mfa_requires_same_transaction_connection');
evSqlDeny(fn()=>$p->query('SELECT account_id FROM mxmed.auth_accounts'),'42000','cross_database_read_denied');
evSqlDeny(fn()=>$p->exec('UPDATE mxmed.auth_accounts SET status=status WHERE 0'),'42000','cross_database_write_denied');
foreach(['ev_director'=>'DIRECTOR','ev_reviewer'=>'MASTER_ADMIN','ev_no_grant'=>'MASTER_ADMIN','ev_operator'=>null,'ev_other'=>null] as $id=>$class){
    $p->prepare("INSERT INTO auth_accounts(account_id,email_address,email_normalized,status,email_verified_at) VALUES(?,?,?,'active',UTC_TIMESTAMP())")->execute([$id,$id.'@example.invalid',$id.'@example.invalid']);
    $p->prepare('INSERT INTO auth_account_credentials(account_id,password_hash,password_changed_at,credential_version) VALUES(?,?,UTC_TIMESTAMP(),1)')->execute([$id,password_hash(EV_PASSWORD,PASSWORD_ARGON2ID)]);
    if($class!==null)$p->prepare("INSERT INTO internal_staff(account_id,governance_class,status) VALUES(?,?,'ACTIVE')")->execute([$id,$class]);
}
$p->exec("INSERT INTO internal_operator_grants(grant_id,account_id,capability,status) VALUES(UUID(),'ev_reviewer','physician_accreditation_review','ACTIVE')");
$reviewer=evLogin($s,'ev_reviewer@example.invalid');$director=evLogin($s,'ev_director@example.invalid');$other=evLogin($s,'ev_other@example.invalid');$unauthorized=evLogin($s,'ev_no_grant@example.invalid');
$notifications=new Identity\Adapters\InMemoryIdentityNotificationAdapter();$consents=new AccountConsentRepository($p);$tokens=new OneTimeTokenRepository($p);
$register=new Identity\Services\RegistrationService($p,$s['accounts'],$s['credentials'],$consents,$tokens,$s['limits'],$notifications,$clock);
$registration=$register->register(['email'=>'ev_physician@example.invalid','password'=>EV_PASSWORD,'terms_version'=>'synthetic-v1','privacy_notice_version'=>'synthetic-v1','terms_accepted'=>true,'privacy_notice_accepted'=>true],['ip'=>null]);
evCheck($registration->accepted(),'canonical_registration_reused');
$email=new Identity\Services\EmailVerificationService($p,$s['accounts'],$consents,$tokens,$s['limits'],$notifications,$clock);
$emailDecision=$email->verify($notifications->lastMessage()->token(),['ip'=>'127.0.0.1']);
evCheck($emailDecision->verified(),'canonical_email_verification_reused_'.$emailDecision->reasonCode());
$physician=evLogin($s,'ev_physician@example.invalid');$account=$physician['context']->accountId();
$enroll=new Identity\Services\GovernanceStaffMfaCeremony($p,$s['mfa'],$s['security'],new Identity\Adapters\CanonicalGovernanceAuditAdapter($p),$clock,['ev_director']);
$e=$enroll->begin($reviewer['context'],$director['context'],$reviewer['email'],EV_PASSWORD,$director['email'],EV_PASSWORD);
$enroll->activate($reviewer['context'],$e['factor_id'],StaffMfaService::totp(str_repeat('e',20),intdiv($clock->now()->getTimestamp(),30)));
$professional=evProfessional($p,'primary','ev_reviewer');$second=evProfessional($p,'other','ev_reviewer');
$clock->time=new DateTimeImmutable('now',new DateTimeZone('UTC')); // CRD02 records its canonical server time.

evDeny(fn()=>$s['evidence']->create($physician['context'],'CLAIM_EXISTING_PROFILE',...$professional),'c4_phone_verification_required','unverified_phone_blocks_professional_intake');
evDeny(fn()=>(new PhoneVerificationService($s['authority'],$s['cipher'],$s['audit']))->issue($physician['context'],'+15550001001',$physician['email'],EV_PASSWORD),'c4_sms_delivery_unavailable','unconfigured_sms_fails_closed');
$otp=$s['phone']->issue($physician['context'],'+15550001001',$physician['email'],EV_PASSWORD);
evCheck(!$s['phone']->verify($physician['context'],$otp['challenge_id'],$otp['contact_version'],'wrong'),'wrong_otp_denied');
evCheck($s['phone']->verify($physician['context'],$otp['challenge_id'],$otp['contact_version'],$s['sms']->last),'phone_verified_through_delivery_and_otp');
evCheck(!$s['phone']->verify($physician['context'],$otp['challenge_id'],$otp['contact_version'],$s['sms']->last),'phone_otp_replay_denied');
evDeny(fn()=>$s['phone']->issue($physician['context'],'+15550001002',$physician['email'],EV_PASSWORD),'c4_phone_resend_throttled','phone_resend_throttle');
$draft=$s['evidence']->create($physician['context'],'CLAIM_EXISTING_PROFILE',...$professional);
evDeny(fn()=>$s['evidence']->create($physician['context'],'CLAIM_EXISTING_PROFILE',$professional[0],$second[1],$professional[2]),'c4_professional_sources_denied','wrong_profile_identity_denied');
evSqlDeny(fn()=>$s['evidence']->create($physician['context'],'CLAIM_EXISTING_PROFILE',...$professional),'23000','duplicate_active_request_denied');
$files=[];foreach(['OFFICIAL_ID','PROFESSIONAL_CREDENTIAL'] as $kind){$f=$s['evidence']->addDocument($physician['context'],$draft['request_id'],$draft['version'],$kind,"%PDF-1.4\nC4-EV01-SYNTHETIC-DOCUMENT\n%%EOF");$draft['version']=$f['version'];$files[]=$f['file_id'];}
$r=$s['evidence']->submit($physician['context'],$draft['request_id'],$draft['version']);$r['files']=$files;
evSqlDeny(fn()=>$p->prepare('UPDATE auth_physician_evidence_requests SET profile_doctor_id=? WHERE request_id=?')->execute([$second[0],$r['request_id']]),'45000','claim_origin_sql_immutable');
evDeny(fn()=>$s['evidence']->beginReview($physician['context'],$r['request_id'],$r['version'],'APPROVED',$physician['email'],EV_PASSWORD),'c4_self_review_denied','self_approval_denied');
evDeny(fn()=>$s['evidence']->beginReview($unauthorized['context'],$r['request_id'],$r['version'],'APPROVED',$unauthorized['email'],EV_PASSWORD),'c4_reviewer_capability_required','staff_without_exact_grant_denied');
evDeny(fn()=>$s['evidence']->readDocument($other['context'],$r['request_id'],$r['version'],$files[0]),'c4_reviewer_capability_required','private_evidence_cross_account_denied');
$proof=evProof($s,$clock,$reviewer,$r,'APPROVED');$facts=array_fill_keys($files,null);
evDeny(fn()=>$s['evidence']->decide($reviewer['context'],$r['request_id'],$r['version'],$proof['proof_id'],$proof['proof'],true,$facts),'c4_actual_document_access_required','approval_without_actual_document_access_denied');
foreach($files as $file){$d=$s['evidence']->readDocument($reviewer['context'],$r['request_id'],$r['version'],$file);evCheck(str_starts_with($d['bytes'],'%PDF')&&$d['headers']['Cache-Control']==='no-store, private, max-age=0'&&!isset($d['storage_key']),'scoped_private_document_read_no_store');}
evDeny(fn()=>$s['evidence']->decide($reviewer['context'],$r['request_id'],$r['version'],$proof['proof_id'],$proof['proof'],false,$facts),'c4_human_relationship_attestation_required','human_relationship_confirmation_required');
$expiredFacts=$facts;$expiredFacts[$files[0]]=$clock->now()->modify('-1 second')->format('Y-m-d\TH:i:s\Z');
evDeny(fn()=>$s['evidence']->decide($reviewer['context'],$r['request_id'],$r['version'],$proof['proof_id'],$proof['proof'],true,$expiredFacts),'c4_reviewed_document_expired','expired_identity_document_denied');
$p->exec('UPDATE gov03b_qa_faults SET enabled=1');
try {$s['evidence']->decide($reviewer['context'],$r['request_id'],$r['version'],$proof['proof_id'],$proof['proof'],true,$facts);throw new LogicException('audit_failure_did_not_abort');}
catch(PDOException $failure){evCheck((string)$failure->getCode()==='45000','audit_failure_denies_approval');}
finally {$p->exec('UPDATE gov03b_qa_faults SET enabled=0');}
evCheck($p->query("SELECT status FROM auth_staff_mfa_proofs WHERE proof_id=".$p->quote($proof['proof_id']))->fetchColumn()==='VERIFIED','audit_failure_rolls_back_mfa_consumption');
evCheck($p->query("SELECT status FROM auth_physician_evidence_requests WHERE request_id=".$p->quote($r['request_id']))->fetchColumn()==='SUBMITTED','audit_failure_rolls_back_review');
$approved=$s['evidence']->decide($reviewer['context'],$r['request_id'],$r['version'],$proof['proof_id'],$proof['proof'],true,$facts);
evCheck($approved['status']==='APPROVED','one_authorized_human_reviewer_accepted');
evDeny(fn()=>evConsumer($s,$p,$approved,$account,$professional[0]),'c4_confirmed_scope_required','unconfirmed_account_cannot_supply_issuer_evidence');
evDeny(fn()=>$s['evidence']->issueConfirmation($other['context'],$approved['request_id'],$approved['version'],$other['email'],EV_PASSWORD),'c4_applicant_scope_denied','different_account_cannot_confirm_same_person');
$confirm=$s['evidence']->issueConfirmation($physician['context'],$approved['request_id'],$approved['version'],$physician['email'],EV_PASSWORD);
evDeny(fn()=>$s['evidence']->confirm($physician['context'],$approved['request_id'],$approved['version'],$confirm['confirmation'],false),'c4_confirmation_denied','explicit_affirmative_acceptance_required');
$replacement=$s['evidence']->issueConfirmation($physician['context'],$approved['request_id'],$approved['version'],$physician['email'],EV_PASSWORD);
evDeny(fn()=>$s['evidence']->confirm($physician['context'],$approved['request_id'],$approved['version'],$confirm['confirmation'],true),'c4_confirmation_denied','reissued_confirmation_supersedes_old_nonce');
$input=['now'=>$clock->now()->format(DATE_ATOM),'session'=>$physician['token'],'request_id'=>$approved['request_id'],'version'=>$approved['version'],'confirmation'=>$replacement['confirmation']];$workers=[];
for($i=0;$i<2;$i++){$proc=proc_open([PHP_BINARY,__FILE__,'--confirm-worker'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode($input,JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
$out=[];foreach($workers as [$proc,$pipes]){$out[]=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);evCheck(proc_close($proc)===0&&$error==='','confirmation_worker_clean');}sort($out);evCheck($out===['DENIED','OK'],'concurrent_confirmation_single_consumer');
$confirmed=['request_id'=>$approved['request_id'],'version'=>$approved['version']+1];
$authority=evConsumer($s,$p,$confirmed,$account,$professional[0]);
evCheck($authority['account_id']===$account&&$authority['profile_doctor_id']===$professional[0]&&(int)$authority['verified_identity_id']===$professional[1]
    &&(int)$authority['professional_credential_id']===$professional[2]&&$authority['review_audit_reference']!==null&&$authority['confirmation_audit_reference']!==null,'exact_confirmed_account_person_profile_port');
evDeny(fn()=>evConsumer($s,$p,$confirmed,$account,$second[0]),'c4_confirmed_scope_required','issuer_wrong_profile_denied');
evDeny(fn()=>$s['evidence']->confirm($physician['context'],$approved['request_id'],$approved['version'],$replacement['confirmation'],true),'c4_request_missing_or_stale','confirmation_replay_denied');

// Source/capability invalidations are adversarial disposable transactions, rolled back.
foreach([
    ["UPDATE internal_operator_grants SET status='REVOKED',revoked_at=UTC_TIMESTAMP() WHERE account_id='ev_reviewer' AND capability='physician_accreditation_review'",'c4_reviewer_capability_required','reviewer_revocation_invalidates_port'],
    ["UPDATE profiles_doctor_credentials SET lifecycle_status='REVOKED' WHERE credential_id=".$professional[2],'c4_professional_sources_denied','credential_revocation_invalidates_port'],
    ["UPDATE profiles_verified_identities SET source_reference='changed' WHERE verified_identity_id=".$professional[1],'c4_professional_revision_changed','changed_identity_invalidates_port'],
    ["UPDATE profiles_doctors SET professional_license='C4EV-SYNTHETIC-primary' WHERE doctor_id='c4ev_other'",'c4_license_conflict','legacy_license_conflict_invalidates_port']
] as [$sql,$reason,$label]){$p->beginTransaction();try{$p->exec($sql);evDeny(fn()=>$s['evidence']->requireConfirmed($p,$confirmed['request_id'],$confirmed['version'],$account,$professional[0]),$reason,$label);}finally{$p->rollBack();}}

$matcher=new ProfileRequestMatcher($s['authority']);
$matches=$matcher->candidates($reviewer['context'],$confirmed['request_id'],'C4EV-SYNTHETIC-primary');
evCheck($matches['model']==='EXACT_PROFESSIONAL_LICENSE_MATCH'&&$matches['selected_profile_id']===null,'license_match_does_not_assign_profile');
evCheck($matcher->candidates($reviewer['context'],$confirmed['request_id'],null,'Synthetic','Physician')['model']==='NO_MATCH','name_alone_is_not_authority_or_composite_match');
$p->prepare("INSERT INTO consultorios(doctor_id,consultorio_id,estado,municipio) VALUES(?,'synthetic-location','Synthetic state','Synthetic city')")->execute([$professional[0]]);
$composite=$matcher->candidates($reviewer['context'],$confirmed['request_id'],null,'Synthetic','Physician','Synthetic specialty','Synthetic state','Synthetic city');
evCheck($composite['model']==='COMPOSITE_PROFESSIONAL_MATCH'&&$composite['selected_profile_id']===null&&$composite['candidate_profile_ids']===[$professional[0]],'composite_requires_professional_and_geographic_context_without_assignment');

evPhone($s,$other,'+15550001003');
$extra=evRequest($s,$other,$second);$extraProof=evProof($s,$clock,$reviewer,$extra,'APPROVED');
foreach($extra['files'] as $file)$s['evidence']->readDocument($reviewer['context'],$extra['request_id'],$extra['version'],$file);
$extraApproved=$s['evidence']->decide($reviewer['context'],$extra['request_id'],$extra['version'],$extraProof['proof_id'],$extraProof['proof'],true,array_fill_keys($extra['files'],null));
$expired=$s['evidence']->issueConfirmation($other['context'],$extraApproved['request_id'],$extraApproved['version'],$other['email'],EV_PASSWORD);
$clock->advance('+301 seconds');
evDeny(fn()=>$s['evidence']->confirm($other['context'],$extraApproved['request_id'],$extraApproved['version'],$expired['confirmation'],true),'c4_confirmation_denied','confirmation_expires_after_five_minutes');
$current=$s['evidence']->issueConfirmation($other['context'],$extraApproved['request_id'],$extraApproved['version'],$other['email'],EV_PASSWORD);
$newPhone=$s['phone']->issue($other['context'],'+15550001004',$other['email'],EV_PASSWORD);
evDeny(fn()=>$s['evidence']->confirm($other['context'],$extraApproved['request_id'],$extraApproved['version'],$current['confirmation'],true),'c4_phone_verification_required','contact_change_invalidates_pending_confirmation');
$otherSession=evLogin($s,$other['email']);
evDeny(fn()=>$s['phone']->verify($otherSession['context'],$newPhone['challenge_id'],$newPhone['contact_version'],$s['sms']->last),'c4_phone_session_mismatch','otp_bound_to_issuing_identity_session');
evCheck($s['phone']->verify($other['context'],$newPhone['challenge_id'],$newPhone['contact_version'],$s['sms']->last),'changed_contact_requires_its_own_otp');
evDeny(fn()=>$s['evidence']->confirm($other['context'],$extraApproved['request_id'],$extraApproved['version'],$current['confirmation'],true),'c4_phone_verification_required','new_verified_contact_does_not_reuse_old_review_version');
$extraProof=evProof($s,$clock,$reviewer,$extraApproved,'REVOKED');
$extraRevoked=$s['evidence']->decide($reviewer['context'],$extraApproved['request_id'],$extraApproved['version'],$extraProof['proof_id'],$extraProof['proof'],false);
evDeny(fn()=>$s['evidence']->confirm($other['context'],$extraRevoked['request_id'],$extraRevoked['version'],$current['confirmation'],true),'c4_confirmation_denied','revoked_review_cannot_consume_pending_nonce');
$clock->advance('+61 seconds');$attempt=$s['phone']->issue($other['context'],'+15550001005',$other['email'],EV_PASSWORD);
for($i=0;$i<5;$i++)evCheck(!$s['phone']->verify($other['context'],$attempt['challenge_id'],$attempt['contact_version'],'wrong'),'otp_attempt_limit_'.$i);
evCheck(!$s['phone']->verify($other['context'],$attempt['challenge_id'],$attempt['contact_version'],$s['sms']->last),'otp_correct_code_cannot_bypass_lockout');
$clock->advance('+61 seconds');$attempt=$s['phone']->issue($other['context'],'+15550001006',$other['email'],EV_PASSWORD);$clock->advance('+601 seconds');
evCheck(!$s['phone']->verify($other['context'],$attempt['challenge_id'],$attempt['contact_version'],$s['sms']->last),'otp_expired_at_ten_minutes');
$clock->advance('+61 seconds');$s['sms']->accept=false;$attempt=$s['phone']->issue($other['context'],'+15550001007',$other['email'],EV_PASSWORD);
evCheck(!$attempt['delivery_accepted']&&!$s['phone']->verify($other['context'],$attempt['challenge_id'],$attempt['contact_version'],$s['sms']->last),'failed_delivery_never_verifies_contact');$s['sms']->accept=true;
$clock->advance('+61 seconds');$sent=$s['sms']->sent;
evDeny(fn()=>$s['phone']->issue($other['context'],'+15550001008',$other['email'],EV_PASSWORD),'c4_phone_send_limit','send_limit_survives_contact_and_challenge_changes');
evCheck($s['sms']->sent===$sent,'rate_limited_issue_sends_nothing');
// Return to the still-active primary contact for pending conflict/new-profile intake.
$conflictActor=$physician;
$conflict=evRequest($s,$conflictActor,$second,'CONFLICT_OR_TRANSFER_REVIEW');
evDeny(fn()=>$s['evidence']->beginReview($reviewer['context'],$conflict['request_id'],$conflict['version'],'APPROVED',$reviewer['email'],EV_PASSWORD),'c4_exception_or_new_profile_resolution_required','conflict_transfer_cannot_routinely_approve');
$new=evRequest($s,$conflictActor,[],'REQUEST_NEW_PROFILE');
evDeny(fn()=>$s['evidence']->beginReview($reviewer['context'],$new['request_id'],$new['version'],'APPROVED',$reviewer['email'],EV_PASSWORD),'c4_exception_or_new_profile_resolution_required','new_profile_request_does_not_create_or_select_profile');

$operator=evLogin($s,'ev_operator@example.invalid');
$p->prepare("INSERT INTO auth_account_memberships(membership_id,account_id,profile_doctor_id,role_code,scope_code,status,assignment_source) VALUES('ev-operator-membership','ev_operator',?,'collaborator','profile','active','owner_operator_invitation')")->execute([$second[0]]);
evDeny(fn()=>$s['evidence']->create($operator['context'],'CLAIM_EXISTING_PROFILE',...$second),'c4_nonmedical_operator_denied','nonmedical_operator_cannot_apply_as_physician');
require_once dirname(__DIR__,3).'/api/_lib/clinical_professional_authority.php';
foreach(['PRESCRIPTION_SIGN','PRESCRIPTION_ISSUE'] as $operation){try{clinical_professional_authority_assert(['doctor_id'=>$professional[0],'user_id'=>$account,'role_code'=>'owner'],$operation);throw new LogicException('clinical_gate_opened');}catch(RuntimeException){evCheck(true,'clinical_signing_stays_closed_'.$operation);}}
$proof=evProof($s,$clock,$reviewer,$confirmed,'REVOKED');$revoked=$s['evidence']->decide($reviewer['context'],$confirmed['request_id'],$confirmed['version'],$proof['proof_id'],$proof['proof'],false);
evDeny(fn()=>evConsumer($s,$p,$revoked,$account,$professional[0]),'c4_confirmed_scope_required','evidence_revocation_invalidates_issuer_port');

$audit=$p->query("SELECT metadata_json FROM platform_audit_events WHERE action='PHYSICIAN_EVIDENCE_CHANGED'")->fetchAll(PDO::FETCH_COLUMN);
$serialized=implode('\n',$audit);
evCheck(!str_contains($serialized,'C4-EV01-SYNTHETIC-DOCUMENT')&&!str_contains($serialized,'+15550001001')&&!str_contains($serialized,'private/physician-evidence/')
    &&!str_contains($serialized,$replacement['confirmation']),'audit_contains_no_documents_contacts_keys_or_confirmation');
$objects=$p->query('SELECT storage_key FROM auth_physician_evidence_files')->fetchAll(PDO::FETCH_COLUMN);
foreach($objects as $key){$stream=$s['storage']->openReadStream($key);$bytes=stream_get_contents($stream['stream']);fclose($stream['stream']);evCheck(!str_contains($bytes,'%PDF')&&!str_contains($bytes,'C4-EV01-SYNTHETIC-DOCUMENT'),'private_objects_encrypted_at_rest');}
evDeny(fn()=>$s['cipher']->open($bytes,'different-request-scope','c4-qa-key'),'c4_private_integrity_failed','ciphertext_cannot_cross_request_scope');
evCheck((int)$p->query('SELECT COUNT(*) FROM profiles_physician_admissions')->fetchColumn()===0
    &&(int)$p->query('SELECT COUNT(*) FROM auth_account_professional_bindings')->fetchColumn()===0,'no_admission_or_professional_binding_issued');
evCheck((int)$p->query("SELECT COUNT(*) FROM auth_account_memberships WHERE role_code='owner'")->fetchColumn()===0,'no_owner_assigned');
evCheck((int)$p->query('SELECT COUNT(*) FROM platform_audit_stream_heads h LEFT JOIN platform_audit_events e ON e.stream_key=h.stream_key AND e.sequence_number=h.last_sequence_number AND e.event_hash=h.last_event_hash WHERE h.last_sequence_number>0 AND e.event_id IS NULL')->fetchColumn()===0,'native_audit_chain_heads_coherent');
evCheck(hash('sha256',json_encode(array_slice(Platform\Services\CanonicalAuditPolicyRegistry::canonicalRows(),0,52),JSON_UNESCAPED_SLASHES))==='2444b5ba944db0bc0a29390a073699e1b85efe4e061376bf6375c781df6db581','all_52_published_audit_policies_unchanged');
evCheck((new Platform\Services\CorrelatableOperationCatalog())->operationForEvent('PHYSICIAN_EVIDENCE_CHANGED')==='PHYSICIAN_EVIDENCE_LIFECYCLE'
    &&(new Platform\Services\SourceModuleCatalog())->moduleForEvent('PHYSICIAN_EVIDENCE_CHANGED')==='AUTH','new_audit_event_finitely_catalogued');
// Exercise the future original-app boundary with canonical cookies and authenticated CSRF.
$csrf=new Identity\Http\CsrfTokenService(str_repeat('c',32),900,$clock,'https://localhost:8443');
$identity=(new ReflectionClass(Identity\Http\IdentityHttpComposition::class))->newInstanceWithoutConstructor();
foreach(['sessions'=>$s['sessions'],'csrf'=>$csrf,'allowedOrigin'=>'https://localhost:8443'] as $field=>$value)(new ReflectionProperty($identity,$field))->setValue($identity,$value);
$body=['csrf_token'=>$csrf->issueAuthenticated($physician['context']->session()->tokenDigest())];$server=['REQUEST_METHOD'=>'POST','HTTPS'=>'on','HTTP_ORIGIN'=>'https://localhost:8443'];$cookies=['__Host-mxmed_session'=>$physician['token']];
evCheck(Identity\Accreditation\EvidenceHttpBoundary::access($identity,$server,$cookies,$body,json_encode($body),'')->accountId()===$account,'canonical_http_boundary_positive');
foreach([[401,$server,[],$body],[403,array_replace($server,['HTTP_ORIGIN'=>'https://other.invalid']),$cookies,$body],[403,$server,$cookies,[]],
    [503,['REQUEST_METHOD'=>'POST','REMOTE_ADDR'=>'127.0.0.1','HTTP_X_FORWARDED_PROTO'=>'https'],$cookies,$body]] as [$status,$srv,$cookie,$input]) {
    try{Identity\Accreditation\EvidenceHttpBoundary::access($identity,$srv,$cookie,$input,json_encode($input),'');throw new LogicException('http_boundary_bypassed');}
    catch(Identity\Http\GovernanceHttpDenied $denied){evCheck($denied->status===$status,'canonical_http_boundary_denied_'.$status);}
}
echo "C4_EV01_DISPOSABLE_INTEGRATION=PASS\n";
