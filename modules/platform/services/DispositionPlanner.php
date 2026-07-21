<?php
declare(strict_types=1);

namespace Platform\Services;

use Platform\Contracts\AuditAvailability;
use Platform\Contracts\AuthorizationDecision;
use Platform\Contracts\CanonicalSourceResolution;
use Platform\Contracts\DispositionAction;
use Platform\Contracts\DispositionPlan;
use Platform\Contracts\DispositionReason;
use Platform\Contracts\DispositionRequest;
use Platform\Contracts\DispositionResolution;
use Platform\Contracts\RiskLevel;
use Platform\Contracts\RetentionPolicyRegistration;

/** Plans only; never executes, persists or schedules a disposition. */
final class DispositionPlanner
{
    public function plan(DispositionRequest $request, ?RetentionPolicyRegistration $registration, CanonicalSourceResolution $source, AuthorizationDecision $authorization, ?string $auditAvailability): DispositionPlan
    {
        if (!$authorization->allowed()) return DispositionPlan::deny($request, DispositionReason::AUTHORIZATION_DENIED, ['authorization:' . $authorization->reasonCode()]);
        if (!$source->allowed()) {
            $reason = $source->reasonCode() === 'canonical_source_conflict' ? DispositionReason::SOURCE_CONFLICT : DispositionReason::SOURCE_UNRESOLVED;
            return DispositionPlan::deny($request, $reason, ['canonical_source']);
        }
        if ($registration === null) return DispositionPlan::deny($request, DispositionReason::POLICY_UNRESOLVED, ['retention_policy']);
        $policy = $registration->policy();
        if ($registration->policyReference() !== null && $request->policyReference() !== $registration->policyReference()) return DispositionPlan::deny($request, DispositionReason::POLICY_UNRESOLVED, ['policy_reference']);
        if ($request->sourceReference() !== $source->source()?->sourceReference()) return DispositionPlan::deny($request, DispositionReason::SOURCE_UNRESOLVED, ['source_reference']);
        if (!$request->simulationOnly()) return DispositionPlan::deny($request, DispositionReason::SIMULATION_ONLY, ['simulation_only']);
        if (DispositionAction::requiresR3($request->action()) && $request->riskLevel() !== RiskLevel::R3) return DispositionPlan::deny($request, DispositionReason::RISK_REQUIRED, ['R3']);
        if ($request->expectedCurrentState() !== null && $request->expectedCurrentState() !== $policy->currentState()) return DispositionPlan::deny($request, DispositionReason::CURRENT_STATE_MISMATCH, ['current_state']);
        if ($policy->retentionUnresolved() && in_array($request->action(), [DispositionAction::DELETE, DispositionAction::ANONYMIZE], true)) return DispositionPlan::deny($request, DispositionReason::RETENTION_UNRESOLVED, ['retention_period']);
        if ($request->action() === DispositionAction::ANONYMIZE && $registration->anonymizationResolution() === DispositionResolution::ANONYMIZATION_UNRESOLVED) return DispositionPlan::deny($request, DispositionReason::ANONYMIZATION_UNRESOLVED, ['anonymization_evidence']);
        if ($request->legalHoldKnown() || $policy->legalHold()) {
            if (DispositionAction::isIrreversible($request->action())) return DispositionPlan::deny($request, DispositionReason::LEGAL_HOLD, ['legal_hold']);
        }
        if ($registration->sensitiveData() && $policy->currentState() === 'active' && $request->requestedTargetState() === 'deleted') return DispositionPlan::deny($request, DispositionReason::CURRENT_STATE_MISMATCH, ['archive_or_review_before_delete']);
        if ($request->auditRequired() && $auditAvailability !== AuditAvailability::AVAILABLE) return DispositionPlan::deny($request, DispositionReason::AUDIT_UNAVAILABLE, ['audit']);
        if (DispositionAction::requiresR3($request->action()) && $request->approvalReferences()->isEmpty()) return DispositionPlan::deny($request, DispositionReason::APPROVAL_REQUIRED, ['approval']);
        if (DispositionAction::isIrreversible($request->action()) && !$request->reconciliationRequired()) return DispositionPlan::deny($request, DispositionReason::RECONCILIATION_REQUIRED, ['reconciliation']);
        if (DispositionAction::isIrreversible($request->action()) && !$request->rollbackRequired()) return DispositionPlan::deny($request, DispositionReason::ROLLBACK_REQUIRED, ['rollback']);
        if ($request->action() === DispositionAction::EXPORT_MASS && $request->expirationSeconds() === null) return DispositionPlan::deny($request, DispositionReason::EXPIRATION_REQUIRED, ['expiration']);
        $steps = ['simulate', 'review_policy', 'verify_authorization', 'verify_canonical_source', 'verify_idempotency', 'reconcile_counts', 'record_audit'];
        if ($request->action() === DispositionAction::EXPORT_MASS) $steps[] = 'minimize_and_expire_export';
        if (DispositionAction::isIrreversible($request->action())) $steps[] = 'obtain_specialized_approval';
        return DispositionPlan::allowSimulation($request, $steps);
    }
}
