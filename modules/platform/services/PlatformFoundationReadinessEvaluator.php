<?php
declare(strict_types=1);

namespace Platform\Services;

use Platform\Contracts\PlatformFoundationReadinessDecision;

final class PlatformFoundationReadinessEvaluator
{
    public function __construct(private readonly LegacyContainmentEvaluator $containmentEvaluator = new LegacyContainmentEvaluator()) {}

    /** @param array<string,mixed> $gateResults */
    public function evaluate(?LegacyContainmentRegistry $registry, array $gateResults): PlatformFoundationReadinessDecision
    {
        $blockers = [];
        foreach (['6A', '6B', '6C', '6D', '6E', '6F'] as $gate) {
            $result = $gateResults[$gate] ?? null;
            $passed = $result === true || $result === 'PASS' || $result === 'PASS_GATE_6F_LEGACY_CONTAINMENT_FOUNDATION_INTEGRATION_READY';
            if (!$passed) $blockers[] = 'gate_' . $gate . '_not_passed';
        }

        if ($registry === null) {
            $blockers[] = 'manifest_missing';
        } else {
            foreach ($registry->records() as $record) {
                $decision = $this->containmentEvaluator->evaluate($registry, $record->surface());
                if (!$decision->contained() || !$decision->resolved() || $decision->status() !== 'retired_fail_closed' && $decision->status() !== 'remediated_read_purity') {
                    $blockers[] = $record->surface() . ':' . $decision->status();
                }
            }
        }
        $blockers = array_values(array_unique($blockers));
        sort($blockers, SORT_STRING);
        return new PlatformFoundationReadinessDecision(false, PlatformFoundationReadinessDecision::NO_GO_LEGACY_BLOCKERS_PRESENT, $blockers, $gateResults);
    }
}

