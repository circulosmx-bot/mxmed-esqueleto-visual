<?php
declare(strict_types=1);
require_once __DIR__.'/../../api/_lib/clinical_measurement_trends.php';
// In-memory fixtures only: never connect to a working MXMed database.
$db=class_exists('Pdo\\Sqlite')?new Pdo\Sqlite('sqlite::memory:'):new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
if(method_exists($db,'createFunction'))$db->createFunction('UTC_TIMESTAMP',static fn()=>'2026-09-24 23:00:00');
else $db->sqliteCreateFunction('UTC_TIMESTAMP',static fn()=>'2026-09-24 23:00:00');
$db->exec('CREATE TABLE clinical_encounters (encounter_id INTEGER,doctor_id TEXT,patient_id TEXT)');
$db->exec('CREATE TABLE clinical_encounter_amendments (encounter_id INTEGER)');
$db->exec('CREATE TABLE clinical_observations (observation_id INTEGER,encounter_id INTEGER,code TEXT,value_numeric TEXT,unit TEXT,systolic_mm_hg TEXT,diastolic_mm_hg TEXT,effective_at TEXT,effective_at_authority TEXT,recorded_at TEXT,source TEXT)');
$db->exec("INSERT INTO clinical_encounters VALUES (1,'d','p'),(2,'d','p'),(3,'d','p'),(4,'other','p'),(5,'d','other')");
$add=static function(int $id,int $enc,string $code,string $value,string $unit,string $date,string $authority='EXPLICIT_EFFECTIVE_TIME',?string $s=null,?string $d=null)use($db):void{
 $db->prepare('INSERT INTO clinical_observations VALUES (?,?,?,?,?,?,?,?,?,?,?)')->execute([$id,$enc,$code,$value,$unit,$s,$d,$date,$authority,$date,'direct_measurement']);
};
$add(1,1,'weight','67.800000','kg','2026-09-01 10:00:00');
$add(2,2,'weight','68.400000','kg','2026-09-20 10:00:00');
$add(3,1,'blood_pressure','','mmHg','2026-09-18 10:00:00','EXPLICIT_EFFECTIVE_TIME','122.00','78.00');
$add(4,2,'heart_rate','74.000000','bpm','2026-09-22 10:00:00');
$add(5,3,'weight','70','kg','2026-09-24 10:00:00'); // current must not win ranking
$add(6,2,'weight','71','kg','2026-09-23 10:00:00','UNKNOWN_LEGACY');
$add(7,2,'weight','72','kg','2026-09-23 11:00:00','CAPTURE_TIME_FALLBACK');
$add(8,2,'weight','160','lb','2026-09-23 12:00:00'); // no conversion into canonical kg capture
$add(9,4,'weight','99','kg','2026-09-23 13:00:00');
$add(10,5,'weight','99','kg','2026-09-23 14:00:00');
$add(11,2,'weight','99','kg','2027-09-23 14:00:00');
$add(12,1,'height','184','cm','2018-09-23 14:00:00'); // older than trends window
$add(13,1,'pain','3','score','2026-09-19 14:00:00'); // reuse is not trend interpretation
$reader=new ClinicalMeasurementTrends($db);
$options=ClinicalMeasurementTrends::options(['view'=>'prior','exclude_encounter_id'=>'3']);
$result=$reader->read('d','p',$options);$rows=array_column($result['items'],null,'code');
$check=static function(bool $v,string $name):void{if(!$v)throw new RuntimeException($name);echo "PASS $name\n";};
$check(count($rows)===5,'one eligible prior per available concept');
$check($rows['weight']['observation_id']===2,'latest weight excludes current unknown fallback incompatible future and foreign rows');
$check($rows['blood_pressure']['observation_id']===3 && $rows['heart_rate']['observation_id']===4,'latest concepts may come from distinct dates and encounters');
$check($rows['height']['observation_id']===12,'all prior dates considered');
$check($rows['pain']['observation_id']===13,'pain reuse without trend promotion');
$check($rows['weight']['value_numeric']==='68.400000' && $rows['blood_pressure']['systolic_mm_hg']==='122.00','stored values and paired pressure unchanged');
$failed=false;try{ClinicalMeasurementTrends::options(['view'=>'prior']);}catch(InvalidArgumentException $e){$failed=true;}$check($failed,'current encounter exclusion required');
$check(ClinicalMeasurementTrends::options([])['view']==='series','existing default read contract preserved');
if(isset($argv[1]))file_put_contents($argv[1],json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
