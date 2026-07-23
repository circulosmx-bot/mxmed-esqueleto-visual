<?php
declare(strict_types=1);

namespace Patients\Composition;

use Identity\Contracts\AccountMembership;
use Identity\Contracts\AccountStatus;
use Identity\Contracts\AuthenticatedAccessContext;
use Identity\Contracts\CanonicalProfileReference;
use Identity\Contracts\MembershipRole;
use Identity\Contracts\MembershipStatus;
use Identity\Contracts\SessionState;
use Platform\Contracts\ActorReference;
use Platform\Contracts\AuthorizationContext;
use Platform\Contracts\AuthorizationDecision;
use Platform\Contracts\AuthorizationPlane;
use Platform\Contracts\AuthorizationRequirement;
use Platform\Contracts\CapabilitySet;
use Platform\Contracts\RiskLevel;
use Platform\Contracts\SafeIdentifier;
use Platform\Contracts\ScopeSet;
use Platform\Contracts\SessionReference;
use Platform\Contracts\SubjectReference;
use Platform\Contracts\TrustedAuthorizationContext;
use Platform\Services\AuthorizationBoundary;

/**
 * Side-effect-free Patients authority composition for deterministic harnesses.
 *
 * Doctor and patient identifiers are authorization targets only. Authority is
 * derived from the validated Identity context and backend membership inputs.
 */
final class PatientsAuthorityCompositionRoot
{
    public function __construct(private AuthorizationBoundary $authorizationBoundary) {}

    /** Only literal server-side boolean true is eligible; every other value fails closed. */
    public static function canonicalActorAuthorityEnabled(array $serverConfig): bool
    {
        return ($serverConfig['feature_flags']['canonical_actor_authority'] ?? false) === true;
    }

    public function resolveServerAuthority(
        AuthenticatedAccessContext $identityContext,
        AccountMembership $membership,
        CanonicalProfileReference $requestedDoctor,
        string $patientId,
        string $ownership,
        string $action,
        string $correlationId,
        string $requestId
    ): AuthorizationDecision {
        $principal = $identityContext->principal();
        $session = $identityContext->session();
        $patientTarget = (new SafeIdentifier($patientId))->value();
        $action = (new SafeIdentifier($action))->value();
        $correlationId = (new SafeIdentifier($correlationId))->value();
        $requestId = (new SafeIdentifier($requestId))->value();

        $principalMatchesSession = $session->principal()->accountId() === $principal->accountId();
        $membershipMatchesPrincipal = $membership->accountId() === $principal->accountId();
        $doctorMatchesMembership = $requestedDoctor->isProfile()
            && $membership->reference()->isProfile()
            && $membership->reference()->targetId() === $requestedDoctor->targetId();
        $serverBindingValid = $principalMatchesSession
            && $membershipMatchesPrincipal
            && $doctorMatchesMembership;
        $resolvedOwnership = $serverBindingValid && in_array($ownership, ['owner', 'member'], true)
            ? $ownership
            : 'denied';

        $realActor = new ActorReference('account', $principal->accountId());
        $doctorTarget = $doctorMatchesMembership ? $requestedDoctor->targetId() : null;
        $context = new AuthorizationContext(
            realActor: $realActor,
            effectiveActor: $realActor,
            affectedSubject: new SubjectReference('patient', $patientTarget),
            sessionReference: new SessionReference((string)$session->sessionId()),
            accountId: $principal->accountId(),
            credentialVersion: $principal->credentialVersion(),
            membershipId: $membership->membershipId(),
            entityId: $doctorTarget,
            profileId: $doctorTarget,
            ownership: $resolvedOwnership,
            role: $serverBindingValid ? $membership->role() : null,
            scopes: new ScopeSet($serverBindingValid ? [$membership->scope()] : []),
            capabilities: new CapabilitySet(),
            action: $action,
            resource: 'patients',
            authorizationPlane: AuthorizationPlane::CUSTOMER_PROFESSIONAL,
            riskLevel: RiskLevel::R0,
            correlationId: $correlationId,
            requestId: $requestId
        );
        $trustedContext = TrustedAuthorizationContext::fromBackend(
            $context,
            'patients_backend_resolver',
            self::sessionStatus($session->state()),
            $principalMatchesSession && $principal->accountStatus() === AccountStatus::ACTIVE,
            $serverBindingValid
                && $membership->status() === MembershipStatus::ACTIVE
                && $membership->grantsAuthority()
        );
        $requirement = new AuthorizationRequirement(
            AuthorizationPlane::CUSTOMER_PROFESSIONAL,
            RiskLevel::R0,
            $action,
            'patients',
            $patientTarget,
            true,
            true,
            true,
            true,
            true,
            MembershipRole::all(),
            new ScopeSet([$membership->scope()]),
            new CapabilitySet()
        );

        return $this->authorizationBoundary->authorize($trustedContext, $requirement);
    }

    private static function sessionStatus(string $state): string
    {
        return match ($state) {
            SessionState::ACTIVE => 'active',
            SessionState::REVOKED => 'revoked',
            SessionState::SUPERSEDED => 'superseded',
            SessionState::IDLE_EXPIRED, SessionState::ABSOLUTE_EXPIRED => 'expired',
            default => 'invalid',
        };
    }
}
