<?php
declare(strict_types=1);
require_once getenv('TRT04_QA_ROOT') . '/api/_lib/clinical_treatments.php';
$pdo=new PDO('mysql:host=localhost;dbname='.getenv('TRT04_QA_DB'),'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$service=new ClinicalTreatments($pdo,'d1','p1','actor');
$checks=[];
function check(bool $ok,string $name): void {global $checks;if(!$ok)throw new RuntimeException('FAIL '.$name);$checks[]=$name;}
function rejects(callable $fn,string $code,string $name): void {try{$fn();throw new RuntimeException('FAIL '.$name.' accepted');}catch(ClinicalTreatmentException $e){check($e->codeName===$code,$name);}}
$planInput=['title'=>'Terapia de rehabilitación','responsible_provider_account_id'=>'provider'];
rejects(fn()=> $service->createPlan(['title'=>'No grant','responsible_provider_account_id'=>'other'],'p-no-grant'),'CLINICAL_GRANT_REQUIRED','provider grant required');
rejects(fn()=> $service->createPlan(['title'=>'Cross doctor','responsible_provider_account_id'=>'outsider'],'p-cross'),'CLINICAL_GRANT_REQUIRED','cross doctor grant denied');
rejects(fn()=> $service->createPlan($planInput+['source_encounter_id'=>2],'p-cross-enc'),'ENCOUNTER_SCOPE_INVALID','plan encounter scope denied');
$plan=$service->createPlan($planInput,'p1');$planId=(int)$plan['record']['plan_id'];
check($planId>0 && $plan['record']['status']==='PLANNED','plan create');
check($plan['record']['responsible_provider_role_snapshot']==='Fisioterapeuta','responsible provider role snapshot');
check($service->createPlan($planInput,'p1')['record']['plan_id']===$planId,'plan replay');
rejects(fn()=> $service->createPlan($planInput+['goals'=>'different'],'p1'),'IDEMPOTENCY_KEY_REUSED','plan conflict');
$p2=$service->createPlan($planInput,'p2');$planId2=(int)$p2['record']['plan_id'];
$a=$service->transitionPlan($planId,'ACTIVE',1,null,'pa1');$b=$service->transitionPlan($planId2,'ACTIVE',1,null,'pa2');
check($a['record']['status']==='ACTIVE' && $b['record']['status']==='ACTIVE','two active plans');
check($service->transitionPlan($planId,'ACTIVE',1,null,'pa1')['idempotency_replay'],'transition replay');
rejects(fn()=> $service->transitionPlan($planId,'PAUSED',1,null,'pa-stale'),'ROW_VERSION_CONFLICT','plan CAS');
$paused=$service->transitionPlan($planId,'PAUSED',2,'Pausa clínica','pa3');
check($paused['record']['status']==='PAUSED','pause');
$service->transitionPlan($planId,'ACTIVE',3,null,'pa4');
$service->transitionPlan($planId,'COMPLETED',4,null,'pa5');
rejects(fn()=> $service->transitionPlan($planId,'ACTIVE',5,null,'pa6'),'INVALID_PLAN_TRANSITION','terminal plan');
$pdo->exec("UPDATE clinical_performer_authorizations SET status='REVOKED',revoked_at=UTC_TIMESTAMP() WHERE doctor_id='d1' AND account_id='performer' AND capability='TREATMENT_PERFORMER'");
$base=['title'=>'Sesión de rehabilitación','encounter_scope'=>'STANDALONE','performed_by_account_id'=>'performer'];
rejects(fn()=> $service->createDraft($base,'s-revoked'),'CLINICAL_GRANT_REQUIRED','revoked grant');
$pdo->exec("INSERT INTO clinical_performer_authorizations (doctor_id,account_id,capability,clinical_role_label,granted_by_account_id) VALUES ('d1','performer','TREATMENT_PERFORMER','Fisioterapeuta','actor')");
$pdo->exec("UPDATE auth_account_memberships SET status='suspended' WHERE membership_id='m3'");
rejects(fn()=> $service->createDraft($base,'s-inactive-membership'),'CLINICAL_GRANT_REQUIRED','inactive membership denied');
$pdo->exec("UPDATE auth_account_memberships SET status='active' WHERE membership_id='m3'");
$pdo->exec("UPDATE auth_accounts SET status='disabled' WHERE account_id='performer'");
rejects(fn()=> $service->createDraft($base,'s-disabled-account'),'CLINICAL_GRANT_REQUIRED','inactive account denied');
$pdo->exec("UPDATE auth_accounts SET status='active' WHERE account_id='performer'");
rejects(fn()=> $service->createDraft(array_replace($base,['encounter_scope'=>'ENCOUNTER']),'s-bad-enc'),'INVALID_ENCOUNTER_SCOPE','encounter required');
rejects(fn()=> $service->createDraft($base+['encounter_ref_id'=>1],'s-bad-standalone'),'INVALID_ENCOUNTER_SCOPE','standalone excludes encounter');
rejects(fn()=> $service->createDraft(array_replace($base,['performed_by_account_id'=>'other']),'s-no-grant'),'CLINICAL_GRANT_REQUIRED','membership alone denied');
rejects(fn()=> $service->createDraft(array_replace($base,['performed_by_account_id'=>'outsider']),'s-cross-grant'),'CLINICAL_GRANT_REQUIRED','cross doctor performer denied');
rejects(fn()=> $service->createDraft($base+['appointment_id'=>'a2'],'s-cross-appt'),'APPOINTMENT_SCOPE_INVALID','cross doctor appointment denied');
rejects(fn()=> $service->createDraft(array_replace($base,['encounter_scope'=>'ENCOUNTER','encounter_ref_id'=>2]),'s-cross-enc'),'ENCOUNTER_SCOPE_INVALID','cross doctor encounter denied');
$draft=$service->createDraft($base,'s1');$id=(int)$draft['record']['session_id'];
check($draft['record']['status']==='DRAFT' && $draft['record']['lineage_root_id']===$id,'standalone draft');
check($draft['record']['performed_by_role_snapshot']==='Fisioterapeuta','performer role snapshot');
check($service->createDraft($base,'s1')['record']['session_id']===$id,'draft replay');
rejects(fn()=> $service->createDraft($base+['note'=>'different'],'s1'),'IDEMPOTENCY_KEY_REUSED','session key conflict');
$items=[['sequence'=>1,'type_key'=>'physical_therapy','title'=>'Ejercicios','note'=>null],['sequence'=>2,'type_key'=>'manual_therapy','title'=>'Movilización','note'=>'Suave']];
$completion=['expected_version'=>1,'procedure_items'=>$items,'performed_local'=>'2026-09-29 14:30:00','performed_timezone'=>'America/Mexico_City','performed_utc_offset_minutes'=>-360];
rejects(fn()=> $service->complete($id,1,array_replace($completion,['performed_utc_offset_minutes'=>-300]),'bad-offset'),'INVALID_PERFORMED_TIME','timezone offset mismatch');
$done=$service->complete($id,1,$completion,'complete-1');
check($done['record']['status']==='COMPLETED' && count($done['record']['procedure_items'])===2,'complete and items');
check($done['record']['performed_at']==='2026-09-29 20:30:00' && $done['record']['performed_at']!==$done['record']['created_at'],'performed instant');
try{$pdo->exec("UPDATE clinical_treatment_sessions SET title='Not allowed' WHERE session_id=$id");throw new RuntimeException('FAIL completed SQL mutation allowed');}catch(PDOException){check(true,'completed SQL immutable');}
check($service->complete($id,1,$completion,'complete-1')['record']['session_id']===$id,'completion replay');
rejects(fn()=> $service->editDraft($id,2,['title'=>'mutated']),'SESSION_NOT_EDITABLE','completed immutable');
rejects(fn()=> $service->complete($id,1,$completion,'complete-again'),'ROW_VERSION_CONFLICT','completion CAS');
$history=$service->standaloneHistory();check(count($history)===1 && (int)$history[0]['session_id']===$id,'standalone projection');
$correction=$service->correct($id,2,['correction_reason'=>'Dato corregido','outcome'=>'Mejoría'], 'correct-1');$new=(int)$correction['record']['session_id'];
check((int)$service->correct($id,2,['correction_reason'=>'Dato corregido','outcome'=>'Mejoría'],'correct-1')['record']['session_id']===$new,'correction replay');
check($new!==$id && $correction['record']['version_number']==2 && $correction['record']['replaces_session_id']==$id,'correction lineage');
check($service->getSession($id)['outcome']===null && count($service->standaloneHistory())===1 && (int)$service->standaloneHistory()[0]['session_id']===$new,'original preserved and no duplicate');
rejects(fn()=> $service->correct($id,2,['correction_reason'=>'Other'], 'correct-2'),'SESSION_NOT_CORRECTABLE','one successor');
rejects(fn()=> $service->void($id,2,'Old version','void-old'),'SESSION_NOT_VOIDABLE','correction versus void');
$void=$service->void($new,1,'Registro anulado','void-1');
check($service->void($new,1,'Registro anulado','void-1')['record']['status']==='VOIDED','void replay');
check($void['record']['status']==='VOIDED' && $void['record']['void_reason']==='Registro anulado' && $void['record']['procedure_items'][0]['title']==='Ejercicios','void retains clinical data');
check(count($service->standaloneHistory())===0,'void excluded from projection');
$enc=$service->createDraft(array_replace($base,['encounter_scope'=>'ENCOUNTER','encounter_ref_id'=>1]),'enc-1');$encId=(int)$enc['record']['session_id'];
$service->complete($encId,1,$completion,'enc-complete');
check(count($service->standaloneHistory())===0,'encounter session excluded');
$linked=$service->createDraft($base+['treatment_plan_id'=>$planId2,'appointment_id'=>'a1'],'linked-1');
check($linked['record']['treatment_plan_id']===$planId2 && $linked['record']['status']==='DRAFT','plan linked draft and appointment boundary');
$service->complete((int)$linked['record']['session_id'],1,$completion,'linked-complete');
check(count($service->standaloneHistory())===1,'plan linked standalone eligible');
check((int)$pdo->query('SELECT COUNT(*) FROM clinical_treatment_plan_audit_events')->fetchColumn()===7,'plan lifecycle audit');
try{$pdo->exec("DELETE FROM clinical_treatment_plan_audit_events WHERE plan_id=$planId");throw new RuntimeException('FAIL audit delete allowed');}catch(PDOException){check(true,'audit append only');}
try{$pdo->exec("UPDATE clinical_treatment_plan_audit_events SET reason='mutated' WHERE plan_id=$planId");throw new RuntimeException('FAIL audit update allowed');}catch(PDOException){check(true,'audit update forbidden');}
try{$pdo->exec("INSERT INTO clinical_performer_authorizations (doctor_id,account_id,capability,clinical_role_label,granted_by_account_id) VALUES ('d1','provider','TREATMENT_RESPONSIBLE_PROVIDER','Fisioterapeuta','actor')");throw new RuntimeException('FAIL duplicate grant allowed');}catch(PDOException){check(true,'active grant unique');}
check((int)$pdo->query("SELECT COUNT(*) FROM clinical_idempotency_requests WHERE idempotency_key='legacy'")->fetchColumn()===1,'legacy ledger preserved');
check((int)$pdo->query("SELECT COUNT(*) FROM clinical_documents WHERE document_type='procedure'")->fetchColumn()===1,'legacy procedure document preserved');
check((int)$pdo->query('SELECT COUNT(*) FROM clinical_treatment_sessions')->fetchColumn()===4,'no legacy document backfill');
function race(callable $command): array {
    $children=[];
    for($i=0;$i<2;$i++) {
        $pid=pcntl_fork();
        if($pid===-1)throw new RuntimeException('fork failed');
        if($pid===0) {
            try {
                $db=new PDO('mysql:host=localhost;dbname='.getenv('TRT04_QA_DB'),'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
                $writer=new ClinicalTreatments($db,'d1','p1','actor');
                usleep(100000);
                $command($writer,$i);
                exit(0);
            } catch(ClinicalTreatmentException $e) { exit($e->httpStatus===409?10:20); }
            catch(Throwable) { exit(20); }
        }
        $children[]=$pid;
    }
    $codes=[];
    foreach($children as $child){pcntl_waitpid($child,$status);$codes[]=pcntl_wexitstatus($status);}
    sort($codes);
    return $codes;
}
$codes=race(fn(ClinicalTreatments $writer,int $i)=>$writer->transitionPlan($planId2,$i===0?'PAUSED':'COMPLETED',2,null,'race-plan-'.$i));
check($codes===[0,10],'concurrent plan CAS one winner');
$pdo=new PDO('mysql:host=localhost;dbname='.getenv('TRT04_QA_DB'),'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$service=new ClinicalTreatments($pdo,'d1','p1','actor');
check((int)$pdo->query('SELECT COUNT(*) FROM clinical_treatment_plan_audit_events')->fetchColumn()===8,'winning plan audit only');
$doubleDraft=$service->createDraft($base,'double-complete-draft');$doubleId=(int)$doubleDraft['record']['session_id'];
$codes=race(fn(ClinicalTreatments $writer,int $i)=>$writer->complete($doubleId,1,$completion,'double-complete-'.$i));
check($codes===[0,10],'simultaneous completion one winner');
$pdo=new PDO('mysql:host=localhost;dbname='.getenv('TRT04_QA_DB'),'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$service=new ClinicalTreatments($pdo,'d1','p1','actor');
$mixedDraft=$service->createDraft($base,'complete-void-draft');$mixedId=(int)$mixedDraft['record']['session_id'];
$codes=race(fn(ClinicalTreatments $writer,int $i)=>$i===0?$writer->complete($mixedId,1,$completion,'complete-void-complete'):$writer->void($mixedId,1,'Abandonado','complete-void-void'));
check($codes===[0,10],'complete versus void one winner');
$pdo=new PDO('mysql:host=localhost;dbname='.getenv('TRT04_QA_DB'),'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$service=new ClinicalTreatments($pdo,'d1','p1','actor');
$cvDraft=$service->createDraft($base,'correct-void-draft');$cvId=(int)$cvDraft['record']['session_id'];
$service->complete($cvId,1,$completion,'correct-void-complete');
$codes=race(fn(ClinicalTreatments $writer,int $i)=>$i===0?$writer->correct($cvId,2,['correction_reason'=>'Concurrent correction'],'correct-void-correct'):$writer->void($cvId,2,'Anulado','correct-void-void'));
check($codes===[0,10],'correction versus void one winner');
$pdo=new PDO('mysql:host=localhost;dbname='.getenv('TRT04_QA_DB'),'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$service=new ClinicalTreatments($pdo,'d1','p1','actor');
$raceDraft=$service->createDraft($base,'race-draft');$raceId=(int)$raceDraft['record']['session_id'];
$service->complete($raceId,1,$completion,'race-complete');
$pdo->exec("UPDATE clinical_performer_authorizations SET status='REVOKED',revoked_at=UTC_TIMESTAMP() WHERE doctor_id='d1' AND account_id='performer' AND capability='TREATMENT_PERFORMER' AND status='ACTIVE'");
rejects(fn()=> $service->createDraft($base,'after-revoke'),'CLINICAL_GRANT_REQUIRED','revocation blocks new session');
$codes=race(fn(ClinicalTreatments $writer,int $i)=>$writer->correct($raceId,2,['correction_reason'=>'Concurrency '.$i],'race-correct-'.$i));
check($codes===[0,10],'concurrent correction one successor');
$pdo=new PDO('mysql:host=localhost;dbname='.getenv('TRT04_QA_DB'),'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$stmt=$pdo->prepare('SELECT COUNT(*) FROM clinical_treatment_sessions WHERE replaces_session_id=?');$stmt->execute([$raceId]);
check((int)$stmt->fetchColumn()===1,'unique correction lineage');
try{$pdo->exec("DELETE FROM clinical_performer_authorizations WHERE doctor_id='d1' AND account_id='performer'");throw new RuntimeException('FAIL grant delete allowed');}catch(PDOException){check(true,'revoked grant retained');}
echo 'TRT04_SERVICE_QA_PASS='.count($checks)."\n";
