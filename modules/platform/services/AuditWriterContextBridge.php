<?php
declare(strict_types=1);
namespace Platform\Services;
use Platform\Contracts\{TrustedActorContext,TrustedAuditContext,TrustedRequestContext};
final class AuditWriterContextBridge
{
    public function toAuditContext(TrustedRequestContext $request, TrustedActorContext $actor): TrustedAuditContext
    {
        return TrustedAuditContext::fromServer($actor->auditIdentityId(), $actor->auditActorType(),
            $actor->actorRole, $actor->actorScope, $request->requestId, $request->correlationId,
            $request->sessionId, $request->trustedClientIp, $request->trustedRawUserAgent,
            $request->sourceModule, $request->sourceRoute);
    }
    public function assertEffectiveEntityMatchesInput(TrustedActorContext $actor, ?string $type, ?string $id): void
    {
        if ($actor->effectiveEntityType !== $type || $actor->effectiveEntityId !== $id) {
            throw new \InvalidArgumentException('effective_entity_handoff_mismatch');
        }
    }
}
