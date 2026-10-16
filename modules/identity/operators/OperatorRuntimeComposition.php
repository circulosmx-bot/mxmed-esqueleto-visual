<?php
declare(strict_types=1);
namespace Identity\Operators;
use Identity\Http\IdentityHttpComposition;
use Platform\Services\{CanonicalAuditWriter,CanonicalAuditPolicyRegistry,CanonicalAuditMetadataSanitizer,TrustedAuditContextValidator,RandomAuditUuidProvider,SystemAuditUtcClock,HmacSha256AuditIpHasher,EnvironmentAuditSecretProvider,CoarseAuditUserAgentSummarizer,CanonicalAuditSealer,CanonicalAuditSerializer,AuditV1PhysicalMapper};
use Platform\Repositories\JoinedPdoCanonicalAuditTransactionAdapter;
final class OperatorRuntimeComposition
{
    private function __construct(public readonly OperatorHttp $http,public readonly OperatorDeploymentConfiguration $configuration){}
    public static function fromIdentity(IdentityHttpComposition $identity):self
    {
        $pdo=$identity->pdo();$config=OperatorDeploymentConfiguration::fromProcess($pdo);$queue=new DurableOperatorInvitationDelivery($pdo,$config->deliveryKey(),$config->deliveryKeyId());
        $writer=new CanonicalAuditWriter(CanonicalAuditPolicyRegistry::canonical(),new CanonicalAuditMetadataSanitizer(),new TrustedAuditContextValidator(),new RandomAuditUuidProvider(),new SystemAuditUtcClock(),new HmacSha256AuditIpHasher(new EnvironmentAuditSecretProvider()),new CoarseAuditUserAgentSummarizer(),new JoinedPdoCanonicalAuditTransactionAdapter($pdo),new CanonicalAuditSealer(new CanonicalAuditSerializer()),new AuditV1PhysicalMapper());
        $service=ProfileOperatorService::deployed($identity,$writer,$queue,$config);
        return new self(new OperatorHttp($identity,$service,$identity->allowedOrigin(),$config->deliveryKey()),$config);
    }
    /** Non-medical provenance remains non-medical after revocation or a changed role label. */
    public static function accountRequiresBoundedShell(IdentityHttpComposition $identity,string $account):bool
    {
        $s=$identity->pdo()->prepare("SELECT COUNT(*) FROM auth_account_memberships WHERE account_id=? AND assignment_source='owner_operator_invitation'");$s->execute([$account]);if((int)$s->fetchColumn()>0)return true;
        $s=$identity->pdo()->prepare("SELECT COUNT(*) FROM auth_profile_operator_invitations i JOIN auth_accounts a ON a.email_normalized=i.email_normalized WHERE a.account_id=? AND i.status='pending' AND i.expires_at>UTC_TIMESTAMP(6)");$s->execute([$account]);return (int)$s->fetchColumn()>0;
    }
}
