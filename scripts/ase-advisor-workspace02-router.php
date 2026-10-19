<?php
declare(strict_types=1);
// Transport adapter only for this task's owned disposable composition.
$path=parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH);
if(getenv('MXMED_ADVISOR_WORKSPACE02_REVIEW')==='1' && ($path==='/public/identity/perfil-medico.php'&&isset($_GET['application'])
    ||$path==='/assets/js/identity/physician-request-continuation.js')){
    try{
        require_once __DIR__.'/ase-advisor-workspace02-runtime.php';$workspace02Services=workspace02OwnedServices();
        if($path==='/assets/js/identity/physician-request-continuation.js')return false;
        Identity\PhysicianRequests\ApplicantPage::show($workspace02Services);
    }catch(Throwable $e){http_response_code(503);header('Cache-Control: no-store');echo 'Revisión aislada no disponible.';}
    return true;
}
if(getenv('MXMED_ADVISOR_WORKSPACE02_REVIEW')==='1' && $path==='/api/internal/workspace/requests.php'){
    try{
        require_once __DIR__.'/ase-advisor-workspace02-runtime.php';
        $workspace02Services=workspace02OwnedServices();
        require __DIR__.'/..'.$path;
    }catch(Throwable $e){http_response_code(503);header('Cache-Control: no-store');echo '{"error":"isolated_service_unavailable"}';}
    return true;
}
return require __DIR__.'/ase-adm02-workflow-router.php';
