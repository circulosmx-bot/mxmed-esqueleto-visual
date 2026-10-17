<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/support/CorrectionHarness.php';
use Profiles\Corrections\{ApplicationService,ApplicationAuthority,ApplicationCatalog,ReportDenied};
use Identity\Services\StaffMfaService;
if(($argv[1]??'')==='worker'){
    $in=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);$p=corConnect();$clock=new CorClock(new DateTimeImmutable($in['now']));$s=corStack($p,$clock);
    $app=new ApplicationService(new ApplicationAuthority($s['service'],$s['security']),$s['mfa']);$actor=$s['sessions']->validate($in['session'])->context();
    try{echo json_encode(['ok'=>true,'result'=>$app->execute($actor,$in['command_id'])],JSON_THROW_ON_ERROR);}catch(ReportDenied $e){echo json_encode(['ok'=>false,'status'=>$e->status]);}exit;
}
// Preserve and exercise PUB-COR01 unchanged, on the new schema too.
require __DIR__.'/CorrectionReportIntegrationTest.php';
$legacyReviewer=$reviewer;$future=$clock->now()->modify('+31 seconds');
$clock->time=new DateTimeImmutable('-2 minutes',new DateTimeZone('UTC'));
$appActors=['app_both','app_second','app_applyonly','app_reviewonly','app_revoked','app_credential','app_factor'];
for($i=0;$i<6;$i++)$appActors[]='app_ui_'.$i;
foreach($appActors as $who){
    $p->prepare("INSERT INTO auth_accounts(account_id,email_address,email_normalized,status,email_verified_at) VALUES(?,?,?,'active',UTC_TIMESTAMP())")->execute([$who,$who.'@example.invalid',$who.'@example.invalid']);
    $p->prepare('INSERT INTO auth_account_credentials(account_id,password_hash,password_changed_at,credential_version) VALUES(?,?,UTC_TIMESTAMP(),1)')->execute([$who,$hash]);
    $p->prepare("INSERT INTO internal_staff(account_id,governance_class,status) VALUES(?,?,'ACTIVE')")->execute([$who,$who==='app_director'?'DIRECTOR':'MASTER_ADMIN']);
}
$appDirector=corLogin($s,'cor_director');$governance=new Identity\Services\InternalGovernanceService($p,new Identity\Http\CanonicalHttpSessionResolver($s['sessions']));
$appCookie=['__Host-mxmed_session'=>$appDirector['token']];$ceremony=new Identity\Services\GovernanceStaffMfaCeremony($p,$s['mfa'],$s['security'],new Identity\Adapters\CanonicalGovernanceAuditAdapter($p),$clock,['cor_director']);
$appLogin=[];
foreach($appActors as $who){
    if($who==='app_director')continue;
    if($who!=='app_reviewonly')$governance->execute($appCookie,'grant',$who,ApplicationCatalog::CAPABILITY);
    if($who!=='app_applyonly')$governance->execute($appCookie,'grant',$who,Profiles\Corrections\ReportCatalog::CAPABILITY);
    $a=corLogin($s,$who);$appLogin[$who]=$a;
    $f=$ceremony->begin($a['context'],$appDirector['context'],$who.'@example.invalid',COR_PASSWORD,'cor_director@example.invalid',COR_PASSWORD);
    $ceremony->activate($a['context'],$f['factor_id'],StaffMfaService::totp(str_repeat('e',20),intdiv($clock->now()->getTimestamp(),30)));
}
$clock->time=$future;$app=new ApplicationService(new ApplicationAuthority($s['service'],$s['security']),$s['mfa']);
$contactRepo=new Profiles\Repositories\DoctorContactPointsRepository($p);$contactController=new Profiles\Controllers\DoctorContactPointsController($contactRepo);
$fixtures=[];
for($n=0;$n<26;$n++){
    $profile='app_profile_'.$n;
    $p->prepare("INSERT INTO profiles_doctors(doctor_id,display_name,professional_license,specialty_primary,profile_status,is_public_candidate,plan_code) VALUES(?,?,?,?,'active',1,'free')")->execute([$profile,'Profesional de aplicación sintética '.$n,'NOT-REAL-APP-LICENSE-'.$n,'Especialidad sintética']);
    foreach(['office-a','office-b'] as $office)$p->prepare('INSERT INTO consultorios(doctor_id,consultorio_id,titulo,municipio,estado) VALUES(?,?,?,?,?)')->execute([$profile,$office,'Consultorio sintético '.$office,'Ciudad sintética','Estado sintético']);
    $category=$n>=16&&$n<20?ApplicationCatalog::SENSITIVE[$n-16]:'CHANGED_PHONE';
    $report=corSubmit($s,$profile,$category)['receipt']['receipt_id'];
    if($n!==15){$proof=corProof($s,$clock,$legacyReviewer,$report);$s['review']->commit($legacyReviewer['context'],$report,1,$proof['case_id'],$proof['proof_id'],$proof['proof']);}
    // Fixture lifecycle only: intake FREE, then an explicit synthetic paid public
    // projection before application tests. No subscription row or runtime writer changes it.
    if($n!==14)$p->prepare("UPDATE profiles_doctors SET plan_code='basic' WHERE doctor_id=?")->execute([$profile]);
    $oldPhone='+1555'.str_pad((string)(8000000+$n),7,'0',STR_PAD_LEFT);
    $created=$contactController->store($profile,['type'=>'phone','value'=>$oldPhone],'synthetic_fixture');corCheck($created['ok'],'fixture_canonical_contact_create_'.$n);
    $contact=(string)$created['data']['contact_point']['contact_point_id'];
    $published=$contactController->update($profile,$contact,['scope'=>'public_profile','is_public'=>true,'use_for_public_profile'=>true,'visibility_plan_min'=>'basic'],'synthetic_fixture');corCheck($published['ok'],'fixture_canonical_contact_publication_'.$n);
    $fixtures[]=['profile'=>$profile,'report_id'=>$report,'contact'=>$contact,'old'=>$oldPhone,'new'=>'+1555'.str_pad((string)(9000000+$n),7,'0',STR_PAD_LEFT)];
}
$p->exec("INSERT INTO auth_account_memberships(membership_id,account_id,profile_doctor_id,role_code,scope_code,status,assignment_source) VALUES('app-managed-fixture','cor_user','app_profile_13','collaborator','profile','active','manual_review')");
$protected=[];
$allTables=$p->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
foreach($allTables as $table)if(preg_match('/^(clinical_|agenda_|subscription_|profile_(?:subscription|operator)|auth_(?:account_memberships|physician_|account_professional)|profiles_physician|profiles_doctors$|consultorios$)/',$table))
    $protected[$table]=$p->query('CHECKSUM TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1];
$reportHash=$p->query('CHECKSUM TABLE profiles_correction_reports')->fetch(PDO::FETCH_NUM)[1];$decisionHash=$p->query('CHECKSUM TABLE profiles_correction_decisions')->fetch(PDO::FETCH_NUM)[1];
function appRequest(ApplicationService $app,array $actor,array $f):array {
    $snap=$app->snapshot($actor['context'],$f['report_id']);$t=$snap['targets'][0]??null;if($t===null)throw new RuntimeException('fixture no public target');
    return ['command_id'=>(new Platform\Services\RandomAuditUuidProvider())->generateCanonicalUuid(),'report_id'=>$f['report_id'],'report_version'=>2,'profile_id'=>$f['profile'],'field_family'=>ApplicationCatalog::FAMILY,
        'target'=>['contact_point_id'=>$f['contact'],'consultorio_id'=>null],'expected_version'=>$t['expected_version'],'proposed_value'=>$f['new'],'verification_refs'=>['synthetic-independent-verification-01'],'facts_confirmed'=>true,'reason'=>'Corrección sintética verificada de teléfono público.'];
}
function appAuthorize(ApplicationService $app,CorClock $clock,array $actor,array $request):array {
    $clock->advance('+31 seconds');$b=$app->begin($actor['context'],$request,$actor['id'].'@example.invalid',COR_PASSWORD);
    $app->authorize($actor['context'],$b['command_id'],$b['challenge_id'],$b['challenge'],StaffMfaService::totp(str_repeat('e',20),intdiv($clock->now()->getTimestamp(),30)));return $b;
}
function appWorkerStart(array $actor,CorClock $clock,string $id):array {
    $proc=proc_open([PHP_BINARY,__FILE__,'worker'],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
    fwrite($pipes[0],json_encode(['now'=>$clock->now()->format(DATE_ATOM),'session'=>$actor['token'],'command_id'=>$id],JSON_THROW_ON_ERROR));fclose($pipes[0]);return [$proc,$pipes];
}
function appWorkerEnd(array $worker):array {
    [$proc,$pipes]=$worker;$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($proc)!==0)throw new RuntimeException('application worker failed '.$err);return json_decode($out,true,16,JSON_THROW_ON_ERROR);
}
$both=$appLogin['app_both'];$otherActor=$appLogin['app_second'];$applyOnly=$appLogin['app_applyonly'];
corCheck(hash('sha256',json_encode(array_slice(Platform\Services\CanonicalAuditPolicyRegistry::canonicalRows(),0,56),JSON_THROW_ON_ERROR))==='33cf56b780d0107ec5ce654dbbc305e9f88df5629935494201b45df5b5011c73','PUB_COR01_first_56_audit_policies_unchanged');
$request=appRequest($app,$both,$fixtures[0]);
corDeny(fn()=>$app->begin($appLogin['app_reviewonly']['context'],$request,'app_reviewonly@example.invalid',COR_PASSWORD),403,'Q02_review_permission_not_application_authority');
$pending=appRequest($app,$both,$fixtures[0]);$pending['report_id']=$fixtures[15]['report_id'];$pending['profile_id']=$fixtures[15]['profile'];
corDeny(fn()=>$app->begin($applyOnly['context'],$pending,'app_applyonly@example.invalid',COR_PASSWORD),403,'Q03_apply_permission_cannot_manufacture_acceptance');
foreach([['profile_id'=>$fixtures[1]['profile']],['target'=>['contact_point_id'=>$fixtures[1]['contact'],'consultorio_id'=>null]],['target'=>['contact_point_id'=>$fixtures[0]['contact'],'consultorio_id'=>'office-b']]] as $n=>$bad)
    corDeny(fn()=>$app->begin($both['context'],array_replace($request,$bad),'app_both@example.invalid',COR_PASSWORD),403,'Q06_Q07_exact_profile_contact_office_denial_'.$n);
foreach([['field_family'=>'OFFICE_ADDRESS'],['proposed_value'=>"'); UPDATE profiles_doctors SET profile_status='hidden' --"],['profile_status'=>'hidden'],['proposed_value'=>['value'=>'+15559000000']],['verification_refs'=>[]],['facts_confirmed'=>false]] as $n=>$bad)
    corDeny(fn()=>$app->begin($both['context'],array_replace($request,$bad),'app_both@example.invalid',COR_PASSWORD),400,'Q08_Q10_strict_typed_allowlist_'.$n);
corDeny(fn()=>$app->begin($both['context'],array_replace($request,['expected_version'=>str_repeat('0',64)]),'app_both@example.invalid',COR_PASSWORD),409,'Q09_current_version_mismatch_denied');
$b=$app->begin($both['context'],$request,'app_both@example.invalid',COR_PASSWORD);
corDeny(fn()=>$app->execute($both['context'],$b['command_id']),403,'Q05_missing_MFA_denied');
$clock->advance('+31 seconds');$code=StaffMfaService::totp(str_repeat('e',20),intdiv($clock->now()->getTimestamp(),30));
try{$app->authorize($both['context'],$b['command_id'],$b['challenge_id'],$b['challenge'],str_pad((string)(((int)$code+1)%1000000),6,'0',STR_PAD_LEFT));throw new RuntimeException('bad OTP allowed');}catch(RuntimeException $e){corCheck($e->getMessage()==='staff_mfa_verification_denied','Q05_wrong_MFA_denied');}
$app->authorize($both['context'],$b['command_id'],$b['challenge_id'],$b['challenge'],$code);
corCheck($contactRepo->findById($fixtures[0]['profile'],$fixtures[0]['contact'])['normalized_value']===$fixtures[0]['old'],'MFA_authorization_is_not_a_mutation');
$done=$app->execute($both['context'],$b['command_id']);corCheck($done['state']==='APPLIED'&&$done['profile_changed'],'Q01_Q12_committed_application_result');
corCheck($app->execute($both['context'],$b['command_id'])['replayed'],'Q15_exact_execute_replay_same_result');
corCheck($app->begin($both['context'],$request,'app_both@example.invalid',COR_PASSWORD)['replayed'],'Q15_exact_begin_replay_same_result');
corDeny(fn()=>$app->begin($both['context'],array_replace($request,['proposed_value'=>'+15559999999']),'app_both@example.invalid',COR_PASSWORD),409,'Q16_changed_command_identity_payload_rejected');
$public=(new Profiles\Controllers\PublicProfileController(new Profiles\Repositories\PublicProfileRepository($p)))->showByDoctorId($fixtures[0]['profile']);
corCheck($public['data']['contact']['phone']===$fixtures[0]['new'],'Q28_original_public_controller_reflects_committed_phone');
// Real canonical duplicate validation, not a second implementation of the writer.
$dup=$contactController->store($fixtures[1]['profile'],['type'=>'phone','value'=>$fixtures[1]['new']],'synthetic_concurrent_fixture');corCheck($dup['ok'],'duplicate_fixture_created_canonically');
$r=appRequest($app,$both,$fixtures[1]);$b=appAuthorize($app,$clock,$both,$r);
corDeny(fn()=>$app->execute($both['context'],$b['command_id']),409,'Q11_canonical_duplicate_validation_enforced');
corCheck($app->snapshot($both['context'],$r['report_id'])['result']===null,'Q13_failed_canonical_writer_never_APPLIED');
$r=appRequest($app,$both,$fixtures[2]);$b=appAuthorize($app,$clock,$both,$r);$before=$app->snapshot($both['context'],$r['report_id'])['targets'][0];
foreach([5,4] as $fault){$p->exec('UPDATE gov03b_qa_faults SET enabled='.$fault);
    try{$app->execute($both['context'],$b['command_id']);throw new RuntimeException('fault accepted');}catch(Throwable $e){
        corCheck($fault===5?($e instanceof ReportDenied&&$e->status===422):($e instanceof PDOException&&$e->getCode()==='45000'&&str_contains($e->getMessage(),'synthetic audit failure')),'exact_injected_failure_reached_'.$fault);
    }
    $p->exec('UPDATE gov03b_qa_faults SET enabled=0');$after=$app->snapshot($both['context'],$r['report_id']);
    corCheck($after['result']===null&&$after['targets'][0]===$before,'Q13_Q14_writer_audit_rollback_no_contact_version_or_APPLIED_'.$fault);
    corCheck($app->reconcile($both['context'],$b['command_id'])['retry_available'],'Q18_explicit_reconciled_retry_'.$fault);
}
corCheck($app->execute($both['context'],$b['command_id'])['state']==='APPLIED','retry_reuses_exact_authorized_command_after_full_rollback');
$r=appRequest($app,$both,$fixtures[3]);$r['proposed_value']=$fixtures[3]['old'];$b=appAuthorize($app,$clock,$both,$r);$p->exec('UPDATE gov03b_qa_faults SET enabled=5');
$noChange=$app->execute($both['context'],$b['command_id']);$p->exec('UPDATE gov03b_qa_faults SET enabled=0');corCheck($noChange['state']==='NO_CHANGE'&&!$noChange['profile_changed'],'already_matching_value_no_writer_no_false_mutation');
// ABA: same value and same DATETIME second must still invalidate the approval.
$r=appRequest($app,$both,$fixtures[4]);$b=appAuthorize($app,$clock,$both,$r);
$contactController->update($fixtures[4]['profile'],$fixtures[4]['contact'],['value'=>'+15558888888'],'synthetic_concurrent_writer');
$contactController->update($fixtures[4]['profile'],$fixtures[4]['contact'],['value'=>$fixtures[4]['old']],'synthetic_concurrent_writer');
corDeny(fn()=>$app->execute($both['context'],$b['command_id']),409,'Q09_monotonic_version_rejects_ABA');
$r=appRequest($app,$both,$fixtures[5]);$b=appAuthorize($app,$clock,$both,$r);$at=$clock->time;$clock->advance('+301 seconds');
corDeny(fn()=>$app->execute($both['context'],$b['command_id']),403,'Q05_stale_MFA_and_reauth_denied');$clock->time=$at;
foreach(['app_revoked','app_credential','app_factor'] as $who){
    $a=$appLogin[$who];$r=appRequest($app,$a,$fixtures[6]);$b=appAuthorize($app,$clock,$a,$r);
    if($who==='app_revoked')$governance->execute($appCookie,'revoke',$who,ApplicationCatalog::CAPABILITY);
    if($who==='app_credential')$p->prepare('UPDATE auth_account_credentials SET credential_version=2 WHERE account_id=?')->execute([$who]);
    if($who==='app_factor')$p->prepare("UPDATE auth_staff_mfa_factors SET status='REVOKED',revoked_at=UTC_TIMESTAMP() WHERE account_id=? AND status='ACTIVE'")->execute([$who]);
    try{$app->execute($a['context'],$b['command_id']);throw new RuntimeException('stale authority allowed');}catch(Throwable $e){corCheck($who==='app_credential'?$e->getMessage()==='governance_session_invalidated':($e instanceof ReportDenied&&$e->status===403),'grant_credential_factor_revalidated_at_commit_'.$who);}
}
// Two different authorized actors/commands compete for the same accepted report.
$ra=appRequest($app,$both,$fixtures[7]);$rb=appRequest($app,$otherActor,$fixtures[7]);$rb['proposed_value']='+15557777777';
$a=appAuthorize($app,$clock,$both,$ra);$b=appAuthorize($app,$clock,$otherActor,$rb);
$workers=[appWorkerStart($both,$clock,$a['command_id']),appWorkerStart($otherActor,$clock,$b['command_id'])];$out=array_map('appWorkerEnd',$workers);
corCheck(count(array_filter($out,fn($r)=>$r['ok']))===1&&count(array_filter($out,fn($r)=>!$r['ok']&&$r['status']===409))===1,'Q17_parallel_distinct_commands_one_committed_report_result');
$r=appRequest($app,$both,$fixtures[8]);$b=appAuthorize($app,$clock,$both,$r);
$out=array_map('appWorkerEnd',[appWorkerStart($both,$clock,$b['command_id']),appWorkerStart($both,$clock,$b['command_id'])]);
corCheck($out[0]['ok']&&$out[1]['ok']&&$out[0]['result']['command_id']===$out[1]['result']['command_id']&&$out[0]['result']['replayed']!==$out[1]['result']['replayed'],'Q17_parallel_exact_command_one_writer_one_replay');
// Kill only our synthetic PHP worker while the owned AFTER UPDATE fault sleeps.
// Its contact/version/proof writes are uncommitted; durable APPLY_STARTED survives.
$r=appRequest($app,$both,$fixtures[9]);$b=appAuthorize($app,$clock,$both,$r);$p->exec('UPDATE gov03b_qa_faults SET enabled=6');$w=appWorkerStart($both,$clock,$b['command_id']);
for($i=0;$i<100;$i++){$q=$p->prepare('SELECT state FROM profiles_correction_application_journal WHERE command_id=? ORDER BY sequence_number DESC LIMIT 1');$q->execute([$b['command_id']]);if($q->fetchColumn()==='APPLY_STARTED')break;usleep(20000);}
proc_terminate($w[0],9);foreach([1,2] as $fd){stream_get_contents($w[1][$fd]);fclose($w[1][$fd]);}proc_close($w[0]);$p->exec('UPDATE gov03b_qa_faults SET enabled=0');
$reconciled=$app->reconcile($both['context'],$b['command_id']);corCheck($reconciled['state']==='RECONCILED_RETRYABLE'&&$reconciled['retry_available'],'Q18_crashed_partial_transaction_explicit_reconciliation');
corCheck($app->execute($both['context'],$b['command_id'])['state']==='APPLIED','Q18_reconciled_retry_commits_once');
for($n=16;$n<20;$n++){
    $snap=$app->snapshot($both['context'],$fixtures[$n]['report_id']);corCheck(!$snap['can_apply']&&$snap['targets']===[],'Q21_Q24_sensitive_report_application_disabled_'.$n);
    $bad=$request;$bad['command_id']=$s['service']->uuid();$bad['report_id']=$fixtures[$n]['report_id'];$bad['profile_id']=$fixtures[$n]['profile'];$bad['target']['contact_point_id']=$fixtures[$n]['contact'];
    corDeny(fn()=>$app->begin($both['context'],$bad,'app_both@example.invalid',COR_PASSWORD),403,'Q21_Q24_sensitive_report_cannot_mutate_'.$n);
}
corCheck(!$app->snapshot($both['context'],$fixtures[14]['report_id'])['can_apply'],'unpublished_FREE_contact_not_promoted_by_application');
corCheck(!$app->snapshot($both['context'],$fixtures[13]['report_id'])['can_apply'],'managed_profile_does_not_bypass_existing_owner_edit_authority');
foreach(['UPDATE profiles_contact_correction_versions SET version=version-1','DELETE FROM profiles_contact_correction_versions'] as $sql){
    try{$p->exec($sql);throw new RuntimeException('version rollback allowed');}catch(PDOException $e){corCheck($e->getCode()==='45000','monotonic_version_history_guard_'.$sql);}
}
$r=appRequest($app,$applyOnly,$fixtures[10]);$b=appAuthorize($app,$clock,$applyOnly,$r);corCheck($app->execute($applyOnly['context'],$b['command_id'])['state']==='APPLIED','apply_only_authority_can_consume_existing_acceptance_without_review_write');
foreach(['profiles_correction_application_commands','profiles_correction_application_authorizations','profiles_correction_application_journal','profiles_correction_application_results'] as $table){
    foreach(['UPDATE '.$table.' SET command_id=command_id','DELETE FROM '.$table] as $sql){try{$p->exec($sql);throw new RuntimeException('immutable application write allowed');}catch(PDOException $e){corCheck($e->getCode()==='45000','Q19_immutable_application_history_'.$sql);}}
}
corCheck($reportHash===$p->query('CHECKSUM TABLE profiles_correction_reports')->fetch(PDO::FETCH_NUM)[1]&&$decisionHash===$p->query('CHECKSUM TABLE profiles_correction_decisions')->fetch(PDO::FETCH_NUM)[1],'Q19_Q20_report_anonymous_payload_and_accepted_history_unchanged');
foreach($protected as $table=>$checksum)corCheck($checksum===$p->query('CHECKSUM TABLE '.$table)->fetch(PDO::FETCH_NUM)[1],'Q25_Q27_protected_source_data_unchanged_'.$table);
$raw=$p->query("SELECT metadata_json FROM platform_audit_events WHERE action='PUBLIC_PROFILE_CORRECTION_APPLICATION'")->fetchAll(PDO::FETCH_COLUMN);
corCheck(!str_contains(implode('',$raw),'example.invalid')&&!str_contains(implode('',$raw),'+1555')&&!str_contains(implode('',$raw),'private-synthetic'),'application_audit_excludes_report_contact_proposed_phone_and_free_text');
$at=$clock->time;$clock->advance('+13 hours');
try{$app->snapshot($both['context'],$fixtures[11]['report_id']);throw new RuntimeException('expired session allowed');}catch(RuntimeException $e){corCheck($e->getMessage()==='governance_session_invalidated','Q04_expired_session_denied');}$clock->time=$at;
$p->prepare("UPDATE auth_accounts SET status='disabled' WHERE account_id='app_applyonly'")->execute();
try{$app->execute($applyOnly['context'],$b['command_id']);throw new RuntimeException('disabled session allowed');}catch(RuntimeException $e){corCheck($e->getMessage()==='governance_session_invalidated','Q04_disabled_current_session_denied_even_for_replay');}
file_put_contents((string)getenv('GOV03B_QA_EVIDENCE').'/application-fixture.json',json_encode(['password'=>COR_PASSWORD,'reports'=>array_slice($fixtures,20,6),'reviewers'=>array_map(fn($i)=>'app_ui_'.$i,range(0,5))],JSON_THROW_ON_ERROR));
echo "PUB_COR02_NATIVE=PASS\n";
