<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/../modules/identity/http/IdentityHttpComposition.php';
Identity\Http\IdentityHttpComposition::registerAutoloader();
Identity\Operators\OperatorQaConfiguration::assertEnabled();
// Schema installation is CLI-only with the local migration administrator, never
// the HTTP runtime user. No global MySQL option or SUPER grant is changed.
$p=new PDO('mysql:host=127.0.0.1;port=3306;dbname='.Identity\Operators\OperatorQaConfiguration::database().';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
if((int)$p->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='auth_accounts'")->fetchColumn()>0
    &&(int)$p->query('SELECT COUNT(*) FROM auth_accounts')->fetchColumn()>0){echo "OP02_FIXTURE=EXISTS\n";exit;}
$root=dirname(__DIR__);
foreach(['2026_07_19_01_create_auth_accounts.sql','2026_07_19_02_create_auth_account_consents.sql','2026_07_20_04_create_auth_account_credentials.sql','2026_07_20_05_create_auth_account_one_time_tokens.sql','2026_07_20_06_create_auth_rate_limit_buckets.sql','2026_09_08_07_create_internal_operator_grants.sql','2026_09_10_01_internal_governance.sql'] as $f)$p->exec(file_get_contents($root.'/modules/identity/db/migrations/'.$f));
$p->exec(file_get_contents($root.'/modules/profiles/db/profiles_doctors_schema.sql'));
$p->exec('CREATE TABLE IF NOT EXISTS medical_groups(group_id VARCHAR(64) PRIMARY KEY) ENGINE=InnoDB');
foreach(['2026_07_19_03_create_auth_account_memberships.sql','2026_10_14_01_create_profile_ownership_governance.sql','2026_10_14_02_profile_ownership_r3_endorsements.sql','2026_10_15_01_identity_governance_r3_runtime.sql','2026_10_15_02_profile_operator_foundation.sql','2026_10_15_03_operator_qa_outbox.sql'] as $f)$p->exec(file_get_contents($root.'/modules/identity/db/migrations/'.$f));
foreach(['modules/profiles/db/2026_06_19_create_subscription_plan_lifecycle.sql','modules/agenda/db/consultorios_schema.sql','modules/agenda/db/availability_catalog_schema.sql','modules/agenda/db/agenda_settings_schema.sql','modules/agenda/db/ready_schema.sql','modules/agenda/db/operators_phase1.sql','modules/patients/db/ready_schema.sql'] as $f)$p->exec(file_get_contents($root.'/'.$f));
// Schema-only part; this historical SQL file also has doctor 1 example seeds, excluded.
$overrides=explode('-- Datos de ejemplo',file_get_contents($root.'/modules/agenda/db/availability_overrides_min.sql'))[0];$p->exec($overrides);
$p->exec('CREATE TABLE IF NOT EXISTS platform_audit_stream_heads(stream_key VARCHAR(191) PRIMARY KEY,last_sequence_number BIGINT NOT NULL,last_event_hash CHAR(64) NOT NULL,hash_version VARCHAR(32),updated_at DATETIME(6)) ENGINE=InnoDB');
$p->exec('CREATE TABLE IF NOT EXISTS platform_audit_events(stream_key VARCHAR(191),sequence_number BIGINT,event_id CHAR(64),schema_version VARCHAR(32),occurred_at_utc DATETIME(6),action VARCHAR(128),risk_level VARCHAR(2),outcome VARCHAR(16),reason_code VARCHAR(128),real_actor_reference VARCHAR(191),effective_actor_reference VARCHAR(191),affected_subject_reference VARCHAR(191),correlation_id VARCHAR(191),request_id VARCHAR(191),case_reference VARCHAR(191),resource_type VARCHAR(128),resource_reference VARCHAR(191),metadata_json JSON,previous_hash CHAR(64),event_hash CHAR(64),created_at_utc DATETIME(6),PRIMARY KEY(stream_key,sequence_number),UNIQUE(event_id),UNIQUE(event_hash)) ENGINE=InnoDB');
if(Identity\Operators\OperatorQaConfiguration::implementationRehearsal()){
 $p->exec(file_get_contents($root.'/modules/identity/db/migrations/2026_10_15_04_operator_presentations.sql'));
 $p->exec(file_get_contents($root.'/modules/agenda/db/migrations/2026_10_15_12_appointment_creator_snapshots.sql'));
}
$p=new PDO('mysql:host=127.0.0.1;port=3306;dbname='.getenv('MXMED_DB_NAME').';charset=utf8mb4',getenv('MXMED_DB_USER'),getenv('MXMED_DB_PASS'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
Identity\Operators\OperatorQaConfiguration::assertEnabled($p);
if((int)$p->query('SELECT COUNT(*) FROM auth_accounts')->fetchColumn()>0){echo "OP02_FIXTURE=EXISTS\n";exit;}
$credentials=json_decode(file_get_contents(getenv('MXMED_OP02_CREDENTIALS_JSON')),true,32,JSON_THROW_ON_ERROR);
$p->beginTransaction();
try{
 foreach($credentials as $role=>$entry){$id='op02-'.$role;$email=$entry['email'];if(!str_ends_with($email,'@example.invalid'))throw new RuntimeException('synthetic_email_required');
  $p->prepare("INSERT INTO auth_accounts(account_id,email_address,email_normalized,status,email_verified_at) VALUES(?,?,?,'active',UTC_TIMESTAMP())")->execute([$id,$email,$email]);
  $p->prepare('INSERT INTO auth_account_credentials(account_id,password_hash,password_changed_at) VALUES(?,?,UTC_TIMESTAMP())')->execute([$id,Identity\Contracts\PasswordHash::hash($entry['password'])]);
  foreach(['terms','privacy_notice'] as $doc)$p->prepare('INSERT INTO auth_account_consents(consent_id,account_id,document_type,document_version,accepted_at) VALUES(?,?,?,?,UTC_TIMESTAMP())')->execute([$id.'-'.$doc,$id,$doc,'op02-synthetic-v1']);
 }
 foreach(['op02-doctor'=>'Dra. Aurora Entorno Aislado','op02-other'=>'Dr. Otro Perfil Sintético'] as $doctor=>$name){
  $p->prepare('INSERT INTO profiles_doctors(doctor_id,display_name) VALUES(?,?)')->execute([$doctor,$name]);
  $acct=$doctor==='op02-doctor'?'op02-owner':'op02-other-owner';
  $p->prepare("INSERT INTO auth_account_memberships(membership_id,account_id,profile_doctor_id,role_code,scope_code,status,assignment_source) VALUES(?,?,?,'owner','profile','active','op02_synthetic_fixture')")->execute([$acct.'-membership',$acct,$doctor]);
  $p->prepare("INSERT INTO profile_subscriptions(subscription_id,entity_type,entity_id,doctor_id,plan_code,contracted_plan_code,effective_plan_code,plan_label,status,starts_at,expires_at) VALUES(UUID(),'doctor',?,?,'optimum','optimum','optimum','Óptimo sintético','active',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 365 DAY))")->execute([$doctor,$doctor]);
  $p->prepare("INSERT INTO consultorios(doctor_id,consultorio_id,titulo) VALUES(?,'1','Consultorio de práctica sintética')")->execute([$doctor]);
  foreach(range(1,7) as $day)$p->prepare("INSERT INTO consultorio_schedule(doctor_id,consultorio_id,weekday,start_time,end_time,is_active) VALUES(?,'1',?,'08:00:00','20:00:00',1)")->execute([$doctor,$day]);
  $p->prepare("INSERT INTO agenda_settings(doctor_id,consultorio_id,appointment_duration_min,gap_between_appointments_min) VALUES(?,'1',30,0)")->execute([$doctor]);
 }
 foreach(['p_op02patient0001','p_op02patient0002'] as $n=>$patient){
  $doctor=$n===0?'op02-doctor':'op02-other';
  $p->prepare('INSERT INTO patients_patients(patient_id,display_name,notes_admin) VALUES(?,?,?)')->execute([$patient,'Persona sintética '.($n+1),'OP02_PRIVATE_CANARY_NOT_FOR_OPERATOR']);
  $p->prepare("INSERT INTO patients_doctor_links(link_id,patient_id,doctor_id) VALUES(?,?,?)")->execute(['op02-link-'.$n,$patient,$doctor]);
  $date=(new DateTimeImmutable('next monday 10:00:00',new DateTimeZone('America/Mexico_City')))->modify('+14 days');
  $p->prepare("INSERT INTO agenda_appointments(appointment_id,doctor_id,consultorio_id,patient_id,start_at,end_at,modality,status,channel_origin,created_by_role,created_by_id) VALUES(?,?,'1',?,?,?,'in_person','tentative','op02_fixture','owner',?)")->execute(['op02-appointment-'.$n,$doctor,$patient,$date->format('Y-m-d H:i:s'),$date->modify('+30 minutes')->format('Y-m-d H:i:s'),$doctor==='op02-doctor'?'op02-owner':'op02-other-owner']);
 }
 $p->commit();echo "OP02_FIXTURE=SYNTHETIC_READY\n";
}catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
