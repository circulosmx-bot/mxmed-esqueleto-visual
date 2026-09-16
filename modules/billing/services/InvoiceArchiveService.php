<?php
declare(strict_types=1);
namespace Billing\Services;

use Billing\Repositories\InvoiceRepository;
use PDOException;

require_once __DIR__.'/HistoricalCfdiParser.php';
require_once __DIR__.'/InvoiceDocumentStorage.php';
require_once __DIR__.'/../repositories/InvoiceRepository.php';

final class InvoiceArchiveService
{
    public function __construct(private InvoiceRepository $repo, private HistoricalCfdiParser $parser, private InvoiceDocumentStorage $storage) {}

    public function preview(string $doctorId, string $patientId, ?string $profileId, string $xmlPath, ?string $pdfPath): array
    {
        self::identifier($patientId);
        if ($profileId !== null) self::identifier($profileId);
        $this->repo->requirePatient($doctorId, $patientId);
        if ($profileId !== null) $this->repo->requireProfile($doctorId, $patientId, $profileId);
        $parsed=$this->parser->parse($xmlPath);
        $pdf=$this->parser->validatePdf($pdfPath);
        if ($this->repo->duplicate($doctorId, $parsed['cfdi_uuid'], $parsed['xml_sha256'])) {
            throw new \DomainException('invoice_already_imported');
        }
        return ['patient_id'=>$patientId,'billing_profile_id'=>$profileId,'source_type'=>'HISTORICAL_IMPORT','status'=>'UNKNOWN',
            'cfdi_version'=>$parsed['cfdi_version'],'cfdi_uuid'=>$parsed['cfdi_uuid'],'series'=>$parsed['series'],
            'folio'=>$parsed['folio'],'issued_at'=>$parsed['issued_at'],'currency_code'=>$parsed['currency_code'],
            'subtotal'=>$parsed['subtotal'],'discount'=>$parsed['discount'],'tax_total'=>$parsed['tax_total'],
            'total'=>$parsed['total'],'receiver_legal_name_snapshot'=>$parsed['receiver_legal_name_snapshot'],
            'receiver_rfc_snapshot'=>$parsed['receiver_rfc_snapshot'],'receiver_fiscal_zip_snapshot'=>$parsed['receiver_fiscal_zip_snapshot'],
            'receiver_regime_code_snapshot'=>$parsed['receiver_regime_code_snapshot'],'cfdi_use_code_snapshot'=>$parsed['cfdi_use_code_snapshot'],
            'xml_sha256'=>$parsed['xml_sha256'],'pdf_sha256'=>$pdf['pdf_sha256'] ?? null];
    }

    public function import(string $doctorId, string $patientId, ?string $profileId, string $xmlPath, ?string $pdfPath, string $confirmedXmlHash, ?string $confirmedPdfHash): array
    {
        $preview=$this->preview($doctorId,$patientId,$profileId,$xmlPath,$pdfPath);
        if (!preg_match('/^[a-f0-9]{64}$/D',$confirmedXmlHash) || !hash_equals($preview['xml_sha256'],$confirmedXmlHash)
            || $preview['pdf_sha256'] !== $confirmedPdfHash) throw new \InvalidArgumentException('invoice_preview_changed');
        $parsed=$this->parser->parse($xmlPath);
        $pdf=$this->parser->validatePdf($pdfPath);
        $invoiceId=self::uuid();
        $xmlKey=InvoiceDocumentStorage::key($doctorId,$invoiceId,'xml');
        $pdfKey=$pdfPath === null ? null : InvoiceDocumentStorage::key($doctorId,$invoiceId,'pdf');
        $stored=[];
        $committed=false;
        try {
            $this->repo->begin();
            $this->repo->requirePatient($doctorId,$patientId,true);
            if ($profileId !== null) $this->repo->requireProfile($doctorId,$patientId,$profileId);
            if ($this->repo->duplicate($doctorId,$parsed['cfdi_uuid'],$parsed['xml_sha256'])) throw new \DomainException('invoice_already_imported');
            $this->storage->storeImmutable($xmlKey,$xmlPath,$parsed['xml_sha256']);
            $stored[]=$xmlKey;
            if ($pdfKey !== null && $pdfPath !== null) { $this->storage->storeImmutable($pdfKey,$pdfPath,$pdf['pdf_sha256']); $stored[]=$pdfKey; }
            $this->repo->insert(array_merge($parsed,[
                'invoice_id'=>$invoiceId,'doctor_id'=>$doctorId,'patient_id'=>$patientId,'billing_profile_id'=>$profileId,
                'source_type'=>'HISTORICAL_IMPORT','status'=>'UNKNOWN','xml_storage_key'=>$xmlKey,'pdf_storage_key'=>$pdfKey,
                'pdf_sha256'=>$pdf['pdf_sha256'] ?? null,'pdf_bytes'=>$pdf['pdf_bytes'] ?? null,
            ]));
            $this->repo->commit();
            $committed=true;
        } catch (\Throwable $error) {
            $this->repo->rollback();
            if (!$committed && !$this->repo->existsId($invoiceId)) {
                foreach (array_reverse($stored) as $key) $this->storage->delete($key);
            }
            if ($error instanceof PDOException && (string)$error->getCode()==='23000') throw new \DomainException('invoice_already_imported');
            throw $error;
        }
        return $this->repo->get($doctorId,$invoiceId,$patientId);
    }

    public function list(string $doctorId, array $filters): array
    {
        $allowed=['patient_id','receiver','rfc','uuid','date_from','date_to','status'];
        if (array_diff(array_keys($filters),$allowed)) throw new \InvalidArgumentException('invalid_invoice_filter');
        $clean=[];
        foreach ($filters as $key=>$value) {
            if (!is_string($value) || mb_strlen($value)>160) throw new \InvalidArgumentException('invalid_invoice_filter');
            $value=trim($value);
            if ($value==='') continue;
            if ($key==='patient_id') self::identifier($value);
            elseif ($key==='status' && !in_array($value,['UNKNOWN','VERIFIED_VALID','VERIFIED_CANCELED'],true)) throw new \InvalidArgumentException('invalid_invoice_filter');
            elseif (str_starts_with($key,'date_') && (!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value) || !checkdate((int)substr($value,5,2),(int)substr($value,8,2),(int)substr($value,0,4)))) throw new \InvalidArgumentException('invalid_invoice_filter');
            elseif (!in_array($key,['patient_id','status','date_from','date_to'],true) && (mb_strlen($value)<2 || preg_match('/[\x00-\x1F\x7F]/',$value))) throw new \InvalidArgumentException('invalid_invoice_filter');
            $clean[$key]=$value;
        }
        if (isset($clean['patient_id'])) $this->repo->requirePatient($doctorId,$clean['patient_id']);
        if (isset($clean['date_from'],$clean['date_to']) && $clean['date_from']>$clean['date_to']) throw new \InvalidArgumentException('invalid_invoice_filter');
        return $this->repo->list($doctorId,$clean);
    }

    public function detail(string $doctorId, string $invoiceId, ?string $patientId=null): array
    {
        self::identifier($invoiceId);
        if ($patientId!==null) self::identifier($patientId);
        return $this->repo->get($doctorId,$invoiceId,$patientId);
    }

    public function document(string $doctorId, string $invoiceId, ?string $patientId, string $kind): array
    {
        if (!in_array($kind,['xml','pdf'],true)) throw new \InvalidArgumentException('invalid_document_type');
        $row=$this->repo->get($doctorId,$invoiceId,$patientId,true);
        $key=$row[$kind.'_storage_key'];
        if (!is_string($key) || $key==='') throw new \DomainException('invoice_document_not_found');
        $expectedKey=InvoiceDocumentStorage::key($doctorId,$invoiceId,$kind);
        if (!hash_equals($expectedKey,$key)) throw new \RuntimeException('invoice_private_integrity_failed');
        $bytes=$this->storage->readVerified($key,(string)$row[$kind.'_sha256'],(int)$row[$kind.'_bytes'], $kind==='xml'?HistoricalCfdiParser::MAX_XML_BYTES:HistoricalCfdiParser::MAX_PDF_BYTES);
        return ['bytes'=>$bytes,'type'=>$kind==='xml'?'application/xml':'application/pdf','filename'=>'cfdi-'.substr($row['cfdi_uuid'],0,8).'.'.$kind];
    }

    private static function identifier(string $value): void
    {
        if (!preg_match('/^[A-Za-z0-9_.:-]{1,64}$/D',$value)) throw new \InvalidArgumentException('invalid_identifier');
    }

    private static function uuid(): string
    {
        $bytes=random_bytes(16);
        $bytes[6]=chr((ord($bytes[6]) & 0x0f)|0x40);
        $bytes[8]=chr((ord($bytes[8]) & 0x3f)|0x80);
        $hex=bin2hex($bytes);
        return substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20);
    }
}
