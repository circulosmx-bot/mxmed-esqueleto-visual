<?php
declare(strict_types=1);

namespace Platform\Audit\Read;

use Platform\Contracts\TrustedActorContext;

/** Backend-derived requester authority; it has no request-payload factory. */
final readonly class TrustedAuditReadAuthority
{
    /** @param list<string> $capabilities */
    private function __construct(
        public string $requesterIdentityId,
        public string $requesterRole,
        public string $requesterScope,
        public array $capabilities,
        public string $provenance,
    ) {}

    /** @param list<string> $capabilities */
    public static function fromTrustedBackend(TrustedActorContext $actor, array $capabilities): self
    {
        if (!in_array($actor->trustSource, ['backend_trusted', 'system'], true)) {
            throw new \InvalidArgumentException('audit_read_authority_not_backend_trusted');
        }
        $identity = $actor->authenticatedIdentityId ?? $actor->realActorId;
        if ($identity === null || !self::safeIdentifier($identity)) {
            throw new \InvalidArgumentException('audit_read_requester_identity_required');
        }
        $normalized = [];
        foreach ($capabilities as $capability) {
            if (!is_string($capability)) {
                throw new \InvalidArgumentException('invalid_audit_read_capability');
            }
            AuditReadAccess::assertCapability($capability);
            $normalized[] = $capability;
        }
        $normalized = array_values(array_unique($normalized, SORT_STRING));
        sort($normalized, SORT_STRING);
        return new self($identity, $actor->actorRole, $actor->actorScope, $normalized, 'trusted_actor_context:' . $actor->authorizationProvenance);
    }

    public function has(string $capability): bool
    {
        AuditReadAccess::assertCapability($capability);
        return in_array($capability, $this->capabilities, true);
    }

    private static function safeIdentifier(string $value): bool
    {
        return $value === trim($value)
            && $value !== ''
            && strlen($value) <= 128
            && preg_match('/^[A-Za-z0-9._:@-]+$/D', $value) === 1
            && preg_match('/(?:password|credential|secret|bearer|otp|raw[_-]?token)/i', $value) !== 1;
    }
}
