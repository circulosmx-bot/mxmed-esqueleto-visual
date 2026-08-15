<?php
declare(strict_types=1);

namespace Platform\Audit\Read;

use Platform\Audit\Read\Contracts\SelfSecuritySubjectResolverPort;
use Platform\Services\CanonicalAuditPolicyRegistry;

/** Canonical eligibility plus a separate fail-closed trusted subject boundary. */
final class SelfSecurityTimelinePolicy
{
    public function __construct(
        private CanonicalAuditPolicyRegistry $canonicalPolicies,
        private SelfSecuritySubjectResolverPort $subjects,
    ) {}

    public function isEligible(string $eventType): bool
    {
        try {
            return ($this->canonicalPolicies->policyFor($eventType)['self_timeline'] ?? null) === true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function assertEligibleRow(array $row, string $trustedSelfIdentityId): void
    {
        $eventType = $row['event_type'] ?? null;
        if (!is_string($eventType) || !$this->isEligible($eventType)) {
            throw new \DomainException('event_not_eligible_for_self_timeline');
        }
        $this->subjects->assertBelongsToSelf($row, $trustedSelfIdentityId);
    }

    /** @return list<string> */
    public function eligibleEventTypes(): array
    {
        $eligible = [];
        foreach (CanonicalAuditPolicyRegistry::canonicalRows() as $row) {
            if (($row['self_timeline'] ?? null) === true) {
                $eligible[] = $row['event_type'];
            }
        }
        return $eligible;
    }
}
