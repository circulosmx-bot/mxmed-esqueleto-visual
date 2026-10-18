<?php
declare(strict_types=1);
namespace Identity\StaffMedia;
use Identity\Contracts\AuthenticatedAccessContext;
use Platform\Contracts\{CanonicalAuditEventInput,TrustedAuditContext};
use Platform\Repositories\JoinedPdoCanonicalAuditTransactionAdapter;
use Platform\Services\{CanonicalAuditWriter,CanonicalAuditPolicyRegistry,CanonicalAuditMetadataSanitizer,TrustedAuditContextValidator,RandomAuditUuidProvider,SystemAuditUtcClock,HmacSha256AuditIpHasher,EnvironmentAuditSecretProvider,CoarseAuditUserAgentSummarizer,CanonicalAuditSealer,CanonicalAuditSerializer,AuditV1PhysicalMapper};
final class Audit
{
    public static function append(\PDO $pdo,AuthenticatedAccessContext $actor,string $case,string $purpose,string $operation,int $version,string $correlation,string $result='SUCCESS'):string
    {
        if(!$pdo->inTransaction())throw new \RuntimeException('staff_media_transaction_required');
        $writer=new CanonicalAuditWriter(CanonicalAuditPolicyRegistry::canonical(),new CanonicalAuditMetadataSanitizer(),new TrustedAuditContextValidator(),new RandomAuditUuidProvider(),new SystemAuditUtcClock(),new HmacSha256AuditIpHasher(new EnvironmentAuditSecretProvider()),new CoarseAuditUserAgentSummarizer(),new JoinedPdoCanonicalAuditTransactionAdapter($pdo),new CanonicalAuditSealer(new CanonicalAuditSerializer()),new AuditV1PhysicalMapper());
        $context=TrustedAuditContext::fromServer($actor->accountId(),'account','STAFF_MEDIA_REQUESTER','staff_media',
            (new RandomAuditUuidProvider())->generateCanonicalUuid(),$correlation,(string)$actor->session()->sessionId(),null,null,'ADMIN','staff-media/'.$operation);
        return (string)$writer->append(new CanonicalAuditEventInput($operation==='write'?'STAFF_MEDIA_CHANGED':'STAFF_MEDIA_READ',$result,
            match($result){'SUCCESS'=>'USER_REQUEST','DENIED'=>'POLICY_DENIED',default=>'INTERNAL_ERROR'},'account',$actor->accountId(),'staff_admission_case',$case,
            ['operation'=>$operation,'case_id'=>$case,'purpose'=>$purpose,'asset_version'=>$version]),$context)->value('event_id');
    }
}
