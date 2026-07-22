<?php
declare(strict_types=1);

namespace Agenda\Appointments;

final class AppointmentConcurrencyGuard
{
    public function evaluate(
        AppointmentSlotIdentity $requestedSlot,
        array $claims,
        ?string $excludeAppointmentId = null,
        bool $activeClaimRequired = true
    ): AppointmentConcurrencyDecision {
        $normalized = [];
        foreach ($claims as $claim) {
            if (!$claim instanceof AppointmentSlotClaim) {
                return AppointmentConcurrencyDecision::deny('invalid_claim', $requestedSlot);
            }
            $normalized[] = $claim;
        }
        if (!$activeClaimRequired) return AppointmentConcurrencyDecision::allow($requestedSlot);
        usort($normalized, static fn(AppointmentSlotClaim $a, AppointmentSlotClaim $b): int => [
            hash('sha256', $a->appointmentId()), $a->slot()->slotKey(), $a->state(), $a->aggregateVersion(), $a->active() ? 1 : 0
        ] <=> [
            hash('sha256', $b->appointmentId()), $b->slot()->slotKey(), $b->state(), $b->aggregateVersion(), $b->active() ? 1 : 0
        ]);
        foreach ($normalized as $claim) {
            if (!$claim->active()) continue;
            if ($excludeAppointmentId !== null && $claim->appointmentId() === $excludeAppointmentId) continue;
            if (!$requestedSlot->overlaps($claim->slot())) continue;
            return AppointmentConcurrencyDecision::deny(
                'slot_conflict',
                $requestedSlot,
                'sha256:' . hash('sha256', $claim->appointmentId())
            );
        }
        return AppointmentConcurrencyDecision::allow($requestedSlot);
    }
}
