<?php
declare(strict_types=1);
namespace Profiles\Corrections;
use Identity\Contracts\AuthenticatedAccessContext;
use Platform\Contracts\{CanonicalAuditEventInput,TrustedAuditContext};
use Platform\Services\{CanonicalAuditWriter,CanonicalAuditPolicyRegistry,CanonicalAuditMetadataSanitizer,TrustedAuditContextValidator,
    RandomAuditUuidProvider,SystemAuditUtcClock,HmacSha256AuditIpHasher,EnvironmentAuditSecretProvider,
    CoarseAuditUserAgentSummarizer,CanonicalAuditSealer,CanonicalAuditSerializer,AuditV1PhysicalMapper};
use Platform\Repositories\JoinedPdoCanonicalAuditTransactionAdapter;
use PDO;

/** New finite Profiles events; existing Identity/C4 audit contracts remain separate. */
final class ReportAudit
{
    private CanonicalAuditWriter $writer;
    public function __construct(private PDO $pdo)
    {
        $this->writer=new CanonicalAuditWriter(CanonicalAuditPolicyRegistry::canonical(),new CanonicalAuditMetadataSanitizer(),
            new TrustedAuditContextValidator(),new RandomAuditUuidProvider(),new SystemAuditUtcClock(),
            new HmacSha256AuditIpHasher(new EnvironmentAuditSecretProvider()),new CoarseAuditUserAgentSummarizer(),
            new JoinedPdoCanonicalAuditTransactionAdapter($pdo),new CanonicalAuditSealer(new CanonicalAuditSerializer()),new AuditV1PhysicalMapper());
    }
    public function append(string $report,string $operation,int $version,string $before,string $after,?AuthenticatedAccessContext $actor=null,?string $case=null):string
    {
        if(!$this->pdo->inTransaction())throw new \RuntimeException('correction_audit_transaction_required');
        if(!in_array($operation,['RECEIVED','QUEUE_READ','REPORT_READ','REVIEW_CHALLENGE','NEEDS_CLARIFICATION','REJECTED','ACCEPTED_FOR_APPLICATION'],true)
            ||(($operation==='RECEIVED')!==($actor===null)))throw new \RuntimeException('correction_audit_operation_invalid');
        $uuid=new RandomAuditUuidProvider();
        // Anonymous is an explicit non-account principal scoped to this report only.
        // No Identity account, membership, session, email, explanation or raw network data.
        $context=TrustedAuditContext::fromServer($actor?->accountId()??('report-'.$report),$actor===null?'anonymous_reporter':'account',
            $actor===null?'unverified_public_reporter':ReportCatalog::CAPABILITY,'public_profile_correction',
            $uuid->generateCanonicalUuid(),$uuid->generateCanonicalUuid(),$actor===null?null:(string)$actor->session()->sessionId(),
            null,null,'PROFILE','public-profile-corrections/'.$operation);
        $event=$this->writer->append(new CanonicalAuditEventInput($actor===null?'PUBLIC_PROFILE_CORRECTION_RECEIVED':'PUBLIC_PROFILE_CORRECTION_REVIEWED',
            'SUCCESS',$actor===null?'USER_REQUEST':'ADMIN_DECISION',$actor===null?null:'account',$actor?->accountId(),
            'public_profile_correction',$report,['operation'=>$operation,'version'=>$version,'previous_status'=>$before,'new_status'=>$after,
                'case_id'=>$case,'capability'=>$actor===null?null:ReportCatalog::CAPABILITY]),$context);
        return (string)$event->value('event_id');
    }
}
