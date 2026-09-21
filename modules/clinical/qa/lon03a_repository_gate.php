<?php
declare(strict_types=1);
require_once __DIR__.'/../../../api/_lib/clinical_longitudinal_antecedents.php';

$db=(string)getenv('LON03A_QA_DB');
if (!preg_match('/^lon03a_qa_[0-9a-f]{12}$/',$db)) throw new RuntimeException('DISPOSABLE_DB_REQUIRED');
$pdo=new PDO('mysql:host=localhost;dbname='.$db.';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$repo=new ClinicalLongitudinalAntecedents($pdo);
function check(bool $ok,string $name): void {if (!$ok) throw new RuntimeException('FAIL '.$name);echo "PASS $name\n";}
function denied(callable $f,string $code): void {try {$f();}catch (ClinicalLongitudinalException $e) {check($e->errorCode===$code,$code);return;}throw new RuntimeException('FAIL expected '.$code);}
function countRows(PDO $pdo,string $table): int {return (int)$pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();}

$required=['clinical_patient_antecedent_facts','clinical_patient_antecedent_reviews','clinical_patient_allergies','clinical_patient_allergy_reviews','clinical_longitudinal_audit_events','clinical_longitudinal_idempotency'];
foreach ($required as $table) check((int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$table'")->fetchColumn()===1,'schema '.$table);
check((int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME IN ('clinical_patient_antecedent_facts','clinical_patient_antecedent_reviews','clinical_patient_allergies','clinical_patient_allergy_reviews') AND CONSTRAINT_TYPE='CHECK'")->fetchColumn()>=10,'clinical CHECK constraints');
check((int)$pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND INDEX_NAME IN ('idx_antecedent_scope','idx_allergy_scope','uq_antecedent_review_scope','uq_allergy_review_scope','uq_longitudinal_entity_version','uq_longitudinal_command')")->fetchColumn()>=16,'scope and uniqueness indexes');
check((int)$pdo->query("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME IN ('clinical_patient_antecedent_facts','clinical_patient_antecedent_reviews','clinical_patient_allergies','clinical_patient_allergy_reviews','clinical_longitudinal_audit_events','clinical_longitudinal_idempotency') AND DELETE_RULE='RESTRICT'")->fetchColumn()===6,'restrict foreign keys');
check((int)$pdo->query("SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND EVENT_OBJECT_TABLE='clinical_longitudinal_audit_events'")->fetchColumn()===2,'immutable audit triggers');
check(countRows($pdo,'clinical_record_entries')===1,'legacy preserved after migration');

$a=$repo->read('antecedents','d_a','p_a');
check($a['knowledge_state_by_category']['FAMILY']==='UNKNOWN','no facts is UNKNOWN');
putenv('MXMED_LON03A_WRITE_ENABLED');
denied(fn()=>$repo->mutate('antecedents','d_a','p_a','u_a','CREATE',[],'disabled'),'LON03A_WRITE_DISABLED');
putenv('MXMED_LON03A_WRITE_ENABLED=1');
$all=$repo->read('allergies','d_a','p_a');
check($all['knowledge_state']==='UNKNOWN','no allergies is UNKNOWN');
denied(fn()=>$repo->read('allergies','d_b','p_a'),'NOT_FOUND');
denied(fn()=>$repo->mutate('antecedents','d_b','p_a','u_b','CREATE',[],'foreign'),'NOT_FOUND');

$none=$repo->mutate('antecedent-reviews','d_a','p_a','u_a','CREATE',['category'=>'FAMILY','review_state'=>'CONFIRMED_NONE'],'review-none');
check($repo->read('antecedents','d_a','p_a')['knowledge_state_by_category']['FAMILY']==='CONFIRMED_NONE','explicit review confirms none');
$allNone=$repo->mutate('allergy-reviews','d_a','p_a','u_a','CREATE',['review_state'=>'CONFIRMED_NONE'],'allergy-none');
check($repo->read('allergies','d_a','p_a')['knowledge_state']==='CONFIRMED_NONE','explicit allergy review confirms none');

$factBody=['category'=>'FAMILY','content'=>'Family history reported','state'=>'CURRENT','provenance'=>'PATIENT_REPORTED'];
$created=$repo->mutate('antecedents','d_a','p_a','u_a','CREATE',$factBody,'fact-1');
$factId=(int)$created['item']['fact_id'];
$otherPdo=new PDO('mysql:host=localhost;dbname='.$db.';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$otherRepo=new ClinicalLongitudinalAntecedents($otherPdo);
$otherLoadedVersion=(int)$otherRepo->read('antecedents','d_a','p_a',$factId)['item']['row_version'];
check((int)$created['item']['row_version']===1 && countRows($pdo,'clinical_patient_antecedent_facts')===1,'antecedent create version');
check($repo->read('antecedents','d_a','p_a')['knowledge_state_by_category']['FAMILY']==='NEEDS_REVIEW','fact invalidates prior review');
$replayed=$repo->mutate('antecedents','d_a','p_a','u_a','CREATE',$factBody,'fact-1');
check((int)$replayed['item']['fact_id']===$factId && countRows($pdo,'clinical_patient_antecedent_facts')===1,'create replay no duplicate');
$reordered=array_reverse($factBody,true);
check((int)$repo->mutate('antecedents','d_a','p_a','u_a','CREATE',$reordered,'fact-1')['item']['fact_id']===$factId,'JSON key order replay');
denied(fn()=>$repo->mutate('antecedents','d_a','p_a','u_a','CREATE',$factBody+['x'=>'changed'],'fact-1'),'UNKNOWN_FIELD');
$changed=$factBody;$changed['content']='Changed payload';
denied(fn()=>$repo->mutate('antecedents','d_a','p_a','u_a','CREATE',$changed,'fact-1'),'IDEMPOTENCY_PAYLOAD_CONFLICT');
$update=$changed+['expected_version'=>1,'reason'=>'Clinical correction'];
$updated=$repo->mutate('antecedents','d_a','p_a','u_a','UPDATE',$update,'fact-2',$factId);
check((int)$updated['item']['row_version']===2,'antecedent CAS update');
check($otherLoadedVersion===1,'second actor loaded same initial version');
denied(fn()=>$otherRepo->mutate('antecedents','d_a','p_a','u_b','UPDATE',$update,'fact-3',$factId),'STALE_VERSION');
check((int)$repo->mutate('antecedents','d_a','p_a','u_a','UPDATE',$update,'fact-2',$factId)['item']['row_version']===2,'update replay');
check(count($repo->read('antecedents','d_a','p_a',$factId,true)['events'])===2,'antecedent audit reconstructable');
$familyReview=$repo->read('antecedent-reviews','d_a','p_a',(int)$none['item']['review_id'])['item'];
$repo->mutate('antecedent-reviews','d_a','p_a','u_a','UPDATE',[
    'category'=>'FAMILY','review_state'=>'REVIEWED_WITH_FACTS','expected_version'=>(int)$familyReview['row_version'],'reason'=>'Reviewed family facts'
],'review-facts',(int)$none['item']['review_id']);
check($repo->read('antecedents','d_a','p_a')['knowledge_state_by_category']['FAMILY']==='REVIEWED_WITH_FACTS','explicit facts review');

$allergyBody=['substance'=>'Synthetic allergen','reaction'=>'Synthetic reaction','state'=>'CURRENT','validation_state'=>'REPORTED','provenance'=>'PATIENT_REPORTED'];
$allergy=$repo->mutate('allergies','d_a','p_a','u_a','CREATE',$allergyBody,'allergy-1');
$allergyId=(int)$allergy['item']['allergy_id'];
check($repo->read('allergies','d_a','p_a')['knowledge_state']==='NEEDS_REVIEW','allergy invalidates prior no-allergy review');
$allergyChanged=$allergyBody;$allergyChanged['reaction']='Corrected synthetic reaction';
$allergyUpdate=$allergyChanged+['expected_version'=>1,'reason'=>'Clinical correction'];
$repo->mutate('allergies','d_a','p_a','u_a','UPDATE',$allergyUpdate,'allergy-2',$allergyId);
denied(fn()=>$otherRepo->mutate('allergies','d_a','p_a','u_b','UPDATE',$allergyUpdate,'allergy-3',$allergyId),'STALE_VERSION');
check(count($repo->read('allergies','d_a','p_a',$allergyId,true)['events'])===2,'allergy audit reconstructable');
$allergyReview=$repo->read('allergy-reviews','d_a','p_a',(int)$allNone['item']['review_id'])['item'];
$repo->mutate('allergy-reviews','d_a','p_a','u_a','UPDATE',[
    'review_state'=>'REVIEWED_WITH_ALLERGIES','expected_version'=>(int)$allergyReview['row_version'],'reason'=>'Reviewed allergy'
],'review-allergies',(int)$allNone['item']['review_id']);
check($repo->read('allergies','d_a','p_a')['knowledge_state']==='REVIEWED_WITH_ALLERGIES','explicit allergy review');

$foreign=$factBody+['source_type'=>'ENCOUNTER','source_id'=>'102'];$foreign['provenance']='ENCOUNTER_DERIVED_EXPLICIT_PROMOTION';
denied(fn()=>$repo->mutate('antecedents','d_a','p_a','u_a','CREATE',$foreign,'foreign-source'),'FOREIGN_SOURCE');
$foreignDocument=$foreign;$foreignDocument['source_type']='DOCUMENT';$foreignDocument['source_id']='302';
denied(fn()=>$repo->mutate('antecedents','d_a','p_a','u_a','CREATE',$foreignDocument,'foreign-document'),'FOREIGN_SOURCE');
$foreignSection=$foreign;$foreignSection['source_type']='SECTION';$foreignSection['source_id']='202';
denied(fn()=>$repo->mutate('antecedents','d_a','p_a','u_a','CREATE',$foreignSection,'foreign-section'),'FOREIGN_SOURCE');
$unsupported=$factBody;$unsupported['provenance']='LEGACY_IMPORTED_CONFIRMED';$unsupported['source_type']='LEGACY_ENTRY';$unsupported['source_id']='401';
denied(fn()=>$repo->mutate('antecedents','d_a','p_a','u_a','CREATE',$unsupported,'legacy-unattributed'),'SOURCE_AUTHORITY_UNAVAILABLE');
$own=$foreign;$own['source_id']='101';
$repo->mutate('antecedents','d_a','p_a','u_a','CREATE',$own,'own-source');
check(countRows($pdo,'clinical_record_entries')===1,'legacy unchanged after commands');

// Force audit insertion failure: current state must roll back with it.
$pdo->exec("CREATE TRIGGER trg_lon03a_qa_audit_fail BEFORE INSERT ON clinical_longitudinal_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='QA_AUDIT_FAIL'");
$before=countRows($pdo,'clinical_patient_antecedent_facts');
try {$repo->mutate('antecedents','d_a','p_a','u_a','CREATE',$factBody,'atomic-fail');throw new RuntimeException('atomicity not rejected');}
catch (PDOException $e) {check(str_contains($e->getMessage(),'QA_AUDIT_FAIL'),'audit insertion failure injected');}
$pdo->exec('DROP TRIGGER trg_lon03a_qa_audit_fail');
check(countRows($pdo,'clinical_patient_antecedent_facts')===$before,'state/audit atomic rollback');
try {$pdo->exec("DELETE FROM clinical_longitudinal_audit_events WHERE entity_type='ANTECEDENT_FACT' LIMIT 1");throw new RuntimeException('audit delete accepted');}
catch (PDOException $e) {check(str_contains($e->getMessage(),'IMMUTABLE_LONGITUDINAL_AUDIT'),'audit DELETE blocked');}

$windowPath=sys_get_temp_dir().'/lon03a-window-'.bin2hex(random_bytes(8));
putenv('MXMED_CLINICAL_WRITE_WINDOW_CONTROL=FILE');putenv('MXMED_CLINICAL_WRITE_WINDOW_STATE_PATH='.$windowPath);
clinical_m6_write_window_initialize_file($windowPath);clinical_m6_write_window_set_state('BLOCK_WRITES');
denied(fn()=>$repo->mutate('allergies','d_a','p_a','u_a','CREATE',$allergyBody,'blocked'),'M6_WRITE_WINDOW_BLOCKED');
check(countRows($pdo,'clinical_patient_allergies')===1,'write window no partial write');
unlink($windowPath);rmdir($windowPath.'.leases');
check(countRows($pdo,'clinical_patient_antecedent_facts')===2 && countRows($pdo,'clinical_patient_allergies')===1,'no legacy backfill or duplicate records');
echo "LON03A_DISPOSABLE_GATE=PASS\n";
