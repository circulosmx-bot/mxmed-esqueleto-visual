<?php
declare(strict_types=1);

namespace Identity\Audit;

use Identity\Audit\Contracts\AuditProducerFailureSignalPort;
use Identity\Audit\Contracts\CanonicalAuditAppendPort;
use Platform\Contracts\AuditEventScopePolicy;
use Platform\Contracts\CanonicalAuditEventInput;
use Platform\Contracts\TrustedActorContext;
use Platform\Contracts\TrustedAuditContext;
use Platform\Contracts\TrustedRequestContext;
use Platform\Services\AuditWriterContextBridge;

final class BoundedBestEffortAuditEmitter
{
    public function __construct(
        private CanonicalAuditAppendPort $writer,
        private AuditWriterContextBridge $bridge,
        private AuditProducerFailureSignalPort $failureSignal,
        private AuditEventScopePolicy $scope,
    ) {}

    public function emit(CanonicalAuditEventInput $input, TrustedRequestContext $request, TrustedActorContext $actor): AuditProducerEmissionResult
    {
        $this->scope->assertRequestMatches($input->eventType, $request);
        if ($actor->targetType !== $input->targetType || $actor->targetId !== $input->targetId) {
            throw new \InvalidArgumentException('actor_target_handoff_mismatch');
        }
        $this->bridge->assertEffectiveEntityMatchesInput($actor, $input->effectiveEntityType, $input->effectiveEntityId);
        $context = $this->bridge->toAuditContext($request, $actor);
        return $this->append($input, $request, $context);
    }

    public function emitActorOptionalPreauth(CanonicalAuditEventInput $input, TrustedRequestContext $request, PreauthActorOptionalContext $actor): AuditProducerEmissionResult
    {
        if (!in_array($input->eventType, [
            'AUTH_REGISTRATION_REQUESTED',
            'AUTH_EMAIL_VERIFICATION_SENT',
            'AUTH_PASSWORD_RECOVERY_REQUESTED',
        ], true)) {
            throw new \InvalidArgumentException('actor_optional_preauth_event_not_allowed');
        }
        $this->scope->assertRequestMatches($input->eventType, $request);
        $actor->assertMatchesInput($input);
        return $this->append($input, $request, $actor->toTrustedAuditContext($request));
    }

    private function append(CanonicalAuditEventInput $input, TrustedRequestContext $request, TrustedAuditContext $context): AuditProducerEmissionResult
    {
        try {
            $this->writer->append($input, $context);
            return AuditProducerEmissionResult::written();
        } catch (\Throwable) {
            $signal = new AuditProducerFailureSignal($request->requestId, $request->correlationId, $input->eventType, 'CANONICAL_AUDIT_WRITE_FAILED');
            try {
                $this->failureSignal->signal($signal);
                return AuditProducerEmissionResult::auditFailedSignalled();
            } catch (\Throwable) {
                return AuditProducerEmissionResult::auditAndSignalFailed();
            }
        }
    }
}
