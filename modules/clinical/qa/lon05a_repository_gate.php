<?php
declare(strict_types=1);
require_once __DIR__.'/../../../api/_lib/clinical_longitudinal_medications.php';
$db=(string)getenv('LON05A_QA_DB');
if(!preg_match('/^lon05a_qa_[0-9a-f]{12}$/',$db))throw new RuntimeException('DISPOSABLE_DB_REQUIRED');
$pdo=new PDO('mysql:host=localhost;dbname='.$db.';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$otherPdo=new PDO('mysql:host=localhost;dbname='.$db.';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$repo=new ClinicalLongitudinalMedications($pdo);$other=new ClinicalLongitudinalMedications($otherPdo);
function ok(bool $yes,string $name): void {if(!$yes)throw new RuntimeException('FAIL '.$name);echo "PASS $name\n";}
function denied(callable $action,string $code): void {try{$action();}catch(ClinicalLongitudinalException $e){ok($e->errorCode===$code,$code);return;}throw new RuntimeException('FAIL expected '.$code);}
function rows(PDO $pdo,string $table): int {return (int)$pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();}
function medId(array $result): int{return (int)$result['item']['medication_id'];}
function v(array $result): int{return (int)$result['item']['row_version'];}
function reason(int $version,string $why): array{return ['expected_version'=>$version,'reason'=>$why];}

ok(rows($pdo,'clinical_patient_medications')===0 && rows($pdo,'clinical_record_entries')===1 && rows($pdo,'clinical_documents')===3,'additive migration preserves legacy and prescriptions');
ok($repo->read('d_a','p_a')['knowledge_state']==='UNREVIEWED','empty list is not confirmed none');
denied(fn()=>$repo->read('d_a','p_b'),'NOT_FOUND');
putenv('MXMED_LON05A_WRITE_ENABLED');
denied(fn()=>$repo->mutate('d_a','p_a','u_a','CREATE',['medication_name'=>'X'],'off'),'LON05A_WRITE_DISABLED');
putenv('MXMED_LON05A_WRITE_ENABLED=1');

$reported=['medication_name'=>'Synthetic patient report','dose'=>'5','dose_unit'=>'mg','frequency'=>'daily'];
$r=$repo->mutate('d_a','p_a','u_a','PATIENT_REPORTED_CREATE',$reported,'reported-1');$reportedId=medId($r);
ok($r['item']['state']==='REPORTED_BY_PATIENT' && $r['item']['provenance']==='PATIENT_REPORTED' && v($r)===1,'patient report remains unconfirmed');
ok((int)$r['list_version']===2 && rows($pdo,'clinical_patient_medication_audit_events')===1,'episode and list versions advance');
$replay=$repo->mutate('d_a','p_a','u_a','PATIENT_REPORTED_CREATE',$reported,'reported-1');
ok(medId($replay)===$reportedId && rows($pdo,'clinical_patient_medications')===1 && rows($pdo,'clinical_patient_medication_audit_events')===1,'create exact replay no duplicate');
denied(fn()=>$repo->mutate('d_a','p_a','u_a','PATIENT_REPORTED_CREATE',['medication_name'=>'Changed'],'reported-1'),'IDEMPOTENCY_PAYLOAD_CONFLICT');

$rx=['medication_name'=>'Synthetic prescription item','source_document_id'=>301,'initial_state'=>'PRESCRIBED_NOT_CONFIRMED_ACTIVE'];
$p=$repo->mutate('d_a','p_a','u_a','PRESCRIPTION_DERIVED_CREATE',$rx,'rx-1');$rxId=medId($p);
ok($p['item']['state']==='PRESCRIBED_NOT_CONFIRMED_ACTIVE' && $p['item']['provenance']==='PRESCRIPTION_DERIVED_EXPLICIT_ENTRY' && (int)$p['item']['source_document_id']===301,'prescription evidence does not imply active use');
ok(rows($pdo,'clinical_patient_medications')===2 && rows($pdo,'clinical_documents')===3,'no prescription auto-create');
foreach([302,303] as $document) {$bad=$rx;$bad['source_document_id']=$document;denied(fn()=>$repo->mutate('d_a','p_a','u_a','PRESCRIPTION_DERIVED_CREATE',$bad,'bad-source-'.$document),'FOREIGN_SOURCE');}
denied(fn()=>$repo->mutate('d_a','p_a','u_a','CREATE',['medication_name'=>'Foreign encounter','source_encounter_id'=>102],'bad-encounter'),'FOREIGN_SOURCE');
denied(fn()=>$repo->mutate('d_b','p_a','u_b','CREATE',['medication_name'=>'Foreign'],'foreign-patient'),'NOT_FOUND');
denied(fn()=>$repo->read('d_a','p_b'),'NOT_FOUND');
denied(fn()=>$repo->read('d_a','p_a',999999),'NOT_FOUND');

$confirmed=$repo->mutate('d_a','p_a','u_a','CONFIRM_ACTIVE',reason(1,'Clinician confirmed current use'),'confirm-r',$reportedId);
ok($confirmed['item']['state']==='ACTIVE_CONFIRMED' && $confirmed['item']['provenance']==='PATIENT_REPORTED','patient report confirmed with provenance retained');
$confirmAudit=rows($pdo,'clinical_patient_medication_audit_events');
ok(v($repo->mutate('d_a','p_a','u_a','CONFIRM_ACTIVE',reason(1,'Clinician confirmed current use'),'confirm-r',$reportedId))===2 && rows($pdo,'clinical_patient_medication_audit_events')===$confirmAudit,'transition exact replay has no duplicate audit');
denied(fn()=>$repo->mutate('d_a','p_a','u_a','CONFIRM_ACTIVE',reason(1,'Changed reason'),'confirm-r',$reportedId),'IDEMPOTENCY_PAYLOAD_CONFLICT');
$confirmedRx=$repo->mutate('d_a','p_a','u_a','CONFIRM_ACTIVE',reason(1,'Clinician confirmed prescribed use'),'confirm-rx',$rxId);
ok($confirmedRx['item']['state']==='ACTIVE_CONFIRMED','prescription confirmation is explicit');
$stale=reason(2,'Stale discontinuation');
$edited=$repo->mutate('d_a','p_a','u_a','UPDATE_REGIMEN',[...$reported,'dose'=>'10','expected_version'=>2,'reason'=>'Dose reviewed'],'dose-change',$reportedId);
ok(v($edited)===3 && $edited['item']['dose']==='10','dose edit CAS');
denied(fn()=>$other->mutate('d_a','p_a','u_b','DISCONTINUE',$stale,'stale-dose-stop',$reportedId),'STALE_VERSION');
$stopped=$repo->mutate('d_a','p_a','u_a','DISCONTINUE',reason(3,'Clinician stopped'),'stop-r',$reportedId);
ok($stopped['item']['state']==='DISCONTINUED' && $stopped['item']['ended_by']==='u_a','explicit discontinuation');
$completed=$repo->mutate('d_a','p_a','u_a','COMPLETE',reason(2,'Course completed'),'complete-rx',$rxId);
ok($completed['item']['state']==='COMPLETED' && $completed['item']['ended_at']!==null,'explicit course completion');
denied(fn()=>$repo->mutate('d_a','p_a','u_a','CONFIRM_ACTIVE',reason(4,'Forbidden restart'),'terminal-1',$reportedId),'TERMINAL_EPISODE');
denied(fn()=>$repo->mutate('d_a','p_a','u_a','CONFIRM_ACTIVE',reason(3,'Forbidden restart'),'terminal-2',$rxId),'TERMINAL_EPISODE');
try{$pdo->exec('UPDATE clinical_patient_medications SET state=\'ACTIVE_CONFIRMED\',ended_at=NULL,ended_by=NULL WHERE medication_id='.$reportedId);throw new RuntimeException('terminal episode changed');}
catch(PDOException $e){ok(str_contains($e->getMessage(),'TERMINAL_MEDICATION_EPISODE_IMMUTABLE'),'database rejects terminal episode reactivation');}
$new=$repo->mutate('d_a','p_a','u_a','CREATE',['medication_name'=>'Synthetic patient report'],'new-episode');
ok(medId($new)!==$reportedId && rows($pdo,'clinical_patient_medications')===3,'restart is new explicit episode, no semantic merge');
$events=$repo->read('d_a','p_a',$reportedId,'medications',true)['events'];
ok(array_column($events,'operation')===['PATIENT_REPORTED_CREATE','CONFIRM_ACTIVE','UPDATE_REGIMEN','DISCONTINUE'],'reported lifecycle audit reconstructable');
ok(array_column($repo->read('d_a','p_a',$rxId,'medications',true)['events'],'operation')===['PRESCRIPTION_DERIVED_CREATE','CONFIRM_ACTIVE','COMPLETE'],'prescribed lifecycle audit reconstructable');

$freshReport=$repo->mutate('d_a','p_a','u_a','PATIENT_REPORTED_CREATE',['medication_name'=>'Synthetic new report'],'new-report');$freshReportId=medId($freshReport);
$freshRx=$repo->mutate('d_a','p_a','u_a','PRESCRIPTION_DERIVED_CREATE',[...$rx,'medication_name'=>'Synthetic new unconfirmed'],'new-rx');$freshRxId=medId($freshRx);
$baseVersion=(int)$repo->read('d_a','p_a')['list_version'];
$reconcileBody=['expected_list_version'=>$baseVersion,'note'=>'Synthetic list review','decisions'=>[
    ['action'=>'CONFIRM_ACTIVE','medication_id'=>$freshReportId,'expected_version'=>1,'reason'=>'Reviewed current use'],
    ['action'=>'KEEP_UNCONFIRMED','medication_id'=>$freshRxId,'expected_version'=>1,'reason'=>'Awaiting verification'],
    ['action'=>'ADD','medication_name'=>'Synthetic list addition','reason'=>'Reported during review']
]];
$mixed=$repo->reconcile('d_a','p_a','u_a',$reconcileBody,'reconcile-1');
ok($mixed['list_version']===$baseVersion+1 && count($mixed['decisions'])===3,'atomic reconciliation and aggregate version');
ok($repo->read('d_a','p_a',$freshReportId)['item']['state']==='ACTIVE_CONFIRMED' && $repo->read('d_a','p_a',$freshRxId)['item']['state']==='PRESCRIBED_NOT_CONFIRMED_ACTIVE','reconciliation decisions preserve distinct states');
$reconciliation=$repo->read('d_a','p_a',(int)$mixed['reconciliation_id'],'medication-reconciliations');
ok(count($reconciliation['items'])===3 && (int)$reconciliation['reconciliation']['starting_list_version']===$baseVersion,'reconciliation audit records decisions and versions');
ok($repo->reconcile('d_a','p_a','u_a',$reconcileBody,'reconcile-1')['reconciliation_id']===$mixed['reconciliation_id'] && rows($pdo,'clinical_medication_reconciliations')===1,'reconciliation exact replay');
$changedReconcile=$reconcileBody;$changedReconcile['note']='Changed';
denied(fn()=>$repo->reconcile('d_a','p_a','u_a',$changedReconcile,'reconcile-1'),'IDEMPOTENCY_PAYLOAD_CONFLICT');
denied(fn()=>$other->reconcile('d_a','p_a','u_b',$reconcileBody,'stale-reconcile'),'STALE_LIST_VERSION');

$beforeRows=rows($pdo,'clinical_patient_medications');$beforeAudit=rows($pdo,'clinical_patient_medication_audit_events');$beforeRecon=rows($pdo,'clinical_medication_reconciliations');
$broken=['expected_list_version'=>$mixed['list_version'],'decisions'=>[
    ['action'=>'ADD','medication_name'=>'Would roll back'],
    ['action'=>'CONFIRM_ACTIVE','medication_id'=>$reportedId,'expected_version'=>4,'reason'=>'Terminal cannot confirm']
]];
denied(fn()=>$repo->reconcile('d_a','p_a','u_a',$broken,'atomic-failure'),'TERMINAL_EPISODE');
ok(rows($pdo,'clinical_patient_medications')===$beforeRows && rows($pdo,'clinical_patient_medication_audit_events')===$beforeAudit && rows($pdo,'clinical_medication_reconciliations')===$beforeRecon,'failed reconciliation leaves no partial list or audit');

$direct=$repo->mutate('d_a','p_a','u_a','UPDATE_REGIMEN',['medication_name'=>'Synthetic new report','dose'=>'2','expected_version'=>2,'reason'=>'Direct edit after list review'],'direct-after-reconcile',$freshReportId);
ok($direct['list_version']===$mixed['list_version']+1,'direct edit participates in aggregate version');
denied(fn()=>$other->reconcile('d_a','p_a','u_b',['expected_list_version'=>$mixed['list_version'],'decisions'=>[['action'=>'UNRESOLVED','medication_id'=>$freshRxId,'expected_version'=>1]]],'race-reconcile'),'STALE_LIST_VERSION');
denied(fn()=>$other->mutate('d_a','p_a','u_b','DISCONTINUE',reason(2,'Stale after direct edit'),'stale-after-direct',$freshReportId),'STALE_VERSION');

$beforeRows=rows($pdo,'clinical_patient_medications');$beforeAudit=rows($pdo,'clinical_patient_medication_audit_events');
$pdo->exec("CREATE TRIGGER trg_lon05a_qa_audit_fail BEFORE INSERT ON clinical_patient_medication_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='QA_AUDIT_FAIL'");
try{$repo->mutate('d_a','p_a','u_a','CREATE',['medication_name'=>'Rollback check'],'audit-failure');throw new RuntimeException('audit failure not rejected');}
catch(PDOException $e){ok(str_contains($e->getMessage(),'QA_AUDIT_FAIL'),'audit failure injected');}
$pdo->exec('DROP TRIGGER trg_lon05a_qa_audit_fail');
ok(rows($pdo,'clinical_patient_medications')===$beforeRows && rows($pdo,'clinical_patient_medication_audit_events')===$beforeAudit,'state and audit roll back together');
try{$pdo->exec('DELETE FROM clinical_patient_medication_audit_events LIMIT 1');throw new RuntimeException('audit deleted');}
catch(PDOException $e){ok(str_contains($e->getMessage(),'IMMUTABLE_LONGITUDINAL_AUDIT'),'medication audit immutable');}

$windowPath=sys_get_temp_dir().'/lon05a-window-'.bin2hex(random_bytes(8));
putenv('MXMED_CLINICAL_WRITE_WINDOW_CONTROL=FILE');putenv('MXMED_CLINICAL_WRITE_WINDOW_STATE_PATH='.$windowPath);
clinical_m6_write_window_initialize_file($windowPath);clinical_m6_write_window_set_state('BLOCK_WRITES');
foreach([
    ['CREATE',['medication_name'=>'Blocked'],null],
    ['PATIENT_REPORTED_CREATE',['medication_name'=>'Blocked'],null],
    ['PRESCRIPTION_DERIVED_CREATE',$rx,null],
    ['UPDATE_REGIMEN',['medication_name'=>'Blocked','expected_version'=>3,'reason'=>'Blocked'],$freshReportId],
    ['CONFIRM_ACTIVE',reason(1,'Blocked'),$freshRxId],
    ['DISCONTINUE',reason(3,'Blocked'),$freshReportId],
    ['COMPLETE',reason(3,'Blocked'),$freshReportId]
] as $i=>$case)denied(fn()=>$repo->mutate('d_a','p_a','u_a',$case[0],$case[1],'blocked-'.$i,$case[2]),'M6_WRITE_WINDOW_BLOCKED');
denied(fn()=>$repo->reconcile('d_a','p_a','u_a',['expected_list_version'=>$direct['list_version'],'decisions'=>[['action'=>'UNRESOLVED','medication_id'=>$freshRxId,'expected_version'=>1]]],'blocked-reconcile'),'M6_WRITE_WINDOW_BLOCKED');
ok(rows($pdo,'clinical_patient_medications')===$beforeRows && rows($pdo,'clinical_patient_medication_audit_events')===$beforeAudit,'blocked window creates no partial state/audit');
unlink($windowPath);rmdir($windowPath.'.leases');
ok(rows($pdo,'clinical_record_entries')===1 && rows($pdo,'clinical_documents')===3,'legacy and prescriptions remain untouched');
echo "LON05A_REPOSITORY_GATE=PASS\n";
