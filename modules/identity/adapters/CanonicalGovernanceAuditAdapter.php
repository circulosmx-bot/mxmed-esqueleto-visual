<?php
declare(strict_types=1);
namespace Identity\Adapters;
use Identity\Contracts\AuthenticatedAccessContext;
use Identity\Services\StaffRoutineOperationPolicy;
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
        $advisorActivation=$class==='ADVISOR'&&($state['status']??null)==='ACTIVE'
            &&$operation==='MFA_ENROLLMENT_ACTIVATED'&&$target===$actor->accountId()
            &&$this->advisorFactorActivation($actor,$reference);
        if(!is_string($class)||($operation!=='MFA_ENROLLMENT_CANCELLED'&&(($state['status']??null)!=='ACTIVE'
            ||(!in_array($class,['DIRECTOR','MASTER_ADMIN'],true)&&!$advisorActivation))))throw new \RuntimeException('governance_audit_staff_required');
        $uuid=new RandomAuditUuidProvider();
        $context=TrustedAuditContext::fromServer($actor->accountId(),'account',$class,'internal_governance',
            $uuid->generateCanonicalUuid(),$uuid->generateCanonicalUuid(),(string)$actor->session()->sessionId(),null,null,'IDENTITY','governance/'.$operation);
        $this->writer->append(new CanonicalAuditEventInput('INTERNAL_GOVERNANCE_CHANGED','SUCCESS','ADMIN_DECISION',
            'account',$actor->accountId(),str_starts_with($operation,'MFA_ENROLLMENT_')?'staff_mfa_factor':'governance_case',$reference,['operation'=>$operation,'target_account_id'=>$target,
            'capability'=>null,'before'=>null,'after'=>$after]),$context);
    }
    private function advisorFactorActivation(AuthenticatedAccessContext $actor,string $factor):bool
    {
        if(!StaffRoutineOperationPolicy::eligibleAdvisorSubject($this->pdo,$actor->accountId()))return false;
        $q=$this->pdo->prepare("SELECT enrollment_context FROM auth_staff_mfa_factors WHERE factor_id=? AND account_id=? AND status='ACTIVE' AND verified_at IS NOT NULL FOR UPDATE");
        $q->execute([$factor,$actor->accountId()]);$raw=$q->fetchColumn();if(!is_string($raw))return false;
        $c=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
        if(($c['subject']['account_id']??null)!==$actor->accountId()
            ||($c['subject']['session_id']??null)!==(string)$actor->session()->sessionId()
            ||($c['subject']['credential_version']??null)!==$actor->principal()->credentialVersion()
            ||!is_string($c['registrar']['account_id']??null)||$c['registrar']['account_id']===$actor->accountId())return false;
        $q=$this->pdo->prepare("SELECT 1 FROM internal_staff s JOIN auth_accounts a ON a.account_id=s.account_id JOIN auth_account_credentials c ON c.account_id=a.account_id WHERE s.account_id=? AND s.status='ACTIVE' AND s.governance_class IN ('DIRECTOR','MASTER_ADMIN') AND a.status='active' AND a.email_verified_at IS NOT NULL AND c.credential_version=? FOR UPDATE");
        $q->execute([$c['registrar']['account_id'],$c['registrar']['credential_version']??null]);return $q->fetchColumn()!==false;
    }
}
