<?php
declare(strict_types=1);
// Only the owning disposable runner can install this composition. No normal selector.
if(PHP_SAPI!=='cli-server'){http_response_code(403);exit;}
if(getenv('ACT02_QA')==='1'&&preg_match('~\.(?:js|css|svg|png|jpg|woff2?|ico)$~D',(string)parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)))return false;
require_once __DIR__.'/CorrectionHarness.php';
$guard=corConnect();$root=dirname(__DIR__,4);
$identity=Identity\Http\IdentityHttpComposition::fromProcessEnvironment();$pdo=$identity->pdo();
if($pdo->query('SELECT DATABASE()')->fetchColumn()!==$guard->query('SELECT DATABASE()')->fetchColumn()
    ||$pdo->query('SELECT CURRENT_USER()')->fetchColumn()!==$guard->query('SELECT CURRENT_USER()')->fetchColumn()){http_response_code(403);exit;}
$security=new Identity\Services\GovernanceSecurityContext($identity->sessions(),$identity->authentication());
if(getenv('ACT02_QA')==='1'){
    require_once $root.'/modules/identity/tests/support/Act02SyntheticComposition.php';
    $bundle=Act02SyntheticComposition::install($identity);
    $path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
    if($path==='/api/identity/physician-onboarding.php')Identity\Accreditation\PhysicianOnboardingHttp::serve($bundle['onboarding']);
    // All other routes use the original entry points and the shared installed runtimes.
}else{
$cipher=new Profiles\Corrections\ReportCipher('cor-qa-key',['cor-qa-key'=>base64_decode((string)getenv('PUB_COR01_KEY'),true)],'synthetic-only-no-real-retention-approval');
$service=new Profiles\Corrections\ReportService($pdo,$cipher,$identity->csrf(),new Profiles\Corrections\ReportRatePolicy($pdo,$cipher,browserLimit:3,peerLimit:100,profileLimit:100));
$mfa=new Identity\Services\StaffMfaService($pdo,base64_encode(str_repeat('m',32)),'cor-qa-mfa',security:$security);
$review=new Profiles\Corrections\ReportReviewService($service,$security,$mfa);
$applications=getenv('PUB_COR02_QA')==='1'?new Profiles\Corrections\ApplicationService(new Profiles\Corrections\ApplicationAuthority($service,$security),$mfa):null;
Profiles\Corrections\ReportRuntime::install(new Profiles\Corrections\ReportHttp($identity,$service,$review,(string)getenv('MXMED_GOVERNANCE_PROXY_KEY'),$applications));
}
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(preg_match('#^/api/profiles/public/doctor/([A-Za-z0-9._:-]{1,64})$#D',$path,$m)){
    header('Content-Type: application/json');
    $dto=(new Profiles\Controllers\PublicProfileController(new Profiles\Repositories\PublicProfileRepository($pdo)))->showByDoctorId($m[1]);
    echo json_encode($dto,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);return true;
}
return false;
