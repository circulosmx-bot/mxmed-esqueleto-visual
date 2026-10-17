<?php
declare(strict_types=1);
/** Real canonical services, synthetic actors, owned random schema/Valkey only. */
if (PHP_SAPI !== 'cli' || getenv('ASE_ALIGN01_QA') !== '1' || getenv('ACT03_QA') !== '1') exit(1);
require_once __DIR__.'/../http/IdentityHttpComposition.php';
Identity\Http\IdentityHttpComposition::registerAutoloader();
require_once __DIR__.'/../../media/storage/LocalPersistentPrivateMediaStorage.php';
require_once __DIR__.'/../../profiles/services/VerifiedPhysicianIdentityService.php';
require_once __DIR__.'/../../profiles/services/VerifiedDoctorCredentialService.php';
require_once __DIR__.'/../../agenda/contracts/OtpProviderPort.php';
require_once __DIR__.'/support/C4EvidenceHarness.php';

use Identity\Accreditation\{PublicEnrollmentService,ReviewedEnrollmentService};
use Identity\Services\{StaffMfaService,GovernanceStaffMfaCeremony,InternalGovernanceService,InternalCapabilityCatalog};
use Identity\ProfessionalSources\SourceProvisioningService;

function aseValue(PDO $p,string $sql,array $args=[]):mixed {$q=$p->prepare($sql);$q->execute($args);return $q->fetchColumn();}
function aseAccount(PDO $p,string $id,string $hash):void {
    $p->prepare("INSERT INTO auth_accounts(account_id,email_address,email_normalized,status,email_verified_at) VALUES(?,?,?,'active',UTC_TIMESTAMP())")->execute([$id,$id.'@example.invalid',$id.'@example.invalid']);
    $p->prepare('INSERT INTO auth_account_credentials(account_id,password_hash,password_changed_at,credential_version) VALUES(?,?,UTC_TIMESTAMP(),1)')->execute([$id,$hash]);
}
function aseDeny(callable $fn,string $label,array $reasons=[]):void {
    try {$fn();} catch (RuntimeException $e) {
        if ($reasons!==[]&&!in_array($e->getMessage(),$reasons,true)) throw new RuntimeException('FAIL '.$label.' unexpected '.$e->getMessage(),0,$e);
        evCheck(true,'ASE_'.$label);return;
    }
    throw new RuntimeException('FAIL ASE_'.$label.' unexpectedly allowed');
}
function aseChallenge(array $s,EvClock $clock,array $actor,string $operation,string $beneficiary='ase_matrix_beneficiary',?string $profile='ase_matrix_profile'):array {
    $clock->advance('+31 seconds');
    $binding=$s['security']->reauthenticate($actor['context'],$actor['email'],EV_PASSWORD,['ip'=>null]);
    $case='asecase_'.bin2hex(random_bytes(12));
    if(!in_array($operation,['PHYSICIAN_ENROLLMENT_REVIEW','PHYSICIAN_EVIDENCE_REVIEW','PROFESSIONAL_SOURCE_PROVISION'],true)){
        // Reserved-operation negatives must fail at the MFA policy, before any
        // parent/FK or schema CHECK could provide a misleading denial.
        return $s['mfa']->issueChallenge($actor['context']->accountId(),$operation,$case,$beneficiary,$beneficiary,$profile,$binding);
    }
    $p=$s['authority']->pdo;$p->beginTransaction();
    try{
        // These synthetic parent cases exercise the MFA port only, never a
        // domain decision. Preserve the FK and active-target uniqueness rules;
        // retire only this helper's preceding unfinished synthetic ceremony.
        $p->prepare("UPDATE auth_governance_cases SET status='REVOKED' WHERE LEFT(case_id,8)='asecase_' AND operation=? AND beneficiary_account_id=? AND profile_doctor_id<=>? AND status IN ('OPEN','APPROVED')")->execute([$operation,$beneficiary,$profile]);
        $p->prepare("INSERT INTO auth_governance_cases(case_id,operation_id,operation,requester_account_id,beneficiary_account_id,profile_doctor_id,reason,status,expires_at,security_context) VALUES(?,?,?,?,?,?,?,'OPEN',?,?)")->execute([$case,$s['authority']->uuid(),$operation,$actor['context']->accountId(),$beneficiary,$profile,'ASE synthetic MFA binding contract only',$s['authority']->sqlTime($clock->now()->modify('+5 minutes')),json_encode(['contract'=>'ASE-MFA-BINDING-QA.1','reviewer'=>$binding],JSON_THROW_ON_ERROR)]);
        $result=$s['mfa']->issueChallenge($actor['context']->accountId(),$operation,$case,$beneficiary,$beneficiary,$profile,$binding)+['case_id'=>$case,'beneficiary'=>$beneficiary,'profile'=>$profile,'operation'=>$operation];
        $p->commit();return $result;
    }catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
}
function aseVerify(array $s,EvClock $clock,array $actor,array $challenge):array {
    return $s['mfa']->verifyChallenge($actor['context']->accountId(),(string)$actor['context']->session()->sessionId(),$actor['context']->principal()->credentialVersion(),$challenge['challenge_id'],$challenge['challenge'],StaffMfaService::totp(str_repeat('e',20),intdiv($clock->now()->getTimestamp(),30)));
}
function aseConsume(PDO $p,array $s,array $actor,array $challenge,array $proof,array $changes=[]):void {
    $v=array_replace(['actor'=>$actor['context']->accountId(),'session'=>(string)$actor['context']->session()->sessionId(),'version'=>$actor['context']->principal()->credentialVersion(),'operation'=>$challenge['operation'],'case'=>$challenge['case_id'],'target'=>$challenge['beneficiary'],'beneficiary'=>$challenge['beneficiary'],'profile'=>$challenge['profile']],$changes);
    $s['mfa']->consumeProof($p,$proof['proof_id'],$proof['proof'],$v['actor'],$v['session'],$v['version'],$v['operation'],$v['case'],$v['target'],$v['beneficiary'],$v['profile'],'ase-consumer');
}
function aseProof(array $s,EvClock $clock,array $actor,string $operation):array {$c=aseChallenge($s,$clock,$actor,$operation);return [$c,aseVerify($s,$clock,$actor,$c)];}
function aseApp(PDO $p,array $s,PublicEnrollmentService $enrollment,string $id,string $hash,string $profile,string $license):array {
    aseAccount($p,$id,$hash);$actor=evLogin($s,$id.'@example.invalid');$browser=PublicEnrollmentService::opaque();
    $ticket=$enrollment->ticket('CLAIM_EXISTING_PROFILE',$profile,'/profiles/doctor.php?doctor_id='.$profile,$browser);$o=$enrollment->start($ticket,$browser);
    $enrollment->bind($actor['context'],$o['application_id'],$o['handle'],$browser);
    $enrollment->save($actor['context'],$o['application_id'],0,$s['authority']->uuid(),['given_names'=>'Synthetic','first_surname'=>'Physician','second_surname'=>'','professional_license'=>$license,'specialty'=>'Synthetic specialty','state'=>'Estado sintético','city'=>'Ciudad sintética'],true);
    return ['actor'=>$actor,'application_id'=>$o['application_id'],'profile'=>$profile];
}
function aseSourceCase(SourceProvisioningService $source,array $actor,EvClock $clock,string $origin,string $action,array $facts=[]):array {
    $clock->advance('+31 seconds');$c=$source->begin($actor['context'],$origin,$action,$facts,$actor['email'],EV_PASSWORD);
    return $c+$source->verify($actor['context'],$c['case_id'],$c['challenge_id'],$c['challenge'],StaffMfaService::totp(str_repeat('e',20),intdiv($clock->now()->getTimestamp(),30)));
}

$p=evConnect();$db=(string)$p->query('SELECT DATABASE()')->fetchColumn();
if (!preg_match('/^mxmed_gate4d_preview_c4ui_([a-f0-9]{10})$/D',$db,$m)||$p->query('SELECT CURRENT_USER()')->fetchColumn()!=='gov03b_'.$m[1].'@127.0.0.1'||getenv('MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT')!=='0')throw new RuntimeException('ase_owned_target_required');
if ((int)aseValue($p,"SELECT COUNT(*) FROM auth_accounts WHERE account_id LIKE 'ase_%'")!==0)throw new RuntimeException('ase_fresh_actors_required');
$clock=new EvClock(new DateTimeImmutable('+2 minutes',new DateTimeZone('UTC')));$s=evStack($p,$clock);$hash=password_hash(EV_PASSWORD,PASSWORD_ARGON2ID);
$governance=new InternalGovernanceService($p,new Identity\Http\CanonicalHttpSessionResolver($s['sessions']));
$director=evLogin($s,'res_director@example.invalid');$directorCookies=['__Host-mxmed_session'=>$director['token']];
aseAccount($p,'ase_master',$hash);$governance->execute($directorCookies,'appoint_master','ase_master');
$governance->execute($directorCookies,'grant','ase_master',InternalCapabilityCatalog::MANAGE_ADVISORS);
$governance->execute($directorCookies,'delegate','ase_master','physician_accreditation_review');
$master=evLogin($s,'ase_master@example.invalid');$masterCookies=['__Host-mxmed_session'=>$master['token']];
$spec=['ase_review'=>['physician_accreditation_review'],'ase_source'=>['professional_source_provision'],'ase_both'=>['physician_accreditation_review','professional_source_provision'],'ase_neither'=>[]];
for($n=0;$n<10;$n++)$spec['ase_ten_'.$n]=[$n%2===0?'physician_accreditation_review':'professional_source_provision'];
$actors=[];
foreach($spec as $id=>$caps){aseAccount($p,$id,$hash);$governance->execute($directorCookies,'register',$id);foreach($caps as $cap)$governance->execute($id==='ase_review'?$masterCookies:$directorCookies,'grant',$id,$cap);$actors[$id]=evLogin($s,$id.'@example.invalid');}
aseAccount($p,'ase_matrix_beneficiary',$hash);
$p->prepare('INSERT INTO profiles_doctors(doctor_id,display_name) VALUES(?,?)')->execute(['ase_matrix_profile','Synthetic MFA scope only']);
evCheck((int)aseValue($p,"SELECT COUNT(*) FROM internal_staff WHERE account_id LIKE 'ase_%' AND governance_class='ADVISOR' AND status='ACTIVE'")===14,'ASE_ten_and_four_independent_advisors_no_two_seat_limit');
aseDeny(fn()=>$governance->execute($masterCookies,'grant','ase_neither','professional_source_provision'),'master_requires_exact_grant_administration_delegation',['governance_denied']);
$p->exec('UPDATE gov03b_qa_faults SET enabled=1');
evSqlDeny(fn()=>$governance->execute($directorCookies,'grant','ase_neither','physician_accreditation_review'),'45000','ASE_governance_audit_failure_rolls_back_grant');
$p->exec('UPDATE gov03b_qa_faults SET enabled=0');
evCheck((int)aseValue($p,"SELECT COUNT(*) FROM internal_operator_grants WHERE account_id='ase_neither'")===0,'ASE_governance_failed_audit_does_not_grant_capability');
foreach(['grant','appoint_master','delegate'] as $operation)aseDeny(fn()=>$governance->execute(['__Host-mxmed_session'=>$actors['ase_both']['token']],$operation,'ase_both',in_array($operation,['grant','delegate'],true)?'professional_source_provision':null),'advisor_no_self_'.$operation,['governance_denied']);
aseDeny(fn()=>$governance->execute($directorCookies,'grant','ase_both',InternalCapabilityCatalog::MANAGE_ADVISORS),'advisor_cannot_receive_reserved_governance_capability',['governance_conflict']);

$ceremony=new GovernanceStaffMfaCeremony($p,$s['mfa'],$s['security'],new Identity\Adapters\CanonicalGovernanceAuditAdapter($p),$clock,['res_director','ase_master']);
$advisorRegistrar=new GovernanceStaffMfaCeremony($p,$s['mfa'],$s['security'],new Identity\Adapters\CanonicalGovernanceAuditAdapter($p),$clock,['ase_review','ase_source']);
aseDeny(fn()=>$advisorRegistrar->begin($actors['ase_review']['context'],$actors['ase_review']['context'],$actors['ase_review']['email'],EV_PASSWORD,$actors['ase_review']['email'],EV_PASSWORD),'self_enrollment_no_independent_registrar',['mfa_independent_registrar_required']);
aseDeny(fn()=>$advisorRegistrar->begin($actors['ase_review']['context'],$actors['ase_source']['context'],$actors['ase_review']['email'],EV_PASSWORD,$actors['ase_source']['email'],EV_PASSWORD),'advisor_cannot_be_MFA_registrar');
$unapproved=new GovernanceStaffMfaCeremony($p,$s['mfa'],$s['security'],new Identity\Adapters\CanonicalGovernanceAuditAdapter($p),$clock,[]);
aseDeny(fn()=>$unapproved->begin($actors['ase_review']['context'],$director['context'],$actors['ase_review']['email'],EV_PASSWORD,$director['email'],EV_PASSWORD),'unapproved_registrar_denied',['mfa_registrar_not_deployment_approved']);
aseDeny(fn()=>$ceremony->begin($actors['ase_neither']['context'],$director['context'],$actors['ase_neither']['email'],EV_PASSWORD,$director['email'],EV_PASSWORD),'advisor_without_routine_grant_not_enrollment_subject');
foreach($spec as $id=>$caps){if($caps===[])continue;$actor=$actors[$id];$registrar=$id==='ase_source'?$master:$director;
    $factor=$ceremony->begin($actor['context'],$registrar['context'],$actor['email'],EV_PASSWORD,$registrar['email'],EV_PASSWORD);
    evCheck(!str_contains((string)aseValue($p,'SELECT secret_ciphertext FROM auth_staff_mfa_factors WHERE factor_id=?',[$factor['factor_id']]),str_repeat('e',20)),'ASE_encrypted_factor_'.$id);
    if($id==='ase_review'){
        aseDeny(fn()=>$unapproved->activate($actor['context'],$factor['factor_id'],StaffMfaService::totp(str_repeat('e',20),intdiv($clock->now()->getTimestamp(),30))),'registrar_allowlist_revalidated_at_activation',['mfa_registrar_not_deployment_approved']);
        $p->exec('UPDATE gov03b_qa_faults SET enabled=1');
        evSqlDeny(fn()=>$ceremony->activate($actor['context'],$factor['factor_id'],StaffMfaService::totp(str_repeat('e',20),intdiv($clock->now()->getTimestamp(),30))),'45000','ASE_MFA_activation_audit_failure_rolls_back');
        $p->exec('UPDATE gov03b_qa_faults SET enabled=0');
        evCheck(aseValue($p,'SELECT status FROM auth_staff_mfa_factors WHERE factor_id=?',[$factor['factor_id']])==='PENDING','ASE_MFA_factor_stays_pending_after_failed_audit');
    }
    $ceremony->activate($actor['context'],$factor['factor_id'],StaffMfaService::totp(str_repeat('e',20),intdiv($clock->now()->getTimestamp(),30)));
}
aseDeny(fn()=>$ceremony->begin($actors['ase_review']['context'],$director['context'],$actors['ase_review']['email'],EV_PASSWORD,$director['email'],EV_PASSWORD),'duplicate_active_factor_denied',['mfa_existing_factor_requires_cancellation_or_recovery']);
$auditAdapter=new Identity\Adapters\CanonicalGovernanceAuditAdapter($p);
$reviewFactor=(string)aseValue($p,"SELECT factor_id FROM auth_staff_mfa_factors WHERE account_id='ase_review' AND status='ACTIVE'");
$sourceFactor=(string)aseValue($p,"SELECT factor_id FROM auth_staff_mfa_factors WHERE account_id='ase_source' AND status='ACTIVE'");
foreach([
    ['MFA_ENROLLMENT_STARTED','ase_review',$reviewFactor],
    ['FIRST_DIRECTOR_BOOTSTRAP','ase_review',$reviewFactor],
    ['OWNER_REPLACEMENT','ase_review',$reviewFactor],
    ['MFA_ENROLLMENT_ACTIVATED','ase_source',$reviewFactor],
    ['MFA_ENROLLMENT_ACTIVATED','ase_review','missing-synthetic-factor'],
    ['MFA_ENROLLMENT_ACTIVATED','ase_review',$sourceFactor],
] as $index=>[$operation,$target,$factor]){
    $p->beginTransaction();try{aseDeny(fn()=>$auditAdapter->append($actors['ase_review']['context'],$operation,$target,$factor),'advisor_cannot_forge_governance_audit_'.$index,['governance_audit_staff_required']);}finally{$p->rollBack();}
}
$differentSession=evLogin($s,'ase_review@example.invalid');
$p->beginTransaction();try{aseDeny(fn()=>$auditAdapter->append($differentSession['context'],'MFA_ENROLLMENT_ACTIVATED','ase_review',$reviewFactor),'factor_activation_audit_bound_to_enrolled_subject_session',['governance_audit_staff_required']);}finally{$p->rollBack();}
evCheck((int)aseValue($p,"SELECT COUNT(*) FROM platform_audit_events WHERE real_actor_reference='account:ase_review' AND resource_reference=? AND JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.producer_metadata.operation'))='MFA_ENROLLMENT_ACTIVATED'",[$reviewFactor])===1,'ASE_MFA_activation_audit_has_actual_advisor_subject_once');

$operations=['PHYSICIAN_ENROLLMENT_REVIEW'=>'physician_accreditation_review','PHYSICIAN_EVIDENCE_REVIEW'=>'physician_accreditation_review','PROFESSIONAL_SOURCE_PROVISION'=>'professional_source_provision'];
foreach($spec as $id=>$caps)foreach($operations as $operation=>$cap){
    if(!in_array($cap,$caps,true)){aseDeny(fn()=>aseChallenge($s,$clock,$actors[$id],$operation),'individual_missing_'.$id.'_'.$operation,['staff_mfa_operation_authority_denied']);continue;}
    [$c,$proof]=aseProof($s,$clock,$actors[$id],$operation);$p->beginTransaction();try{aseConsume($p,$s,$actors[$id],$c,$proof);$p->commit();}catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
    evCheck(aseValue($p,'SELECT status FROM auth_staff_mfa_proofs WHERE proof_id=?',[$proof['proof_id']])==='CONSUMED','ASE_independent_capability_'.$id.'_'.$operation);
}

// The exact live actor is bound to every scope dimension; the proof is not authority by itself.
[$c,$proof]=aseProof($s,$clock,$actors['ase_both'],'PHYSICIAN_ENROLLMENT_REVIEW');
foreach(['actor'=>'ase_review','session'=>'different-synthetic-session','version'=>2,'beneficiary'=>'different-beneficiary','target'=>'different-target','profile'=>'different-profile','case'=>'different-case','operation'=>'PHYSICIAN_EVIDENCE_REVIEW'] as $field=>$value){$p->beginTransaction();try{aseDeny(fn()=>aseConsume($p,$s,$actors['ase_both'],$c,$proof,[$field=>$value]),'proof_bound_'.$field,['staff_mfa_proof_denied']);}finally{$p->rollBack();}}
$p->beginTransaction();aseConsume($p,$s,$actors['ase_both'],$c,$proof);$p->commit();
$p->beginTransaction();try{aseDeny(fn()=>aseConsume($p,$s,$actors['ase_both'],$c,$proof),'proof_one_time_consumption',['staff_mfa_proof_denied']);}finally{$p->rollBack();}
aseDeny(fn()=>aseVerify($s,$clock,$actors['ase_both'],$c),'challenge_one_time_verification',['staff_mfa_verification_denied']);
[$c,$proof]=aseProof($s,$clock,$actors['ase_both'],'PHYSICIAN_EVIDENCE_REVIEW');
$sameStep=aseChallenge($s,$clock,$actors['ase_both'],'PHYSICIAN_EVIDENCE_REVIEW');
$previousCode=StaffMfaService::totp(str_repeat('e',20),(int)aseValue($p,"SELECT last_totp_time_step FROM auth_staff_mfa_factors WHERE account_id='ase_both' AND status='ACTIVE'"));
aseDeny(fn()=>$s['mfa']->verifyChallenge('ase_both',(string)$actors['ase_both']['context']->session()->sessionId(),1,$sameStep['challenge_id'],$sameStep['challenge'],$previousCode),'totp_step_replay_denied',['staff_mfa_verification_denied']);
$c=aseChallenge($s,$clock,$actors['ase_both'],'PHYSICIAN_ENROLLMENT_REVIEW');
aseDeny(fn()=>$s['mfa']->verifyChallenge('ase_review',(string)$actors['ase_review']['context']->session()->sessionId(),1,$c['challenge_id'],$c['challenge'],StaffMfaService::totp(str_repeat('e',20),intdiv($clock->now()->getTimestamp(),30))),'different_account_challenge_denied',['staff_mfa_verification_denied']);
aseDeny(fn()=>$s['mfa']->verifyChallenge('ase_both','different-synthetic-session',1,$c['challenge_id'],$c['challenge'],StaffMfaService::totp(str_repeat('e',20),intdiv($clock->now()->getTimestamp(),30))),'different_session_challenge_denied',['staff_mfa_reauthentication_mismatch']);
$clock->advance('+301 seconds');aseDeny(fn()=>aseVerify($s,$clock,$actors['ase_both'],$c),'expired_mfa_challenge_denied',['staff_mfa_verification_denied']);

$governance->execute($directorCookies,'grant','ase_both','public_profile_correction_review');
evCheck((int)aseValue($p,"SELECT COUNT(*) FROM internal_operator_grants WHERE account_id='ase_both' AND capability='public_profile_correction_review' AND status='ACTIVE' AND revoked_at IS NULL")===1,'ASE_out_of_scope_correction_grant_is_independently_explicit');
foreach(['INITIAL_OWNER','FIRST_DIRECTOR_BOOTSTRAP','MFA_RECOVERY','OWNER_REPLACEMENT','OWNER_REMOVAL','PHYSICIAN_AUTHORITY_CHANGE','PUBLIC_PROFILE_CORRECTION_REVIEW','PUBLIC_PROFILE_CORRECTION_APPLY'] as $operation)aseDeny(fn()=>aseChallenge($s,$clock,$actors['ase_both'],$operation),'reserved_MFA_operation_'.$operation,['staff_mfa_operation_authority_denied']);

$enrollment=new PublicEnrollmentService($s['authority'],$s['cipher'],str_repeat('q',64));$review=new ReviewedEnrollmentService($enrollment,$s['mfa']);$source=new SourceProvisioningService($enrollment,$s['cipher'],$s['storage'],$s['mfa']);
$professional=evProfessional($p,'ase_review','ase_review');$p->prepare("UPDATE profiles_doctors SET profile_status='active',is_public_candidate=1,plan_code='free',professional_license=? WHERE doctor_id=?")->execute(['C4EV-SYNTHETIC-ase_review',$professional[0]]);
$p->prepare('INSERT INTO consultorios(doctor_id,consultorio_id,titulo,municipio,estado) VALUES(?,?,?,?,?)')->execute([$professional[0],'ase-office','Consultorio sintético','Ciudad sintética','Estado sintético']);
$app=aseApp($p,$s,$enrollment,'ase_applicant',$hash,$professional[0],'C4EV-SYNTHETIC-ase_review');
$governance->execute($directorCookies,'register','ase_applicant');$governance->execute($directorCookies,'grant','ase_applicant','physician_accreditation_review');
aseDeny(fn()=>$review->begin($app['actor']['context'],$app['application_id'],1,0,'APPROVED',$app['actor']['email'],EV_PASSWORD),'advisor_self_review_denied',['c4_self_review_denied']);
aseDeny(fn()=>$review->begin($actors['ase_source']['context'],$app['application_id'],1,0,'APPROVED',$actors['ase_source']['email'],EV_PASSWORD),'source_capability_never_implies_enrollment_review',['c4_reviewer_capability_required']);
$clock->advance('+31 seconds');$rc=$review->begin($actors['ase_review']['context'],$app['application_id'],1,0,'APPROVED',$actors['ase_review']['email'],EV_PASSWORD);
$rp=$review->verify($actors['ase_review']['context'],$rc['case_id'],$rc['challenge_id'],$rc['challenge'],StaffMfaService::totp(str_repeat('e',20),intdiv($clock->now()->getTimestamp(),30)));
aseDeny(fn()=>$review->decide($actors['ase_both']['context'],$rc['case_id'],$rp['proof_id'],$rp['proof'],true),'different_reviewer_cannot_consume_review_proof',['enrollment_review_case_denied']);
aseDeny(fn()=>$review->begin($actors['ase_review']['context'],$app['application_id'],2,0,'APPROVED',$actors['ase_review']['email'],EV_PASSWORD),'stale_intake_version_denied',['enrollment_version_conflict']);
$p->exec('UPDATE gov03b_qa_faults SET enabled=1');evSqlDeny(fn()=>$review->decide($actors['ase_review']['context'],$rc['case_id'],$rp['proof_id'],$rp['proof'],true),'45000','ASE_enrollment_audit_failure_rolls_back');$p->exec('UPDATE gov03b_qa_faults SET enabled=0');
evCheck(aseValue($p,'SELECT status FROM auth_staff_mfa_proofs WHERE proof_id=?',[$rp['proof_id']])==='VERIFIED','ASE_enrollment_failed_audit_keeps_proof');
$resolution=$review->decide($actors['ase_review']['context'],$rc['case_id'],$rp['proof_id'],$rp['proof'],true);
evCheck($resolution['state']==='APPROVED'&&$review->decide($actors['ase_review']['context'],$rc['case_id'],$rp['proof_id'],$rp['proof'],true)['replayed'],'ASE_review_only_enrollment_commits_once');
evPhone($s,$app['actor'],'+15550004001');$r=$s['evidence']->createFromEnrollment($app['actor']['context'],$app['application_id']);$files=[];
foreach(['OFFICIAL_ID','PROFESSIONAL_CREDENTIAL'] as $kind){$f=$s['evidence']->addDocument($app['actor']['context'],$r['request_id'],$r['version'],$kind,"%PDF-1.4\nASE-ALIGN01 SYNTHETIC ".$kind."\n%%EOF");$r['version']=$f['version'];$files[]=$f['file_id'];}
$r=$s['evidence']->submit($app['actor']['context'],$r['request_id'],$r['version']);$ep=evProof($s,$clock,$actors['ase_review'],$r,'APPROVED');
foreach($files as $file)$s['evidence']->readDocument($actors['ase_review']['context'],$r['request_id'],$r['version'],$file);
aseDeny(fn()=>$s['evidence']->decide($actors['ase_source']['context'],$r['request_id'],$r['version'],$ep['proof_id'],$ep['proof'],true,array_fill_keys($files,null)),'source_capability_never_implies_evidence_review',['c4_reviewer_capability_required']);
$r=$s['evidence']->decide($actors['ase_review']['context'],$r['request_id'],$r['version'],$ep['proof_id'],$ep['proof'],true,array_fill_keys($files,null));
evCheck($r['status']==='APPROVED','ASE_review_only_evidence_human_decision_commits');

$profile='ase_source_profile';$license='ASE-SYNTHETIC-source-only';
$p->prepare("INSERT INTO profiles_doctors(doctor_id,display_name,professional_license,specialty_primary,profile_status,is_public_candidate,plan_code,preloaded_free_plan,preloaded_free_reference,preloaded_free_recorded_at) VALUES(?,?,?,'Synthetic specialty','active',1,'free','free','ase-disposable-reviewed-FREE',UTC_TIMESTAMP())")->execute([$profile,'Profesional precargado sintético',$license]);
$p->prepare('INSERT INTO consultorios(doctor_id,consultorio_id,titulo,municipio,estado) VALUES(?,?,?,?,?)')->execute([$profile,'ase-source-office','Consultorio sintético','Ciudad sintética','Estado sintético']);
$sa=aseApp($p,$s,$enrollment,'ase_source_applicant',$hash,$profile,$license);$origin=$sa['application_id'];
aseDeny(fn()=>$source->begin($actors['ase_review']['context'],$origin,'COLLECT',[],$actors['ase_review']['email'],EV_PASSWORD),'review_capability_never_implies_source_provision',['source_capability_denied']);
$sc=aseSourceCase($source,$actors['ase_source'],$clock,$origin,'COLLECT');$source->authorize($actors['ase_source']['context'],$sc['case_id'],$sc['proof_id'],$sc['proof']);
$png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l1sAAAAASUVORK5CYII=');$version=1;
foreach(['OFFICIAL_ID','PROFESSIONAL_CREDENTIAL'] as $kind)$version=$source->upload($sa['actor']['context'],$origin,$version,$kind,$png,$sa['actor']['email'],EV_PASSWORD)['version'];
foreach($source->status($actors['ase_source']['context'],$origin,true)['files'] as $file)$source->document($actors['ase_source']['context'],$origin,$file['file_id']);
$facts=['given_names'=>'Synthetic','first_surname'=>'Physician','second_surname'=>'','license_number'=>$license,'professional_area_label'=>'Synthetic area','institution_name'=>'Synthetic institution','identity_source'=>'ASE synthetic human identity source','identity_reference'=>'ase-synthetic-human-identity-reference','identity_procedure'=>'OFFICIAL_REGISTRY_HUMAN_CHECK','credential_source'=>'ASE synthetic human credential source','credential_reference'=>'ase-synthetic-human-credential-reference','credential_procedure'=>'DIRECT_ISSUING_INSTITUTION_CONFIRMATION','verified_at'=>$clock->now()->format('Y-m-d H:i:s'),'verification_outcome'=>'VERIFIED','relationship_confirmed'=>true];
$sc=aseSourceCase($source,$actors['ase_source'],$clock,$origin,'AUTHORIZE',$facts);$source->authorize($actors['ase_source']['context'],$sc['case_id'],$sc['proof_id'],$sc['proof']);
$baseline=[];foreach(['profiles_verified_identities','profiles_doctor_credentials','auth_professional_source_journal','platform_audit_events'] as $table)$baseline[$table]=aseValue($p,'SELECT COUNT(*) FROM '.$table);
$p->exec('UPDATE gov03b_qa_faults SET enabled=22');evSqlDeny(fn()=>$source->execute($actors['ase_source']['context'],$origin),'45000','ASE_source_only_audit_failure_rolls_back');$p->exec('UPDATE gov03b_qa_faults SET enabled=0');
foreach($baseline as $table=>$count)evCheck(aseValue($p,'SELECT COUNT(*) FROM '.$table)===$count,'ASE_source_only_atomic_rollback_'.$table);
$out=$source->execute($actors['ase_source']['context'],$origin);evCheck($out['state']==='COMPLETE','ASE_source_only_exact_capability_real_VID01_CRD02_flow');
evCheck($source->execute($actors['ase_source']['context'],$origin)['state']==='COMPLETE','ASE_source_only_idempotent_result');
evCheck((int)aseValue($p,'SELECT COUNT(*) FROM profiles_verified_identities WHERE doctor_id=?',[$profile])===1&&(int)aseValue($p,"SELECT COUNT(*) FROM profiles_doctor_credentials WHERE doctor_id=? AND credential_type='PROFESSIONAL'",[$profile])===1,'ASE_exactly_one_canonical_source_pair');

// Immediate independent revocation preserves earlier attribution and the other capability.
[$revocationChallenge,$revocationProof]=aseProof($s,$clock,$actors['ase_both'],'PHYSICIAN_EVIDENCE_REVIEW');
$history=$p->query('SELECT event_id,metadata_json FROM platform_audit_events ORDER BY event_id')->fetchAll(PDO::FETCH_KEY_PAIR);
$grant=(string)aseValue($p,"SELECT grant_id FROM internal_operator_grants WHERE account_id='ase_both' AND capability='physician_accreditation_review' AND status='ACTIVE'");
$other=evConnect();$otherStack=evStack($other,new EvClock($clock->now()));
$otherGovernance=new InternalGovernanceService($other,new Identity\Http\CanonicalHttpSessionResolver($otherStack['sessions']));
$p->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$p->beginTransaction();
try{
    evCheck(aseValue($p,'SELECT status FROM internal_operator_grants WHERE grant_id=?',[$grant])==='ACTIVE','ASE_MVCC_establishes_repeatable_read_grant_snapshot');
    $otherGovernance->execute($directorCookies,'revoke','ase_both','physician_accreditation_review');
    evCheck(aseValue($p,'SELECT status FROM internal_operator_grants WHERE grant_id=?',[$grant])==='ACTIVE','ASE_MVCC_plain_read_demonstrates_stale_active_grant');
    evCheck(!Identity\Services\StaffRoutineOperationPolicy::allowAdvisor($p,'ase_both','PHYSICIAN_EVIDENCE_REVIEW'),'ASE_MVCC_authority_current_read_observes_committed_revocation');
    evCheck(Identity\Services\StaffRoutineOperationPolicy::allowAdvisor($p,'ase_both','PROFESSIONAL_SOURCE_PROVISION')&&Identity\Services\StaffRoutineOperationPolicy::eligibleAdvisorSubject($p,'ase_both'),'ASE_MVCC_remaining_individual_capability_is_current');
}finally{$p->rollBack();}
aseDeny(fn()=>aseChallenge($s,$clock,$actors['ase_both'],'PHYSICIAN_EVIDENCE_REVIEW'),'revoked_capability_denies_next_operation');
$p->beginTransaction();try{aseDeny(fn()=>aseConsume($p,$s,$actors['ase_both'],$revocationChallenge,$revocationProof),'revoked_capability_denies_already_verified_proof');}finally{$p->rollBack();}
[$c,$proof]=aseProof($s,$clock,$actors['ase_both'],'PROFESSIONAL_SOURCE_PROVISION');evCheck(isset($proof['proof_id']),'ASE_other_capability_survives_independent_revocation');
evCheck(aseValue($p,'SELECT status FROM internal_operator_grants WHERE grant_id=?',[$grant])==='REVOKED','ASE_revocation_retains_original_grant');
$after=$p->query('SELECT event_id,metadata_json FROM platform_audit_events ORDER BY event_id')->fetchAll(PDO::FETCH_KEY_PAIR);
evCheck(array_intersect_key($after,$history)===$history,'ASE_prior_actor_audit_history_immutable');

foreach([['internal_staff',"status='SUSPENDED'","status='ACTIVE'"],['auth_accounts',"status='disabled'","status='active'"],['auth_accounts','email_verified_at=NULL','email_verified_at=UTC_TIMESTAMP()'],['auth_account_credentials','credential_version=2','credential_version=1']] as [$table,$bad,$good]){
    $p->exec('UPDATE '.$table.' SET '.$bad." WHERE account_id='ase_review'");
    aseDeny(fn()=>aseChallenge($s,$clock,$actors['ase_review'],'PHYSICIAN_ENROLLMENT_REVIEW'),'current_state_'.$table.'_'.$bad);
    $p->exec('UPDATE '.$table.' SET '.$good." WHERE account_id='ase_review'");
    // Credential/session invalidation is durable; use a fresh legitimate login after restoration.
    $actors['ase_review']=evLogin($s,'ase_review@example.invalid');
}
$temporary=evLogin($s,'ase_review@example.invalid');$s['sessions']->logout($temporary['token']);aseDeny(fn()=>aseChallenge($s,$clock,$temporary,'PHYSICIAN_ENROLLMENT_REVIEW'),'revoked_session_denied',['governance_session_invalidated']);
aseAccount($p,'ase_no_factor',$hash);$governance->execute($directorCookies,'register','ase_no_factor');$governance->execute($directorCookies,'grant','ase_no_factor','physician_accreditation_review');$noFactor=evLogin($s,'ase_no_factor@example.invalid');$c=aseChallenge($s,$clock,$noFactor,'PHYSICIAN_EVIDENCE_REVIEW');aseDeny(fn()=>aseVerify($s,$clock,$noFactor,$c),'missing_enrolled_factor_denied',['staff_mfa_enrolled_factor_required']);
aseDeny(fn()=>$s['security']->reauthenticate($actors['ase_source']['context'],$actors['ase_review']['email'],EV_PASSWORD,[]),'forged_reviewer_identity_denied',['governance_reauthentication_denied']);
require_once dirname(__DIR__,3).'/api/_lib/clinical_professional_authority.php';
foreach(['PRESCRIPTION_SIGN','PRESCRIPTION_ISSUE'] as $operation)aseDeny(fn()=>clinical_professional_authority_assert(['doctor_id'=>$profile,'user_id'=>'ase_source','role_code'=>'owner'],$operation),'advisor_clinical_boundary_'.$operation);
evCheck((int)aseValue($p,"SELECT COUNT(*) FROM auth_account_memberships WHERE account_id LIKE 'ase_%'")===0,'ASE_no_owner_or_operator_membership_created');
echo "ASE_ALIGN01_NATIVE=PASS\n";
