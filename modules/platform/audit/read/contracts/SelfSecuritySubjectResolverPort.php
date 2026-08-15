<?php
declare(strict_types=1);

namespace Platform\Audit\Read\Contracts;

/**
 * Trusted backend boundary for proving that a canonical audit subject belongs
 * to the authenticated account. Unknown or unresolvable relationships deny.
 */
interface SelfSecuritySubjectResolverPort
{
    /** @param array<string,mixed> $canonicalAuditRow */
    public function assertBelongsToSelf(array $canonicalAuditRow, string $trustedAccountIdentityId): void;
}
