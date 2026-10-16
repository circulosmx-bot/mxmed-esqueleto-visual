<?php
declare(strict_types=1);
namespace Identity\Adapters;
use Identity\Contracts\AuthenticatedAccessContext;
use Platform\Contracts\{CanonicalAuditEventInput,TrustedAuditContext};
use Platform\Repositories\JoinedPdoCanonicalAuditTransactionAdapter;
use Platform\Services\{CanonicalAuditWriter,CanonicalAuditPolicyRegistry,CanonicalAuditMetadataSanitizer,
    TrustedAuditContextValidator,RandomAuditUuidProvider,SystemAuditUtcClock,HmacSha256AuditIpHasher,
    EnvironmentAuditSecretProvider,CoarseAuditUserAgentSummarizer,CanonicalAuditSealer,CanonicalAuditSerializer,AuditV1PhysicalMapper};
use PDO;

final class CanonicalGovernanceAuditAdapter
{
    private CanonicalAuditWriter $writer;
    public function __construct(private PDO $pdo, ?CanonicalAuditWriter $writer=null)
    {
        $this->writer=$writer??new CanonicalAuditWriter(CanonicalAuditPolicyRegistry::canonical(),new CanonicalAuditMetadataSanitizer(),
            new TrustedAuditContextValidator(),new RandomAuditUuidProvider(),new SystemAuditUtcClock(),
            new HmacSha256AuditIpHasher(new EnvironmentAuditSecretProvider()),new CoarseAuditUserAgentSummarizer(),
            new JoinedPdoCanonicalAuditTransactionAdapter($pdo),new CanonicalAuditSealer(new CanonicalAuditSerializer()),new AuditV1PhysicalMapper());
    }
    public function append(AuthenticatedAccessContext $actor,string $operation,string $target,string $reference,array $after=[]):void
    {
        if(!$this->pdo->inTransaction())throw new \RuntimeException('governance_audit_transaction_required');
        $staff=$this->pdo->prepare('SELECT governance_class,status FROM internal_staff WHERE account_id=? FOR UPDATE');$staff->execute([$actor->accountId()]);$state=$staff->fetch(PDO::FETCH_ASSOC);$class=$state['governance_class']??null;
        if(!is_string($class)||($operation!=='MFA_ENROLLMENT_CANCELLED'&&(($state['status']??null)!=='ACTIVE'||!in_array($class,['DIRECTOR','MASTER_ADMIN'],true))))throw new \RuntimeException('governance_audit_staff_required');
        $uuid=new RandomAuditUuidProvider();
        $context=TrustedAuditContext::fromServer($actor->accountId(),'account',$class,'internal_governance',
            $uuid->generateCanonicalUuid(),$uuid->generateCanonicalUuid(),(string)$actor->session()->sessionId(),null,null,'IDENTITY','governance/'.$operation);
        $this->writer->append(new CanonicalAuditEventInput('INTERNAL_GOVERNANCE_CHANGED','SUCCESS','ADMIN_DECISION',
            'account',$actor->accountId(),str_starts_with($operation,'MFA_ENROLLMENT_')?'staff_mfa_factor':'governance_case',$reference,['operation'=>$operation,'target_account_id'=>$target,
            'capability'=>null,'before'=>null,'after'=>$after]),$context);
    }
}
