<?php
declare(strict_types=1);

namespace Identity\Audit;

use Platform\Contracts\CanonicalAuditEventInput;
use Platform\Contracts\TrustedAuditContext;
use Platform\Contracts\TrustedRequestContext;

/**
 * MP01E-local handoff for normal pre-authentication events whose actor is
 * genuinely unknown. It deliberately does not manufacture a system or
 * authenticated actor and it is never an authentication-failure context.
 */
final readonly class PreauthActorOptionalContext
{
    private function __construct(
        public string $actorIdentityId,
        public string $actorType,
        public string $actorRole,
        public string $actorScope,
        public bool $authenticationFailure,
        public string $targetType,
        public string $targetId,
        public string $authorizationProvenance,
    ) {}

    public static function normalUnknown(string $targetType, string $targetId): self
    {
        if (!in_array($targetType, ['ACCOUNT', 'AUTH_IDENTIFIER_HMAC'], true)) {
            throw new \InvalidArgumentException('unsupported_actor_optional_preauth_target_type');
        }
        if (trim($targetId) === '') {
            throw new \InvalidArgumentException('missing_actor_optional_preauth_target');
        }
        return new self(
            'UNKNOWN',
            'UNKNOWN',
            'UNKNOWN',
            'PRE_AUTH',
            false,
            $targetType,
            $targetId,
            'backend_pre_auth_normal_flow',
        );
    }

    public function assertMatchesInput(CanonicalAuditEventInput $input): void
    {
        if ($this->authenticationFailure || $this->actorIdentityId !== 'UNKNOWN' || $this->actorType !== 'UNKNOWN') {
            throw new \LogicException('invalid_actor_optional_preauth_semantics');
        }
        if ($input->effectiveEntityType !== null || $input->effectiveEntityId !== null) {
            throw new \InvalidArgumentException('preauth_effective_entity_forbidden');
        }
        if ($input->targetType !== $this->targetType || $input->targetId !== $this->targetId) {
            throw new \InvalidArgumentException('preauth_target_handoff_mismatch');
        }
    }

    public function toTrustedAuditContext(TrustedRequestContext $request): TrustedAuditContext
    {
        return TrustedAuditContext::fromServer(
            $this->actorIdentityId,
            $this->actorType,
            $this->actorRole,
            $this->actorScope,
            $request->requestId,
            $request->correlationId,
            $request->sessionId,
            $request->trustedClientIp,
            $request->trustedRawUserAgent,
            $request->sourceModule,
            $request->sourceRoute,
        );
    }
}
