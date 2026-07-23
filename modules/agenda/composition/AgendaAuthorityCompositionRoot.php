<?php
declare(strict_types=1);

namespace Agenda\Composition;

use Agenda\Security\AgendaActorAuthorityResolver;
use Agenda\Security\AgendaAuthorizationTarget;
use Agenda\Security\AgendaAuthorityResolution;
use Agenda\Security\ClientAuthorityClaims;
use Agenda\Security\OperatorBinding;
use Identity\Contracts\AccountMembership;
use Identity\Contracts\AuthenticatedAccessContext;
use Identity\Contracts\CanonicalProfileReference;
use Platform\Contracts\AuditTrailPort;
use Platform\Services\AuthorizationBoundary;

/**
 * Pure composition boundary for the canonical Agenda authority contracts.
 *
 * CUT01-A only makes this root loadable by deterministic harnesses. Runtime
 * dispatch remains legacy while canonical_actor_authority is not activated.
 */
final class AgendaAuthorityCompositionRoot
{
    private AgendaActorAuthorityResolver $resolver;

    public function __construct(
        AuthorizationBoundary $authorizationBoundary,
        ?AuditTrailPort $auditTrail = null
    ) {
        $this->resolver = new AgendaActorAuthorityResolver($authorizationBoundary, $auditTrail);
    }

    /** Only literal server-side boolean true is eligible; every other value fails closed. */
    public static function canonicalActorAuthorityEnabled(array $serverConfig): bool
    {
        return ($serverConfig['feature_flags']['canonical_actor_authority'] ?? false) === true;
    }

    public function resolver(): AgendaActorAuthorityResolver
    {
        return $this->resolver;
    }

    public function resolve(
        ?AuthenticatedAccessContext $identityContext,
        ?AccountMembership $membership,
        ?CanonicalProfileReference $requestedProfile,
        AgendaAuthorizationTarget $target,
        ?OperatorBinding $operatorBinding = null,
        ?ClientAuthorityClaims $clientClaims = null
    ): AgendaAuthorityResolution {
        return $this->resolver->resolve(
            $identityContext,
            $membership,
            $requestedProfile,
            $target,
            $operatorBinding,
            $clientClaims
        );
    }
}
