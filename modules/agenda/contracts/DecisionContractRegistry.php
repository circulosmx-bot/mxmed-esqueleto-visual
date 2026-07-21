<?php
declare(strict_types=1);

namespace Agenda\Contracts;

final class DecisionContractRegistry
{
    public static function all(): array
    {
        return [
            'DEC-013A' => ['artifact' => ActorAuthorityContract::class, 'test' => 'actor authority'],
            'DEC-013B' => ['artifact' => ScheduleAvailabilityContract::class, 'test' => 'schedule availability'],
            'DEC-013C' => ['artifact' => AppointmentLifecycleContract::class, 'test' => 'appointment lifecycle'],
            'DEC-013D' => ['artifact' => IdempotencyContract::class, 'test' => 'idempotency'],
            'DEC-013E' => ['artifact' => PublicOtpPolicy::class, 'test' => 'public otp'],
            'DEC-013F' => ['artifact' => ContactDescriptor::class, 'test' => 'contact privacy'],
            'DEC-013G' => ['artifact' => PatientIdentityMatch::class, 'test' => 'identity duplicates'],
            'DEC-013H' => ['artifact' => PatientMergeContract::class, 'test' => 'merge disabled'],
            'DEC-013I' => ['artifact' => MigrationContract::class, 'test' => 'migration contract'],
            'DEC-013J' => ['artifact' => AuditEventContract::class, 'test' => 'audit event'],
            'DEC-013K' => ['artifact' => RetentionContract::class, 'test' => 'retention'],
            'DEC-013L' => ['artifact' => RolloutContract::class, 'test' => 'rollout'],
        ];
    }
}
