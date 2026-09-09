<?php
declare(strict_types=1);
namespace Media\Services;
require_once __DIR__.'/../../identity/http/IdentityHttpComposition.php';
\Identity\Http\IdentityHttpComposition::registerAutoloader();
use Platform\Contracts\{AuthorizationContext,TrustedAuthorizationContext,AuthorizationRequirement,AuthorizationPlane,CapabilitySet,RiskLevel};
use Platform\Services\AuthorizationBoundary;

final class MediaReviewAuthority
{
    public const CAPABILITY = 'media_review_read';
    public static function requirement(): AuthorizationRequirement
    {
        return new AuthorizationRequirement(
            authorizationPlane: AuthorizationPlane::INTERNAL_OPERATOR,
            riskLevel: RiskLevel::R0,
            action: 'read', resourceType: 'media_review_submission',
            actorAuthenticatedRequired: true,
            capabilitiesRequired: new CapabilitySet([self::CAPABILITY])
        );
    }
    public static function requireRead(AuthorizationContext|TrustedAuthorizationContext|null $context): void
    {
        if (!(new AuthorizationBoundary())->authorize($context, self::requirement())->allowed()) {
            throw new \RuntimeException('review_access_denied');
        }
    }
}
