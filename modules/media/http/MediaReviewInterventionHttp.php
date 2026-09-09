<?php
declare(strict_types=1);
namespace Media\Http;
require_once __DIR__.'/../services/MediaReviewInterventionService.php';
use Identity\Http\{IdentityHttpComposition,CanonicalHttpSessionResolver};
use Identity\Repositories\InternalOperatorGrantRepository;
use Identity\Services\InternalOperatorAuthority;
use Media\Services\MediaReviewInterventionService;
use Platform\Contracts\RiskLevel;

final class MediaReviewInterventionHttp
{
    public static function run(bool $download):void
    {
        header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('Cross-Origin-Resource-Policy: same-origin');
        try {
            if(!is_string($_COOKIE['__Host-mxmed_session']??null))throw new \RuntimeException('intervention_denied');
            $identity=IdentityHttpComposition::fromProcessEnvironment();$resolver=new CanonicalHttpSessionResolver($identity->sessions());$session=$resolver->resolve($_COOKIE);
            $cap=$download?MediaReviewInterventionService::DOWNLOAD:MediaReviewInterventionService::CORRECT;
            $context=(new InternalOperatorAuthority($resolver,new InternalOperatorGrantRepository($identity->pdo())))->resolve($_COOKIE,$download?'download_source':'upload_corrected','media_review_submission',RiskLevel::R1);
            if($session===null || $context===null || $context->context()->accountId()!==$session->accountId() || !$context->context()->capabilities()->contains($cap))throw new \RuntimeException('intervention_denied');
            $method=$download?'GET':'POST';if(($_SERVER['REQUEST_METHOD']??'')!==$method){header('Allow: '.$method);self::error(405,'method_not_allowed');return;}
            if($download){
                if(array_keys($_GET)!==['submission_id'] || !is_string($_GET['submission_id']))throw new \RuntimeException('intervention_invalid_request');
                $id=$_GET['submission_id'];
            }else{
                if($_GET!==[] || array_diff(array_keys($_POST),['submission_id','csrf'])!==[] || !is_string($_POST['submission_id']??null))throw new \RuntimeException('intervention_invalid_request');
                if(!is_string($_POST['csrf']??null) || !$identity->csrf()->validAuthenticated($_POST['csrf'],$session->session()->tokenDigest()))throw new \RuntimeException('intervention_denied');
                if(array_keys($_FILES)!==['corrected'] || !is_array($_FILES['corrected']) || !is_string($_FILES['corrected']['tmp_name']??null) || !is_uploaded_file($_FILES['corrected']['tmp_name']))throw new \RuntimeException('intervention_invalid_upload');
                $id=$_POST['submission_id'];
            }
            require_once __DIR__.'/../../../api/_lib/db.php';require_once __DIR__.'/../private-bootstrap.php';
            $service=new MediaReviewInterventionService(\mxmed_pdo(),\mxmed_private_media_storage());
            if($download){
                $file=$service->download($context,$id);
                header('Content-Type: '.$file['mime']);header('Content-Disposition: attachment; filename="'.$file['filename'].'"');header('Content-Length: '.strlen($file['bytes']));echo $file['bytes'];
            }else{header('Content-Type: application/json; charset=UTF-8');echo json_encode($service->corrected($context,$id,$_FILES['corrected']),JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
        }catch(\Throwable $e){
            $status=match($e->getMessage()){'intervention_denied'=>403,'intervention_invalid_request','intervention_invalid_upload'=>400,'intervention_not_found'=>404,'intervention_conflict'=>409,default=>str_starts_with($e->getMessage(),'logo_upload_')?400:503};
            if($status===503)error_log('media_intervention_unavailable');
            self::error($status,match($status){403=>'forbidden',400=>'invalid_request',404=>'not_found',409=>'already_processed_or_ineligible',default=>'unavailable'});
        }
    }
    private static function error(int $status,string $error):void {http_response_code($status);header('Content-Type: application/json; charset=UTF-8');echo json_encode(['ok'=>false,'error'=>$error]);}
}
