<?php
declare(strict_types=1);

namespace Platform\Audit;

use Platform\Services\CanonicalAuditPolicyRegistry;

/** Director-ratified finite, versioned backend catalog; version 1 is empty. */
final class SensitiveAdminActionCatalog
{
    public const VERSION = 'mp01f-sensitive-admin-v1';
    public const FREE_FORM_ALLOWED = false;
    public const UNKNOWN_KEY_ALLOWED = false;
    public const DUPLICATE_EMISSION = false;

    /** @var array<string,array{target_type:string,authority:string}> */
    private const ACTIONS = [];

    private const SPECIFIC_CANONICAL_EVENTS = [
        'PROFILE_CLAIM_REQUESTED',
        'PROFILE_CLAIM_APPROVED',
        'PROFILE_CLAIM_REJECTED',
        'PROFILE_OWNERSHIP_ASSIGNED',
        'PROFILE_OWNERSHIP_TRANSFERRED',
        'INVITATION_CREATED',
        'INVITATION_ACCEPTED',
        'INVITATION_REVOKED',
        'ROLE_ASSIGNED',
        'ROLE_REVOKED',
        'STEP_UP_CHALLENGE_SUCCEEDED',
        'STEP_UP_CHALLENGE_FAILED',
        'BREAK_GLASS_STARTED',
        'BREAK_GLASS_ENDED',
    ];

    /** @return array<string,array{target_type:string,authority:string}> */
    public function all(): array
    {
        return self::ACTIONS;
    }

    /** @return array{target_type:string,authority:string} */
    public function definition(string $actionKey): array
    {
        if (in_array($actionKey, self::SPECIFIC_CANONICAL_EVENTS, true)) {
            throw new \InvalidArgumentException('specific_canonical_event_must_not_be_duplicated');
        }
        if (preg_match('/^[A-Z][A-Z0-9_]{2,63}$/D', $actionKey) !== 1) {
            throw new \InvalidArgumentException('invalid_sensitive_admin_action_key');
        }
        return self::ACTIONS[$actionKey]
            ?? throw new \InvalidArgumentException('unknown_sensitive_admin_action_key');
    }

    public function assertPolicyCompatibility(CanonicalAuditPolicyRegistry $policies): void
    {
        $allowed = $policies->policyFor('SENSITIVE_ADMIN_ACTION')['allowed_producer_metadata'];
        if (self::ACTIONS !== [] && !in_array('action_code', $allowed, true)) {
            throw new \LogicException('sensitive_admin_action_code_policy_not_ratified');
        }
    }
}
