<?php
declare(strict_types=1);
require_once __DIR__.'/../../../api/_lib/clinical_longitudinal_problems.php';
$db=(string)getenv('LON04A_QA_DB');
if (!preg_match('/^lon04a_qa_[0-9a-f]{12}$/',$db)) throw new RuntimeException('DISPOSABLE_DB_REQUIRED');
$pdo=new PDO('mysql:host=localhost;dbname='.$db.';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$repo=new ClinicalLongitudinalProblems($pdo);
function ok(bool $yes,string $name): void {if (!$yes) throw new RuntimeException('FAIL '.$name);echo "PASS $name\n";}
function denied(callable $action,string $code): void {try {$action();}catch (ClinicalLongitudinalException $e) {ok($e->errorCode===$code,$code);return;}throw new RuntimeException('FAIL expected '.$code);}
function rows(PDO $pdo,string $table): int {return (int)$pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();}
function version(array $result): int {return (int)$result['item']['row_version'];}

ok(rows($pdo,'clinical_patient_problems')===0 && rows($pdo,'clinical_record_entries')===1,'migration has no legacy backfill');
ok(rows($pdo,'clinical_patient_problem_audit_events')===0,'no automatic promotion from seeded assessment');
ok($repo->read('d_a','p_a')['knowledge_state']==='UNREVIEWED','empty list is not confirmed none');
denied(fn()=>$repo->read('d_a','p_b'),'NOT_FOUND');
putenv('MXMED_LON04A_WRITE_ENABLED');
denied(fn()=>$repo->mutate('d_a','p_a','u_a','CREATE',[],'off'),'LON04A_WRITE_DISABLED');
putenv('MXMED_LON04A_WRITE_ENABLED=1');

$body=['label'=>'Synthetic chronic problem','provenance'=>'EXPLICIT_LONGITUDINAL_ENTRY'];
$created=$repo->mutate('d_a','p_a','u_a','CREATE',$body,'create-1');$id=(int)$created['item']['problem_id'];
ok(version($created)===1 && $created['item']['status']==='ACTIVE' && rows($pdo,'clinical_patient_problems')===1,'explicit ACTIVE create');
ok(rows($pdo,'clinical_patient_problem_audit_events')===1 && rows($pdo,'clinical_longitudinal_idempotency')===1,'state audit idempotency atomic receipt');
ok((int)$repo->mutate('d_a','p_a','u_a','CREATE',$body,'create-1')['item']['problem_id']===$id && rows($pdo,'clinical_patient_problems')===1,'create exact replay');
$changed=$body;$changed['label']='Changed';
denied(fn()=>$repo->mutate('d_a','p_a','u_a','CREATE',$changed,'create-1'),'IDEMPOTENCY_PAYLOAD_CONFLICT');
denied(fn()=>$repo->read('d_a','p_a',$id+999),'NOT_FOUND');
denied(fn()=>$repo->mutate('d_b','p_a','u_b','CREATE',$body,'foreign'),'NOT_FOUND');
denied(fn()=>$repo->mutate('d_a','p_b','u_a','CREATE',$body,'foreign'),'NOT_FOUND');

$promotion=['label'=>'Synthetic promoted diagnosis','source_encounter_id'=>101,'source_section_id'=>201];
$promoted=$repo->mutate('d_a','p_a','u_a','EXPLICIT_PROMOTION_FROM_ENCOUNTER',$promotion,'promotion-1');$promotedId=(int)$promoted['item']['problem_id'];
ok($promoted['item']['provenance']==='ENCOUNTER_DERIVED_EXPLICIT_PROMOTION' && (int)$promoted['item']['source_section_id']===201,'explicit assessment promotion');
ok($repo->read('d_a','p_a',$promotedId,true)['events'][0]['operation']==='EXPLICIT_PROMOTION_FROM_ENCOUNTER','promotion audit operation');
ok((int)$repo->mutate('d_a','p_a','u_a','EXPLICIT_PROMOTION_FROM_ENCOUNTER',$promotion,'promotion-1')['item']['problem_id']===$promotedId && rows($pdo,'clinical_patient_problems')===2,'promotion replay no duplicate');
$changedPromotion=$promotion;$changedPromotion['label']='Changed promotion';
denied(fn()=>$repo->mutate('d_a','p_a','u_a','EXPLICIT_PROMOTION_FROM_ENCOUNTER',$changedPromotion,'promotion-1'),'IDEMPOTENCY_PAYLOAD_CONFLICT');
foreach ([[102,202],[101,203],[101,204]] as [$encounter,$section]) {
    $invalid=$promotion;$invalid['source_encounter_id']=$encounter;$invalid['source_section_id']=$section;
    denied(fn()=>$repo->mutate('d_a','p_a','u_a','EXPLICIT_PROMOTION_FROM_ENCOUNTER',$invalid,'bad-'.$section),'FOREIGN_SOURCE');
}
ok(rows($pdo,'clinical_patient_problems')===2,'foreign and non-assessment sources rejected');

$otherPdo=new PDO('mysql:host=localhost;dbname='.$db.';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$other=new ClinicalLongitudinalProblems($otherPdo);
$stale=version($other->read('d_a','p_a',$id));
$edit=['label'=>'Corrected synthetic problem','expected_version'=>1,'reason'=>'Corrected identity'];
$edited=$repo->mutate('d_a','p_a','u_a','UPDATE',$edit,'edit-1',$id);
ok(version($edited)===2,'row_version edit CAS');
denied(fn()=>$other->mutate('d_a','p_a','u_b','UPDATE',$edit,'edit-stale',$id),'STALE_VERSION');
$resolved=$repo->mutate('d_a','p_a','u_a','RESOLVE',['expected_version'=>2,'reason'=>'Clinician resolved'],'resolve-1',$id);
ok($resolved['item']['status']==='RESOLVED' && $resolved['item']['resolution_by']==='u_a' && $resolved['item']['resolution_at']!==null,'explicit resolve metadata');
denied(fn()=>$other->mutate('d_a','p_a','u_b','UPDATE',['label'=>'Stale edit','expected_version'=>2,'reason'=>'Stale'],'edit-after-resolve',$id),'STALE_VERSION');
denied(fn()=>$repo->mutate('d_a','p_a','u_a','MARK_INACTIVE',['expected_version'=>3,'reason'=>'Invalid'],'bad-resolved-inactive',$id),'INVALID_TRANSITION');
$reactivated=$repo->mutate('d_a','p_a','u_a','REACTIVATE',['expected_version'=>3,'reason'=>'Recurrence','source_encounter_id'=>101,'source_section_id'=>201],'reactivate-1',$id);
ok((int)$reactivated['item']['problem_id']===$id && $reactivated['item']['status']==='ACTIVE' && version($reactivated)===4,'explicit reactivation retains identity');
ok($reactivated['item']['resolution_at']===null && $repo->read('d_a','p_a',$id,true)['events'][2]['operation']==='RESOLVE','resolution remains in audit');
$inactive=$repo->mutate('d_a','p_a','u_a','MARK_INACTIVE',['expected_version'=>4,'reason'=>'Quiescent'],'inactive-1',$id);
ok($inactive['item']['status']==='INACTIVE','explicit inactive is distinct');
$active=$repo->mutate('d_a','p_a','u_a','ACTIVATE',['expected_version'=>5,'reason'=>'Clinically current again'],'activate-1',$id);
ok($active['item']['status']==='ACTIVE','explicit inactive activation');
$inactiveAgain=$repo->mutate('d_a','p_a','u_a','MARK_INACTIVE',['expected_version'=>6,'reason'=>'Quiescent again'],'inactive-2',$id);
$resolvedInactive=$repo->mutate('d_a','p_a','u_a','RESOLVE',['expected_version'=>7,'reason'=>'Condition concluded'],'resolve-2',$id);
ok($inactiveAgain['item']['status']==='INACTIVE' && $resolvedInactive['item']['status']==='RESOLVED','INACTIVE to RESOLVED allowed');
denied(fn()=>$other->mutate('d_a','p_a','u_b','ACTIVATE',['expected_version'=>7,'reason'=>'Stale activation'],'race-activate',$id),'STALE_VERSION');
$reactivatedAgain=$repo->mutate('d_a','p_a','u_a','REACTIVATE',['expected_version'=>8,'reason'=>'Explicit recurrence'],'reactivate-2',$id);
denied(fn()=>$other->mutate('d_a','p_a','u_b','RESOLVE',['expected_version'=>8,'reason'=>'Stale resolve'],'race-resolve',$id),'STALE_VERSION');
ok($reactivatedAgain['item']['status']==='ACTIVE' && version($reactivatedAgain)===9,'resolve/reactivate race has one winner');
$events=$repo->read('d_a','p_a',$id,true)['events'];
ok(array_column($events,'operation')===['CREATE','UPDATE','RESOLVE','REACTIVATE','MARK_INACTIVE','ACTIVATE','MARK_INACTIVE','RESOLVE','REACTIVATE'],'audit reconstructs lifecycle');
ok((int)$events[3]['source_encounter_id']===101 && $events[3]['actor_user_id']==='u_a','reactivation evidence actor and time');
$similar=$repo->mutate('d_a','p_a','u_a','CREATE',$body,'distinct-episode');
ok((int)$similar['item']['problem_id']!==$id && rows($pdo,'clinical_patient_problems')===3,'no semantic merge');
ok(rows($pdo,'clinical_record_entries')===1,'legacy preserved');

$beforeProblems=rows($pdo,'clinical_patient_problems');$beforeAudit=rows($pdo,'clinical_patient_problem_audit_events');
$pdo->exec("CREATE TRIGGER trg_lon04a_qa_audit_fail BEFORE INSERT ON clinical_patient_problem_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='QA_AUDIT_FAIL'");
try {$repo->mutate('d_a','p_a','u_a','CREATE',$body,'audit-fail');throw new RuntimeException('audit failure not rejected');}
catch (PDOException $e) {ok(str_contains($e->getMessage(),'QA_AUDIT_FAIL'),'audit failure injected');}
$pdo->exec('DROP TRIGGER trg_lon04a_qa_audit_fail');
ok(rows($pdo,'clinical_patient_problems')===$beforeProblems && rows($pdo,'clinical_patient_problem_audit_events')===$beforeAudit,'audit and state atomic rollback');
try {$pdo->exec('DELETE FROM clinical_patient_problem_audit_events LIMIT 1');throw new RuntimeException('audit deleted');}
catch (PDOException $e) {ok(str_contains($e->getMessage(),'IMMUTABLE_LONGITUDINAL_AUDIT'),'audit immutable');}

$windowPath=sys_get_temp_dir().'/lon04a-window-'.bin2hex(random_bytes(8));
putenv('MXMED_CLINICAL_WRITE_WINDOW_CONTROL=FILE');putenv('MXMED_CLINICAL_WRITE_WINDOW_STATE_PATH='.$windowPath);
clinical_m6_write_window_initialize_file($windowPath);clinical_m6_write_window_set_state('BLOCK_WRITES');
foreach ([
    ['CREATE',$body,null],['EXPLICIT_PROMOTION_FROM_ENCOUNTER',$promotion,null],
    ['UPDATE',['label'=>'Blocked','expected_version'=>9,'reason'=>'Blocked'],$id],
    ['RESOLVE',['expected_version'=>9,'reason'=>'Blocked'],$id],
    ['MARK_INACTIVE',['expected_version'=>9,'reason'=>'Blocked'],$id],
    ['REACTIVATE',['expected_version'=>8,'reason'=>'Blocked'],$id]
] as $index=>$case) denied(fn()=>$repo->mutate('d_a','p_a','u_a',$case[0],$case[1],'blocked-'.$index,$case[2]),'M6_WRITE_WINDOW_BLOCKED');
ok(rows($pdo,'clinical_patient_problems')===$beforeProblems && rows($pdo,'clinical_patient_problem_audit_events')===$beforeAudit,'blocked window no partial state or audit');
unlink($windowPath);rmdir($windowPath.'.leases');
echo "LON04A_REPOSITORY_GATE=PASS\n";
