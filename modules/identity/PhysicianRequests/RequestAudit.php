<?php
declare(strict_types=1);
namespace Identity\PhysicianRequests;
use Identity\Contracts\AuthenticatedAccessContext;
use Platform\Contracts\{CanonicalAuditEventInput,TrustedAuditContext};
use Platform\Repositories\JoinedPdoCanonicalAuditTransactionAdapter;
use Platform\Services\{CanonicalAuditWriter,CanonicalAuditPolicyRegistry,CanonicalAuditMetadataSanitizer,TrustedAuditContextValidator,RandomAuditUuidProvider,SystemAuditUtcClock,HmacSha256AuditIpHasher,EnvironmentAuditSecretProvider,CoarseAuditUserAgentSummarizer,CanonicalAuditSealer,CanonicalAuditSerializer,AuditV1PhysicalMapper};
use PDO;
/** Same-PDO protected canonical event. No free text or document data enters metadata. */
final class RequestAudit
{
    private CanonicalAuditWriter $writer;
    public function __construct(private PDO $pdo)
    {
        $this->writer=new CanonicalAuditWriter(CanonicalAuditPolicyRegistry::canonical(),new CanonicalAuditMetadataSanitizer(),new TrustedAuditContextValidator(),new RandomAuditUuidProvider(),new SystemAuditUtcClock(),new HmacSha256AuditIpHasher(new EnvironmentAuditSecretProvider()),new CoarseAuditUserAgentSummarizer(),new JoinedPdoCanonicalAuditTransactionAdapter($pdo),new CanonicalAuditSealer(new CanonicalAuditSerializer()),new AuditV1PhysicalMapper());
    }
    /** Security outcome only; excluded from all successful business timelines. */
    public function denied(AuthenticatedAccessContext $actor):void
    {
        if(!$this->pdo->inTransaction())throw new \RuntimeException('physician_request_transaction_required');
        $ids=new RandomAuditUuidProvider();
        $context=TrustedAuditContext::fromServer($actor->accountId(),'account','authenticated_account','physician_profile_request',$ids->generateCanonicalUuid(),$ids->generateCanonicalUuid(),(string)$actor->session()->sessionId(),null,null,'ADMIN','physician-profile-request/access-denied');
        $this->writer->append(new CanonicalAuditEventInput('SENSITIVE_ADMIN_ACTION','DENIED','POLICY_DENIED','account',$actor->accountId(),'protected_workflow','physician-profile-requests',[]),$context);
    }
    public function append(AuthenticatedAccessContext $actor,array $origin,string $operation,int $version,string $before,string $after,?string $case=null,?string $profile=null,?string $capability=null):string
    {
        if(!$this->pdo->inTransaction())throw new \RuntimeException('physician_request_transaction_required');
        if(!in_array($operation,['EVIDENCE_LINKED','EVIDENCE_READ','REVIEW_CHALLENGE','REQUEST_APPROVED','REQUEST_REJECTED','INFORMATION_REQUESTED','INFORMATION_RESPONDED','PROFILE_MATERIALIZED','MEMBERSHIP_PREPARED','OWNERSHIP_ACTIVATED'],true))throw new \InvalidArgumentException('physician_request_audit_invalid');
        $role=$capability===null?'authenticated_account':(new RequestAuthority($this->pdo))->assert($actor,$capability,$origin['account_id'],true)['governance_class'];
        $ids=new RandomAuditUuidProvider();
        $context=TrustedAuditContext::fromServer($actor->accountId(),'account',$role,'physician_profile_request',$ids->generateCanonicalUuid(),$ids->generateCanonicalUuid(),(string)$actor->session()->sessionId(),null,null,'AUTH','physician_profile_request/'.$operation);
        $event=$this->writer->append(new CanonicalAuditEventInput('PHYSICIAN_PROFILE_REQUEST_CHANGED','SUCCESS',$capability===null?'USER_REQUEST':'ADMIN_DECISION','account',$actor->accountId(),'physician_profile_request',$origin['origin_id'],['operation'=>$operation,'version'=>$version,'previous_status'=>$before,'new_status'=>$after,'case_id'=>$case,'review_reason'=>in_array($operation,['REQUEST_REJECTED','INFORMATION_REQUESTED'],true)?'REASON_IN_PROTECTED_REQUEST':null,'capability'=>$capability,'profile_doctor_id'=>$profile,'application_id'=>$origin['origin_id'],'request_type'=>$origin['initial_origin']]),$context);
        return (string)$event->value('event_id');
    }
}
