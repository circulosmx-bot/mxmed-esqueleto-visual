<?php
declare(strict_types=1);

namespace Agenda\Security;

use Agenda\Contracts\ActorAuthorityContract;
use Agenda\Contracts\ActorReference as AgendaActorReference;
use Identity\Contracts\AccountMembership;
use Identity\Contracts\AccountStatus;
use Identity\Contracts\AuthenticatedAccessContext;
use Identity\Contracts\CanonicalProfileReference;
use Identity\Contracts\MembershipRole;
use Identity\Contracts\MembershipStatus;
use Identity\Contracts\SessionState;
use Platform\Contracts\ActorReference as PlatformActorReference;
use Platform\Contracts\AuthorizationContext;
use Platform\Contracts\AuthorizationPlane;
use Platform\Contracts\AuthorizationRequirement;
use Platform\Contracts\AuditTrailPort;
use Platform\Contracts\CapabilitySet;
use Platform\Contracts\ReasonCode;
use Platform\Contracts\ScopeSet;
use Platform\Contracts\SessionReference;
use Platform\Contracts\SubjectReference;
use Platform\Contracts\TrustedAuthorizationContext;
use Platform\Services\AuthorizationBoundary;

/** Resolves Agenda authority strictly from validated Identity and backend bindings. */
final class AgendaActorAuthorityResolver
{
    public function __construct(private AuthorizationBoundary $authorizationBoundary, private ?AuditTrailPort $auditPort) {}

    public function resolve(
        ?AuthenticatedAccessContext $identityContext,
        ?AccountMembership $membership,
        ?CanonicalProfileReference $requestedProfile,
        AgendaAuthorizationTarget $target,
        ?OperatorBinding $operatorBinding = null,
        ?ClientAuthorityClaims $clientClaims = null
    ): AgendaAuthorityResolution {
        $claims = $clientClaims ?? ClientAuthorityClaims::none();
        $emptyDiagnostic = $claims->diagnostic([
            'role_mismatch' => false,
            'actor_mismatch' => false,
            'profile_mismatch' => false,
            'operator_mismatch' => false,
            'attempt_detected' => false,
        ]);

        if ($identityContext === null) return AgendaAuthorityResolution::deny(401, 'session_missing', $emptyDiagnostic);
        $principal = $identityContext->principal();
        $session = $identityContext->session();
        if ($session->state() !== SessionState::ACTIVE) return AgendaAuthorityResolution::deny(401, 'session_invalid', $emptyDiagnostic);
        if ($principal->accountStatus() !== AccountStatus::ACTIVE || $session->principal()->accountId() !== $principal->accountId()) {
            return AgendaAuthorityResolution::deny(401, 'session_invalid', $emptyDiagnostic);
        }
        if ($membership === null) return AgendaAuthorityResolution::deny(403, 'membership_missing', $emptyDiagnostic);
        if ($membership->accountId() !== $principal->accountId()) return AgendaAuthorityResolution::deny(403, 'membership_account_mismatch', $emptyDiagnostic);
        if (!$membership->grantsAuthority() || $membership->status() !== MembershipStatus::ACTIVE) return AgendaAuthorityResolution::deny(403, 'membership_inactive', $emptyDiagnostic);
        if ($requestedProfile === null || !$requestedProfile->isProfile() || !$membership->reference()->isProfile()) {
            return AgendaAuthorityResolution::deny(403, 'profile_unresolved', $emptyDiagnostic);
        }
        if ($requestedProfile->targetId() !== $membership->reference()->targetId() || $target->profileId() !== $requestedProfile->targetId()) {
            return AgendaAuthorityResolution::deny(403, 'profile_mismatch', $emptyDiagnostic);
        }

        $rule = PrivateAgendaRoutePolicy::find($target->resource());
        if ($rule === null || !$rule->allowsMethod($target->method()) || !$rule->failClosed()) {
            return AgendaAuthorityResolution::deny(403, 'private_route_denied', $emptyDiagnostic);
        }
        if ($target->action() !== $rule->action()) return AgendaAuthorityResolution::deny(403, 'action_denied', $emptyDiagnostic);
        if ($target->requestedScope() !== $membership->scope() || $target->requestedScope() !== $rule->scope()) {
            return AgendaAuthorityResolution::deny(403, 'scope_denied', $emptyDiagnostic);
        }

        $role = $membership->role();
        $diagnostic = $claims->diagnostic($claims->mismatchAgainst($role, $principal->accountId(), $requestedProfile->targetId(), $operatorBinding));
        if ($rule->ownerOnly() && $role !== MembershipRole::OWNER) {
            return AgendaAuthorityResolution::deny(403, 'ownership_denied', $diagnostic);
        }
        if (!in_array($role, $rule->allowedRoles(), true)) return AgendaAuthorityResolution::deny(403, 'role_denied', $diagnostic);
        if ($operatorBinding !== null) {
            if (!$rule->operatorAllowed()) return AgendaAuthorityResolution::deny(403, 'operator_route_denied', $diagnostic);
            if (!$operatorBinding->isUsableFor($principal->accountId(), $requestedProfile->targetId(), $target->requestedScope())) {
                return AgendaAuthorityResolution::deny(403, 'operator_binding_invalid', $diagnostic);
            }
        } elseif ($role === MembershipRole::COLLABORATOR) {
            return AgendaAuthorityResolution::deny(403, 'operator_binding_required', $diagnostic);
        }

        $ownership = $role === MembershipRole::OWNER ? 'owner' : 'member';
        $realAgendaActor = new AgendaActorReference('account', $principal->accountId());
        $effectiveId = $operatorBinding?->operatorId() ?? $principal->accountId();
        $effectiveKind = $operatorBinding === null ? 'account' : 'operator';
        $effectiveAgendaActor = new AgendaActorReference($effectiveKind, $effectiveId);
        $agendaContext = new AgendaServerAuthorityContext(
            $identityContext,
            $membership,
            $requestedProfile,
            $realAgendaActor,
            $effectiveAgendaActor,
            $ownership,
            $target->requestedScope(),
            $target->correlationId(),
            $target->requestId()
        );

        $realPlatformActor = new PlatformActorReference('account', $agendaContext->accountId());
        $effectivePlatformActor = new PlatformActorReference($effectiveKind, $effectiveId);
        $platformContext = new AuthorizationContext(
            realActor: $realPlatformActor,
            effectiveActor: $effectivePlatformActor,
            affectedSubject: new SubjectReference('profile', $agendaContext->profileId()),
            sessionReference: new SessionReference((string)$session->sessionId()),
            accountId: $agendaContext->accountId(),
            credentialVersion: $principal->credentialVersion(),
            membershipId: $agendaContext->membershipId(),
            entityId: $agendaContext->profileId(),
            profileId: $agendaContext->profileId(),
            ownership: $agendaContext->ownership(),
            role: $agendaContext->role(),
            scopes: new ScopeSet([$agendaContext->scope()]),
            capabilities: new CapabilitySet(),
            action: $rule->action(),
            resource: $target->resource(),
            authorizationPlane: AuthorizationPlane::CUSTOMER_PROFESSIONAL,
            riskLevel: $rule->risk(),
            correlationId: $agendaContext->correlationId(),
            requestId: $agendaContext->requestId()
        );
        $trustedContext = TrustedAuthorizationContext::fromBackend($platformContext, AgendaServerAuthorityContext::SOURCE_BACKEND_RESOLVER, 'active', true, true);
        $requirement = new AuthorizationRequirement(
            AuthorizationPlane::CUSTOMER_PROFESSIONAL,
            $rule->risk(),
            $rule->action(),
            $target->resource(),
            $agendaContext->profileId(),
            true,
            true,
            true,
            true,
            $rule->ownershipRequired(),
            $rule->allowedRoles(),
            new ScopeSet([$rule->scope()]),
            new CapabilitySet()
        );
        $decision = $this->authorizationBoundary->authorize($trustedContext, $requirement, $this->auditPort);
        if (!$decision->allowed()) {
            $status = in_array($decision->reasonCode(), [ReasonCode::AUDIT_UNAVAILABLE, ReasonCode::AUDIT_REQUIRED], true) ? 503 : 403;
            return AgendaAuthorityResolution::deny($status, $decision->reasonCode(), $diagnostic, $decision);
        }

        $authority = ActorAuthorityContract::trusted(
            $realAgendaActor,
            $effectiveAgendaActor,
            $agendaContext->accountId(),
            $agendaContext->membershipId(),
            $agendaContext->role(),
            $agendaContext->scope(),
            $agendaContext->ownership()
        );
        return AgendaAuthorityResolution::allow($authority, $trustedContext, $decision, $diagnostic);
    }
}
