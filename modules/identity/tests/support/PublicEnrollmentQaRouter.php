<?php
declare(strict_types=1);
// Explicit injection only in this owning new disposable server; no production selector.
if(PHP_SAPI!=='cli-server'||getenv('PUB_ENR02_QA')!=='1'||getenv('MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT')!=='0'
    ||!preg_match('/^mxmed_gate4d_preview_c4ui_([a-f0-9]{10})$/D',(string)getenv('MXMED_DB_NAME'),$m)
    ||getenv('MXMED_DB_USER')!=='gov03b_'.$m[1]){http_response_code(403);exit;}
$root=dirname(__DIR__,4);
if(getenv('ACT02_QA')==='1'&&preg_match('~\.(?:js|css|svg|png|jpg|woff2?|ico)$~D',(string)parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)))return false;
require_once $root.'/modules/identity/http/IdentityHttpComposition.php';
Identity\Http\IdentityHttpComposition::registerAutoloader();
$identity=Identity\Http\IdentityHttpComposition::fromProcessEnvironment();$pdo=$identity->pdo();
if($pdo->query('SELECT DATABASE()')->fetchColumn()!==getenv('MXMED_DB_NAME')||$pdo->query('SELECT CURRENT_USER()')->fetchColumn()!==getenv('MXMED_DB_USER').'@127.0.0.1'){http_response_code(403);exit;}
$security=new Identity\Services\GovernanceSecurityContext($identity->sessions(),$identity->authentication());
if(getenv('ACT02_QA')==='1'){
    require_once __DIR__.'/Act02SyntheticComposition.php';
    $bundle=Act02SyntheticComposition::install($identity);
    $path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
    if($path==='/api/identity/physician-onboarding.php')Identity\Accreditation\PhysicianOnboardingHttp::serve($bundle['onboarding']);
    if($path==='/public/identity/perfil-medico.php'){Identity\Accreditation\PhysicianOnboardingPage::show($identity,false,$bundle['onboarding']);return true;}
    if($path==='/internal/media-review/index.php'&&($_GET['section']??'')==='acreditacion'){Identity\Accreditation\PhysicianOnboardingPage::show($identity,true,$bundle['onboarding']);return true;}
    return false;
}
$cipher=new Identity\Accreditation\EvidenceCipher('c4-qa-key',['c4-qa-key'=>base64_decode((string)getenv('C4_EV_QA_KEY'),true)],'synthetic-retention-fixture-no-real-policy');
$service=new Identity\Accreditation\PublicEnrollmentService(new Identity\Accreditation\EvidenceAuthority($pdo,$security),$cipher,(string)getenv('PUB_ENR02_QA_PROOF_KEY'));
$review=null;$onboarding=null;
if(getenv('PUB_ENR03_QA')==='1'){
    require_once __DIR__.'/C4UiSyntheticSms.php';
    require_once $root.'/modules/media/storage/LocalPersistentPrivateMediaStorage.php';
    $mfa=new Identity\Services\StaffMfaService($pdo,base64_encode(str_repeat('m',32)),'c4-qa-mfa',security:$security);
    $storage=new Media\Storage\LocalPersistentPrivateMediaStorage((string)getenv('C4_EV_QA_STORAGE_ROOT'));
    $composition=new Identity\Accreditation\PhysicianAdmissionComposition($pdo,$security,$cipher,$storage,$mfa);
    $review=new Identity\Accreditation\ReviewedEnrollmentService($service,$mfa,$composition->projection);
    $phone=new Identity\Accreditation\PhoneVerificationService($service->a,$cipher,new Identity\Accreditation\EvidenceAudit($pdo),new C4UiSyntheticSms());
    $onboarding=new Identity\Accreditation\PhysicianOnboardingHttp($identity,$composition,$phone,$review);
}
Identity\Accreditation\PublicEnrollmentRuntime::install(new Identity\Accreditation\PublicEnrollmentHttp($identity,$service,(string)getenv('MXMED_GOVERNANCE_PROXY_KEY'),$review));
if($onboarding!==null){
    $path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
    if($path==='/api/identity/physician-onboarding.php')Identity\Accreditation\PhysicianOnboardingHttp::serve($onboarding);
    if($path==='/public/identity/perfil-medico.php'){Identity\Accreditation\PhysicianOnboardingPage::show($identity,false,$onboarding);return true;}
    if($path==='/internal/media-review/index.php'&&($_GET['section']??'')==='acreditacion'){Identity\Accreditation\PhysicianOnboardingPage::show($identity,true,$onboarding);return true;}
}
return false;
