<?php
declare(strict_types=1);
namespace Media\Services;
require_once __DIR__.'/MediaReviewAuthority.php';
use Platform\Contracts\{AuditTrailPort,AuditAvailability,AuditWriteResult,AuditEventReference,CanonicalAuditEventInput,TrustedAuditContext,TrustedAuthorizationContext};
use Platform\Services\{CanonicalAuditWriter,CanonicalAuditPolicyRegistry,CanonicalAuditMetadataSanitizer,TrustedAuditContextValidator,RandomAuditUuidProvider,SystemAuditUtcClock,HmacSha256AuditIpHasher,EnvironmentAuditSecretProvider,CoarseAuditUserAgentSummarizer,CanonicalAuditSealer,CanonicalAuditSerializer,AuditV1PhysicalMapper};
use Platform\Repositories\JoinedPdoCanonicalAuditTransactionAdapter;

/** The boundary calls this only after all canonical permission checks succeed. */
final class MediaReviewAudit implements AuditTrailPort
{
    private CanonicalAuditWriter $writer;
    public function __construct(\PDO $pdo, private TrustedAuthorizationContext $context, private \Closure $prepare, private string $eventType, private string $capability, private string $route)
    {
        $this->writer = new CanonicalAuditWriter(CanonicalAuditPolicyRegistry::canonical(), new CanonicalAuditMetadataSanitizer(),
            new TrustedAuditContextValidator(), new RandomAuditUuidProvider(), new SystemAuditUtcClock(),
            new HmacSha256AuditIpHasher(new EnvironmentAuditSecretProvider()), new CoarseAuditUserAgentSummarizer(),
            new JoinedPdoCanonicalAuditTransactionAdapter($pdo), new CanonicalAuditSealer(new CanonicalAuditSerializer()), new AuditV1PhysicalMapper());
    }
    public function availability(): string { return AuditAvailability::AVAILABLE; }
    public function write(AuditEventReference $event): string
    {
        // Prepares the authorized operation; all authority changes remain uncommitted.
        $data = ($this->prepare)();
        $subject = $this->context->context();
        $uuid = new RandomAuditUuidProvider();
        $actor = (string)$subject->accountId();
        $trusted = TrustedAuditContext::fromServer($actor,'account','internal_operator',$this->capability,
            $uuid->generateCanonicalUuid(),$uuid->generateCanonicalUuid(),$subject->sessionReference()->value(),null,null,
            'MEDIA',$this->route);
        $this->writer->append(new CanonicalAuditEventInput($this->eventType,'SUCCESS','ADMIN_DECISION',
            'account',$actor,'media_review_submission',$data['submission_id'],$data),$trusted);
        // ACCEPTED means an actual canonical event and chain update in the outer transaction.
        return AuditWriteResult::ACCEPTED;
    }
}
