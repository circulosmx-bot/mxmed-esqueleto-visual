<?php
declare(strict_types=1);

namespace Platform\Audit;

use Identity\Audit\AuditProducerEmissionResult;
use Identity\Audit\BoundedBestEffortAuditEmitter;
use Platform\Audit\Contracts\Mp01fAuditProducer;
use Platform\Contracts\CanonicalAuditEventInput;
use Platform\Contracts\TrustedActorContext;
use Platform\Contracts\TrustedRequestContext;
use Platform\Services\CanonicalAuditPolicyRegistry;

/** Dormant MP01F producer delegating its bounded write path to MP01E's shared emitter. */
final class CanonicalMp01fAuditProducer implements Mp01fAuditProducer
{
    public function __construct(
        private BoundedBestEffortAuditEmitter $emitter,
        private CanonicalAuditPolicyRegistry $policies,
        private SensitiveAdminActionCatalog $sensitiveAdminActions,
    ) {
        $this->sensitiveAdminActions->assertPolicyCompatibility($this->policies);
    }

    public function emit(
        AuthoritativeAuditOutcome $outcome,
        TrustedRequestContext $request,
        TrustedActorContext $actor,
    ): AuditProducerEmissionResult {
        $policy = $this->policies->assertAllowed($outcome->eventType, $outcome->result, $outcome->reasonCode);
        if (!str_starts_with($outcome->outcomeAuthority, 'committed_')) {
            throw new \LogicException('untrusted_outcome_authority');
        }
        if ($outcome->eventType === 'SENSITIVE_ADMIN_ACTION' && $outcome->sensitiveAdminActionKey === null) {
            throw new \LogicException('sensitive_admin_catalog_key_missing');
        }
        if ($outcome->eventType !== 'SENSITIVE_ADMIN_ACTION' && $outcome->sensitiveAdminActionKey !== null) {
            throw new \LogicException('sensitive_admin_catalog_key_on_specific_event');
        }
        if ($policy['actor_required'] !== true) {
            throw new \LogicException('mp01f_actor_policy_regression');
        }
        if ($policy['session_required'] && $request->sessionId === null) {
            throw new \InvalidArgumentException('mp01f_session_required');
        }
        $metadataKeys = array_keys($outcome->metadata);
        sort($metadataKeys, SORT_STRING);
        $allowedMetadata = $policy['allowed_producer_metadata'];
        sort($allowedMetadata, SORT_STRING);
        if (array_diff($metadataKeys, $allowedMetadata) !== []) {
            throw new \InvalidArgumentException('producer_metadata_not_allowed');
        }

        $input = new CanonicalAuditEventInput(
            $outcome->eventType,
            $outcome->result,
            $outcome->reasonCode,
            $actor->effectiveEntityType,
            $actor->effectiveEntityId,
            $outcome->target->type,
            $outcome->target->id,
            $outcome->metadata,
        );
        return $this->emitter->emit($input, $request, $actor);
    }
}
