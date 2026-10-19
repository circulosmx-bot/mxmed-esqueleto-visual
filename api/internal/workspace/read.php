<?php
declare(strict_types=1);
require_once __DIR__.'/../../../modules/media/services/AdvisorWorkspaceService.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');header('Cross-Origin-Resource-Policy: same-origin');
try{
    $service=\Media\Services\AdvisorWorkspaceService::fromCookies($_COOKIE);
    if(($_SERVER['REQUEST_METHOD']??'')!=='GET'){http_response_code(405);header('Allow: GET');echo '{"ok":false,"error":"method_not_allowed"}';return;}
    if(array_diff(array_keys($_GET),['query','doctor_id'])||$_POST||$_FILES||isset($_GET['query'])&&!is_string($_GET['query'])||isset($_GET['doctor_id'])&&!is_string($_GET['doctor_id']))throw new InvalidArgumentException('workspace_invalid_request');
    echo json_encode(['ok'=>true,'data'=>$service->snapshot($_GET['query']??'',$_GET['doctor_id']??null)],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
    $status=match($e->getMessage()){'workspace_login_required'=>401,'workspace_staff_required','review_access_denied'=>403,'workspace_invalid_request'=>400,'workspace_not_found'=>404,default=>503};
    if($status===503)error_log('advisor_workspace_read_unavailable');http_response_code($status);
    echo json_encode(['ok'=>false,'error'=>match($status){401=>'authentication_required',403=>'forbidden',400=>'invalid_request',404=>'not_found',default=>'workspace_unavailable'}]);
}
