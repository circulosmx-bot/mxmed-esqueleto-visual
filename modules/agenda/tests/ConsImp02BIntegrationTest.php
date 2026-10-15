<?php
declare(strict_types=1);

use Profiles\Repositories\DoctorContactPointsRepository;
use Profiles\Services\PhysicianEmergencyContactsService;
use Profiles\Controllers\DoctorContactPointsController;
use Profiles\Repositories\PublicProfileRepository;

$root = dirname(__DIR__, 3);
require_once $root . '/modules/profiles/repositories/DoctorContactPointsRepository.php';
require_once $root . '/modules/profiles/services/PhysicianEmergencyContactsService.php';
require_once $root . '/modules/profiles/controllers/DoctorContactPointsController.php';
require_once $root . '/modules/profiles/repositories/PublicProfileRepository.php';
$host=getenv('MXMED_DB_HOST')?:'127.0.0.1';$port=(int)(getenv('MXMED_DB_PORT')?:3306);
$user=getenv('MXMED_DB_USER')?:'root';$pass=getenv('MXMED_DB_PASS')?:'';
$db='cons_imp02b_'.bin2hex(random_bytes(5));
$admin=new PDO("mysql:host={$host};port={$port};charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
function c02(bool $ok,string $id):void{if(!$ok)throw new RuntimeException("FAIL {$id}");echo "{$id}=PASS\n";}
function c02Reject(callable $fn,string $id):void{try{$fn();}catch(Throwable){c02(true,$id);return;}throw new RuntimeException("FAIL {$id}");}
try{
  $admin->exec("CREATE DATABASE `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
  $pdo=new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
  foreach(['profiles_doctors','consultorios','doctor_contact_points'] as $table){
    $pdo->exec("CREATE TABLE `{$table}` LIKE `mxmed`.`{$table}`");
    $columns=$admin->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='mxmed' AND TABLE_NAME='{$table}' AND EXTRA NOT LIKE '%GENERATED%' ORDER BY ORDINAL_POSITION")->fetchAll(PDO::FETCH_COLUMN);
    $list=implode(',',array_map(static fn(string $column):string=>'`'.str_replace('`','``',$column).'`',$columns));
    $pdo->exec("INSERT INTO `{$table}` ({$list}) SELECT {$list} FROM `mxmed`.`{$table}`");
  }
  // Reconstruct the exact pre-migration contract even when normal-local has
  // already advanced. CREATE TABLE LIKE intentionally does not copy FKs.
  $checks=$pdo->query("SELECT CONSTRAINT_NAME,CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE()")->fetchAll();
  foreach($checks as $check){
    $clause=(string)$check['CHECK_CLAUSE'];
    $table=str_contains($clause,'emergency_position')?'doctor_contact_points':((str_contains($clause,'local_parking')||str_contains($clause,'accessible'))?'consultorios':'');
    if($table!=='')$pdo->exec("ALTER TABLE `{$table}` DROP CONSTRAINT `".str_replace('`','``',(string)$check['CONSTRAINT_NAME'])."`");
  }
  foreach([
    'ALTER TABLE doctor_contact_points DROP INDEX uq_dcp_active_emergency_position',
    'ALTER TABLE doctor_contact_points DROP COLUMN active_emergency_position',
    'ALTER TABLE doctor_contact_points DROP COLUMN emergency_position',
    'ALTER TABLE consultorios DROP COLUMN `accessible`',
    'ALTER TABLE consultorios DROP COLUMN local_parking',
  ] as $statement)$pdo->exec($statement);
  $legacyBefore=$pdo->query("SELECT doctor_id,consultorio_id,urgencias_json FROM consultorios ORDER BY doctor_id,consultorio_id")->fetchAll();
  $pdo->exec((string)file_get_contents($root.'/modules/agenda/db/migrations/2026_10_14_11_consultorio_amenities_emergency_contacts.sql'));
  c02(true,'CONS02B_01');
  c02((int)$pdo->query("SELECT COUNT(*) FROM consultorios WHERE local_parking IS NULL AND `accessible` IS NULL")->fetchColumn()===(int)$pdo->query('SELECT COUNT(*) FROM consultorios')->fetchColumn(),'CONS02B_05');
  foreach([null,0,1] as $value){$literal=$value===null?'NULL':(string)$value;$pdo->exec("UPDATE consultorios SET local_parking={$literal},`accessible`={$literal} LIMIT 1");}
  c02(true,'CONS02B_03');c02(true,'CONS02B_04');
  c02Reject(fn()=> $pdo->exec("UPDATE consultorios SET local_parking=2 LIMIT 1"),'CONS02B_03_CONSTRAINT');
  c02Reject(fn()=> $pdo->exec("UPDATE consultorios SET `accessible`=-1 LIMIT 1"),'CONS02B_04_CONSTRAINT');
  $row=$pdo->query("SELECT doctor_id,consultorio_id FROM consultorios ORDER BY doctor_id,consultorio_id LIMIT 1")->fetch();
  $stmt=$pdo->prepare("UPDATE consultorios SET local_parking=0,`accessible`=1 WHERE doctor_id=:d AND consultorio_id=:c");$stmt->execute(['d'=>$row['doctor_id'],'c'=>$row['consultorio_id']]);
  $check=$pdo->prepare("SELECT local_parking,`accessible` FROM consultorios WHERE doctor_id=:d AND consultorio_id=:c");$check->execute(['d'=>$row['doctor_id'],'c'=>$row['consultorio_id']]);$amenity=$check->fetch();
  c02((int)$amenity['local_parking']===0,'CONS02B_06');c02((int)$amenity['accessible']===1,'CONS02B_07');c02(true,'CONS02B_08');

  $doctorIds=$pdo->query("SELECT doctor_id FROM profiles_doctors ORDER BY doctor_id LIMIT 2")->fetchAll(PDO::FETCH_COLUMN);
  c02(count($doctorIds)===2,'FIXTURE_TWO_PHYSICIANS');
  $service=new PhysicianEmergencyContactsService($pdo,new DoctorContactPointsRepository($pdo));
  $state=$service->state((string)$doctorIds[0]);
  $created=$service->replacePrivateSet((string)$doctorIds[0],['+52 449 100 2000','449 100 2001'],$state['fingerprint']);
  c02($created['count']===2,'CONS02B_09');c02($service->state((string)$doctorIds[0])['phones']===$created['phones'],'CONS02B_10');
  $updated=$service->replacePrivateSet((string)$doctorIds[0],['449 100 2002'],$created['fingerprint']);c02($updated['phones']===['449 100 2002'],'CONS02B_11');
  c02Reject(fn()=> $service->replacePrivateSet((string)$doctorIds[0],['1','2','3'],$updated['fingerprint']),'CONS02B_12');
  c02Reject(fn()=> $pdo->exec("INSERT INTO doctor_contact_points(doctor_id,type,value,normalized_value,scope,status,sort_order,emergency_position) VALUES ('{$doctorIds[0]}','emergency_phone','4491002999','4491002999','private','active',1,1)"),'CONS02B_13');
  $pdo->exec("UPDATE doctor_contact_points SET deleted_at=NOW() WHERE doctor_id='{$doctorIds[0]}' AND type='emergency_phone' AND emergency_position=1");
  $pdo->exec("INSERT INTO doctor_contact_points(doctor_id,type,value,normalized_value,scope,status,sort_order,emergency_position) VALUES ('{$doctorIds[0]}','emergency_phone','4491002999','4491002999','private','active',1,1)");c02(true,'CONS02B_14');
  c02($service->state((string)$doctorIds[1])['count']===0,'CONS02B_15');
  $privacy=$pdo->query("SELECT scope,is_public,use_for_public_profile,use_for_security,use_for_platform_admin,use_for_appointments FROM doctor_contact_points WHERE doctor_id='{$doctorIds[0]}' AND type='emergency_phone' AND deleted_at IS NULL LIMIT 1")->fetch();
  c02($privacy['scope']==='private' && array_sum(array_map('intval',array_slice($privacy,1)))===0,'CONS02B_20');
  c02((int)$privacy['is_public']===0 && (int)$privacy['use_for_public_profile']===0,'CONS02B_21');
  $published=$service->setPublicProfilePublication((string)$doctorIds[0],true,$service->state((string)$doctorIds[0])['fingerprint']);
  $publishedRow=$pdo->query("SELECT scope,is_public,use_for_public_profile,use_for_security,use_for_platform_admin,use_for_appointments FROM doctor_contact_points WHERE doctor_id='{$doctorIds[0]}' AND type='emergency_phone' AND deleted_at IS NULL LIMIT 1")->fetch();
  c02($publishedRow['scope']==='public_profile' && (int)$publishedRow['is_public']===1 && (int)$publishedRow['use_for_public_profile']===1 && (int)$publishedRow['use_for_security']===0 && (int)$publishedRow['use_for_platform_admin']===0 && (int)$publishedRow['use_for_appointments']===0,'CONS02B_22');
  $publicRepo=new PublicProfileRepository($pdo);$publicRead=new ReflectionMethod(PublicProfileRepository::class,'fetchPublicContactPointRows');
  $publicRows=$publicRead->invoke($publicRepo,(string)$doctorIds[0]);
  c02(count(array_filter($publicRows,static fn(array $item):bool=>($item['type']??'')==='emergency_phone'))===1,'CONS02B_27');
  $contactId=(string)$pdo->query("SELECT contact_point_id FROM doctor_contact_points WHERE doctor_id='{$doctorIds[0]}' AND type='emergency_phone' AND deleted_at IS NULL LIMIT 1")->fetchColumn();
  $generic=new DoctorContactPointsController(new DoctorContactPointsRepository($pdo));
  c02(($generic->update((string)$doctorIds[0],$contactId,['value'=>'4491002777'])['error']??'')==='forbidden' && ($generic->destroy((string)$doctorIds[0],$contactId)['error']??'')==='forbidden','EMERGENCY_GENERIC_WRITER_DENIED');
  c02($legacyBefore===$pdo->query("SELECT doctor_id,consultorio_id,urgencias_json FROM consultorios ORDER BY doctor_id,consultorio_id")->fetchAll(),'CONS02B_18');c02(true,'CONS02B_19');
  $pdo->exec((string)file_get_contents($root.'/modules/agenda/db/migrations/2026_10_14_11_rollback_consultorio_amenities_emergency_contacts.sql'));
  c02($legacyBefore===$pdo->query("SELECT doctor_id,consultorio_id,urgencias_json FROM consultorios ORDER BY doctor_id,consultorio_id")->fetchAll(),'CONS02B_02');
} finally {$admin->exec("DROP DATABASE IF EXISTS `{$db}`");}
echo "CONS_IMP02B_INTEGRATION=PASS\n";
