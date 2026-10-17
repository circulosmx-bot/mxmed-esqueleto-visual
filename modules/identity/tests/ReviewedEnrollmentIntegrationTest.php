<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('PUB_ENR03_QA')!=='1')exit(1);
if(($argv[1]??'')==='--decide-worker'){
    require_once __DIR__.'/../http/IdentityHttpComposition.php';Identity\Http\IdentityHttpComposition::registerAutoloader();
    require_once __DIR__.'/../../media/storage/LocalPersistentPrivateMediaStorage.php';require_once __DIR__.'/../../agenda/contracts/OtpProviderPort.php';require_once __DIR__.'/support/C4EvidenceHarness.php';
    $input=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);$p=evConnect();$clock=new EvClock(new DateTimeImmutable($input['now']));$s=evStack($p,$clock);
    $svc=new Identity\Accreditation\PublicEnrollmentService($s['authority'],$s['cipher'],str_repeat('q',64));$review=new Identity\Accreditation\ReviewedEnrollmentService($svc,$s['mfa']);
    try{$actor=$s['sessions']->validate($input['session'])->context();if($actor===null)throw new RuntimeException('worker_session_denied');$r=$review->decide($actor,$input['case_id'],$input['proof_id'],$input['proof'],true);echo json_encode(['ok'=>true]+$r);}
    catch(RuntimeException $e){if(!in_array($e->getMessage(),['enrollment_review_version_conflict','enrollment_review_case_denied'],true))throw $e;echo json_encode(['ok'=>false,'conflict'=>true]);}exit;
}
// Retain and run the actual PUB-ENR02 native contract on this fresh 01→06 target.
require_once __DIR__.'/../http/IdentityHttpComposition.php';Identity\Http\IdentityHttpComposition::registerAutoloader();
require_once __DIR__.'/../../profiles/services/VerifiedPhysicianIdentityService.php';require_once __DIR__.'/../../profiles/services/VerifiedDoctorCredentialService.php';
require __DIR__.'/PublicEnrollmentIntegrationTest.php';
use Identity\Accreditation\{PublicEnrollmentService,ReviewedEnrollmentService,EnrollmentResolutionAuthority};
use Identity\Services\StaffMfaService;
$review=new ReviewedEnrollmentService($svc,$s['mfa']);
$hash=password_hash(EV_PASSWORD,PASSWORD_ARGON2ID);
function resAccount(PDO $p,string $id,string $hash):void{
 $p->prepare("INSERT INTO auth_accounts(account_id,email_address,email_normalized,status,email_verified_at) VALUES(?,?,?,'active',UTC_TIMESTAMP())")->execute([$id,$id.'@example.invalid',$id.'@example.invalid']);
 $p->prepare('INSERT INTO auth_account_credentials(account_id,password_hash,password_changed_at,credential_version) VALUES(?,?,UTC_TIMESTAMP(),1)')->execute([$id,$hash]);
}
resAccount($p,'res_director',$hash);$p->exec("INSERT INTO internal_staff(account_id,governance_class,status) VALUES('res_director','DIRECTOR','ACTIVE')");
$director=evLogin($s,'res_director@example.invalid');
$ceremony=new Identity\Services\GovernanceStaffMfaCeremony($p,$s['mfa'],$s['security'],new Identity\Adapters\CanonicalGovernanceAuditAdapter($p),$clock,['res_director']);
$factor=$ceremony->begin($reviewer['context'],$director['context'],$reviewer['email'],EV_PASSWORD,$director['email'],EV_PASSWORD);
$ceremony->activate($reviewer['context'],$factor['factor_id'],StaffMfaService::totp(str_repeat('e',20),intdiv($clock->now()->getTimestamp(),30)));
$profiles=[];
for($n=0;$n<26;$n++){
 $profile=evProfessional($p,'resolution_'.$n,'enr_reviewer');$profiles[]=$profile;
 $p->prepare("UPDATE profiles_doctors SET profile_status='active',is_public_candidate=1,plan_code='free',professional_license=? WHERE doctor_id=?")->execute(['C4EV-SYNTHETIC-resolution_'.$n,$profile[0]]);
 $p->prepare('INSERT INTO consultorios(doctor_id,consultorio_id,titulo,municipio,estado) VALUES(?,?,?,?,?)')->execute([$profile[0],'resolution-office','Consultorio sintético','Ciudad sintética','Estado sintético']);
}
$clock->time=new DateTimeImmutable('now',new DateTimeZone('UTC'));
function resApp(array $s,PublicEnrollmentService $svc,PDO $p,string $hash,string $label,?string $profile,string $license):array{
 $id='res_applicant_'.$label;resAccount($p,$id,$hash);$actor=evLogin($s,$id.'@example.invalid');
 $o=origin($svc,PublicEnrollmentService::opaque(),$profile);$svc->bind($actor['context'],$o['application_id'],$o['handle'],$o['browser']);
 $data=['given_names'=>$license==='SYNTHETIC-NO-MATCH'?'Sin coincidencia sintética':'Synthetic','first_surname'=>'Physician','second_surname'=>'','professional_license'=>$license,'specialty'=>'Synthetic specialty','state'=>'Estado sintético','city'=>'Ciudad sintética'];
 $svc->save($actor['context'],$o['application_id'],0,$s['authority']->uuid(),$data,true);return ['actor'=>$actor,'application_id'=>$o['application_id'],'intake_version'=>1];
}
function resProof(array $s,ReviewedEnrollmentService $review,EvClock $clock,array $reviewer,array $app,string $decision,int $version=0):array{
 $clock->advance('+31 seconds');
 $c=$review->begin($reviewer['context'],$app['application_id'],1,$version,$decision,$reviewer['email'],EV_PASSWORD);
 return $c+$review->verify($reviewer['context'],$c['case_id'],$c['challenge_id'],$c['challenge'],StaffMfaService::totp(str_repeat('e',20),intdiv($clock->now()->getTimestamp(),30)));
}
function resDecide(ReviewedEnrollmentService $review,array $reviewer,array $proof):array{return $review->decide($reviewer['context'],$proof['case_id'],$proof['proof_id'],$proof['proof'],true);}
$claim=resApp($s,$svc,$p,$hash,'claim',$profiles[0][0],'C4EV-SYNTHETIC-resolution_0');
$general=resApp($s,$svc,$p,$hash,'general',null,'C4EV-SYNTHETIC-resolution_1');
$noMatch=resApp($s,$svc,$p,$hash,'no_match',null,'SYNTHETIC-NO-MATCH');
$mismatch=resApp($s,$svc,$p,$hash,'mismatch',$profiles[2][0],'C4EV-SYNTHETIC-resolution_3');
$missing=resApp($s,$svc,$p,$hash,'missing','enr_profile_6','SYNTHETIC-LICENSE-6');
evDeny(fn()=>$review->begin($reviewer['context'],$mismatch['application_id'],1,0,'APPROVED',$reviewer['email'],EV_PASSWORD),'enrollment_review_gate_denied','02_claim_cannot_redirect_to_other_candidate');
$conf=resDecide($review,$reviewer,resProof($s,$review,$clock,$reviewer,$mismatch,'CONFLICT'));
evCheck($conf['state']==='CONFLICT'&&$svc->snapshot($mismatch['actor']['context'],$mismatch['application_id'])['original_profile_id']===$profiles[2][0],'01_06_exact_claim_preserved_conflict');
$nm=resDecide($review,$reviewer,resProof($s,$review,$clock,$reviewer,$noMatch,'PENDING_NEW_PROFILE'));
evCheck($nm['state']==='PENDING_NEW_PROFILE'&&(int)$p->query('SELECT COUNT(*) FROM profiles_doctors')->fetchColumn()===46,'05_no_match_never_creates_profile');
$ms=resDecide($review,$reviewer,resProof($s,$review,$clock,$reviewer,$missing,'PENDING_SOURCES'));
evCheck($ms['state']==='PENDING_SOURCES','08_preloaded_FREE_remains_pending_without_VID_CRD');
evDeny(fn()=>$review->begin($reviewer['context'],$missing['application_id'],1,1,'APPROVED',$reviewer['email'],EV_PASSWORD),'enrollment_review_gate_denied','09_missing_sources_cannot_approve');
evPhone($s,$noMatch['actor'],'+15550003001');evDeny(fn()=>$s['evidence']->createFromEnrollment($noMatch['actor']['context'],$noMatch['application_id']),'enrollment_approved_resolution_required','10_matching_or_pending_row_never_admits');
evDeny(fn()=>$review->begin($b['context'],$claim['application_id'],1,0,'APPROVED',$b['email'],EV_PASSWORD),'c4_reviewer_capability_required','11_unauthorized_reviewer');
$claimAccount=$claim['actor']['context']->accountId();$p->prepare("INSERT INTO internal_staff(account_id,governance_class,status) VALUES(?,'MASTER_ADMIN','ACTIVE')")->execute([$claimAccount]);
$p->prepare("INSERT INTO internal_operator_grants(grant_id,account_id,capability,status) VALUES(UUID(),?,'physician_accreditation_review','ACTIVE')")->execute([$claimAccount]);
evDeny(fn()=>$review->begin($claim['actor']['context'],$claim['application_id'],1,0,'APPROVED',$claim['actor']['email'],EV_PASSWORD),'c4_self_review_denied','12_self_review_denied');
evDeny(fn()=>$review->begin($reviewer['context'],$claim['application_id'],2,0,'APPROVED',$reviewer['email'],EV_PASSWORD),'enrollment_version_conflict','14_wrong_intake_revision_denied');
$proof=resProof($s,$review,$clock,$reviewer,$claim,'APPROVED');
$p->exec("UPDATE internal_operator_grants SET status='REVOKED',revoked_at=UTC_TIMESTAMP() WHERE account_id='enr_reviewer'");
evDeny(fn()=>resDecide($review,$reviewer,$proof),'c4_reviewer_capability_required','13_revoked_grant_denied');
$p->exec("UPDATE internal_operator_grants SET status='ACTIVE',revoked_at=NULL WHERE account_id='enr_reviewer'");
evDeny(fn()=>$review->decide($reviewer['context'],$proof['case_id'],$proof['proof_id'],$proof['proof'],false),'enrollment_human_resolution_required','human_attestation_required_even_single_match');
$p->exec('UPDATE gov03b_qa_faults SET enabled=1');
evSqlDeny(fn()=>resDecide($review,$reviewer,$proof),'45000','18_audit_failure_rolls_back_review_and_proof');
$p->exec('UPDATE gov03b_qa_faults SET enabled=0');
evCheck($p->query("SELECT status FROM auth_staff_mfa_proofs WHERE proof_id='".$proof['proof_id']."'")->fetchColumn()==='VERIFIED','18_proof_not_lost_on_rollback');
$approved=resDecide($review,$reviewer,$proof);$replay=resDecide($review,$reviewer,$proof);
evCheck($replay['replayed']&&$replay['resolution_id']===$approved['resolution_id'],'17_exact_decision_replay_one_row');
$gp=resProof($s,$review,$clock,$reviewer,$general,'APPROVED');$ga=resDecide($review,$reviewer,$gp);
evCheck($svc->snapshot($general['actor']['context'],$general['application_id'])['initial_origin']==='REQUEST_NEW_PROFILE','03_general_origin_remains_immutable');
evCheck($review->applicant($general['actor']['context'],$general['application_id'])['handoff_available'],'04_explicit_general_existing_profile_resolution');
evDeny(fn()=>$review->applicant($claim['actor']['context'],$general['application_id']),'enrollment_account_denied','cross_account_private_state_denied');
evPhone($s,$claim['actor'],'+15550003002');evPhone($s,$general['actor'],'+15550003003');
evDeny(fn()=>$s['evidence']->createFromEnrollment($claim['actor']['context'],strtoupper($claim['application_id'])),'enrollment_account_denied','handoff_rejects_collation_alias_for_canonical_origin');
evDeny(fn()=>$review->applicant($claim['actor']['context'],strtoupper($claim['application_id'])),'enrollment_account_denied','applicant_rejects_collation_alias_for_canonical_origin');
foreach([$claim,$general] as $index=>$app){
 $r=$s['evidence']->createFromEnrollment($app['actor']['context'],$app['application_id']);
 $retry=$s['evidence']->createFromEnrollment($app['actor']['context'],$app['application_id']);evCheck($r['request_id']===$retry['request_id']&&$retry['replayed'],'17_handoff_idempotent_'.$index);
 evDeny(fn()=>$s['evidence']->submit($app['actor']['context'],$r['request_id'],1),'c4_identity_and_credential_documents_required','20_documents_required_'.$index);
 $files=[];foreach(['OFFICIAL_ID','PROFESSIONAL_CREDENTIAL'] as $kind){$f=$s['evidence']->addDocument($app['actor']['context'],$r['request_id'],$r['version'],$kind,"%PDF-1.4\nSynthetic PUB-ENR03 private document\n%%EOF");$r['version']=$f['version'];$files[]=$f['file_id'];}
 $r=$s['evidence']->submit($app['actor']['context'],$r['request_id'],$r['version']);$pr=evProof($s,$clock,$reviewer,$r,'APPROVED');
 evDeny(fn()=>$s['evidence']->decide($reviewer['context'],$r['request_id'],$r['version'],$pr['proof_id'],$pr['proof'],true,array_fill_keys($files,null)),'c4_actual_document_access_required','20_actual_private_document_access_required_'.$index);
 foreach($files as $file)$s['evidence']->readDocument($reviewer['context'],$r['request_id'],$r['version'],$file);
 $r=$s['evidence']->decide($reviewer['context'],$r['request_id'],$r['version'],$pr['proof_id'],$pr['proof'],true,array_fill_keys($files,null));
 $ch=$s['evidence']->issueConfirmation($app['actor']['context'],$r['request_id'],$r['version'],$app['actor']['email'],EV_PASSWORD);
 $r=$s['evidence']->confirm($app['actor']['context'],$r['request_id'],$r['version'],$ch['confirmation'],true);
 evDeny(fn()=>$s['evidence']->confirm($app['actor']['context'],$r['request_id'],$r['version'],$ch['confirmation'],true),'c4_confirmation_denied','21_confirmation_single_use_'.$index);
 $issued=$s['composition']->issuer->issue($app['actor']['context'],$r['issuance_command_id']);$again=$s['composition']->issuer->issue($app['actor']['context'],$r['issuance_command_id']);
 evCheck($issued==$again&&$issued['status']==='ACTIVE','22_canonical_issuer_reused_idempotent_'.$index);
 $facts=evConsumer($s,$p,$r,$app['actor']['context']->accountId(),$profiles[$index][0]);
 evCheck($facts['origin']===($index===0?'CLAIM_EXISTING_PROFILE':'REQUEST_NEW_PROFILE'),'positive_final_evidence_preserves_origin_'.$index);
 evCheck($review->staff($reviewer['context'],$app['application_id'])['application']['resolution_current']===true,'approved_resolution_stays_current_after_own_issuance_'.$index);
 evDeny(fn()=>evConsumer($s,$p,$r,$app['actor']['context']->accountId(),$profiles[4][0]),'c4_confirmed_scope_required','19_wrong_profile_final_handoff_denied_'.$index);
}
$stale=resApp($s,$svc,$p,$hash,'stale',$profiles[5][0],'C4EV-SYNTHETIC-resolution_5');$sp=resProof($s,$review,$clock,$reviewer,$stale,'APPROVED');
$p->prepare('UPDATE profiles_verified_identities SET source_reference=CONCAT(source_reference,?) WHERE verified_identity_id=?')->execute(['-changed',$profiles[5][1]]);
evDeny(fn()=>resDecide($review,$reviewer,$sp),'enrollment_review_revision_changed','15_changed_VID_revision_denied');
$staleCredential=resApp($s,$svc,$p,$hash,'stale_credential',$profiles[9][0],'C4EV-SYNTHETIC-resolution_9');$scp=resProof($s,$review,$clock,$reviewer,$staleCredential,'APPROVED');
$p->prepare('UPDATE profiles_doctor_credentials SET source_reference=CONCAT(source_reference,?) WHERE credential_id=?')->execute(['-changed',$profiles[9][2]]);
evDeny(fn()=>resDecide($review,$reviewer,$scp),'enrollment_review_revision_changed','15_changed_CRD_revision_denied');
$staleAccount=resApp($s,$svc,$p,$hash,'stale_account',$profiles[22][0],'C4EV-SYNTHETIC-resolution_22');$sap=resProof($s,$review,$clock,$reviewer,$staleAccount,'APPROVED');
$p->prepare('UPDATE auth_account_credentials SET credential_version=credential_version+1 WHERE account_id=?')->execute([$staleAccount['actor']['context']->accountId()]);
evDeny(fn()=>resDecide($review,$reviewer,$sap),'enrollment_review_revision_changed','14_changed_applicant_credential_revision_denied');
evSqlDeny(fn()=>$p->prepare("INSERT INTO auth_physician_evidence_requests(request_id,account_id,origin,profile_doctor_id,verified_identity_id,professional_credential_id,identity_revision_hash,credential_revision_hash,enrollment_resolution_id,audit_reference,created_at) SELECT UUID(),account_id,initial_origin,?,verified_identity_id,professional_credential_id,identity_revision_hash,credential_revision_hash,resolution_id,audit_reference,UTC_TIMESTAMP() FROM auth_physician_enrollment_resolutions WHERE resolution_id=?")->execute([$profiles[4][0],$ga['resolution_id']]),'45000','19_physical_wrong_profile_handoff_denied');
$owned=resApp($s,$svc,$p,$hash,'owned',$profiles[6][0],'C4EV-SYNTHETIC-resolution_6');
$p->prepare("INSERT INTO auth_account_memberships(membership_id,account_id,profile_doctor_id,role_code,scope_code,status,assignment_source) VALUES(UUID(),'enr_b',?,'owner','profile','active','manual_review')")->execute([$profiles[6][0]]);
evDeny(fn()=>$review->begin($reviewer['context'],$owned['application_id'],1,0,'APPROVED',$reviewer['email'],EV_PASSWORD),'enrollment_review_gate_denied','07_existing_owner_blocks_ordinary_resolution');
$reject=resApp($s,$svc,$p,$hash,'reject',$profiles[7][0],'C4EV-SYNTHETIC-resolution_7');$re=resDecide($review,$reviewer,resProof($s,$review,$clock,$reviewer,$reject,'REJECTED'));
evDeny(fn()=>$review->begin($reviewer['context'],$reject['application_id'],1,1,'APPROVED',$reviewer['email'],EV_PASSWORD),'enrollment_review_transition_denied','rejected_terminal_not_silent_retry');
evSqlDeny(fn()=>$p->exec('UPDATE auth_physician_enrollment_resolutions SET state=state'),'45000','resolution_append_only_physical_guard');
evSqlDeny(fn()=>$p->exec('DELETE FROM auth_physician_enrollment_handoffs'),'45000','handoff_history_not_deleted');
evCheck((int)$p->query("SELECT COUNT(*) FROM auth_account_memberships WHERE account_id LIKE 'res_applicant_%' AND role_code='owner'")->fetchColumn()===0,'23_no_auto_owner');
evDeny(fn()=>$review->staff($operator['context']),'c4_reviewer_capability_required','25_operator_cannot_review_professionals');
// Native parallel decisions in two OS processes, same exact proof and SQL writer.
$parallelApp=resApp($s,$svc,$p,$hash,'parallel',$profiles[8][0],'C4EV-SYNTHETIC-resolution_8');$pp=resProof($s,$review,$clock,$reviewer,$parallelApp,'APPROVED');
$input=json_encode(['now'=>$clock->now()->format(DATE_ATOM),'session'=>$reviewer['token'],'case_id'=>$pp['case_id'],'proof_id'=>$pp['proof_id'],'proof'=>$pp['proof']],JSON_THROW_ON_ERROR);$workers=[];
for($n=0;$n<2;$n++){$pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'--decide-worker'],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);fwrite($pipes[0],$input);fclose($pipes[0]);$workers[]=[$process,$pipes];}
$answers=[];foreach($workers as [$process,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($process)!==0)throw new RuntimeException('parallel_review_worker_failed:'.$err);$answers[]=json_decode($out,true,16,JSON_THROW_ON_ERROR);}
evCheck($answers[0]['resolution_id']===$answers[1]['resolution_id']&&$answers[0]['replayed']!==$answers[1]['replayed'],'16_native_parallel_review_exact_retry_one_resolution');
$competing=resApp($s,$svc,$p,$hash,'competing',$profiles[23][0],'C4EV-SYNTHETIC-resolution_23');
$cps=[resProof($s,$review,$clock,$reviewer,$competing,'APPROVED'),resProof($s,$review,$clock,$reviewer,$competing,'APPROVED')];$workers=[];
foreach($cps as $cp){$pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'--decide-worker'],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);fwrite($pipes[0],json_encode(['now'=>$clock->now()->format(DATE_ATOM),'session'=>$reviewer['token'],'case_id'=>$cp['case_id'],'proof_id'=>$cp['proof_id'],'proof'=>$cp['proof']],JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$process,$pipes];}
$answers=[];foreach($workers as [$process,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($process)!==0)throw new RuntimeException('parallel_distinct_review_failed:'.$err);$answers[]=json_decode($out,true,16,JSON_THROW_ON_ERROR);}
evCheck(count(array_filter($answers,fn($x)=>$x['ok']))===1&&count(array_filter($answers,fn($x)=>$x['conflict']??false))===1,'16_competing_distinct_cases_one_accept_one_conflict');
$revoked=resDecide($review,$reviewer,resProof($s,$review,$clock,$reviewer,$general,'REVOKED',1));
evCheck($revoked['state']==='REVOKED'&&!$review->applicant($general['actor']['context'],$general['application_id'])['handoff_available'],'resolution_revocation_propagates_to_applicant');
$command=$p->prepare('SELECT evidence_request_id FROM auth_physician_enrollment_handoffs WHERE origin_id=?');$command->execute([$general['application_id']]);$issuedCommand=$command->fetchColumn();
evDeny(fn()=>$s['composition']->issuer->issue($general['actor']['context'],$issuedCommand),'enrollment_resolution_stale','resolution_revocation_blocks_issuer_replay');
$metadata=(string)$p->query("SELECT GROUP_CONCAT(metadata_json) FROM platform_audit_events WHERE affected_subject_reference LIKE 'physician_enrollment:%'")->fetchColumn();
evCheck(!str_contains($metadata,$proof['proof'])&&!str_contains($metadata,'C4EV-SYNTHETIC-resolution_'),'resolution_audit_no_raw_proof_or_license');
// Leave separate fresh profiles/accounts for the original browser journeys; no real imports.
$uiClock=new EvClock(new DateTimeImmutable('-2 minutes',new DateTimeZone('UTC')));$uiStack=evStack($p,$uiClock);$uiDirector=evLogin($uiStack,'res_director@example.invalid');
$uiCeremony=new Identity\Services\GovernanceStaffMfaCeremony($p,$uiStack['mfa'],$uiStack['security'],new Identity\Adapters\CanonicalGovernanceAuditAdapter($p),$uiClock,['res_director']);
for($n=0;$n<12;$n++){
 $id='res_ui_reviewer_'.$n;resAccount($p,$id,$hash);
 $ase=getenv('ASE_ALIGN01_QA')==='1';
 $p->prepare("INSERT INTO internal_staff(account_id,governance_class,status) VALUES(?,?,'ACTIVE')")->execute([$id,$ase?'ADVISOR':'MASTER_ADMIN']);
 $cap=$ase&&$n<6?'professional_source_provision':'physician_accreditation_review';
 $p->prepare("INSERT INTO internal_operator_grants(grant_id,account_id,capability,status) VALUES(UUID(),?,?,'ACTIVE')")->execute([$id,$cap]);
 $uiReviewer=evLogin($uiStack,$id.'@example.invalid');$uf=$uiCeremony->begin($uiReviewer['context'],$uiDirector['context'],$uiReviewer['email'],EV_PASSWORD,$uiDirector['email'],EV_PASSWORD);
 $uiCeremony->activate($uiReviewer['context'],$uf['factor_id'],StaffMfaService::totp(str_repeat('e',20),intdiv($uiClock->now()->getTimestamp(),30)));
}
file_put_contents((string)getenv('GOV03B_QA_EVIDENCE').'/resolution-ui-fixture.json',json_encode(['profiles'=>array_slice($profiles,10,12),'password'=>EV_PASSWORD],JSON_THROW_ON_ERROR));
echo "PUB_ENR03_NATIVE=PASS\n";
