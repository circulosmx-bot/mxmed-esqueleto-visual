<?php
declare(strict_types=1);
namespace Billing\Repositories;

use PDO;

final class InvoiceRepository
{
    public function __construct(private PDO $pdo) {}

    public function begin(): void { $this->pdo->beginTransaction(); }
    public function commit(): void { $this->pdo->commit(); }
    public function rollback(): void { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); }

    public function requirePatient(string $doctorId, string $patientId, bool $lock = false): void
    {
        $sql = "SELECT l.link_id FROM patients_doctor_links l JOIN patients_patients p ON p.patient_id=l.patient_id WHERE l.doctor_id=:doctor AND l.patient_id=:patient AND l.status='active' AND p.status='active' LIMIT 1";
        if ($lock) $sql .= ' FOR UPDATE';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['doctor'=>$doctorId,'patient'=>$patientId]);
        if (!$stmt->fetchColumn()) throw new \DomainException('patient_scope_denied');
    }

    public function requireProfile(string $doctorId, string $patientId, string $profileId): void
    {
        $stmt = $this->pdo->prepare('SELECT billing_profile_id FROM billing_patient_profiles WHERE doctor_id=:doctor AND patient_id=:patient AND billing_profile_id=:profile AND archived_at IS NULL LIMIT 1');
        $stmt->execute(['doctor'=>$doctorId,'patient'=>$patientId,'profile'=>$profileId]);
        if (!$stmt->fetchColumn()) throw new \DomainException('billing_profile_scope_denied');
    }

    public function duplicate(string $doctorId, string $uuid, string $xmlHash): bool
    {
        $stmt = $this->pdo->prepare('SELECT invoice_id FROM billing_invoices WHERE doctor_id=:doctor AND (cfdi_uuid=:uuid OR xml_sha256=:hash) LIMIT 1');
        $stmt->execute(['doctor'=>$doctorId,'uuid'=>$uuid,'hash'=>$xmlHash]);
        return (bool)$stmt->fetchColumn();
    }

    public function insert(array $row): void
    {
        $columns = array_keys($row);
        $sql = 'INSERT INTO billing_invoices ('.implode(',', $columns).') VALUES (:'.implode(',:', $columns).')';
        $this->pdo->prepare($sql)->execute($row);
    }

    public function get(string $doctorId, string $invoiceId, ?string $patientId = null, bool $includeKeys = false): array
    {
        $columns = 'i.invoice_id,i.patient_id,i.billing_profile_id,i.source_type,i.cfdi_version,i.cfdi_uuid,i.series,i.folio,i.issued_at,i.currency_code,i.subtotal,i.discount,i.tax_total,i.total,i.status,i.receiver_legal_name_snapshot,i.receiver_rfc_snapshot,i.receiver_fiscal_zip_snapshot,i.receiver_regime_code_snapshot,i.cfdi_use_code_snapshot,i.xml_sha256,i.pdf_sha256,i.xml_bytes,i.pdf_bytes,i.created_at,p.display_name AS patient_name';
        if ($includeKeys) $columns .= ',i.xml_storage_key,i.pdf_storage_key';
        $sql = "SELECT $columns FROM billing_invoices i JOIN patients_doctor_links l ON l.doctor_id=i.doctor_id AND l.patient_id=i.patient_id AND l.status='active' JOIN patients_patients p ON p.patient_id=i.patient_id AND p.status='active' WHERE i.doctor_id=:doctor AND i.invoice_id=:invoice AND i.archived_at IS NULL";
        $params=['doctor'=>$doctorId,'invoice'=>$invoiceId];
        if ($patientId !== null) { $sql.=' AND i.patient_id=:patient'; $params['patient']=$patientId; }
        $stmt=$this->pdo->prepare($sql.' LIMIT 1');
        $stmt->execute($params);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new \DomainException('invoice_not_found');
        return $row;
    }

    public function list(string $doctorId, array $filters): array
    {
        $sql = "SELECT i.invoice_id,i.patient_id,i.cfdi_uuid,i.series,i.folio,i.issued_at,i.currency_code,i.total,i.status,i.receiver_legal_name_snapshot,i.receiver_rfc_snapshot,i.pdf_storage_key IS NOT NULL AS has_pdf,p.display_name AS patient_name FROM billing_invoices i JOIN patients_doctor_links l ON l.doctor_id=i.doctor_id AND l.patient_id=i.patient_id AND l.status='active' JOIN patients_patients p ON p.patient_id=i.patient_id AND p.status='active' WHERE i.doctor_id=:doctor AND i.archived_at IS NULL";
        $params=['doctor'=>$doctorId];
        foreach (['patient_id'=>'i.patient_id','status'=>'i.status','date_from'=>'i.issued_at','date_to'=>'i.issued_at'] as $key=>$column) {
            if (!isset($filters[$key])) continue;
            $op=$key==='date_from'?'>=':($key==='date_to'?'<=':'=');
            $sql.=" AND $column $op :$key";
            $params[$key]=$key==='date_to'?$filters[$key].' 23:59:59':$filters[$key];
        }
        foreach (['receiver'=>'i.receiver_legal_name_snapshot','rfc'=>'i.receiver_rfc_snapshot','uuid'=>'i.cfdi_uuid'] as $key=>$column) {
            if (!isset($filters[$key])) continue;
            $sql.=" AND $column LIKE :$key";
            $params[$key]='%'.str_replace(['%','_','\\'],['\\%','\\_','\\\\'],$filters[$key]).'%';
        }
        $stmt=$this->pdo->prepare($sql.' ORDER BY i.issued_at DESC,i.invoice_id DESC LIMIT 100');
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function existsId(string $invoiceId): bool
    {
        $stmt=$this->pdo->prepare('SELECT invoice_id FROM billing_invoices WHERE invoice_id=:id LIMIT 1');
        $stmt->execute(['id'=>$invoiceId]);
        return (bool)$stmt->fetchColumn();
    }
}
