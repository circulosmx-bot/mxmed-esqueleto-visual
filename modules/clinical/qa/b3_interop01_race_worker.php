<?php
declare(strict_types=1);
require_once dirname(__DIR__,3).'/api/_lib/healthcare_study_interop.php';
$input=json_decode((string)file_get_contents($argv[1]??''),true);
if (!is_array($input) || !preg_match('/^b3_interop01_[a-f0-9]{10}$/',(string)($input['db']??''))) exit(2);
while (microtime(true)<(float)$input['start']) usleep(1000);
try {
    $pdo=new PDO('mysql:host=localhost;dbname='.$input['db'].';charset=utf8mb4','root','',[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $service=new HealthcareStudyInteropService($pdo);
    $args=$input['args'];
    $value=match($input['operation']) {
        'send'=>$service->sendReferral(...$args),
        'accept'=>$service->acceptReferral(...$args),
        'publish'=>$service->publishReleasedResult($args[0],new class implements ProviderReleasedResultAuthority {
            public function assertReleased(array $release):void {
                if (($release['qa_released']??false)!==true)throw new RuntimeException('NOT_RELEASED');
            }
        },new ClinicalPrivateBinaryStorage($input['private'],dirname(__DIR__,3))),
        default=>throw new RuntimeException('UNKNOWN_OPERATION'),
    };
    echo json_encode(['ok'=>true,'value'=>$value],JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_THROW_ON_ERROR);
}
