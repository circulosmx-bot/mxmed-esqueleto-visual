<?php
declare(strict_types=1);
namespace Media\Http;
require_once __DIR__.'/../services/ProfilePhotoApprovalService.php';
use Identity\Http\{IdentityHttpComposition,CanonicalHttpSessionResolver};
use Identity\Repositories\InternalOperatorGrantRepository;
use Identity\Services\InternalOperatorAuthority;
use Media\Services\{MediaReviewAuthority,ProfilePhotoApprovalService};
use Platform\Contracts\RiskLevel;

final class ProfilePhotoApprovalHttp
{
    public static function run(bool $options=false): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');
        header('Cross-Origin-Resource-Policy: same-origin');
        try {
            // No development PHP-session fixture, headers, ownership, or client roles here.
            if (!is_string($_COOKIE['__Host-mxmed_session']??null)) throw new \RuntimeException('approval_denied');
            $identity=IdentityHttpComposition::fromProcessEnvironment();
            $resolver=new CanonicalHttpSessionResolver($identity->sessions());
            $session=$resolver->resolve($_COOKIE);
            $authority=new InternalOperatorAuthority($resolver,new InternalOperatorGrantRepository($identity->pdo()));
            $context=$authority->resolve($_COOKIE,$options?'read':'approve','media_review_submission',$options?RiskLevel::R0:RiskLevel::R1);
            if ($session===null || $context===null || $session->accountId()!==$context->context()->accountId()) throw new \RuntimeException('approval_denied');
            $caps=$context->context()->capabilities();
            if (!$caps->contains(MediaReviewAuthority::CAPABILITY)) throw new \RuntimeException('approval_denied');
            if ($options) {
                MediaReviewAuthority::requireRead($context);
                if (($_SERVER['REQUEST_METHOD']??'')!=='GET') { self::respond(405,['ok'=>false,'error'=>'method_not_allowed'],'GET');return; }
                $canApprove=$caps->contains(ProfilePhotoApprovalService::CAPABILITY);
                $canDownload=$caps->contains('media_review_source_download');$canCorrect=$caps->contains('media_review_corrected_upload');
                self::respond(200,['ok'=>true,'can_approve'=>$canApprove,'can_download_source'=>$canDownload,'can_upload_corrected'=>$canCorrect,
                    'csrf'=>($canApprove||$canCorrect)?$identity->csrf()->issueAuthenticated($session->session()->tokenDigest()):null]);return;
            }
            if (!$caps->contains(ProfilePhotoApprovalService::CAPABILITY)) throw new \RuntimeException('approval_denied');
            if (($_SERVER['REQUEST_METHOD']??'')!=='POST') { self::respond(405,['ok'=>false,'error'=>'method_not_allowed'],'POST');return; }
            if ($_GET!==[] || strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/json') throw new \RuntimeException('approval_invalid_request');
            $raw=file_get_contents('php://input',false,null,0,2049);
            if ($raw===false || strlen($raw)>2048) throw new \RuntimeException('approval_invalid_request');
            $input=json_decode($raw,true);
            if (!is_array($input) || array_diff(array_keys($input),['submission_id','csrf'])!==[] || !is_string($input['submission_id']??null)) throw new \RuntimeException('approval_invalid_request');
            if (!is_string($input['csrf']??null) || !$identity->csrf()->validAuthenticated($input['csrf'],$session->session()->tokenDigest())) throw new \RuntimeException('approval_denied');
            require_once __DIR__.'/../../../api/_lib/db.php';
            require_once __DIR__.'/../private-bootstrap.php';
            $service=new ProfilePhotoApprovalService(\mxmed_pdo(),\mxmed_private_media_storage(),\mxmed_public_media_storage());
            self::respond(200,$service->approve($context,$input['submission_id']));
        } catch (\Throwable $e) {
            $status=match($e->getMessage()) {'approval_denied'=>403,'approval_invalid_request'=>400,'approval_not_found'=>404,'approval_conflict'=>409,default=>503};
            if ($status===503) error_log('media_profile_approval_unavailable');
            self::respond($status,['ok'=>false,'error'=>match($status){403=>'forbidden',400=>'invalid_request',404=>'not_found',409=>'already_processed_or_ineligible',default=>'unavailable'}]);
        }
    }
    private static function respond(int $status,array $body,?string $allow=null): void
    {
        http_response_code($status);if ($allow!==null) header('Allow: '.$allow);
        echo json_encode($body,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    }
}
