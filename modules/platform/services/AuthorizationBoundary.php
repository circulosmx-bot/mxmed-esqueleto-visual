<?php
declare(strict_types=1);

namespace Platform\Services;

use Platform\Contracts\AuditAvailability;
use Platform\Contracts\AuditEventReference;
use Platform\Contracts\AuditTrailPort;
use Platform\Contracts\AuditWriteResult;
use Platform\Contracts\AuthorizationContext;
use Platform\Contracts\AuthorizationDecision;
use Platform\Contracts\AuthorizationPlane;
use Platform\Contracts\AuthorizationRequirement;
use Platform\Contracts\ReasonCode;
use Platform\Contracts\RiskLevel;
use Platform\Contracts\TrustedAuthorizationContext;

/** Pure, deterministic authorization boundary; no HTTP, globals, storage or business mutation. */
final class AuthorizationBoundary
{
    public function authorize(AuthorizationContext|TrustedAuthorizationContext|null $context, AuthorizationRequirement $requirement, ?AuditTrailPort $audit = null): AuthorizationDecision
    {
        $evaluated = [];
        $missing = [];
        if ($context === null) return AuthorizationDecision::deny($requirement->riskLevel(), ReasonCode::AUTH_CONTEXT_MISSING, $evaluated, ['trusted_context']);
        if ($context instanceof AuthorizationContext) return AuthorizationDecision::deny($requirement->riskLevel(), ReasonCode::CLIENT_IDENTITY_NOT_AUTHORITATIVE, $evaluated, ['trusted_context']);
        if (!$context->isTrusted()) return AuthorizationDecision::deny($requirement->riskLevel(), ReasonCode::CLIENT_IDENTITY_NOT_AUTHORITATIVE, $evaluated, ['trusted_context']);
        if (!$context->clientIdentityAuthoritative()) return AuthorizationDecision::deny($requirement->riskLevel(), ReasonCode::CLIENT_IDENTITY_NOT_AUTHORITATIVE, $evaluated, ['backend_identity']);
        if ($context->transitionalOpen()) return AuthorizationDecision::deny($requirement->riskLevel(), ReasonCode::TRANSITIONAL_OPEN_DENIED, $evaluated, ['strict_mode']);

        $subject = $context->context();
        if ($requirement->actorAuthenticatedRequired()) {
            if ($context->sessionStatus() === null || $subject->sessionReference() === null) return $this->deny($requirement->riskLevel(), ReasonCode::SESSION_MISSING, $evaluated, ['session']);
            if ($context->sessionStatus() === 'invalid') return $this->deny($requirement->riskLevel(), ReasonCode::SESSION_INVALID, $evaluated, ['valid_session']);
            if ($context->sessionStatus() === 'expired') return $this->deny($requirement->riskLevel(), ReasonCode::SESSION_EXPIRED, $evaluated, ['active_session']);
            if (in_array($context->sessionStatus(), ['revoked', 'superseded'], true)) return $this->deny($requirement->riskLevel(), ReasonCode::SESSION_REVOKED, $evaluated, ['non_revoked_session']);
            $evaluated[] = 'session';
            if ($subject->credentialVersion() === null) return $this->deny($requirement->riskLevel(), ReasonCode::CREDENTIAL_VERSION_MISMATCH, $evaluated, ['credential_version']);
            $evaluated[] = 'credential_version';
            if ($subject->accountId() === null || $context->accountActive() !== true) return $this->deny($requirement->riskLevel(), ReasonCode::ACCOUNT_INACTIVE, $evaluated, ['active_account']);
            $evaluated[] = 'account';
        }
        if ($requirement->membershipRequired()) {
            if ($subject->membershipId() === null) return $this->deny($requirement->riskLevel(), ReasonCode::MEMBERSHIP_MISSING, $evaluated, ['membership']);
            if ($context->membershipActive() !== true) return $this->deny($requirement->riskLevel(), ReasonCode::MEMBERSHIP_INACTIVE, $evaluated, ['active_membership']);
            $evaluated[] = 'membership';
        }
        if ($requirement->entityProfileRequired()) {
            if ($subject->entityId() === null) return $this->deny($requirement->riskLevel(), ReasonCode::ENTITY_UNRESOLVED, $evaluated, ['entity']);
            if ($subject->profileId() === null) return $this->deny($requirement->riskLevel(), ReasonCode::PROFILE_UNRESOLVED, $evaluated, ['profile']);
            $evaluated[] = 'entity';
            $evaluated[] = 'profile';
        }
        if ($requirement->authorizationPlane() !== $subject->authorizationPlane()) return $this->deny($requirement->riskLevel(), ReasonCode::AUTHORIZATION_PLANE_MISMATCH, $evaluated, ['plane']);
        if ($requirement->authorizationPlane() === AuthorizationPlane::PUBLIC_SYSTEM && !$requirement->publicRouteDeclared() && !$requirement->systemRouteDeclared()) {
            $reason = $requirement->actorAuthenticatedRequired() ? ReasonCode::SYSTEM_ROUTE_NOT_DECLARED : ReasonCode::PUBLIC_ROUTE_NOT_DECLARED;
            return $this->deny($requirement->riskLevel(), $reason, $evaluated, ['declared_route']);
        }
        if ($requirement->ownershipRequired() && ($subject->ownership() === null || $subject->ownership() === 'denied')) return $this->deny($requirement->riskLevel(), ReasonCode::OWNERSHIP_DENIED, $evaluated, ['ownership']);
        if ($requirement->ownershipRequired()) $evaluated[] = 'ownership';
        if ($requirement->rolesAccepted() !== [] && ($subject->role() === null || !in_array($subject->role(), $requirement->rolesAccepted(), true))) return $this->deny($requirement->riskLevel(), ReasonCode::ROLE_DENIED, $evaluated, ['role']);
        if ($requirement->rolesAccepted() !== []) $evaluated[] = 'role';
        foreach ($requirement->scopesRequired()->values() as $scope) {
            if (!$subject->scopes()->contains($scope)) return $this->deny($requirement->riskLevel(), ReasonCode::SCOPE_DENIED, $evaluated, ['scope:' . $scope]);
        }
        if (!$requirement->scopesRequired()->isEmpty()) $evaluated[] = 'scope';
        foreach ($requirement->capabilitiesRequired()->values() as $capability) {
            if (!$subject->capabilities()->contains($capability)) return $this->deny($requirement->riskLevel(), ReasonCode::CAPABILITY_DENIED, $evaluated, ['capability:' . $capability]);
        }
        if (!$requirement->capabilitiesRequired()->isEmpty()) $evaluated[] = 'capability';
        if ($subject->action() !== $requirement->action()) return $this->deny($requirement->riskLevel(), ReasonCode::ACTION_UNSUPPORTED, $evaluated, ['action']);
        $evaluated[] = 'action';
        if ($subject->resource() !== $requirement->resourceType()) return $this->deny($requirement->riskLevel(), ReasonCode::RESOURCE_UNRESOLVED, $evaluated, ['resource']);
        if ($requirement->resourceIdRequired() && $requirement->resourceId() === null) return $this->deny($requirement->riskLevel(), ReasonCode::RESOURCE_UNRESOLVED, $evaluated, ['resource_id']);
        $evaluated[] = 'resource';
        if ($subject->riskLevel() !== $requirement->riskLevel()) return $this->deny($requirement->riskLevel(), ReasonCode::RISK_REQUIREMENT_UNSATISFIED, $evaluated, ['risk_level']);
        $evaluated[] = 'risk';
        if ($requirement->caseRequired() && $subject->caseId() === null) return $this->deny($requirement->riskLevel(), ReasonCode::CASE_REQUIRED, $evaluated, ['case']);
        if ($requirement->caseRequired()) $evaluated[] = 'case';
        if ($requirement->reauthenticationRequired() && !$context->reauthenticated()) return $this->deny($requirement->riskLevel(), ReasonCode::REAUTHENTICATION_REQUIRED, $evaluated, ['reauthentication']);
        if ($requirement->reauthenticationRequired()) $evaluated[] = 'reauthentication';
        if ($requirement->mfaRequired() && !$context->mfaVerified()) return $this->deny($requirement->riskLevel(), ReasonCode::MFA_REQUIRED, $evaluated, ['mfa']);
        if ($requirement->mfaRequired()) $evaluated[] = 'mfa';
        if ($requirement->approvalRequired() && $subject->approvalReferences()->isEmpty()) return $this->deny($requirement->riskLevel(), ReasonCode::APPROVAL_REQUIRED, $evaluated, ['approval']);
        if ($requirement->approvalRequired()) $evaluated[] = 'approval';
        if ($requirement->dualApprovalRequired() && count($subject->approvalReferences()->values()) < 2) return $this->deny($requirement->riskLevel(), ReasonCode::APPROVAL_REQUIRED, $evaluated, ['dual_approval']);
        if ($requirement->dualApprovalRequired()) $evaluated[] = 'dual_approval';
        if ($requirement->auditTrailRequired()) {
            if ($audit === null || $audit->availability() !== AuditAvailability::AVAILABLE) return $this->deny($requirement->riskLevel(), ReasonCode::AUDIT_UNAVAILABLE, $evaluated, ['audit']);
            $event = new AuditEventReference($requirement->action(), $requirement->riskLevel(), $subject->realActor(), $subject->effectiveActor(), $subject->affectedSubject(), $subject->correlationId(), $subject->requestId(), AuditWriteResult::ACCEPTED, ['resource_type' => $requirement->resourceType(), 'decision' => 'allow']);
            $auditResult = $audit->write($event);
            if ($auditResult === AuditWriteResult::UNAVAILABLE) return $this->deny($requirement->riskLevel(), ReasonCode::AUDIT_UNAVAILABLE, $evaluated, ['audit']);
            if ($auditResult !== AuditWriteResult::ACCEPTED) return $this->deny($requirement->riskLevel(), ReasonCode::AUDIT_REQUIRED, $evaluated, ['audit']);
            $evaluated[] = 'audit';
        }
        return AuthorizationDecision::allow($requirement->riskLevel(), 'all_requirements_satisfied', $evaluated, $subject->correlationId());
    }

    /** @param list<string> $evaluated @param list<string> $missing */
    private function deny(string $riskLevel, string $reason, array $evaluated, array $missing): AuthorizationDecision
    {
        return AuthorizationDecision::deny($riskLevel, $reason, $evaluated, $missing);
    }
}
