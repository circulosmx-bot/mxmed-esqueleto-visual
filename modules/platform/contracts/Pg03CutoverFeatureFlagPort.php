<?php
declare(strict_types=1);

namespace Platform\Contracts;

interface Pg03CutoverFeatureFlagPort
{
    public function knownFlags(): array;
    public function configuredValue(string $flag): bool;
    public function effectiveEnabled(string $flag): bool;
    public function readiness(): array;
}

final class ClosedPg03CutoverFeatureFlagRegistry implements Pg03CutoverFeatureFlagPort
{
    private const KNOWN_FLAGS = [
        'canonical_actor_authority',
        'canonical_schedule_read',
        'canonical_availability_compare',
        'canonical_public_agenda',
        'canonical_appointment_lifecycle',
        'canonical_patient_identity',
        'patient_identity_persistence',
        'legacy_write_disable',
        'shadow_audit',
        'read_compare',
        'backfill',
    ];

    public function __construct(private array $config)
    {
    }

    public function knownFlags(): array
    {
        return self::KNOWN_FLAGS;
    }

    public function configuredValue(string $flag): bool
    {
        return in_array($flag, self::KNOWN_FLAGS, true)
            && ($this->config['feature_flags'][$flag] ?? null) === true;
    }

    public function effectiveEnabled(string $flag): bool
    {
        return false;
    }

    public function readiness(): array
    {
        return [
            'mode' => 'r0_registry_only',
            'known_flags' => count(self::KNOWN_FLAGS),
            'configured_true_flags' => array_values(array_filter(
                self::KNOWN_FLAGS,
                fn(string $flag): bool => $this->configuredValue($flag)
            )),
            'activation_authorized' => false,
            'all_effective_disabled' => true,
            'ready' => false,
        ];
    }
}
