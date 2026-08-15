<?php
declare(strict_types=1);

namespace Platform\Audit\Read;

final readonly class AuthorizedAuditRead
{
    private function __construct(
        public AuditReadQuery $query,
        public string $requesterIdentityId,
        public ?string $forcedSelfIdentityId,
        public string $authorityProvenance,
    ) {}

    public static function grant(AuditReadQuery $query, TrustedAuditReadAuthority $authority): self
    {
        return new self(
            $query,
            $authority->requesterIdentityId,
            $query->capability === AuditReadAccess::SELF_SECURITY ? $authority->requesterIdentityId : null,
            $authority->provenance,
        );
    }
}

/** Default-deny authorization over backend-derived capabilities only. */
final class AuditReadAuthorizer
{
    public function authorize(AuditReadQuery $query, TrustedAuditReadAuthority $authority): AuthorizedAuditRead
    {
        AuditReadAccess::assertCombination($query->capability, $query->scope, $query->accessReason);
        if (!$authority->has($query->capability)) {
            throw new \DomainException('audit_read_capability_denied');
        }
        if ($query->capability === AuditReadAccess::SELF_SECURITY && $query->scopeValue !== 'TRUSTED_SELF') {
            throw new \DomainException('self_timeline_identity_override_denied');
        }
        return AuthorizedAuditRead::grant($query, $authority);
    }
}
