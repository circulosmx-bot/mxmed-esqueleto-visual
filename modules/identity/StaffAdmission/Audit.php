<?php
declare(strict_types=1);
namespace Identity\StaffAdmission;
use Identity\Contracts\AuthenticatedAccessContext;
use Platform\Contracts\{CanonicalAuditEventInput,TrustedAuditContext};
use Platform\Repositories\JoinedPdoCanonicalAuditTransactionAdapter;
use Platform\Services\{CanonicalAuditWriter,CanonicalAuditPolicyRegistry,CanonicalAuditMetadataSanitizer,TrustedAuditContextValidator,RandomAuditUuidProvider,SystemAuditUtcClock,HmacSha256AuditIpHasher,EnvironmentAuditSecretProvider,CoarseAuditUserAgentSummarizer,CanonicalAuditSealer,CanonicalAuditSerializer,AuditV1PhysicalMapper};
final class Audit
{
    public static function append(\PDO $pdo,AuthenticatedAccessContext $actor,string $case,string $operation,string $id,int $version,string $provenance):string
    {
        if(!$pdo->inTransaction())throw new \RuntimeException('admission_transaction_required');
        $writer=new CanonicalAuditWriter(CanonicalAuditPolicyRegistry::canonical(),new CanonicalAuditMetadataSanitizer(),new TrustedAuditContextValidator(),new RandomAuditUuidProvider(),new SystemAuditUtcClock(),new HmacSha256AuditIpHasher(new EnvironmentAuditSecretProvider()),new CoarseAuditUserAgentSummarizer(),new JoinedPdoCanonicalAuditTransactionAdapter($pdo),new CanonicalAuditSealer(new CanonicalAuditSerializer()),new AuditV1PhysicalMapper());
        $reading=in_array($operation,['read','result'],true);
        $admin=in_array($operation,['prepare','update','revoke','invitation_issue','invitation_reissue','invitation_revoke','invitation_delivery'],true);
        $context=TrustedAuditContext::fromServer($actor->accountId(),'account',$reading?'AUTHORIZED_CASE_READER':($admin?'DIRECTOR':'APPLICANT'),'staff_admission',
            (new RandomAuditUuidProvider())->generateCanonicalUuid(),$id,(string)$actor->session()->sessionId(),null,null,'ADMIN','staff-admission/'.$operation);
        return (string)$writer->append(new CanonicalAuditEventInput($reading?'STAFF_ADMISSION_CASE_READ':'STAFF_ADMISSION_CASE_CHANGED','SUCCESS',$admin?'ADMIN_DECISION':'USER_REQUEST','account',$actor->accountId(),'staff_admission_case',$case,['operation'=>$operation,'case_id'=>$case,'version'=>$version,'provenance'=>$provenance]),$context)->value('event_id');
    }
}
