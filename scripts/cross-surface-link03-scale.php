<?php
declare(strict_types=1);
require_once __DIR__.'/../modules/identity/http/IdentityHttpComposition.php';
\Identity\Http\IdentityHttpComposition::registerAutoloader();
$o=realpath($argv[1]??'');if(!$o||!preg_match('~^/private/tmp/mxmed-ase-adm02imp01-[a-zA-Z0-9_]+$~D',$o))throw new RuntimeException('owned_required');
$e=json_decode(file_get_contents($o.'/review-env.json'),true,16,JSON_THROW_ON_ERROR);$p=new PDO('mysql:unix_socket='.$o.'/mysql.sock;dbname='.$e['MXMED_DB_NAME'].';charset=utf8mb4','ase_fixture',$e['ASE_INT02A_FIXTURE_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
if(realpath($p->query('SELECT @@datadir')->fetchColumn())!==$o.'/mysql')throw new RuntimeException('owned_required');
$p->exec('CREATE TABLE IF NOT EXISTS qa_link03_profiles (doctor_id VARCHAR(64) PRIMARY KEY,display_name VARCHAR(120),specialty_primary VARCHAR(120),profile_status VARCHAR(20))');
$p->exec('CREATE TABLE IF NOT EXISTS qa_link03_locations (doctor_id VARCHAR(64),consultorio_id VARCHAR(64),titulo VARCHAR(120),municipio VARCHAR(120),estado VARCHAR(120),PRIMARY KEY(doctor_id,consultorio_id))');
$s=$p->prepare('INSERT IGNORE INTO qa_link03_profiles VALUES (?,?,?,?)');$ids=[];
for($i=1;$i<=80;$i++){$id=sprintf('qa_%03d',$i);$ids[]=$id;$s->execute([$id,'Synthetic scale '.$id,'Synthetic','draft']);}
$source=new Identity\PhysicianAdministration\SqlSource(fn(bool $ops)=>$p,'qa_link03');$seen=[];$after='';$pages=0;
do{$rows=$source->search($ids,'Synthetic',$after,20);if(count($rows)>21)throw new RuntimeException('unbounded');$more=count($rows)>20;$rows=array_slice($rows,0,20);foreach($rows as $r)$seen[]=$r['doctor_id'];$after=end($rows)['doctor_id']??'';$pages++;}while($more&&$pages<10);
if($seen!==$ids||$pages!==4)throw new RuntimeException('pagination');
if(count($source->search([$ids[0]],'Synthetic','',20))!==1)throw new RuntimeException('scope');
if($source->search($ids,'%','',20)!==[])throw new RuntimeException('wildcard_injection');
for($i=0;$i<20;$i++)if(count($source->search($ids,'Synthetic','',20))!==21)throw new RuntimeException('repeat');
$p->beginTransaction();$joined=new Identity\PhysicianAdministration\SqlSource(fn(bool $ops)=>$p,'qa_link03',true);if(count($joined->search([$ids[0]],'Synthetic','',20))!==1||!$p->inTransaction())throw new RuntimeException('canonical_join');$p->rollBack();
$checks=['canonical same-PDO composition preserves caller transaction','80 synthetic records / four pages / no duplicates','20+1 bounded query','exact resource list','literal wildcard escaping','20 repeated reads'];
file_put_contents($o.'/link03-evidence/scale.json',json_encode(['pass'=>true,'checks'=>$checks,'production_load_test'=>false],JSON_PRETTY_PRINT));echo 'SCALE_PASS '.count($checks).PHP_EOL;
