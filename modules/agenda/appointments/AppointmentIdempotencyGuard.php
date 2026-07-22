<?php
declare(strict_types=1);

namespace Agenda\Appointments;

final class AppointmentIdempotencyGuard
{
    public function evaluate(AppointmentTransitionCommand $command, ?AppointmentIdempotencyRecord $record): AppointmentIdempotencyDecision
    {
        $fingerprint = AppointmentOperationFingerprint::fromCommand($command);
        if ($record === null) return AppointmentIdempotencyDecision::newOperation($fingerprint);
        if ($record->idempotencyKey()->value() !== $command->idempotencyKey()->value()
            || $record->appointmentId() !== $command->appointmentId()
            || $record->operationId() !== $command->operationId()) {
            return AppointmentIdempotencyDecision::conflict($fingerprint, $record);
        }
        if ($record->fingerprint()->value() === $fingerprint->value()) {
            return AppointmentIdempotencyDecision::replay($fingerprint, $record);
        }
        return AppointmentIdempotencyDecision::conflict($fingerprint, $record);
    }
}
