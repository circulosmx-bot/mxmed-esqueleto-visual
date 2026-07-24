<?php
declare(strict_types=1);

namespace Platform\Shadow;

use InvalidArgumentException;

final readonly class R0ShadowEvaluationResult
{
    private const SURFACES = [
        'canonical_actor_authority',
        'canonical_schedule_read',
        'canonical_availability_compare',
        'canonical_appointment_lifecycle',
        'canonical_patient_identity',
    ];

    public function __construct(
        private string $surface,
        private string $legacyOutcomeCode,
        private string $canonicalOutcomeCode,
        private string $reasonCode,
        private string $correlationRef,
        private string $scopeRef,
        private string $legacyDigest,
        private string $canonicalDigest,
        private bool $legacyInvariant,
        private ?string $hardStop,
        private ?R0ShadowSafeReturnPlan $safeReturn
    ) {
        if (!in_array($surface, self::SURFACES, true)) {
            throw new InvalidArgumentException('UNKNOWN_OPERATION');
        }
        foreach ([$legacyOutcomeCode, $canonicalOutcomeCode, $reasonCode] as $code) {
            if (preg_match('/\A[A-Z][A-Z0-9_]*\z/', $code) !== 1) {
                throw new InvalidArgumentException('invalid_r0_shadow_result_code');
            }
        }
        foreach ([$correlationRef, $scopeRef] as $reference) {
            if (preg_match('/\Atest:[a-z0-9][a-z0-9._:-]*\z/', $reference) !== 1) {
                throw new InvalidArgumentException('invalid_r0_shadow_test_reference');
            }
        }
        foreach ([$legacyDigest, $canonicalDigest] as $digest) {
            if (preg_match('/\A[a-f0-9]{64}\z/', $digest) !== 1) {
                throw new InvalidArgumentException('invalid_r0_shadow_digest');
            }
        }
        if ($hardStop === null && (!$legacyInvariant || $safeReturn !== null)) {
            throw new InvalidArgumentException('invalid_invariant_without_hard_stop');
        }
        if ($hardStop !== null) {
            R0ShadowHardStop::assertEligible($hardStop);
            if (
                $legacyInvariant
                || $reasonCode !== $hardStop
                || $safeReturn === null
                || $safeReturn->trigger() !== $hardStop
            ) {
                throw new InvalidArgumentException('hard_stop_safe_return_mismatch');
            }
        }
    }

    public function hardStop(): ?string
    {
        return $this->hardStop;
    }

    public function legacyInvariant(): bool
    {
        return $this->legacyInvariant;
    }

    public function safeReturn(): ?R0ShadowSafeReturnPlan
    {
        return $this->safeReturn;
    }

    public function toArray(): array
    {
        return [
            'surface' => $this->surface,
            'legacy_outcome_code' => $this->legacyOutcomeCode,
            'canonical_outcome_code' => $this->canonicalOutcomeCode,
            'reason_code' => $this->reasonCode,
            'correlation_ref' => $this->correlationRef,
            'scope_ref' => $this->scopeRef,
            'legacy_digest' => $this->legacyDigest,
            'canonical_digest' => $this->canonicalDigest,
            'legacy_invariant' => $this->legacyInvariant,
            'hard_stop' => $this->hardStop,
            'safe_return' => $this->safeReturn?->toArray(),
        ];
    }
}
