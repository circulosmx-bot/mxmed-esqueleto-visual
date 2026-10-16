<?php
declare(strict_types=1);
namespace Identity\Operators;
use Identity\Http\IdentityHttpComposition;
use Platform\Services\{CanonicalAuditWriter,CanonicalAuditPolicyRegistry,CanonicalAuditMetadataSanitizer,TrustedAuditContextValidator,RandomAuditUuidProvider,SystemAuditUtcClock,HmacSha256AuditIpHasher,EnvironmentAuditSecretProvider,CoarseAuditUserAgentSummarizer,CanonicalAuditSealer,CanonicalAuditSerializer,AuditV1PhysicalMapper};
/** Existing isolated composition delegates the SAME HTTP/Agenda adapter. */
final class OperatorQaHttp extends OperatorHttp
{
    public function __construct()
    {
        if(!defined('MXMED_OP02_ROUTED')||MXMED_OP02_ROUTED!==true)throw new \RuntimeException('op02_http_boundary_required');
        OperatorQaConfiguration::assertEnabled();$identity=IdentityHttpComposition::fromProcessEnvironment();$pdo=$identity->pdo();$outbox=new IsolatedInvitationOutbox($pdo);
        $writer=new CanonicalAuditWriter(CanonicalAuditPolicyRegistry::canonical(),new CanonicalAuditMetadataSanitizer(),new TrustedAuditContextValidator(),new RandomAuditUuidProvider(),new SystemAuditUtcClock(),new HmacSha256AuditIpHasher(new EnvironmentAuditSecretProvider()),new CoarseAuditUserAgentSummarizer(),new IsolatedOperatorAuditTransaction($pdo),new CanonicalAuditSealer(new CanonicalAuditSerializer()),new AuditV1PhysicalMapper());
        parent::__construct($identity,ProfileOperatorService::isolated($identity,$writer,$outbox),OperatorQaConfiguration::origin(),(string)getenv('MXMED_PREVIEW_PEPPER'),$outbox);
    }
}
