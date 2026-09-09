<?php
declare(strict_types=1);
namespace Identity\Services;
use Identity\Http\CanonicalHttpSessionResolver;
use Identity\Repositories\InternalOperatorGrantRepository;
use Platform\Contracts\{TrustedAuthorizationContext,AuthorizationContext,AuthorizationPlane,ActorReference,SessionReference};

final class InternalOperatorAuthority
{
    public function __construct(private CanonicalHttpSessionResolver $sessions, private InternalOperatorGrantRepository $grants) {}
    public function resolve(array $cookies, string $action, string $resource, string $risk): ?TrustedAuthorizationContext
    {
        $authenticated = $this->sessions->resolve($cookies);
        if ($authenticated === null) return null;
        $capabilities = $this->grants->activeCapabilities($authenticated->accountId());
        if ($capabilities->isEmpty()) return null;
        $actor = new ActorReference('account', $authenticated->accountId());
        $context = new AuthorizationContext(
            realActor: $actor, effectiveActor: $actor,
            sessionReference: new SessionReference((string)$authenticated->session()->sessionId()),
            accountId: $authenticated->accountId(), credentialVersion: $authenticated->principal()->credentialVersion(),
            capabilities: $capabilities, action: $action, resource: $resource,
            authorizationPlane: AuthorizationPlane::INTERNAL_OPERATOR, riskLevel: $risk
        );
        return TrustedAuthorizationContext::fromBackend($context,'canonical_internal_operator', 'active',true,false);
    }
}
