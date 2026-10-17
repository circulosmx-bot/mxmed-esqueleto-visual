<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/support/CorrectionHarness.php';
use Profiles\Corrections\{ReportCatalog,ReportEligibility,ReportDenied,ReportRatePolicy};
use Identity\Services\StaffMfaService;
if(($argv[1]??'')==='worker'){
    $in=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);$p=corConnect();$clock=new CorClock(new DateTimeImmutable($in['now']));$s=corStack($p,$clock);
    $actor=$in['action']==='submit'?null:$s['sessions']->validate($in['session'])->context();
    try{
        if($in['action']==='submit')$r=$s['service']->submit($in['intent']['intent'],$in['intent']['csrf_token'],$in['browser'],'127.0.0.1',$in['route'],$in['payload']);
        else $r=$s['review']->commit($actor,$in['report_id'],$in['version'],$in['case_id'],$in['proof_id'],$in['proof']);
        echo json_encode(['ok'=>true,'data'=>$r],JSON_THROW_ON_ERROR);
    }catch(ReportDenied $e){echo json_encode(['ok'=>false,'status'=>$e->status]);}exit;
}
$p=corConnect();$clock=new CorClock(new DateTimeImmutable('-10 minutes',new DateTimeZone('UTC')));$s=corStack($p,$clock);
$hash=password_hash(COR_PASSWORD,PASSWORD_ARGON2ID);
$actors=['cor_director','cor_reviewer','cor_second','cor_no_grant','cor_c4_only','cor_owner','cor_user','cor_revoked','cor_replaced','cor_disabled','cor_credential','cor_session'];
for($i=0;$i<6;$i++)$actors[]='cor_ui_'.$i;
foreach($actors as $id){
    $p->prepare("INSERT INTO auth_accounts(account_id,email_address,email_normalized,status,email_verified_at) VALUES(?,?,?,'active',UTC_TIMESTAMP())")->execute([$id,$id.'@example.invalid',$id.'@example.invalid']);
    $p->prepare('INSERT INTO auth_account_credentials(account_id,password_hash,password_changed_at,credential_version) VALUES(?,?,UTC_TIMESTAMP(),1)')->execute([$id,$hash]);
    if(!in_array($id,['cor_owner','cor_user'],true))$p->prepare("INSERT INTO internal_staff(account_id,governance_class,status) VALUES(?,?,'ACTIVE')")->execute([$id,$id==='cor_director'?'DIRECTOR':'MASTER_ADMIN']);
}
$director=corLogin($s,'cor_director');
$governance=new Identity\Services\InternalGovernanceService($p,new Identity\Http\CanonicalHttpSessionResolver($s['sessions']));
$cookie=['__Host-mxmed_session'=>$director['token']];
$ceremony=new Identity\Services\GovernanceStaffMfaCeremony($p,$s['mfa'],$s['security'],new Identity\Adapters\CanonicalGovernanceAuditAdapter($p),$clock,['cor_director']);
$login=[];
foreach($actors as $id){
    if(in_array($id,['cor_director','cor_owner','cor_user','cor_no_grant'],true))continue;
    $governance->execute($cookie,'grant',$id,$id==='cor_c4_only'?'physician_accreditation_review':ReportCatalog::CAPABILITY);
    $actor=corLogin($s,$id);$login[$id]=$actor;
    $factor=$ceremony->begin($actor['context'],$director['context'],$id.'@example.invalid',COR_PASSWORD,'cor_director@example.invalid',COR_PASSWORD);
    $ceremony->activate($actor['context'],$factor['factor_id'],StaffMfaService::totp(str_repeat('e',20),intdiv($clock->now()->getTimestamp(),30)));
}
for($i=0;$i<12;$i++){
    $id='cor_profile_'.$i;
    $p->prepare("INSERT INTO profiles_doctors(doctor_id,display_name,professional_license,specialty_primary,profile_status,is_public_candidate,plan_code) VALUES(?,?,?,?,'active',1,?)")
        ->execute([$id,'Profesional sintético '.$i,'NOT-A-REAL-LICENSE-'.$i,'Especialidad sintética',$i===1?'optimum':'free']);
    $p->prepare('INSERT INTO consultorios(doctor_id,consultorio_id,titulo,municipio,estado) VALUES(?,?,?,?,?)')->execute([$id,'synthetic-office','Consultorio sintético','Ciudad sintética','Estado sintético']);
}
$p->exec("INSERT INTO auth_account_memberships(membership_id,account_id,profile_doctor_id,role_code,scope_code,status,assignment_source) VALUES('cor-owner','cor_owner','cor_profile_2','owner','profile','active','manual_review'),('cor-collaborator','cor_user','cor_profile_3','collaborator','profile','active','manual_review')");
$protected=[];
foreach(['profiles_doctors','consultorios','auth_account_memberships','auth_physician_enrollment_origins','auth_physician_evidence_requests','profiles_physician_admissions','agenda_appointments','clinical_documents'] as $table)
    $protected[$table]=$p->query('CHECKSUM TABLE '.$table)->fetch(PDO::FETCH_NUM)[1];
$clock->time=new DateTimeImmutable('now',new DateTimeZone('UTC'));
corCheck(count(ReportCatalog::CATEGORIES)===10&&count(ReportCatalog::RELATIONS)===8,'closed_versioned_catalogs');
corCheck(hash('sha256',json_encode(array_slice(Platform\Services\CanonicalAuditPolicyRegistry::canonicalRows(),0,54),JSON_THROW_ON_ERROR))==='3c554ceaeb6b796ef8dc045c594712d370ac5d2a8b5cb925066f55686f52ae71','first_54_audit_policies_unchanged');
corCheck((new Platform\Services\CorrelatableOperationCatalog())->operationForEvent('PUBLIC_PROFILE_CORRECTION_RECEIVED')==='PUBLIC_PROFILE_CORRECTION_LIFECYCLE'
    &&(new Platform\Services\SourceModuleCatalog())->moduleForEvent('PUBLIC_PROFILE_CORRECTION_REVIEWED')==='PROFILE','finite_new_audit_catalogs');
$one=corSubmit($s,'cor_profile_0');$id=$one['receipt']['receipt_id'];
corCheck(strlen($id)===36&&!$one['receipt']['replayed'],'Q01_Q07_FREE_unmanaged_anonymous_durable_report');
foreach([1,2,3] as $i)corDeny(fn()=>$s['service']->intent('cor_profile_'.$i,ReportEligibility::route('cor_profile_'.$i),$one['browser']),403,'Q02_Q03_paid_owner_collaborator_denied_'.$i);
$p->fault='FROM `auth_account_memberships`';corDeny(fn()=>$s['service']->eligibility->resolve('cor_profile_0'),403,'Q04_unavailable_membership_source_fails_closed');$p->fault=null;
$row=$p->query('SELECT * FROM profiles_correction_reports')->fetch(PDO::FETCH_ASSOC);
corCheck($row['profile_doctor_id']==='cor_profile_0'&&$row['original_route']===$one['route'],'Q05_exact_original_profile_and_route');
corCheck(!str_contains($row['payload_ciphertext'],'example.invalid')&&!str_contains($row['payload_ciphertext'],'Información'),'Q17_private_ciphertext_not_plaintext');
$payload=$s['cipher']->open('report:'.$id.':cor_profile_0',$row['key_id'],$row['payload_ciphertext']);
corCheck($payload['contact']==='private-synthetic@example.invalid'&&$payload['relation']==='','optional_contact_relation_minimization');
try{$s['cipher']->open('report:'.$id.':cor_profile_4',$row['key_id'],$row['payload_ciphertext']);throw new RuntimeException('wrong AAD allowed');}catch(RuntimeException $e){corCheck($e->getMessage()==='correction_decryption_failed','Q22_ciphertext_exact_report_profile_scope');}
$again=fn(array $d,?string $csrf=null,?string $browser=null,?string $route=null)=>$s['service']->submit($one['intent']['intent'],$csrf??$one['intent']['csrf_token'],$browser??$one['browser'],'127.0.0.1',$route??$one['route'],$d);
corDeny(fn()=>$again($one['payload'],'bad'),403,'Q12_missing_invalid_CSRF');
corDeny(fn()=>$again($one['payload'],browser:bin2hex(random_bytes(32))),403,'anonymous_cookie_binding');
corDeny(fn()=>$again($one['payload'],route:ReportEligibility::route('cor_profile_4')),403,'Q09_profile_substitution_denied');
foreach([['category'=>'OWNER'],['relation'=>'OWNER'],['explanation'=>str_repeat('x',2001)],['privacy_ack'=>false],['explanation'=>[1]],['contact'=>'invalid'],['doctor_id'=>'cor_profile_4']] as $n=>$bad)
    corDeny(fn()=>$again(array_replace($one['payload'],$bad)),400,'Q10_Q11_strict_payload_'.$n);
corCheck($again($one['payload'])['receipt_id']===$id&&(int)$p->query('SELECT COUNT(*) FROM profiles_correction_reports')->fetchColumn()===1,'Q15_exact_replay_one_report');
corDeny(fn()=>$again(array_replace($one['payload'],['explanation'=>'Different valid synthetic report explanation.'])),409,'idempotency_payload_conflict');
$audit=$p->query("SELECT real_actor_reference,effective_actor_reference,metadata_json FROM platform_audit_events WHERE action='PUBLIC_PROFILE_CORRECTION_RECEIVED'")->fetch(PDO::FETCH_ASSOC);
corCheck(str_starts_with($audit['real_actor_reference'],'anonymous_reporter:report-')&&$audit['effective_actor_reference']===null
    &&!str_contains($audit['metadata_json'],'example.invalid')&&!str_contains($audit['metadata_json'],'explanation'),'explicit_non_account_anonymous_audit_no_private_payload');
$browser=bin2hex(random_bytes(32));for($i=0;$i<3;$i++)corSubmit($s,'cor_profile_4',browser:$browser);
corDeny(fn()=>corSubmit($s,'cor_profile_4',browser:$browser),429,'Q14_report_specific_browser_rate_denial');
$rateBefore=$p->query('SELECT COUNT(*) FROM auth_rate_limit_buckets')->fetchColumn();
$p->fault='profiles_correction_rate_buckets';try{corSubmit($s,'cor_profile_4');throw new RuntimeException('rate storage failure allowed');}catch(PDOException){corCheck(true,'rate_storage_failure_denied');}$p->fault=null;
corCheck($rateBefore===$p->query('SELECT COUNT(*) FROM auth_rate_limit_buckets')->fetchColumn(),'report_rate_operation_never_consumes_auth_buckets');
$rateClock=new CorClock($clock->now()->modify('+3 hours'));
$peerPolicy=new ReportRatePolicy($p,$s['cipher'],$rateClock,3,2,100);
for($i=0;$i<2;$i++)$peerPolicy->consume(bin2hex(random_bytes(32)),'198.51.100.4','cor_profile_4');
corDeny(fn()=>$peerPolicy->consume(bin2hex(random_bytes(32)),'198.51.100.4','cor_profile_4'),429,'report_peer_bucket_denial_despite_new_browser');
$profilePolicy=new ReportRatePolicy($p,$s['cipher'],$rateClock,3,100,1);
$profilePolicy->consume(bin2hex(random_bytes(32)),'198.51.100.5','cor_profile_5');
corDeny(fn()=>$profilePolicy->consume(bin2hex(random_bytes(32)),'198.51.100.6','cor_profile_5'),429,'report_profile_bucket_denial_despite_new_peer');
$count=$p->query('SELECT COUNT(*) FROM profiles_correction_reports')->fetchColumn();$p->exec('UPDATE gov03b_qa_faults SET enabled=1');
try{corSubmit($s,'cor_profile_4');throw new RuntimeException('audit failure accepted');}catch(PDOException){corCheck($count===$p->query('SELECT COUNT(*) FROM profiles_correction_reports')->fetchColumn(),'Q16_required_audit_failure_rolls_back_intake');}$p->exec('UPDATE gov03b_qa_faults SET enabled=0');
foreach(['cor_no_grant','cor_c4_only','cor_owner','cor_user'] as $who){$a=$login[$who]??corLogin($s,$who);corDeny(fn()=>$s['review']->open($a['context'],$id),403,'Q19_Q20_separate_exact_review_grant_'.$who);}
$reviewer=$login['cor_reviewer'];$second=$login['cor_second'];
corCheck(count($s['review']->queue($reviewer['context'])['reports'])===4&&$s['review']->open($reviewer['context'],$id)['payload']['contact']===$payload['contact'],'authorized_queue_and_private_exact_open');
corCheck($s['review']->queue($reviewer['context'],1)['reports']===[],'bounded_queue_pagination');
corDeny(fn()=>$s['review']->queue($reviewer['context'],-1),400,'invalid_queue_page_denied');
foreach(['cor_revoked','cor_replaced','cor_disabled','cor_credential','cor_session'] as $who){
    $proof=corProof($s,$clock,$login[$who],$id);
    if($who==='cor_revoked')$governance->execute($cookie,'revoke',$who,ReportCatalog::CAPABILITY);
    if($who==='cor_replaced'){$governance->execute($cookie,'revoke',$who,ReportCatalog::CAPABILITY);$governance->execute($cookie,'grant',$who,ReportCatalog::CAPABILITY);}
    if($who==='cor_disabled')$p->prepare("UPDATE auth_accounts SET status='disabled' WHERE account_id=?")->execute([$who]);
    if($who==='cor_credential')$p->prepare('UPDATE auth_account_credentials SET credential_version=2 WHERE account_id=?')->execute([$who]);
    if($who==='cor_session')$s['sessions']->logout($login[$who]['token']);
    try{$s['review']->commit($login[$who]['context'],$id,1,$proof['case_id'],$proof['proof_id'],$proof['proof']);throw new RuntimeException('invalid authority accepted');}
    catch(ReportDenied $e){corCheck($e->status===403,'Q21_authority_revalidated_'.$who);}
    catch(RuntimeException $e){corCheck($e->getMessage()==='governance_session_invalidated','Q21_session_credential_revalidated_'.$who);}
}
$proof=corProof($s,$clock,$reviewer,$id,'NEEDS_CLARIFICATION');$other=corSubmit($s,'cor_profile_5');$otherId=$other['receipt']['receipt_id'];
corDeny(fn()=>$s['review']->commit($reviewer['context'],$otherId,1,$proof['case_id'],$proof['proof_id'],$proof['proof']),403,'Q22_other_report_profile_proof_denied');
corDeny(fn()=>$s['review']->commit($second['context'],$id,1,$proof['case_id'],$proof['proof_id'],$proof['proof']),403,'Q22_other_account_proof_denied');
$p->exec('UPDATE gov03b_qa_faults SET enabled=1');try{$s['review']->commit($reviewer['context'],$id,1,$proof['case_id'],$proof['proof_id'],$proof['proof']);throw new RuntimeException('decision audit failure allowed');}catch(PDOException){corCheck((int)$p->query('SELECT COUNT(*) FROM profiles_correction_decisions')->fetchColumn()===0,'decision_audit_rollback_including_proof_and_case');}$p->exec('UPDATE gov03b_qa_faults SET enabled=0');
$s['review']->commit($reviewer['context'],$id,1,$proof['case_id'],$proof['proof_id'],$proof['proof']);
corCheck($s['review']->open($reviewer['context'],$id)['status']==='NEEDS_CLARIFICATION','human_clarification_recorded_no_delivery');
$proof=corProof($s,$clock,$reviewer,$id,'REJECTED',2);$s['review']->commit($reviewer['context'],$id,2,$proof['case_id'],$proof['proof_id'],$proof['proof']);
foreach(['UPDATE profiles_correction_reports SET category=category','DELETE FROM profiles_correction_reports','UPDATE profiles_correction_decisions SET version=version','DELETE FROM profiles_correction_decisions','UPDATE profiles_correction_review_intents SET report_version=report_version','DELETE FROM profiles_correction_review_intents'] as $sql){
    try{$p->exec($sql);throw new RuntimeException('immutable write accepted');}catch(PDOException $e){corCheck($e->getCode()==='45000','Q24_database_append_only_'.$sql);}
}
foreach(['PHYSICIAN_RETIRED','PHYSICIAN_DECEASED'] as $category){$r=corSubmit($s,'cor_profile_6',$category)['receipt']['receipt_id'];$proof=corProof($s,$clock,$reviewer,$r);$d=$s['review']->commit($reviewer['context'],$r,1,$proof['case_id'],$proof['proof_id'],$proof['proof']);corCheck($d['status']==='ACCEPTED_FOR_APPLICATION'&&$d['profile_changed']===false,'Q26_Q27_Q28_private_acceptance_no_application_'.$category);}
$unsupported=['status'=>'APPLIED','reason'=>'FACTS_SUPPORTED','decision_note'=>'Synthetic review','evidence_refs'=>['synthetic-reference'],'facts_confirmed'=>true];
corDeny(fn()=>$s['review']->begin($reviewer['context'],$otherId,1,$unsupported,'cor_reviewer@example.invalid',COR_PASSWORD),400,'APPLIED_unavailable');
corDeny(fn()=>$s['review']->begin($reviewer['context'],$otherId,1,array_replace($unsupported,['status'=>'ACCEPTED_FOR_APPLICATION','evidence_refs'=>[]]),'cor_reviewer@example.invalid',COR_PASSWORD),400,'accept_requires_evidence_reference');
corDeny(fn()=>$s['review']->begin($reviewer['context'],$otherId,1,array_replace($unsupported,['status'=>'ACCEPTED_FOR_APPLICATION','facts_confirmed'=>false]),'cor_reviewer@example.invalid',COR_PASSWORD),400,'accept_requires_human_fact_confirmation');
// Real independent PDO/processes, two fresh MFA proofs, one CAS winner.
$proofA=corProof($s,$clock,$reviewer,$otherId);$proofB=corProof($s,$clock,$second,$otherId,'REJECTED');$workers=[];
foreach([[$reviewer,$proofA],[$second,$proofB]] as [$a,$proof]){
    $proc=proc_open([PHP_BINARY,__FILE__,'worker'],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
    fwrite($pipes[0],json_encode(['action'=>'commit','now'=>$clock->now()->format(DATE_ATOM),'session'=>$a['token'],'report_id'=>$otherId,'version'=>1]+$proof,JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];
}
$wins=0;$conflicts=0;foreach($workers as [$proc,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($proc)!==0)throw new RuntimeException('worker failed '.$err);$r=json_decode($out,true,16,JSON_THROW_ON_ERROR);$wins+=(int)$r['ok'];$conflicts+=(int)(!$r['ok']&&$r['status']===409);}
corCheck($wins===1&&$conflicts===1,'Q23_real_parallel_decisions_one_CAS_winner');
// Anonymous parallel retries use no Identity session at all.
$browser=bin2hex(random_bytes(32));$route=ReportEligibility::route('cor_profile_7');$intent=$s['service']->intent('cor_profile_7',$route,$browser);$workers=[];
for($i=0;$i<2;$i++){
    $proc=proc_open([PHP_BINARY,__FILE__,'worker'],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
    fwrite($pipes[0],json_encode(['action'=>'submit','now'=>$clock->now()->format(DATE_ATOM),'intent'=>$intent,'browser'=>$browser,'route'=>$route,'payload'=>$one['payload']],JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];
}
$ids=[];$replays=[];foreach($workers as [$proc,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($proc)!==0)throw new RuntimeException('anonymous worker failed '.$err);$r=json_decode($out,true,16,JSON_THROW_ON_ERROR);corCheck($r['ok'],'parallel_anonymous_retry_allowed');$ids[]=$r['data']['receipt_id'];$replays[]=$r['data']['replayed'];}
sort($replays);corCheck(count(array_unique($ids))===1&&$replays===[false,true],'parallel_anonymous_idempotency_one_immutable_report');
$beforeTime=$clock->time;$clock->advance('+901 seconds');
corDeny(fn()=>$s['service']->submit($intent['intent'],$intent['csrf_token'],$browser,'127.0.0.1',$route,$one['payload']),403,'expired_public_origin_and_CSRF_denied');$clock->time=$beforeTime;
foreach($protected as $table=>$checksum)corCheck($checksum===$p->query('CHECKSUM TABLE '.$table)->fetch(PDO::FETCH_NUM)[1],'Q08_Q25_Q29_Q30_Q31_Q32_unchanged_'.$table);
file_put_contents((string)getenv('GOV03B_QA_EVIDENCE').'/cor-fixture.json',json_encode(['password'=>COR_PASSWORD,'profiles'=>array_map(fn($i)=>'cor_profile_'.($i+6),range(0,5)),
    'reviewers'=>array_map(fn($i)=>'cor_ui_'.$i,range(0,5))],JSON_THROW_ON_ERROR));
echo "PUB_COR01_NATIVE=PASS\n";
