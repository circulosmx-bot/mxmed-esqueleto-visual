<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/operators/OperatorPanelReadiness.php';
use Identity\Operators\OperatorPanelReadiness;
function expectPanel(bool $condition,string $label): void { if(!$condition)throw new RuntimeException($label);echo $label."=PASS\n"; }
// Fixed disposable name, never an existing database and never any running review DB.
$name='mxmed_gate4d_preview_opint04_contract';
$admin=new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$s=$admin->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?');$s->execute([$name]);
if((int)$s->fetchColumn()!==0)throw new RuntimeException('Disposable contract database already exists; refusing overwrite');
$admin->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try{
 $pdo=new PDO('mysql:host=127.0.0.1;port=3306;dbname='.$name.';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $pdo->exec('CREATE TABLE agenda_operators(operator_id VARCHAR(64),doctor_id VARCHAR(64),alias VARCHAR(64),status VARCHAR(24),created_at DATETIME,temp_password_hash VARCHAR(190))');
 $pdo->exec("INSERT INTO agenda_operators VALUES ('a','1','First','active',NOW(),'CREDENTIAL_CANARY'),('b','1','Second','paused',NOW(),'CREDENTIAL_CANARY'),('c','1','Third','archived',NOW(),'CREDENTIAL_CANARY'),('other','2','CROSS_PROFILE_CANARY','active',NOW(),'CREDENTIAL_CANARY')");
 $pdo->exec('CREATE TABLE agenda_operator_audit_events(event_id VARCHAR(64),doctor_id VARCHAR(64),operator_id VARCHAR(64),event_type VARCHAR(64),action_label VARCHAR(64),at DATETIME,notes TEXT)');
 $pdo->exec("INSERT INTO agenda_operator_audit_events VALUES ('e','1','a','created','Registro anterior',NOW(),'PRIVATE_NOTE_CANARY'),('other','2','other','created','CROSS_PROFILE_CANARY',NOW(),'PRIVATE_NOTE_CANARY')");
 $context=['professional'=>['doctor_id'=>'1','role'=>'collaborator'],'account'=>['id'=>'synthetic']];
 $a=OperatorPanelReadiness::normalReadOnly($pdo,$context);
 expectPanel(!$a['schema_ready']&&!$a['service_ready']&&!$a['can_manage']&&$a['status']==='schema_not_ready','P01_missing_schema_read_only');
 expectPanel($a['legacy']['counts']===['active'=>1,'paused'=>1,'pending'=>0,'archived'=>1]&&count($a['legacy']['audit'])===1,'P02_legacy_inventory_and_history');
 $encoded=json_encode($a);expectPanel(!str_contains($encoded,'CANARY'),'P03_projection_no_credentials_notes_or_cross_profile');
 $pdo->exec("INSERT INTO agenda_operators VALUES ('d','1','Fourth','active',NOW(),'CREDENTIAL_CANARY')");
 expectPanel(OperatorPanelReadiness::normalReadOnly($pdo,$context)['legacy']['over_two_seats'],'P04_excess_requires_reconciliation');
 foreach(OperatorPanelReadiness::REQUIRED_SCHEMA as $table=>$columns){$defs=implode(',',array_map(fn($c)=>'`'.$c.'` VARCHAR(190)',$columns));$pdo->exec('CREATE TABLE `'.$table.'` ('.$defs.')');}
 $a=OperatorPanelReadiness::normalReadOnly($pdo,$context);
 expectPanel($a['schema_ready']&&!$a['service_ready']&&!$a['can_manage']&&$a['status']==='activation_pending','P05_schema_presence_never_activates_writer');
 $context['professional']['role']='owner';expectPanel(!OperatorPanelReadiness::normalReadOnly($pdo,$context)['can_manage'],'P06_owner_label_not_activation');
 $pdo->exec('DROP TABLE agenda_operators');$a=OperatorPanelReadiness::normalReadOnly($pdo,$context);expectPanel($a['legacy']['read_status']==='unavailable'&&$a['legacy']['counts']===null,'P07_missing_inventory_not_empty_success');
 expectPanel(OperatorPanelReadiness::available(['owner'=>false])['can_manage']===false&&OperatorPanelReadiness::available(['owner'=>true])['can_manage']===true,'P08_validated_service_snapshot_presentation');
}finally{$admin->exec("DROP DATABASE `$name`");echo "DISPOSABLE_DATABASE_DELETED=true\n";}
