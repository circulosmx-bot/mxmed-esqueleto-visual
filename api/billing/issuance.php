<?php
declare(strict_types=1);

require_once __DIR__.'/../_lib/db.php';
require_once __DIR__.'/../../modules/media/services/GallerySessionScope.php';
require_once __DIR__.'/../../modules/billing/services/IssuerProfileService.php';
require_once __DIR__.'/../../modules/billing/services/CsdCredentialService.php';
require_once __DIR__.'/../../modules/billing/services/InvoiceDraftService.php';
require_once __DIR__.'/../../modules/billing/services/CfdiPreStampValidator.php';
require_once __DIR__.'/../../modules/billing/services/UnconfiguredPacAdapter.php';
require_once __DIR__.'/../../modules/billing/services/BillingIssuanceAudit.php';

use Billing\Services\SatCfdiCatalog;
use Billing\Services\SatIssuanceCatalog;
use Billing\Services\IssuerProfileService;
use Billing\Services\InvoiceDraftService;
use Billing\Services\CfdiPreStampValidator;
use Billing\Services\CsdCredentialService;
use Billing\Services\EncryptedLocalCsdStore;
use Media\Services\GallerySessionScope;

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
function issuanceReply(int $status,array $body):never {http_response_code($status);echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
session_start(['use_strict_mode'=>true]);
$scope=GallerySessionScope::resolve($_SESSION);
if($scope===null)issuanceReply(401,['ok'=>false,'error'=>'unauthorized']);
$method=$_SERVER['REQUEST_METHOD']??'GET';
if(!in_array($method,['GET','POST','PUT','DELETE'],true))issuanceReply(405,['ok'=>false,'error'=>'method_not_allowed']);
$owner=hash('sha256',$scope['user_id'].'|'.$scope['doctor_id']);
if(($_SESSION['billing_issuance_owner']??null)!==$owner){$_SESSION['billing_issuance_owner']=$owner;$_SESSION['billing_issuance_csrf']=bin2hex(random_bytes(32));}
$csrf=$_SESSION['billing_issuance_csrf'];
if($method!=='GET'&&!hash_equals($csrf,(string)($_SERVER['HTTP_X_BILLING_ISSUANCE_CSRF']??'')))issuanceReply(403,['ok'=>false,'error'=>'csrf_failed']);
session_write_close();

try {
    $pdo=mxmed_pdo();$doctorId=$scope['doctor_id'];$fiscal=new SatCfdiCatalog();$codes=new SatIssuanceCatalog();
    $issuers=new IssuerProfileService($pdo,$fiscal);$drafts=new InvoiceDraftService($pdo,$fiscal,$codes);
    $action=(string)($_GET['action']??'');
    if($method==='GET'){
        if(array_diff(array_keys($_GET),['action','issuer_id','draft_id']))issuanceReply(400,['ok'=>false,'error'=>'invalid_request']);
        if($action==='bootstrap')issuanceReply(200,['ok'=>true,'data'=>['csrf_token'=>$csrf,'issuers'=>$issuers->list($doctorId),
            'drafts'=>$drafts->list($doctorId),'sat'=>$codes->publicData(),'fiscal'=>$fiscal->publicData(),
            'pac_provider_selected'=>false,'certification_enabled'=>false,'csd_registration_available'=>(bool)getenv('MXMED_CSD_ENCRYPTION_KEY')]]);
        if($action==='issuer_csd' && is_string($_GET['issuer_id']??null)){
            $csds=(new CsdCredentialService($pdo,$issuers))->list($doctorId,$_GET['issuer_id']);
            issuanceReply(200,['ok'=>true,'data'=>['credentials'=>$csds,'csrf_token'=>$csrf]]);
        }
        if($action==='draft' && is_string($_GET['draft_id']??null))issuanceReply(200,['ok'=>true,'data'=>['draft'=>$drafts->get($doctorId,$_GET['draft_id']),'csrf_token'=>$csrf]]);
        if($action==='preview' && is_string($_GET['draft_id']??null)){
            $draft=$drafts->get($doctorId,$_GET['draft_id']);
            $inspection=(new CfdiPreStampValidator($pdo,$fiscal,$codes))->inspect($doctorId,$draft);
            // Preview never returns private CSD material or a potentially stampable signed XML.
            $issuer=$inspection['issuer'];$receiver=$inspection['receiver'];unset($inspection['issuer'],$inspection['receiver']);
            issuanceReply(200,['ok'=>true,'data'=>['draft'=>$draft,'issuer'=>$issuer===false?null:array_intersect_key($issuer,array_flip(['alias','issuer_legal_name','rfc','fiscal_regime_code','expedition_postal_code'])),
                'receiver'=>$receiver===false?null:array_intersect_key($receiver,array_flip(['alias','receiver_legal_name','rfc','fiscal_zip_code','fiscal_regime_code'])),
                'validation'=>$inspection,'csrf_token'=>$csrf]]);
        }
        issuanceReply(400,['ok'=>false,'error'=>'invalid_request']);
    }
    if($_GET!==[] || $method==='DELETE' && (($_SERVER['CONTENT_TYPE']??'')===''))issuanceReply(400,['ok'=>false,'error'=>'invalid_request']);
    $contentType=strtolower(trim(explode(';',(string)($_SERVER['CONTENT_TYPE']??''))[0]));
    if($contentType==='multipart/form-data'){
        if($method!=='POST' || ($_POST['action']??'')!=='register_csd' || !is_string($_POST['issuer_id']??null))issuanceReply(400,['ok'=>false,'error'=>'invalid_request']);
        $bytes=[];
        foreach(['certificate'=>'cer','private_key'=>'key'] as $field=>$kind){
            $file=$_FILES[$field]??null;
            if(!is_array($file)||($file['error']??null)!==UPLOAD_ERR_OK||($file['size']??0)>32768||!is_uploaded_file($file['tmp_name']))issuanceReply(422,['ok'=>false,'error'=>'invalid_csd_files']);
            $bytes[$kind]=file_get_contents($file['tmp_name']);
        }
        if(!is_string($_POST['password']??null))issuanceReply(422,['ok'=>false,'error'=>'invalid_csd_password']);
        $credential=(new CsdCredentialService($pdo,$issuers))->register($doctorId,$_POST['issuer_id'],$bytes['cer'],$bytes['key'],$_POST['password']);
        issuanceReply(201,['ok'=>true,'data'=>['credential'=>$credential]]);
    }
    if($contentType!=='application/json')issuanceReply(415,['ok'=>false,'error'=>'json_required']);
    $raw=file_get_contents('php://input',false,null,0,131073);
    if($raw===false||strlen($raw)>131072)issuanceReply(413,['ok'=>false,'error'=>'payload_too_large']);
    $body=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
    if(!is_array($body)||array_is_list($body)||!is_string($body['action']??null))issuanceReply(400,['ok'=>false,'error'=>'invalid_request']);
    $action=$body['action'];
    if($action==='create_issuer' && $method==='POST' && is_array($body['profile']??null))issuanceReply(201,['ok'=>true,'data'=>['issuer'=>$issuers->create($doctorId,$body['profile'])]]);
    if($action==='update_issuer' && $method==='PUT' && is_string($body['issuer_id']??null) && is_array($body['profile']??null))issuanceReply(200,['ok'=>true,'data'=>['issuer'=>$issuers->update($doctorId,$body['issuer_id'],$body['profile'])]]);
    if($action==='default_issuer' && $method==='POST' && is_string($body['issuer_id']??null))issuanceReply(200,['ok'=>true,'data'=>['issuer'=>$issuers->makeDefault($doctorId,$body['issuer_id'])]]);
    if($action==='archive_issuer' && $method==='DELETE' && is_string($body['issuer_id']??null)){$issuers->archive($doctorId,$body['issuer_id']);issuanceReply(200,['ok'=>true,'data'=>['archived'=>true]]);}
    if($action==='create_draft' && $method==='POST' && is_array($body['draft']??null))issuanceReply(201,['ok'=>true,'data'=>['draft'=>$drafts->save($doctorId,$body['draft'])]]);
    if($action==='update_draft' && $method==='PUT' && is_string($body['draft_id']??null) && is_array($body['draft']??null))issuanceReply(200,['ok'=>true,'data'=>['draft'=>$drafts->save($doctorId,$body['draft'],$body['draft_id'])]]);
    if($action==='certify' && $method==='POST'){
        (new \Billing\Services\BillingIssuanceAudit($pdo))->record($doctorId,'CERTIFICATION_BLOCKED','PAC_UNCONFIGURED');
        issuanceReply(409,['ok'=>false,'error'=>'pac_provider_selection_required']);
    }
    issuanceReply(400,['ok'=>false,'error'=>'invalid_request']);
} catch(\DomainException $e){
    $code=$e->getMessage();$status=($code==='patient_scope_denied')?403:(str_contains($code,'conflict')||str_contains($code,'locked')?409:404);
    issuanceReply($status,['ok'=>false,'error'=>$code]);
} catch(\InvalidArgumentException|\JsonException $e){issuanceReply(422,['ok'=>false,'error'=>$e->getMessage()]);
} catch(\RuntimeException $e){if($e->getMessage()==='csd_encryption_key_required')issuanceReply(503,['ok'=>false,'error'=>'csd_encryption_key_required']);issuanceReply(503,['ok'=>false,'error'=>'billing_issuance_unavailable']);
} catch(\Throwable $e){issuanceReply(503,['ok'=>false,'error'=>'billing_issuance_unavailable']);}
