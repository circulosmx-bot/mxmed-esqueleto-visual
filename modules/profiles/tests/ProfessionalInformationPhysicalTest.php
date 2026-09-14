<?php
declare(strict_types=1);
require_once __DIR__.'/../services/ProfessionalInformationService.php';
use Profiles\Services\ProfessionalInformationService as Service;
function check(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
$pdo=new PDO(getenv('IP01A_TEST_DSN'),getenv('IP01A_TEST_USER')?:'root',getenv('IP01A_TEST_PASS')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
check(str_starts_with((string)$pdo->query('SELECT DATABASE()')->fetchColumn(),'ip01a_test_'),'Synthetic database required');
$pdo->exec('CREATE TABLE profiles_doctors (doctor_id VARCHAR(64) PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
$pdo->exec(file_get_contents(__DIR__.'/../db/2026_09_14_professional_information.sql'));
$pdo->exec("INSERT INTO profiles_doctors VALUES ('synthetic-a'),('synthetic-b')");
$service=new Service($pdo);$draft=Service::emptyDraft();
$draft['public_professional_summary']='Trayectoria sintética';
foreach(Service::TYPES as $type)$draft['items'][$type]=[$type.' uno',$type.' dos',$type.' tres'];
check($service->current('synthetic-a')===Service::emptyDraft(),'empty backend');
$service->save('synthetic-a',$draft);check($service->current('synthetic-a')===$draft,'complete roundtrip');
check($service->current('synthetic-b')===Service::emptyDraft(),'doctor isolation');
$second=new Service(new PDO(getenv('IP01A_TEST_DSN'),'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]));
check($second->current('synthetic-a')===$draft,'independent connection roundtrip');
$changed=$draft;$changed['items']['DISEASE']=array_reverse($changed['items']['DISEASE']);$changed['items']['COURSE']=[];$changed['items']['MEMBERSHIP'][]='Nuevo';$changed['items']['TREATMENT']=array_reverse($changed['items']['TREATMENT']);
$service->save('synthetic-a',$changed);check($service->current('synthetic-a')===$changed,'removal/order/replacement');
foreach(['tooManyServices','longDisease','emptyItem','unknownType','extraCredential','longSummary'] as $case){
 $bad=$changed;
 if($case==='tooManyServices')$bad['items']['SERVICE']=array_fill(0,5,'Servicio');
 if($case==='longDisease')$bad['items']['DISEASE']=[str_repeat('a',41)];
 if($case==='emptyItem')$bad['items']['COURSE']=['  '];
 if($case==='unknownType')$bad['items']['LICENSE']=[];
 if($case==='extraCredential')$bad['professional_license']='123';
 if($case==='longSummary')$bad['public_professional_summary']=str_repeat('x',65536);
 try{$service->save('synthetic-a',$bad);throw new RuntimeException('Accepted '.$case);}catch(InvalidArgumentException $expected){}
 check($service->current('synthetic-a')===$changed,'invalid save changed state');
}
// Fail after summary update and deletion, during insertion: entire prior state survives.
$pdo->exec("CREATE TRIGGER ip01a_fail BEFORE INSERT ON profiles_doctor_professional_items FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic failure'");
try{$service->save('synthetic-a',$draft);throw new RuntimeException('Expected rollback');}catch(PDOException $expected){}
check($service->current('synthetic-a')===$changed,'rollback complete state');$pdo->exec('DROP TRIGGER ip01a_fail');
$duplicate=$changed;$duplicate['items']['CERTIFICATION']=['Duplicado','Duplicado'];$service->save('synthetic-a',$duplicate);check($service->current('synthetic-a')===$duplicate,'duplicates preserved');
require_once __DIR__.'/../repositories/PublicProfileRepository.php';
require_once __DIR__.'/../controllers/PublicProfileController.php';
$repository=new \Profiles\Repositories\PublicProfileRepository($pdo);
$public=$repository->resolvePublicDoctorProfile('synthetic-a')['professional'];
check($public['bio_long']===$duplicate['public_professional_summary'],'public canonical summary');
check($public['services']===$duplicate['items']['SERVICE'],'public canonical service order');
check($public['conditions_treated']===array_merge($duplicate['items']['DISEASE'],$duplicate['items']['TREATMENT']),'public disease/treatment contract');
$controller=new \Profiles\Controllers\PublicProfileController($repository);
$response=$controller->showByDoctorId('synthetic-a');
check($response['data']['professional']['services']===$duplicate['items']['SERVICE'],'public DTO canonical wiring');
echo "IP01A_PHYSICAL_QA=PASS\n";
