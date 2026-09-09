<?php
declare(strict_types=1);
namespace Media\Services;
require_once __DIR__.'/MediaReviewAuthority.php';
use Platform\Contracts\{AuditTrailPort,AuditAvailability,AuditWriteResult,AuditEventReference,CanonicalAuditEventInput,TrustedAuditContext,TrustedAuthorizationContext};
use Platform\Services\{CanonicalAuditWriter,CanonicalAuditPolicyRegistry,CanonicalAuditMetadataSanitizer,TrustedAuditContextValidator,RandomAuditUuidProvider,SystemAuditUtcClock,HmacSha256AuditIpHasher,EnvironmentAuditSecretProvider,CoarseAuditUserAgentSummarizer,CanonicalAuditSealer,CanonicalAuditSerializer,AuditV1PhysicalMapper};
use Platform\Repositories\JoinedPdoCanonicalAuditTransactionAdapter;

/** The boundary calls this only after all canonical permission checks succeed. */
final class ProfilePhotoApprovalAudit implements AuditTrailPort
{
    private CanonicalAuditWriter $writer;
    public function __construct(\PDO $pdo, private TrustedAuthorizationContext $context, private \Closure $prepare)
    {
        $this->writer = new CanonicalAuditWriter(CanonicalAuditPolicyRegistry::canonical(), new CanonicalAuditMetadataSanitizer(),
            new TrustedAuditContextValidator(), new RandomAuditUuidProvider(), new SystemAuditUtcClock(),
            new HmacSha256AuditIpHasher(new EnvironmentAuditSecretProvider()), new CoarseAuditUserAgentSummarizer(),
            new JoinedPdoCanonicalAuditTransactionAdapter($pdo), new CanonicalAuditSealer(new CanonicalAuditSerializer()), new AuditV1PhysicalMapper());
    }
    public function availability(): string { return AuditAvailability::AVAILABLE; }
    public function write(AuditEventReference $event): string
    {
        // Locks and verifies the candidate, but does not commit or publish anything.
        $data = ($this->prepare)();
        $subject = $this->context->context();
        $uuid = new RandomAuditUuidProvider();
        $actor = (string)$subject->accountId();
        $trusted = TrustedAuditContext::fromServer($actor,'account','internal_operator','media_review_approve',
            $uuid->generateCanonicalUuid(),$uuid->generateCanonicalUuid(),$subject->sessionReference()->value(),null,null,
            'MEDIA','POST /api/internal/media-review/approve.php');
        $this->writer->append(new CanonicalAuditEventInput('MEDIA_PROFILE_PHOTO_APPROVED','SUCCESS','ADMIN_DECISION',
            'account',$actor,'media_review_submission',$data['submission_id'],$data),$trusted);
        // ACCEPTED means an actual canonical event and chain update in the outer transaction.
        return AuditWriteResult::ACCEPTED;
    }
}
