<?php
declare(strict_types=1);

$host=getenv('MXMED_DB_HOST')?:'127.0.0.1';$port=(int)(getenv('MXMED_DB_PORT')?:3306);
$user=getenv('MXMED_DB_USER')?:'root';$pass=getenv('MXMED_DB_PASS')?:'';
$database='cons_vis01_emergency_'.bin2hex(random_bytes(6));$root=dirname(__DIR__,3);
$admin=new PDO("mysql:host={$host};port={$port};charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
function consEmergency(bool $ok,string $label):void{if(!$ok)throw new RuntimeException('FAIL '.$label);echo "CONS_VIS01_EMERGENCY_{$label}=PASS\n";}
try{
  $admin->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
  $pdo=new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
  $pdo->exec("CREATE TABLE profiles_doctors (doctor_id VARCHAR(64) NOT NULL PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $pdo->exec("CREATE TABLE consultorios (doctor_id VARCHAR(64) NOT NULL,consultorio_id VARCHAR(64) NOT NULL,urgencias_json JSON NULL,PRIMARY KEY(doctor_id,consultorio_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $pdo->exec("INSERT INTO profiles_doctors VALUES ('doctor-a'),('doctor-b')");
  $pdo->exec("INSERT INTO consultorios VALUES ('doctor-a','office-1',JSON_ARRAY('4491111111')),('doctor-a','office-2',JSON_ARRAY('4492222222')),('doctor-b','office-1',JSON_ARRAY())");
  $before=$pdo->query('SELECT doctor_id,consultorio_id,urgencias_json FROM consultorios ORDER BY doctor_id,consultorio_id')->fetchAll();
  $pdo->exec((string)file_get_contents($root.'/docs/profile/CONS_VIS01_EMERGENCY_CONTACT_SCHEMA_PROPOSAL.sql'));
  consEmergency((int)$pdo->query('SELECT COUNT(*) FROM physician_emergency_contacts')->fetchColumn()===0,'NO_SILENT_BACKFILL');
  consEmergency($before===$pdo->query('SELECT doctor_id,consultorio_id,urgencias_json FROM consultorios ORDER BY doctor_id,consultorio_id')->fetchAll(),'LEGACY_DATA_PRESERVED');
  $pdo->exec("INSERT INTO physician_emergency_contacts (doctor_id,emergency_phones_json) VALUES ('doctor-a',JSON_ARRAY('4493333333')),('doctor-b',JSON_ARRAY('4494444444'))");
  consEmergency((int)$pdo->query('SELECT COUNT(*) FROM physician_emergency_contacts')->fetchColumn()===2,'PHYSICIAN_ISOLATION');
  try{$pdo->exec("INSERT INTO physician_emergency_contacts (doctor_id,emergency_phones_json) VALUES ('doctor-a',JSON_ARRAY())");throw new RuntimeException('FAIL duplicate');}catch(PDOException $e){consEmergency((string)($e->errorInfo[0]??'')==='23000','ONE_CANONICAL_ROW');}
  try{$pdo->exec("INSERT INTO physician_emergency_contacts (doctor_id,emergency_phones_json) VALUES ('doctor-c',JSON_ARRAY())");throw new RuntimeException('FAIL foreign');}catch(PDOException $e){consEmergency((string)($e->errorInfo[0]??'')==='23000','KNOWN_PHYSICIAN_REQUIRED');}
  $pdo->exec('DROP TABLE physician_emergency_contacts');
  consEmergency((int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='physician_emergency_contacts'")->fetchColumn()===0,'PROPOSAL_ROLLBACK');
  consEmergency($before===$pdo->query('SELECT doctor_id,consultorio_id,urgencias_json FROM consultorios ORDER BY doctor_id,consultorio_id')->fetchAll(),'ROLLBACK_PRESERVES_LEGACY');
}finally{$admin->exec("DROP DATABASE IF EXISTS `{$database}`");}
echo "CONS_VIS01_EMERGENCY_MIGRATION_REHEARSAL=PASS\n";
