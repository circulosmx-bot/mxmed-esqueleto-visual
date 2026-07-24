<?php
declare(strict_types=1);

namespace Platform\Shadow;

final readonly class R0ShadowSafeReturnPlan
{
    public function __construct(private string $trigger)
    {
        R0ShadowHardStop::assertEligible($trigger);
    }

    public function trigger(): string
    {
        return $this->trigger;
    }

    public function toArray(): array
    {
        return [
            'trigger' => $this->trigger,
            'source_stage' => 'R0',
            'source_mode' => 'disabled',
            'target_stage' => 'R0',
            'target_mode' => 'disabled',
            'new_evaluations_allowed' => false,
            'legacy_continues' => true,
            'canonical_response_allowed' => false,
            'canonical_write_allowed' => false,
            'preserve_sanitized_evidence' => true,
            'sql_rollback_required' => false,
            'database_action' => 'none',
            'clinical_action' => 'none',
            'otp_action' => 'none',
        ];
    }
}
