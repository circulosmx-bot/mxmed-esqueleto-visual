<?php
declare(strict_types=1);

namespace Platform\Services;

use Platform\Contracts\LegacyContainmentDecision;
use Platform\Contracts\LegacyContainmentStatus;

final class LegacyContainmentEvaluator
{
    public function evaluate(?LegacyContainmentRegistry $registry, string $surface): LegacyContainmentDecision
    {
        $surface = trim($surface);
        if ($surface === '') {
            return new LegacyContainmentDecision('__empty_surface__', false, false, LegacyContainmentStatus::UNRESOLVED_STOP_REQUIRED, 'surface_required', ['surface']);
        }
        if ($registry === null) {
            return new LegacyContainmentDecision($surface, false, false, LegacyContainmentStatus::UNRESOLVED_STOP_REQUIRED, 'manifest_missing', ['manifest']);
        }
        $record = $registry->find($surface);
        if ($record === null) {
            return new LegacyContainmentDecision($surface, false, false, LegacyContainmentStatus::UNRESOLVED_STOP_REQUIRED, 'surface_missing', [$surface]);
        }
        $status = $record->status();
        if ($status === LegacyContainmentStatus::UNRESOLVED_STOP_REQUIRED) {
            return new LegacyContainmentDecision($surface, false, false, $status, 'unresolved_surface', [$surface]);
        }
        if (LegacyContainmentStatus::isBlocker($status)) {
            return new LegacyContainmentDecision($surface, true, false, $status, 'contained_deferred_blocker', [$surface]);
        }
        return new LegacyContainmentDecision($surface, true, true, $status, 'contained_resolved', []);
    }
}
