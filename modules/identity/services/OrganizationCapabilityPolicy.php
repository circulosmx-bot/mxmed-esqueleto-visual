<?php
declare(strict_types=1);

namespace Identity\Services;

/** Organization membership and a paid plan do not authorize provider clinical work. */
final class OrganizationCapabilityPolicy
{
    public const PHYSICIAN_CAPABILITIES = [
        'patients', 'clinical_record', 'prescriptions', 'agenda_appointments',
    ];

    public function allows(string $organizationType, string $role, string $capability): bool
    {
        if (!in_array($role, ['owner', 'administrator', 'collaborator'], true)) return false;

        // Preserve the current medical-group authorization path. Its existing
        // subscription capability authority still decides the final grant.
        if ($organizationType === 'MEDICAL_GROUP') return true;

        // No provider capability has been ratified or implemented yet. This
        // explicitly covers physician capabilities and denies all unknowns.
        if (in_array($capability, self::PHYSICIAN_CAPABILITIES, true)) return false;
        return false;
    }
}
