<?php
declare(strict_types=1);
$path=parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH);
if(in_array($path,['/assets/js/identity/professional-sources.js','/assets/js/identity/physician-onboarding.js','/assets/js/identity/physician-enrollment.js'],true)){require_once __DIR__.'/ase-adm02-workflow-runtime.php';staffWorkflowOwnedServices();return false;}
if($path==='/public/identity/acceso.php'){
    require_once __DIR__.'/ase-adm02-workflow-runtime.php';staffWorkflowOwnedServices();
    $owned=realpath((string)getenv('ASE_INT02A_OWNED'));
    $command='python3 '.realpath(__DIR__.'/advisor-capability-closure02-review.py').' '.$owned;
    $advisor=($_GET['next']??'')==='/internal/staff-workspace/index.php';
    $staffSyntheticLoginGuide=['email'=>$advisor?'full_grants_closure@example.test':'qa_director@example.test',
        'credentials'=>$command.($advisor?' credentials-advisor':' credentials-director'),
        'mfa'=>$command.($advisor?' mfa-advisor':' mfa-director'),'staff_control04'=>true];
    require __DIR__.'/../public/identity/acceso.php';return true;
}
$onboarding=$path==='/api/identity/physician-onboarding.php'||($path==='/internal/media-review/index.php'&&($_GET['section']??'')==='acreditacion');
if($onboarding||$path==='/api/internal/workspace/requests.php'){
    try{
        require_once __DIR__.'/advisor-capability-closure02-runtime.php';$workspace02Services=closure02Services();
        if($path==='/api/identity/physician-onboarding.php')Identity\Accreditation\PhysicianOnboardingHttp::serve($workspace02Services['onboarding']);
        elseif($onboarding)Identity\Accreditation\PhysicianOnboardingPage::show($workspace02Services['identity'],true,$workspace02Services['onboarding']);
        elseif(($_GET['mode']??'')==='admin_portrait')require __DIR__.'/cross-surface-ux05-portrait.php';
        else require __DIR__.'/..'.$path;
    }catch(Throwable $e){error_log('closure_runtime: '.$e->getMessage());http_response_code(503);header('Cache-Control: no-store');echo '{"error":"isolated_service_unavailable"}';}
    return true;
}
return require __DIR__.'/ase-advisor-workspace02-router.php';
