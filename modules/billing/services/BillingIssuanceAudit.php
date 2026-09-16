<?php
declare(strict_types=1);
namespace Billing\Services;

use PDO;

/** Metadata-only billing audit. No payload, XML, CSD material or PAC credentials. */
final class BillingIssuanceAudit
{
    public function __construct(private PDO $pdo) {}
    public function record(string $doctorId,string $action,string $result,?string $patientId=null,?string $draftId=null,?string $issuerId=null,?string $profileId=null,?string $invoiceId=null):void
    {
        $actions=['ISSUER_CREATED','ISSUER_UPDATED','ISSUER_DEFAULT_CHANGED','ISSUER_ARCHIVED','CSD_REGISTERED','DRAFT_CREATED','DRAFT_UPDATED','CERTIFICATION_BLOCKED'];
        if(!in_array($action,$actions,true) || !in_array($result,['SUCCESS','PAC_UNCONFIGURED'],true))throw new \InvalidArgumentException('invalid_billing_audit_event');
        $q=$this->pdo->prepare('INSERT INTO billing_invoice_issuance_events (event_id,doctor_id,patient_id,draft_id,invoice_id,issuer_profile_id,billing_profile_id,action,result) VALUES (?,?,?,?,?,?,?,?,?)');
        $b=random_bytes(16);$b[6]=chr((ord($b[6])&15)|64);$b[8]=chr((ord($b[8])&63)|128);$h=bin2hex($b);
        $id=substr($h,0,8).'-'.substr($h,8,4).'-'.substr($h,12,4).'-'.substr($h,16,4).'-'.substr($h,20);
        $q->execute([$id,$doctorId,$patientId,$draftId,$invoiceId,$issuerId,$profileId,$action,$result]);
    }
}
