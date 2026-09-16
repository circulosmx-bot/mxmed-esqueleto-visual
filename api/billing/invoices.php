<?php
declare(strict_types=1);

require_once __DIR__.'/../_lib/db.php';
require_once __DIR__.'/../../modules/media/services/GallerySessionScope.php';
require_once __DIR__.'/../../modules/billing/services/InvoiceArchiveService.php';

use Billing\Repositories\InvoiceRepository;
use Billing\Services\HistoricalCfdiParser;
use Billing\Services\InvoiceArchiveService;
use Billing\Services\InvoiceDocumentStorage;
use Media\Services\GallerySessionScope;

header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('Cross-Origin-Resource-Policy: same-origin');
function invoiceReply(int $code,array $body): never {
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code($code);
    echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function invoiceUpload(string $field,bool $required): ?string {
    $file=$_FILES[$field]??null;
    if ($file===null && !$required) return null;
    if (!is_array($file) || is_array($file['error']??null) || is_array($file['tmp_name']??null)) throw new \InvalidArgumentException('invalid_invoice_upload');
    if (($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE && !$required) return null;
    if (($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_string($file['tmp_name']??null)
        || !is_uploaded_file($file['tmp_name'])) throw new \InvalidArgumentException('invalid_invoice_upload');
    $max=$field==='xml_file'?HistoricalCfdiParser::MAX_XML_BYTES:HistoricalCfdiParser::MAX_PDF_BYTES;
    if (!is_int($file['size']??null) || $file['size']<1 || $file['size']>$max) throw new \InvalidArgumentException('invoice_document_size_invalid');
    return $file['tmp_name'];
}

session_start(['use_strict_mode'=>true]);
$scope=GallerySessionScope::resolve($_SESSION);
if ($scope===null) invoiceReply(401,['ok'=>false,'error'=>'unauthorized']);
$method=$_SERVER['REQUEST_METHOD']??'GET';
if (!in_array($method,['GET','POST'],true)) invoiceReply(405,['ok'=>false,'error'=>'method_not_allowed']);
$owner=hash('sha256',$scope['user_id'].'|'.$scope['doctor_id']);
if (($_SESSION['billing_invoices_owner']??null)!==$owner) {
    $_SESSION['billing_invoices_owner']=$owner;
    $_SESSION['billing_invoices_csrf']=bin2hex(random_bytes(32));
}
$csrf=(string)$_SESSION['billing_invoices_csrf'];
if ($method==='POST' && !hash_equals($csrf,(string)($_SERVER['HTTP_X_BILLING_INVOICES_CSRF']??''))) invoiceReply(403,['ok'=>false,'error'=>'csrf_failed']);
session_write_close();

try {
    $jsonSearch=$method==='POST' && strtolower(trim(explode(';',(string)($_SERVER['CONTENT_TYPE']??''))[0]))==='application/json';
    $action=$method==='GET'?($_GET['action']??'list'):($jsonSearch?'search':($_POST['action']??''));
    if (!is_string($action)) throw new \InvalidArgumentException('invalid_request');
    if ($method==='GET' && $action==='context' && array_keys($_GET)===['action']) invoiceReply(200,['ok'=>true,'data'=>['csrf_token'=>$csrf]]);
    $service=new InvoiceArchiveService(new InvoiceRepository(mxmed_pdo()),new HistoricalCfdiParser(),InvoiceDocumentStorage::runtime());
    if ($method==='GET') {
        if ($action==='list') {
            $filters=$_GET;
            unset($filters['action']);
            if (isset($filters['receiver']) || isset($filters['rfc'])) throw new \InvalidArgumentException('invoice_private_filter_requires_post');
            invoiceReply(200,['ok'=>true,'data'=>['invoices'=>$service->list($scope['doctor_id'],$filters)]]);
        }
        if (!in_array($action,['detail','download'],true) || array_diff(array_keys($_GET),['action','invoice_id','patient_id','kind'])
            || !is_string($_GET['invoice_id']??null) || (isset($_GET['patient_id'])&&!is_string($_GET['patient_id']))) throw new \InvalidArgumentException('invalid_request');
        $patient=isset($_GET['patient_id'])?(string)$_GET['patient_id']:null;
        if ($action==='detail') {
            if (isset($_GET['kind'])) throw new \InvalidArgumentException('invalid_request');
            invoiceReply(200,['ok'=>true,'data'=>['invoice'=>$service->detail($scope['doctor_id'],(string)$_GET['invoice_id'],$patient)]]);
        }
        if (!is_string($_GET['kind']??null)) throw new \InvalidArgumentException('invalid_request');
        $document=$service->document($scope['doctor_id'],(string)$_GET['invoice_id'],$patient,(string)$_GET['kind']);
        header('Content-Type: '.$document['type']);
        header('Content-Disposition: attachment; filename="'.$document['filename'].'"');
        header('Content-Length: '.strlen($document['bytes']));
        header('Content-Security-Policy: sandbox');
        echo $document['bytes'];
        exit;
    }
    if ($jsonSearch) {
        if ($_GET!==[] || $_FILES!==[] || $_POST!==[]) throw new \InvalidArgumentException('invalid_request');
        $raw=file_get_contents('php://input',false,null,0,4097);
        if (!is_string($raw) || strlen($raw)>4096) throw new \InvalidArgumentException('invalid_request');
        $body=json_decode($raw,true,16,JSON_THROW_ON_ERROR);
        if (!is_array($body) || count($body)!==2 || array_diff(array_keys($body),['action','filters']) || ($body['action']??null)!=='search'
            || !is_array($body['filters']) || ($body['filters']!==[] && array_is_list($body['filters']))) throw new \InvalidArgumentException('invalid_request');
        invoiceReply(200,['ok'=>true,'data'=>['invoices'=>$service->list($scope['doctor_id'],$body['filters'])]]);
    }
    if ($_GET!==[] || !in_array($action,['preview','import'],true)
        || array_diff(array_keys($_POST),['action','patient_id','billing_profile_id','confirmed_xml_sha256','confirmed_pdf_sha256'])
        || array_diff(array_keys($_FILES),['xml_file','pdf_file'])
        || !is_string($_POST['patient_id']??null)
        || (isset($_POST['billing_profile_id'])&&!is_string($_POST['billing_profile_id']))) throw new \InvalidArgumentException('invalid_request');
    $patient=(string)$_POST['patient_id'];
    $profile=trim((string)($_POST['billing_profile_id']??''));
    $profile=$profile===''?null:$profile;
    $xml=invoiceUpload('xml_file',true);
    $pdf=invoiceUpload('pdf_file',false);
    if ($action==='preview') {
        if (isset($_POST['confirmed_xml_sha256']) || isset($_POST['confirmed_pdf_sha256'])) throw new \InvalidArgumentException('invalid_request');
        invoiceReply(200,['ok'=>true,'data'=>['preview'=>$service->preview($scope['doctor_id'],$patient,$profile,$xml,$pdf)]]);
    }
    if (!is_string($_POST['confirmed_xml_sha256']??null)
        || (isset($_POST['confirmed_pdf_sha256'])&&!is_string($_POST['confirmed_pdf_sha256']))) throw new \InvalidArgumentException('invalid_request');
    $confirmedPdf=trim((string)($_POST['confirmed_pdf_sha256']??''));
    invoiceReply(201,['ok'=>true,'data'=>['invoice'=>$service->import($scope['doctor_id'],$patient,$profile,$xml,$pdf,(string)$_POST['confirmed_xml_sha256'],$confirmedPdf===''?null:$confirmedPdf)]]);
} catch (\DomainException $error) {
    $code=$error->getMessage();
    invoiceReply($code==='invoice_already_imported'?409:404,['ok'=>false,'error'=>$code]);
} catch (\InvalidArgumentException|\JsonException $error) {
    invoiceReply(422,['ok'=>false,'error'=>$error->getMessage()]);
} catch (\Throwable $error) {
    invoiceReply(503,['ok'=>false,'error'=>'invoice_archive_unavailable']);
}
