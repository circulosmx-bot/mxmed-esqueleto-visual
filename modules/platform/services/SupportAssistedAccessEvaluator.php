<?php
declare(strict_types=1);

namespace Platform\Services;

use Platform\Contracts\AuthorizationDecision;
use Platform\Contracts\AuthorizationPlane;
use Platform\Contracts\AuditTrailPort;
use Platform\Contracts\AuditWriteResult;
use Platform\Contracts\PrivilegedAccessMode;
use Platform\Contracts\PrivilegedAccessReason;
use Platform\Contracts\PrivilegedAccessRequest;
use Platform\Contracts\RiskLevel;
use Platform\Contracts\SupportAccessState;

final class SupportAssistedAccessEvaluator
{
    public function __construct(private readonly bool $featureEnabled = false, private readonly PrivilegedAccessPolicySupport $support = new PrivilegedAccessPolicySupport()) {}

    public function evaluate(PrivilegedAccessRequest $request, AuthorizationDecision $authorization, ?AuditTrailPort $audit, string $nowUtc): \Platform\Contracts\PrivilegedAccessDecision
    {
        if (!$this->featureEnabled) return $this->support->decision($request, PrivilegedAccessReason::FEATURE_DISABLED, ['feature_flag'], ['feature_disabled']);
        if ($request->mode() !== PrivilegedAccessMode::SUPPORT_ASSISTED || $request->authorizationPlane() !== AuthorizationPlane::INTERNAL_OPERATOR) return $this->support->decision($request, PrivilegedAccessReason::MODE_MISMATCH, ['mode', 'authorization_plane'], ['support_assisted_internal_operator']);
        if (!in_array($request->riskLevel(), [RiskLevel::R2, RiskLevel::R3], true)) return $this->support->decision($request, PrivilegedAccessReason::RISK_REQUIREMENT_UNSATISFIED, ['risk'], ['risk_r2_or_r3']);
        if ($request->state() !== SupportAccessState::APPROVED) return $this->support->decision($request, PrivilegedAccessReason::INVALID_STATE, ['approved_state'], ['state_approved']);
        if ($request->realActor() === null || $request->effectiveActor() === null || self::actorKey($request->realActor()) === self::actorKey($request->effectiveActor())) return $this->support->decision($request, PrivilegedAccessReason::ACTOR_SEPARATION_REQUIRED, ['real_actor', 'effective_actor'], ['distinct_actors']);
        if ($request->affectedSubject() === null) return $this->support->decision($request, PrivilegedAccessReason::AUTHORIZATION_DENIED, ['affected_subject'], ['affected_subject']);
        if ($request->caseReference() === null) return $this->support->decision($request, PrivilegedAccessReason::CASE_REQUIRED, ['case'], ['case_reference']);
        if ($request->reasonReference() === null) return $this->support->decision($request, PrivilegedAccessReason::REASON_REQUIRED, ['reason'], ['reason_reference']);
        if (($reason = $this->support->scopeReason($request->scopes())) !== null) return $this->support->decision($request, $reason, ['scopes'], [$reason]);
        if (($reason = $this->support->temporalReason($request, $nowUtc)) !== null) return $this->support->decision($request, $reason, ['expiration'], [$reason]);
        if (!$request->reauthenticationVerified()) return $this->support->decision($request, PrivilegedAccessReason::REAUTHENTICATION_REQUIRED, ['reauthentication'], ['reauthentication_verified']);
        if (!$request->mfaVerified()) return $this->support->decision($request, PrivilegedAccessReason::MFA_REQUIRED, ['mfa'], ['mfa_verified']);
        if (($reason = $this->support->approvalReason($request, 1, $nowUtc)) !== null) return $this->support->decision($request, $reason, ['approval'], [$reason]);
        if (!$request->visibilityRequired()) return $this->support->decision($request, PrivilegedAccessReason::VISIBILITY_REQUIRED, ['visibility'], ['visibility_required']);
        if ($request->clinicalAccessRequested()) return $this->support->decision($request, PrivilegedAccessReason::CLINICAL_ACCESS_DENIED, ['clinical_boundary'], ['clinical_access_disabled']);
        if (($reason = $this->support->authorizationReason($request, $authorization)) !== null) return $this->support->decision($request, $reason, ['central_authorization'], [$reason]);
        $auditStatus = $this->support->auditStatus($request, 'allow', PrivilegedAccessReason::POLICY_SATISFIED_NOT_ACTIVATABLE, $nowUtc, $audit);
        if ($auditStatus === AuditWriteResult::UNAVAILABLE) return $this->support->decision($request, PrivilegedAccessReason::AUDIT_UNAVAILABLE, ['audit'], ['audit_available'], false, $auditStatus);
        if ($auditStatus !== AuditWriteResult::ACCEPTED) return $this->support->decision($request, PrivilegedAccessReason::AUDIT_REQUIRED, ['audit'], ['audit_accepted'], false, $auditStatus);
        return $this->support->decision($request, PrivilegedAccessReason::POLICY_SATISFIED_NOT_ACTIVATABLE, ['feature_flag', 'actors', 'case', 'reason', 'scopes', 'expiration', 'reauthentication', 'mfa', 'approval', 'visibility', 'authorization', 'audit'], [], true, $auditStatus, 'support_assisted_policy_satisfied');
    }

    private static function actorKey(\Platform\Contracts\ActorReference $actor): string { return $actor->kind() . ':' . $actor->id(); }
}
