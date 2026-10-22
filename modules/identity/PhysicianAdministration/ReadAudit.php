<?php
declare(strict_types=1);
namespace Identity\PhysicianAdministration;
use Identity\Contracts\AuthenticatedAccessContext;
use Platform\Contracts\{CanonicalAuditEventInput,TrustedAuditContext};
use Platform\Repositories\JoinedPdoCanonicalAuditTransactionAdapter;
use Platform\Services\{CanonicalAuditWriter,CanonicalAuditPolicyRegistry,CanonicalAuditMetadataSanitizer,TrustedAuditContextValidator,RandomAuditUuidProvider,SystemAuditUtcClock,HmacSha256AuditIpHasher,EnvironmentAuditSecretProvider,CoarseAuditUserAgentSummarizer,CanonicalAuditSealer,CanonicalAuditSerializer,AuditV1PhysicalMapper};
use PDO;
/** Same-PDO protected canonical event. No free text or document data enters metadata. */
final class ReadAudit
{
    private CanonicalAuditWriter $writer;
    public function __construct(private PDO $pdo)
    {
        $this->writer=new CanonicalAuditWriter(CanonicalAuditPolicyRegistry::canonical(),new CanonicalAuditMetadataSanitizer(),new TrustedAuditContextValidator(),new RandomAuditUuidProvider(),new SystemAuditUtcClock(),new HmacSha256AuditIpHasher(new EnvironmentAuditSecretProvider()),new CoarseAuditUserAgentSummarizer(),new JoinedPdoCanonicalAuditTransactionAdapter($pdo),new CanonicalAuditSealer(new CanonicalAuditSerializer()),new AuditV1PhysicalMapper());
    }
    public function denied(AuthenticatedAccessContext $actor):void
    {
        $ids=new RandomAuditUuidProvider();
        $context=TrustedAuditContext::fromServer($actor->accountId(),'account','authenticated_account','physician_administration',$ids->generateCanonicalUuid(),$ids->generateCanonicalUuid(),(string)$actor->session()->sessionId(),null,null,'ADMIN','physician-administration/access-denied');
        $this->writer->append(new CanonicalAuditEventInput('SENSITIVE_ADMIN_ACTION','DENIED','POLICY_DENIED','account',$actor->accountId(),'protected_workflow','physician-administration',[]),$context);
    }
    public function append(AuthenticatedAccessContext $actor,string $role,string $reference,string $mode):void
    {
        $ids=new RandomAuditUuidProvider();
        $context=TrustedAuditContext::fromServer($actor->accountId(),'account',$role,'physician_administration',$ids->generateCanonicalUuid(),$ids->generateCanonicalUuid(),(string)$actor->session()->sessionId(),null,null,'ADMIN','physician-administration/'.$mode);
        $this->writer->append(new CanonicalAuditEventInput('SENSITIVE_ADMIN_ACTION','SUCCESS','ADMIN_DECISION','account',$actor->accountId(),'profile_doctor',$reference,[]),$context);
    }
}
