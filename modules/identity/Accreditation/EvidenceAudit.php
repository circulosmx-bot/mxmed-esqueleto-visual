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
        $ids=new RandomAuditUuidProvider();
        $staff=$staffRead||in_array($operation,['REVIEW_CHALLENGE','APPROVED','REJECTED','SUSPENDED','REVOKED','EXPIRED'],true);
        $role='authenticated_account';
        if ($staff) {
            $q=$this->pdo->prepare("SELECT s.governance_class FROM internal_staff s JOIN internal_operator_grants g ON g.account_id=s.account_id
                WHERE s.account_id=? AND s.status='ACTIVE' AND g.capability='physician_accreditation_review' AND g.status='ACTIVE' AND g.revoked_at IS NULL FOR UPDATE");
            $q->execute([$actor->accountId()]);$role=$q->fetchColumn();
            if (!is_string($role)) throw new \RuntimeException('c4_audit_staff_authority_required');
        }
        $context=TrustedAuditContext::fromServer($actor->accountId(),'account',$role,'physician_evidence',
            $ids->generateCanonicalUuid(),$ids->generateCanonicalUuid(),(string)$actor->session()->sessionId(),null,null,'AUTH','physician-evidence/'.$operation);
        $reason=$staff?'ADMIN_DECISION':'USER_REQUEST';
        $event=$this->writer->append(new CanonicalAuditEventInput('PHYSICIAN_EVIDENCE_CHANGED','SUCCESS',$reason,
            'account',$actor->accountId(),'physician_evidence',$target,
            ['operation'=>$operation,'version'=>$version,'previous_status'=>$before,'new_status'=>$after,'case_id'=>$caseId,
                'review_reason'=>$reviewReason,'capability'=>$staff?'physician_accreditation_review':null]),$context);
        return (string)$event->value('event_id');
    }
}
