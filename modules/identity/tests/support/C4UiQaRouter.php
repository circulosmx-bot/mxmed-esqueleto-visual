<?php
declare(strict_types=1);
// Owning CLI server router ONLY; cannot select mxmed or any persistent review DB.
if (PHP_SAPI!=='cli-server'||getenv('MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT')!=='0'
    ||!preg_match('/^mxmed_gate4d_preview_c4ui_([a-f0-9]{10})$/D',(string)getenv('MXMED_DB_NAME'),$m)
    ||getenv('MXMED_DB_USER')!=='gov03b_'.$m[1]){http_response_code(403);exit;}
$root=dirname(__DIR__,4);
require_once $root.'/modules/identity/http/IdentityHttpComposition.php';
Identity\Http\IdentityHttpComposition::registerAutoloader();
require_once $root.'/modules/media/storage/LocalPersistentPrivateMediaStorage.php';
require_once $root.'/modules/agenda/contracts/OtpProviderPort.php';
require_once __DIR__.'/C4UiSyntheticSms.php';
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if (!in_array($path,['/public/identity/perfil-medico.php','/api/identity/physician-onboarding.php','/internal/media-review/index.php'],true))return false;
$identity=Identity\Http\IdentityHttpComposition::fromProcessEnvironment();$pdo=$identity->pdo();
if ($pdo->query('SELECT DATABASE()')->fetchColumn()!==getenv('MXMED_DB_NAME')){http_response_code(403);exit;}
$pending=is_file((string)getenv('C4_UI_QA_MODE_FILE'))&&trim(file_get_contents((string)getenv('C4_UI_QA_MODE_FILE')))==='pending';
if($pending){
    $http=new Identity\Accreditation\PhysicianOnboardingHttp($identity);
    if($path==='/api/identity/physician-onboarding.php')Identity\Accreditation\PhysicianOnboardingHttp::serve($http);
    Identity\Accreditation\PhysicianOnboardingPage::show($identity,$path==='/internal/media-review/index.php',$http);return true;
}
$security=new Identity\Services\GovernanceSecurityContext($identity->sessions(),$identity->authentication());
$a=new Identity\Accreditation\EvidenceAuthority($pdo,$security);
$cipher=new Identity\Accreditation\EvidenceCipher('c4-qa-key',['c4-qa-key'=>base64_decode((string)getenv('C4_EV_QA_KEY'),true)],'synthetic-retention-fixture-no-real-policy');
$mfa=new Identity\Services\StaffMfaService($pdo,base64_encode(str_repeat('m',32)),'c4-qa-mfa',security:$security);
$storage=new Media\Storage\LocalPersistentPrivateMediaStorage((string)getenv('C4_EV_QA_STORAGE_ROOT'));
$services=new Identity\Accreditation\PhysicianAdmissionComposition($pdo,$security,$cipher,$storage,$mfa);
$phone=new Identity\Accreditation\PhoneVerificationService($a,$cipher,new Identity\Accreditation\EvidenceAudit($pdo),new C4UiSyntheticSms());
$http=new Identity\Accreditation\PhysicianOnboardingHttp($identity,$services,$phone);
if($path==='/api/identity/physician-onboarding.php')Identity\Accreditation\PhysicianOnboardingHttp::serve($http);
if($path==='/public/identity/perfil-medico.php'){Identity\Accreditation\PhysicianOnboardingPage::show($identity,false,$http);return true;}
if($path==='/internal/media-review/index.php'&&($_GET['section']??'')==='acreditacion'){Identity\Accreditation\PhysicianOnboardingPage::show($identity,true,$http);return true;}
return false;
