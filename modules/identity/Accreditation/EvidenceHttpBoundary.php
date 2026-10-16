<?php
declare(strict_types=1);
namespace Identity\Accreditation;

use Identity\Contracts\AuthenticatedAccessContext;
use Identity\Http\{GovernanceHttpSecurity,IdentityHttpComposition};

/** Future original-app integration must pass this gate before any callable mutation.
 * No new route is installed. HMAC-authenticated HTTPS proxy, exact origin, canonical
 * session cookie and authenticated CSRF reuse the existing Identity implementation.
 */
final class EvidenceHttpBoundary
{
    public static function access(IdentityHttpComposition $identity,array $server,array $cookies,array $body,string $raw,string $transportKey): AuthenticatedAccessContext
    {
        return GovernanceHttpSecurity::access($identity->sessions(),$identity->csrf(),$identity->allowedOrigin(),$server,$cookies,$body,$raw,$transportKey);
    }
}
