<?php
declare(strict_types=1);
namespace Identity\Accreditation;

use Identity\Contracts\AuthenticatedAccessContext;
use Platform\Contracts\{CanonicalAuditEventInput,TrustedAuditContext};
use Platform\Repositories\JoinedPdoCanonicalAuditTransactionAdapter;
use Platform\Services\{CanonicalAuditWriter,CanonicalAuditPolicyRegistry,CanonicalAuditMetadataSanitizer,TrustedAuditContextValidator,
    RandomAuditUuidProvider,SystemAuditUtcClock,HmacSha256AuditIpHasher,EnvironmentAuditSecretProvider,
    CoarseAuditUserAgentSummarizer,CanonicalAuditSealer,CanonicalAuditSerializer,AuditV1PhysicalMapper};
use PDO;

/** Canonical joined writer, same connection/transaction. No raw evidence or contacts. */
final class EvidenceAudit
{
    private CanonicalAuditWriter $writer;
    public function usesConnection(PDO $pdo):bool{return $pdo===$this->pdo;}
    public function __construct(private PDO $pdo)
    {
        $this->writer=new CanonicalAuditWriter(CanonicalAuditPolicyRegistry::canonical(),new CanonicalAuditMetadataSanitizer(),
            new TrustedAuditContextValidator(),new RandomAuditUuidProvider(),new SystemAuditUtcClock(),
            new HmacSha256AuditIpHasher(new EnvironmentAuditSecretProvider()),new CoarseAuditUserAgentSummarizer(),
            new JoinedPdoCanonicalAuditTransactionAdapter($pdo),new CanonicalAuditSealer(new CanonicalAuditSerializer()),new AuditV1PhysicalMapper());
    }
    public function append(AuthenticatedAccessContext $actor,string $target,string $operation,int $version,string $before,string $after,
        ?string $caseId=null,?string $reviewReason=null,bool $staffRead=false): string
    {
        if (!$this->pdo->inTransaction()) throw new \RuntimeException('c4_audit_transaction_required');
        if (!in_array($operation,['PHONE_CHALLENGE','PHONE_DELIVERY','PHONE_VERIFIED','PHONE_DENIED','REQUEST_CREATED','FILE_ADDED','FILE_READ',
            'SUBMITTED','REVIEW_CHALLENGE','APPROVED','REJECTED','CONFIRMATION_ISSUED','CONFIRMED','SUSPENDED','REVOKED','EXPIRED'],true))
            throw new \InvalidArgumentException('c4_audit_operation_invalid');
        $staff=$staffRead||in_array($operation,['REVIEW_CHALLENGE','APPROVED','REJECTED','SUSPENDED','REVOKED','EXPIRED'],true);
        return $this->write($actor,$target,'PHYSICIAN_EVIDENCE_CHANGED','physician_evidence',$operation,$version,$before,$after,$caseId,$reviewReason,$staff);
    }
    public function authority(AuthenticatedAccessContext $actor,string $target,string $operation,int $version,string $before,string $after,
        string $caseId,?string $reason=null,bool $staff=false):string
    {
        if (!in_array($operation,['ADMISSION_STAGED','BINDING_STAGED','ADMISSION_APPROVED','BINDING_ACTIVE','CHANGE_CHALLENGE',
            'ADMISSION_REJECTED','ADMISSION_SUSPENDED','ADMISSION_REVOKED','ADMISSION_EXPIRED',
            'BINDING_REJECTED','BINDING_SUSPENDED','BINDING_REVOKED','BINDING_EXPIRED'],true)) throw new \InvalidArgumentException('physician_audit_operation_invalid');
        return $this->write($actor,$target,'PHYSICIAN_AUTHORITY_CHANGED','physician_authority',$operation,$version,$before,$after,$caseId,$reason,$staff);
    }
    /** Unverified enrollment intake, distinguished by target and finite operations.
     * Reuses the canonical evidence lifecycle event; never an approval/authority event. */
    public function enrollment(AuthenticatedAccessContext $actor,string $target,string $operation,int $version,string $before,string $after):string
    {
        if(!in_array($operation,['ORIGIN_BOUND','INTAKE_SAVED','INTAKE_SUBMITTED','MATCH_CANDIDATES_READ'],true))throw new \InvalidArgumentException('enrollment_audit_operation_invalid');
        return $this->write($actor,$target,'PHYSICIAN_EVIDENCE_CHANGED','physician_enrollment',$operation,$version,$before,$after,null,null,false);
    }
    public function resolution(AuthenticatedAccessContext $actor,string $target,string $operation,int $version,string $before,string $after,string $caseId,bool $staff=true):string
    {
        if(!in_array($operation,['RESOLUTION_CHALLENGE','RESOLUTION_DECIDED','RESOLUTION_READ','RESOLUTION_HANDOFF'],true))throw new \InvalidArgumentException('enrollment_resolution_audit_operation_invalid');
        return $this->write($actor,$target,'PHYSICIAN_EVIDENCE_CHANGED','physician_enrollment',$operation,$version,$before,$after,$caseId,null,$staff);
    }
    public function source(AuthenticatedAccessContext $actor,string $target,string $operation,int $version,?string $caseId=null,bool $staff=true):string
    {
        if(!in_array($operation,['CHALLENGE','COLLECTION_OPENED','FILE_ADDED','FILE_READ','AUTHORIZED','VID01_RECORDED','CRD02_RECORDED','COMPLETE','RECOVERY_AUTHORIZED','REVOKED','C4_REFERENCE_LINKED'],true))throw new \InvalidArgumentException('source_audit_operation_invalid');
        return $this->write($actor,$target,'PHYSICIAN_EVIDENCE_CHANGED','professional_source',$operation,$version,'SOURCE_REVIEW',$operation,$caseId,null,$staff,'professional_source_provision');
    }
    private function write(AuthenticatedAccessContext $actor,string $target,string $eventType,string $targetType,string $operation,int $version,
        string $before,string $after,?string $caseId,?string $reviewReason,bool $staff,string $capability='physician_accreditation_review'):string
    {
        if (!$this->pdo->inTransaction()) throw new \RuntimeException('c4_audit_transaction_required');
        $ids=new RandomAuditUuidProvider();
        $role='authenticated_account';
        if ($staff) {
            $q=$this->pdo->prepare("SELECT s.governance_class FROM internal_staff s JOIN internal_operator_grants g ON g.account_id=s.account_id
                WHERE s.account_id=? AND s.status='ACTIVE' AND g.capability=? AND g.status='ACTIVE' AND g.revoked_at IS NULL FOR UPDATE");
            $q->execute([$actor->accountId(),$capability]);$role=$q->fetchColumn();
            if (!is_string($role)) throw new \RuntimeException('c4_audit_staff_authority_required');
        }
        $context=TrustedAuditContext::fromServer($actor->accountId(),'account',$role,$targetType,
            $ids->generateCanonicalUuid(),$ids->generateCanonicalUuid(),(string)$actor->session()->sessionId(),null,null,'AUTH',$targetType.'/'.$operation);
        $reason=$staff?'ADMIN_DECISION':'USER_REQUEST';
        $event=$this->writer->append(new CanonicalAuditEventInput($eventType,'SUCCESS',$reason,
            'account',$actor->accountId(),$targetType,$target,
            ['operation'=>$operation,'version'=>$version,'previous_status'=>$before,'new_status'=>$after,'case_id'=>$caseId,
                'review_reason'=>$reviewReason,'capability'=>$staff?$capability:null]),$context);
        return (string)$event->value('event_id');
    }
}
